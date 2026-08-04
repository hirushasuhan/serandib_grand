<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/guards/staff.php';

use App\Core\Auth;
use App\Models\Booking;
use App\Services\FrontDeskService;
use App\Core\Csrf;
use App\Core\Flash;

$bookingId = (int)($_GET['booking_id'] ?? $_POST['booking_id'] ?? 0);
$booking = Booking::find($bookingId);

if (!$booking) {
    Flash::error("Booking not found.");
    redirect('staff/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    
    $nic = trim($_POST['nic_passport'] ?? '');
    $fdService = new FrontDeskService();

    try {
        $fdService->checkIn($booking, $nic ?: null);
        Flash::success("Guest successfully checked in for booking {$booking->booking_ref}! Room set to Occupied.");
        redirect('staff/dashboard.php');
    } catch (\PDOException $e) {
        // MUST precede the RuntimeException block: PDOException extends
        // RuntimeException, so without this a database error would be treated as
        // a domain message and its SQL printed to the user.
        \App\Core\Logger::error($e);
        Flash::error('A database error occurred. Please try again.');
    } catch (\RuntimeException $e) {
        // FrontDeskService throws RuntimeException with a human-readable reason
        // (wrong status, date not reached yet). Safe to show.
        Flash::error($e->getMessage());
    } catch (\Throwable $e) {
        // Everything else may embed SQL, table names or file paths.
        \App\Core\Logger::error($e);
        Flash::error('Check-in could not be completed. Please try again.');
        redirect('staff/checkin.php?booking_id=' . $bookingId);
    }
}

$pageTitle = 'Guest Check-In';
require __DIR__ . '/../includes/header.php';
?>

<div class="app-shell">
  <?php require __DIR__ . '/../includes/sidebar.php'; ?>

  <div class="app-shell__content">
    <?php require __DIR__ . '/../includes/nav.php'; ?>

    <main id="main" class="app-shell__main">
      
      <div style="max-width: 600px; margin-inline: auto;">
        
        <div class="card" style="padding: var(--space-8); border-top: 4px solid var(--success-500);">
          <h1 class="page-title">Process Guest Check-In</h1>
          <p class="text-muted" style="margin-bottom: var(--space-6);">Verify identity and assign physical room key card.</p>

          <div style="background-color: var(--color-surface-2); padding: var(--space-4); border-radius: var(--radius-md); margin-bottom: var(--space-6); font-size: var(--text-sm);">
            <div><strong>Booking Reference:</strong> <span class="mono"><?= e($booking->booking_ref) ?></span></div>
            <div><strong>Guest Name:</strong> <?= e($booking->guest()?->full_name) ?></div>
            <div><strong>Room Assigned:</strong> Room <?= e($booking->room()?->room_number) ?> (<?= e($booking->room()?->roomType()?->name) ?>)</div>
            <div><strong>Stay Dates:</strong> <?= formatDate($booking->check_in) ?> to <?= formatDate($booking->check_out) ?> (<?= $booking->nights ?> Nights)</div>
          </div>

          <form method="post" action="<?= url('staff/checkin.php') ?>">
            <?= Csrf::field() ?>
            <input type="hidden" name="booking_id" value="<?= $booking->id ?>">

            <div class="form-group">
              <label class="form-label">NIC / Passport Number (Identity Verification)</label>
              <input type="text" name="nic_passport" class="form-control" value="<?= e($booking->guest()?->nic_passport) ?>" placeholder="e.g. 199012345678 or N1234567" required>
            </div>

            <div style="display: flex; gap: var(--space-3); margin-top: var(--space-6);">
              <a href="<?= url('staff/dashboard.php') ?>" class="btn btn--secondary">Cancel</a>
              <button type="submit" class="btn btn--primary" style="flex: 1;">Complete Check-In &rarr;</button>
            </div>
          </form>

        </div>

      </div>

    </main>
    <?php require __DIR__ . '/../includes/footer.php'; ?>
  </div>
</div>
