<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/guards/guest.php';

use App\Core\Auth;
use App\Models\Booking;

$id = (int)($_GET['id'] ?? 0);
$booking = Booking::findForUser($id, Auth::id());

if (!$booking) {
    http_response_code(404);
    require __DIR__ . '/../404.php';
    exit;
}

$pageTitle = 'Booking Confirmation ' . $booking->booking_ref;

require __DIR__ . '/../includes/header.php';
?>

<div class="container" style="max-width: 720px; padding-block: var(--space-8);">
  
  <div class="no-print" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-6);">
    <a href="<?= url('guest/my-bookings.php') ?>" class="btn btn--secondary">&larr; Back to My Bookings</a>
    <button data-print class="btn btn--primary"><?= icon('printer', 16) ?> Print Confirmation Slip</button>
  </div>

  <div class="card print-invoice" style="padding: var(--space-8); border: 2px solid var(--color-border);">
    
    <div class="invoice-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: var(--space-6);">
      <div>
        <h1 style="font-family: var(--font-display); color: var(--color-primary); margin-bottom: var(--space-1);"><?= e(\App\Models\Setting::get('hotel_name')) ?></h1>
        <p class="text-muted" style="font-size: var(--text-xs); margin: 0;"><?= e(\App\Models\Setting::get('hotel_address')) ?></p>
        <p class="text-muted" style="font-size: var(--text-xs); margin: 0;"><?= e(\App\Models\Setting::get('hotel_phone')) ?> | <?= e(\App\Models\Setting::get('hotel_email')) ?></p>
      </div>

      <div style="text-align: right;">
        <span class="badge badge--<?= e($booking->status) ?>" style="font-size: var(--text-sm);"><?= strtoupper($booking->status) ?></span>
        <div style="font-family: var(--font-mono); font-size: var(--text-lg); font-weight: bold; margin-top: var(--space-2);"><?= e($booking->booking_ref) ?></div>
        <div style="font-size: var(--text-xs); color: var(--color-text-subtle);">Booked: <?= formatDate($booking->created_at) ?></div>
      </div>
    </div>

    <hr style="border: 0; border-top: 1px solid var(--color-border); margin-block: var(--space-6);">

    <div class="grid grid--2 mb-6" style="font-size: var(--text-sm);">
      <div>
        <strong>Guest Details:</strong>
        <p class="text-muted" style="margin-top: 4px;">
          <?= e($booking->guest()?->full_name) ?><br>
          <?= e($booking->guest()?->email) ?><br>
          <?= e($booking->guest()?->phone) ?>
        </p>
      </div>

      <div>
        <strong>Stay Information:</strong>
        <p class="text-muted" style="margin-top: 4px;">
          <strong>Check-In:</strong> <?= formatDate($booking->check_in) ?> (From 14:00)<br>
          <strong>Check-Out:</strong> <?= formatDate($booking->check_out) ?> (Until 11:00)<br>
          <strong>Duration:</strong> <?= $booking->nights ?> Night(s)<br>
          <strong>Occupancy:</strong> <?= $booking->adults ?> Adult(s), <?= $booking->children ?> Child(ren)
        </p>
      </div>
    </div>

    <div class="table-container mb-6">
      <table class="table">
        <thead>
          <tr>
            <th>Description</th>
            <th>Rate / Night</th>
            <th>Nights</th>
            <th class="text-right">Line Total</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>
              <strong><?= e($booking->room()?->roomType()?->name ?? 'Reserved Room') ?></strong><br>
              <span class="text-muted" style="font-size: var(--text-xs);">Room Number: <?= e($booking->room()?->room_number ?? 'Assigned at check-in') ?></span>
            </td>
            <td><?= money($booking->room_rate) ?></td>
            <td><?= $booking->nights ?></td>
            <td class="text-right" style="font-weight: bold;"><?= money($booking->subtotal) ?></td>
          </tr>
        </tbody>
      </table>
    </div>

    <div style="max-width: 300px; margin-left: auto; font-size: var(--text-sm);">
      <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
        <span>Subtotal:</span>
        <span><?= money($booking->subtotal) ?></span>
      </div>
      <?php if ((float)$booking->discount > 0): ?>
        <div style="display: flex; justify-content: space-between; margin-bottom: 4px; color: var(--success-500);">
          <span>Discount:</span>
          <span>-<?= money($booking->discount) ?></span>
        </div>
      <?php endif; ?>
      <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
        <span>Service Charge (10%):</span>
        <span><?= money($booking->service_charge) ?></span>
      </div>
      <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
        <span>Government Tax (8%):</span>
        <span><?= money($booking->tax_amount) ?></span>
      </div>
      <div style="display: flex; justify-content: space-between; padding-top: 8px; border-top: 2px solid var(--color-border-strong); font-size: var(--text-lg); font-weight: bold; color: var(--color-primary);">
        <span>Total Amount:</span>
        <span><?= money($booking->total_amount) ?></span>
      </div>
    </div>

    <div style="margin-top: var(--space-8); padding-top: var(--space-4); border-top: 1px dashed var(--color-border); font-size: var(--text-xs); color: var(--color-text-subtle); text-align: center;">
      Please present this booking confirmation slip or reference number upon arrival at Front Desk. Payment is settled at the hotel.
    </div>

  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
