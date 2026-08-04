<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/guards/staff.php';

use App\Core\Auth;
use App\Models\Booking;
use App\Services\FrontDeskService;
use App\Services\InvoiceService;
use App\Core\Csrf;
use App\Core\Flash;

$bookingId = (int)($_GET['booking_id'] ?? $_POST['booking_id'] ?? 0);
$booking = Booking::find($bookingId);

if (!$booking) {
    Flash::error("Booking not found.");
    redirect('staff/dashboard.php');
}

$balanceDue = $booking->balanceDue();
$isManager = Auth::can('approve_cancellation');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    
    $override = isset($_POST['manager_override']) && $isManager;
    $fdService = new FrontDeskService();

    try {
        // Auto-generate invoice if not already generated
        $invService = new InvoiceService();
        $invService->generate($booking, Auth::id());

        $fdService->checkOut($booking, $override);
        Flash::success("Guest successfully checked out from Room {$booking->room()?->room_number}! Room set to Cleaning.");
        redirect('staff/invoice.php?booking_id=' . $bookingId);
    } catch (\PDOException $e) {
        // MUST precede the RuntimeException block: PDOException extends
        // RuntimeException, so without this a database error would be treated as
        // a domain message and its SQL printed to the user.
        \App\Core\Logger::error($e);
        Flash::error('A database error occurred. Please try again.');
    } catch (\RuntimeException $e) {
        // Domain rule violated (balance outstanding, wrong status) — safe to show.
        Flash::error($e->getMessage());
    } catch (\Throwable $e) {
        \App\Core\Logger::error($e);
        Flash::error('Check-out could not be completed. Please try again.');
        redirect('staff/checkout.php?booking_id=' . $bookingId);
    }
}

$pageTitle = 'Guest Check-Out & Folio';
require __DIR__ . '/../includes/header.php';
?>

<div class="app-shell">
  <?php require __DIR__ . '/../includes/sidebar.php'; ?>

  <div class="app-shell__content">
    <?php require __DIR__ . '/../includes/nav.php'; ?>

    <main id="main" class="app-shell__main">
      
      <div style="max-width: 650px; margin-inline: auto;">
        
        <div class="card" style="padding: var(--space-8); border-top: 4px solid var(--warning-500);">
          <h1 class="page-title">Process Guest Check-Out</h1>
          <p class="text-muted" style="margin-bottom: var(--space-6);">Review folio balance, record final payment, and issue invoice.</p>

          <div style="background-color: var(--color-surface-2); padding: var(--space-4); border-radius: var(--radius-md); margin-bottom: var(--space-6); font-size: var(--text-sm);">
            <div><strong>Booking Ref:</strong> <span class="mono"><?= e($booking->booking_ref) ?></span></div>
            <div><strong>Guest Name:</strong> <?= e($booking->guest()?->full_name) ?></div>
            <div><strong>Room Number:</strong> Room <?= e($booking->room()?->room_number) ?></div>
            <div><strong>Total Bill Amount:</strong> <?= money($booking->total_amount) ?></div>
            <div><strong>Total Paid So Far:</strong> <?= money($booking->totalPaid()) ?></div>
            <div style="font-size: var(--text-base); font-weight: bold; margin-top: 8px; color: <?= $balanceDue > 0 ? 'var(--danger-500)' : 'var(--success-500)' ?>;">
              Balance Due: <?= money($balanceDue) ?>
            </div>
          </div>

          <?php if ($balanceDue > 0): ?>
            <div class="card mb-6" style="background-color: var(--warning-bg); border-color: var(--warning-500); font-size: var(--text-sm); display: flex; align-items: flex-start; gap: 10px;">
              <span style="color: var(--warning-500); flex-shrink: 0;"><?= icon('alert-triangle', 18) ?></span>
              <span>
                Outstanding balance of <strong><?= money($balanceDue) ?></strong> must be settled before checkout.
                <a href="<?= url('staff/payments.php?booking_id=' . $booking->id) ?>" class="btn btn--primary btn--sm" style="margin-top: 8px; display: inline-flex;"><?= icon('credit-card', 16) ?> Record Payment Now</a>
              </span>
            </div>
          <?php endif; ?>

          <form method="post" action="<?= url('staff/checkout.php') ?>">
            <?= Csrf::field() ?>
            <input type="hidden" name="booking_id" value="<?= $booking->id ?>">

            <?php if ($balanceDue > 0 && $isManager): ?>
              <div class="form-group" style="padding: var(--space-3); background-color: var(--danger-bg); border-radius: var(--radius-sm);">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: var(--text-xs); color: var(--danger-500);">
                  <input type="checkbox" name="manager_override" value="1">
                  <strong>Manager Override:</strong> Force check-out with unpaid balance
                </label>
              </div>
            <?php endif; ?>

            <div style="display: flex; gap: var(--space-3); margin-top: var(--space-6);">
              <a href="<?= url('staff/dashboard.php') ?>" class="btn btn--secondary">Cancel</a>
              <button type="submit" class="btn btn--primary" style="flex: 1;" <?= ($balanceDue > 0 && !$isManager) ? 'disabled' : '' ?>>
                Complete Check-Out & Generate Invoice &rarr;
              </button>
            </div>
          </form>

        </div>

      </div>

    </main>
    <?php require __DIR__ . '/../includes/footer.php'; ?>
  </div>
</div>
