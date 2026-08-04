<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/guards/staff.php';

use App\Core\Auth;
use App\Models\RoomType;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use App\Core\Csrf;
use App\Core\Validator;
use App\Core\Flash;
use App\Exceptions\BookingConflictException;

$user = Auth::user();
$pageTitle = 'Walk-In Reservation';

$errors = $_SESSION['errors'] ?? [];
unset($_SESSION['errors']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();

    $v = Validator::make($_POST, [
        'full_name'        => 'required|min:3|max:120',
        'email'            => 'required|email|max:160',
        'phone'            => 'required|phone',
        'nic_passport'     => 'nullable|nic_passport',
        'room_id'          => 'required|integer|exists:rooms,id',
        'check_in'         => 'required|date_format:Y-m-d',
        'check_out'        => 'required|date_format:Y-m-d|after:check_in|max_nights:30',
        'adults'           => 'required|integer|min:1|max:10',
        'children'         => 'nullable|integer|min:0|max:6',
        'special_requests' => 'nullable|max:500',
    ]);

    if ($v->fails()) {
        $_SESSION['errors'] = $v->errors();
        $_SESSION['_old']   = $_POST;
        redirect('staff/walkin.php');
    }

    $clean = $v->validated();

    // Find, or inline-create, the guest account.
    $guest = User::findByEmail($clean['email']);
    if (!$guest) {
        /**
         * SECURITY: the previous version gave every walk-in guest the same
         * hard-coded password ('Walkin@1234'). Because that string sits in the
         * source code, anyone could log in as any walk-in guest and read their
         * reservations. Each account now gets a long random password that nobody
         * — including staff — ever sees; the guest sets their own via the
         * "forgot password" flow if they want online access.
         */
        $guest = User::create([
            'full_name'     => $clean['full_name'],
            'email'         => strtolower(trim($clean['email'])),
            'phone'         => $clean['phone'],
            'nic_passport'  => $clean['nic_passport'] ?? null,
            'password_hash' => User::hashPassword(bin2hex(random_bytes(24))),
            'role'          => 'guest',
            'status'        => 'active',
        ]);
    }

    try {
        $bs = new BookingService();

        // Explicit payload — never the raw $_POST array, which would let a
        // `discount` field ride along into the pricing calculation and bypass
        // the manager-approval rule for discounts above 10%.
        $booking = $bs->create([
            'room_id'          => (int) $clean['room_id'],
            'check_in'         => $clean['check_in'],
            'check_out'        => $clean['check_out'],
            'adults'           => (int) $clean['adults'],
            'children'         => (int) ($clean['children'] ?? 0),
            'special_requests' => $clean['special_requests'] ?? null,
        ], $guest, 'walk_in', Auth::id());

        Flash::success("Walk-in booking {$booking->booking_ref} created and confirmed.");
        redirect('staff/checkin.php?booking_id=' . $booking->id);

    } catch (\PDOException $e) {
        // Must precede the union catch below: PDOException extends
        // RuntimeException and its message can contain SQL.
        \App\Core\Logger::error($e);
        Flash::error('A database error occurred. Please try again.');
        redirect('staff/walkin.php');

    } catch (BookingConflictException | \RuntimeException $e) {
        // Our own domain exceptions carry messages written for humans.
        Flash::error($e->getMessage());
        redirect('staff/walkin.php');

    } catch (\Throwable $e) {
        // Anything else (PDOException, TypeError…) can contain SQL and paths.
        \App\Core\Logger::error($e);
        Flash::error('Could not create the walk-in booking. Please try again.');
        redirect('staff/walkin.php');
    }
}

$roomTypes = RoomType::activeOnly();
$checkIn   = date('Y-m-d');
$checkOut  = date('Y-m-d', strtotime('+1 day'));

$availService = new AvailabilityService();

require __DIR__ . '/../includes/header.php';
?>

<div class="app-shell">
  <?php require __DIR__ . '/../includes/sidebar.php'; ?>

  <div class="app-shell__content">
    <?php require __DIR__ . '/../includes/nav.php'; ?>

    <main id="main" class="app-shell__main">
      
      <div style="max-width: 720px; margin-inline: auto;">
        
        <div class="card" style="padding: var(--space-8);">
          <h1 class="page-title">Front Desk Walk-In Reservation</h1>
          <p class="page-hint">Create immediate walk-in reservation for guest standing at the desk.</p>

          <form method="post" action="<?= url('staff/walkin.php') ?>" data-validate>
            <?= Csrf::field() ?>

            <h3>1. Guest Contact Information</h3>
            <div class="grid grid--2 mb-6">
              <div class="form-group">
                <label class="form-label">Full Name</label>
                <input type="text" name="full_name" class="form-control" placeholder="Guest Full Name" required>
              </div>

              <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="guest@example.com" required>
              </div>

              <div class="form-group">
                <label class="form-label">Phone Number</label>
                <input type="tel" name="phone" class="form-control" placeholder="0712345678" required>
              </div>

              <div class="form-group">
                <label class="form-label">NIC / Passport Number</label>
                <input type="text" name="nic_passport" class="form-control" placeholder="Optional at creation">
              </div>
            </div>

            <h3>2. Reservation Dates & Room Selection</h3>
            <div class="grid grid--2 mb-6">
              <div class="form-group">
                <label class="form-label">Check-In Date</label>
                <input type="date" name="check_in" class="form-control" value="<?= $checkIn ?>" required>
              </div>

              <div class="form-group">
                <label class="form-label">Check-Out Date</label>
                <input type="date" name="check_out" class="form-control" value="<?= $checkOut ?>" required>
              </div>

              <div class="form-group" style="grid-column: span 2;">
                <label class="form-label">Assign Available Room</label>
                <select name="room_id" class="form-select" required>
                  <?php foreach ($roomTypes as $type): ?>
                    <?php $rooms = $availService->availableRooms($type->id, $checkIn, $checkOut); ?>
                    <optgroup label="<?= e($type->name) ?> (<?= money($type->base_price) ?>/night)">
                      <?php if (empty($rooms)): ?>
                        <option disabled>-- Sold out --</option>
                      <?php else: ?>
                        <?php foreach ($rooms as $r): ?>
                          <option value="<?= $r['id'] ?>">Room <?= e($r['room_number']) ?> (Floor <?= $r['floor'] ?>)</option>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </optgroup>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <h3>3. Occupancy &amp; Requests</h3>
            <?php /* These fields were missing from the form while the handler
                     required them, so every submission failed validation with
                     "The adults field is required." */ ?>
            <div class="grid grid--2 mb-6">
              <div class="form-group">
                <label class="form-label" for="wi-adults">Adults</label>
                <input type="number" id="wi-adults" name="adults" class="form-control"
                       value="2" min="1" max="10" required>
              </div>

              <div class="form-group">
                <label class="form-label" for="wi-children">Children</label>
                <input type="number" id="wi-children" name="children" class="form-control"
                       value="0" min="0" max="6">
              </div>

              <div class="form-group" style="grid-column: span 2;">
                <label class="form-label" for="wi-requests">Special Requests (optional)</label>
                <textarea id="wi-requests" name="special_requests" rows="2" class="form-textarea"
                          placeholder="e.g. extra bed, ground floor, late arrival" maxlength="500"></textarea>
                <div class="form-hint">Plain text, max 500 characters.</div>
              </div>
            </div>

            <button type="submit" class="btn btn--primary btn--lg btn--block">Create Walk-In Reservation &rarr;</button>
          </form>

        </div>

      </div>

    </main>
    <?php require __DIR__ . '/../includes/footer.php'; ?>
  </div>
</div>
