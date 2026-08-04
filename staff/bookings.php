<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/guards/staff.php';

use App\Core\Auth;
use App\Models\Booking;
use App\Services\BookingService;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Flash;

$user = Auth::user();
$pageTitle = 'All Hotel Bookings';

// Action: Confirm or Reject booking
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    $bookingId = (int)($_POST['booking_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    
    $booking = Booking::find($bookingId);
    if ($booking) {
        /**
         * FIX: BookingService::transition() throws a RuntimeException when
         * BookingStatus::canTransition() says the requested change is illegal —
         * e.g. two staff members confirming/rejecting the same pending booking
         * within a moment of each other, or a double-click resubmitting the
         * form. Without a try/catch this reached the global exception handler
         * and produced a 500 page instead of a normal "already handled"
         * message, on the busiest staff list page.
         */
        try {
            $bs = new BookingService();
            if ($action === 'confirm') {
                $bs->transition($booking, 'confirmed');
                Flash::success("Booking {$booking->booking_ref} confirmed successfully.");
            } elseif ($action === 'reject') {
                $bs->transition($booking, 'rejected', $_POST['reason'] ?? 'Rejected by staff');
                Flash::warning("Booking {$booking->booking_ref} rejected.");
            }
        } catch (\RuntimeException $e) {
            Flash::error($e->getMessage());
        } catch (\Throwable $e) {
            \App\Core\Logger::error($e);
            Flash::error('Could not update that booking. Please refresh and try again.');
        }
    } else {
        Flash::error('Booking record not found.');
    }
    redirect('staff/bookings.php');
}

$statusFilter = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');

$where = ["1=1"];
$params = [];

if ($statusFilter) {
    $where[] = "b.status = :status";
    $params[':status'] = $statusFilter;
}

if ($search) {
    $where[] = "(b.booking_ref LIKE :q OR u.full_name LIKE :q OR u.phone LIKE :q OR r.room_number LIKE :q)";
    $params[':q'] = "%{$search}%";
}

$sql = "SELECT b.*, u.full_name, u.phone, r.room_number, rt.name AS room_type_name
        FROM bookings b
        JOIN users u ON u.id = b.user_id
        JOIN rooms r ON r.id = b.room_id
        JOIN room_types rt ON rt.id = r.room_type_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY b.created_at DESC LIMIT 50";

$rows = Database::getInstance()->query($sql, $params)->fetchAll();

require __DIR__ . '/../includes/header.php';
?>

<div class="app-shell">
  <?php require __DIR__ . '/../includes/sidebar.php'; ?>

  <div class="app-shell__content">
    <?php require __DIR__ . '/../includes/nav.php'; ?>

    <main id="main" class="app-shell__main">
      
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-6);">
        <div>
          <h1 class="page-title">Reservations Management</h1>
          <p class="page-hint" style="margin-bottom: 0;">Search, confirm online pending bookings, or process guest check-in/out.</p>
        </div>
        <a href="<?= url('staff/walkin.php') ?>" class="btn btn--primary"><?= icon('log-in', 16) ?> New Walk-In Booking</a>
      </div>

      <!-- FILTER & SEARCH BAR -->
      <div class="card mb-8">
        <form method="get" action="<?= url('staff/bookings.php') ?>" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-4); align-items: end;">
          
          <div>
            <label class="form-label">Search Keyword</label>
            <input type="text" name="search" class="form-control" value="<?= e($search) ?>" placeholder="Ref no, guest name, phone, room...">
          </div>

          <div>
            <label class="form-label">Filter Status</label>
            <select name="status" class="form-select">
              <option value="">All Statuses</option>
              <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending Approval</option>
              <option value="confirmed" <?= $statusFilter === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
              <option value="checked_in" <?= $statusFilter === 'checked_in' ? 'selected' : '' ?>>Checked In</option>
              <option value="checked_out" <?= $statusFilter === 'checked_out' ? 'selected' : '' ?>>Checked Out</option>
              <option value="cancel_requested" <?= $statusFilter === 'cancel_requested' ? 'selected' : '' ?>>Cancel Requested</option>
            </select>
          </div>

          <div>
            <button type="submit" class="btn btn--primary btn--block">Search Bookings</button>
          </div>

        </form>
      </div>

      <!-- BOOKINGS TABLE -->
      <div class="table-container">
        <table class="table table--hoverable" data-responsive>
          <thead>
            <tr>
              <th>Ref</th>
              <th>Guest Name</th>
              <th>Room & Type</th>
              <th>Check-In / Out</th>
              <th>Total</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr><td colspan="7" class="text-center text-muted" style="padding: var(--space-8);">No booking records found matching search.</td></tr>
            <?php else: ?>
              <?php foreach ($rows as $b): ?>
                <tr>
                  <td data-label="Ref"><span class="mono" style="font-weight: bold;"><?= e($b['booking_ref']) ?></span></td>
                  <td data-label="Guest"><?= e($b['full_name']) ?><br><span class="text-muted" style="font-size: var(--text-xs);"><?= e($b['phone']) ?></span></td>
                  <td data-label="Room">Room <?= e($b['room_number']) ?> &bull; <?= e($b['room_type_name']) ?></td>
                  <td data-label="Dates"><?= formatDate($b['check_in']) ?> to <?= formatDate($b['check_out']) ?></td>
                  <td data-label="Total" style="font-weight: bold;"><?= money($b['total_amount']) ?></td>
                  <td data-label="Status"><span class="badge badge--<?= e($b['status']) ?>"><?= ucfirst(str_replace('_', ' ', $b['status'])) ?></span></td>
                  <td data-label="Actions">
                    <div style="display: flex; gap: var(--space-2);">
                      
                      <?php if ($b['status'] === 'pending'): ?>
                        <form method="post" action="<?= url('staff/bookings.php') ?>" style="display: inline;">
                          <?= Csrf::field() ?>
                          <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                          <input type="hidden" name="action" value="confirm">
                          <button type="submit" class="btn btn--primary btn--sm">Confirm</button>
                        </form>

                        <form method="post" action="<?= url('staff/bookings.php') ?>" style="display: inline;" data-confirm="Reject this booking?" >
                          <?= Csrf::field() ?>
                          <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                          <input type="hidden" name="action" value="reject">
                          <button type="submit" class="btn btn--danger btn--sm">Reject</button>
                        </form>
                      <?php elseif ($b['status'] === 'confirmed'): ?>
                        <a href="<?= url('staff/checkin.php?booking_id=' . $b['id']) ?>" class="btn btn--primary btn--sm">Check In</a>
                      <?php elseif ($b['status'] === 'checked_in'): ?>
                        <a href="<?= url('staff/checkout.php?booking_id=' . $b['id']) ?>" class="btn btn--secondary btn--sm">Check Out</a>
                      <?php endif; ?>

                      <a href="<?= url('staff/payments.php?booking_id=' . $b['id']) ?>" class="btn btn--ghost btn--sm">Payments</a>

                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

    </main>
    <?php require __DIR__ . '/../includes/footer.php'; ?>
  </div>
</div>
