<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/guards/staff.php';

use App\Core\Auth;
use App\Models\Booking;
use App\Services\InvoiceService;
use App\Core\Flash;

$bookingId = (int)($_GET['booking_id'] ?? 0);
$booking = Booking::find($bookingId);

if (!$booking) {
    Flash::error("Booking not found.");
    redirect('staff/dashboard.php');
}

$invService = new InvoiceService();
$invoice = $invService->generate($booking, Auth::id());
$items = $invoice->items();

$pageTitle = 'Invoice ' . $invoice->invoice_no;

require __DIR__ . '/../includes/header.php';
?>

<div class="container" style="max-width: 800px; padding-block: var(--space-8);">
  
  <div class="no-print" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-6);">
    <a href="<?= url('staff/dashboard.php') ?>" class="btn btn--secondary">&larr; Back to Front Desk</a>
    <button data-print class="btn btn--primary">🖨️ Print Official Invoice (PDF)</button>
  </div>

  <div class="card print-invoice" style="padding: var(--space-10); border: 2px solid var(--color-border);">
    
    <div class="invoice-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: var(--space-8);">
      <div>
        <h1 style="font-family: var(--font-display); color: var(--color-primary); font-size: 2rem; margin-bottom: 4px;"><?= e(\App\Models\Setting::get('hotel_name')) ?></h1>
        <p class="text-muted" style="font-size: var(--text-xs); margin: 0;"><?= e(\App\Models\Setting::get('hotel_address')) ?></p>
        <p class="text-muted" style="font-size: var(--text-xs); margin: 0;">Phone: <?= e(\App\Models\Setting::get('hotel_phone')) ?> | Email: <?= e(\App\Models\Setting::get('hotel_email')) ?></p>
      </div>

      <div style="text-align: right;">
        <h2 style="font-family: var(--font-mono); font-size: var(--text-2xl); color: var(--color-primary); margin-bottom: 4px;"><?= e($invoice->invoice_no) ?></h2>
        <div style="font-size: var(--text-xs); color: var(--color-text-muted);">Issued Date: <?= formatDate($invoice->issued_at) ?></div>
        <div style="font-size: var(--text-xs); color: var(--color-text-muted);">Booking Ref: <span class="mono"><?= e($booking->booking_ref) ?></span></div>
      </div>
    </div>

    <hr style="border: 0; border-top: 1px solid var(--color-border); margin-block: var(--space-6);">

    <div class="grid grid--2 mb-8" style="font-size: var(--text-sm);">
      <div>
        <strong>Billed To (Guest):</strong>
        <p class="text-muted" style="margin-top: 4px;">
          <strong><?= e($booking->guest()?->full_name) ?></strong><br>
          Email: <?= e($booking->guest()?->email) ?><br>
          Phone: <?= e($booking->guest()?->phone) ?><br>
          NIC/Passport: <?= e($booking->guest()?->nic_passport ?? 'N/A') ?>
        </p>
      </div>

      <div>
        <strong>Stay Summary:</strong>
        <p class="text-muted" style="margin-top: 4px;">
          Room Number: <strong><?= e($booking->room()?->room_number) ?></strong> (<?= e($booking->room()?->roomType()?->name) ?>)<br>
          Check-In: <?= formatDate($booking->check_in) ?><br>
          Check-Out: <?= formatDate($booking->check_out) ?><br>
          Duration: <?= $booking->nights ?> Night(s)
        </p>
      </div>
    </div>

    <!-- LINE ITEMS TABLE -->
    <div class="table-container mb-6">
      <table class="table">
        <thead>
          <tr>
            <th>Item Description</th>
            <th class="text-center">Qty</th>
            <th class="text-right">Unit Price</th>
            <th class="text-right">Line Total</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $item): ?>
            <tr>
              <td><?= e($item['description']) ?></td>
              <td class="text-center"><?= (float)$item['qty'] ?></td>
              <td class="text-right"><?= money($item['unit_price']) ?></td>
              <td class="text-right" style="font-weight: bold;"><?= money($item['line_total']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- INVOICE TOTALS BREAKDOWN -->
    <div style="max-width: 340px; margin-left: auto; font-size: var(--text-sm);">
      <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
        <span>Subtotal:</span>
        <span><?= money($invoice->subtotal) ?></span>
      </div>
      <?php if ((float)$invoice->discount > 0): ?>
        <div style="display: flex; justify-content: space-between; margin-bottom: 4px; color: var(--success-500);">
          <span>Discount:</span>
          <span>-<?= money($invoice->discount) ?></span>
        </div>
      <?php endif; ?>
      <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
        <span>Service Charge (<?= (float)$invoice->service_rate ?>%):</span>
        <span><?= money($invoice->service_amt) ?></span>
      </div>
      <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
        <span>Government Tax (<?= (float)$invoice->tax_rate ?>%):</span>
        <span><?= money($invoice->tax_amount) ?></span>
      </div>
      
      <div style="display: flex; justify-content: space-between; padding-top: 8px; border-top: 2px solid var(--color-border-strong); font-size: var(--text-xl); font-weight: bold; color: var(--color-primary); margin-top: 4px;">
        <span>Grand Total:</span>
        <span><?= money($invoice->grand_total) ?></span>
      </div>

      <div style="display: flex; justify-content: space-between; margin-top: 8px; color: var(--success-500); font-weight: bold;">
        <span>Amount Paid:</span>
        <span><?= money($booking->totalPaid()) ?></span>
      </div>
      <div style="display: flex; justify-content: space-between; color: var(--color-text-muted);">
        <span>Balance Due:</span>
        <span><?= money($booking->balanceDue()) ?></span>
      </div>
    </div>

    <div style="margin-top: var(--space-12); padding-top: var(--space-4); border-top: 1px solid var(--color-border); text-align: center; font-size: var(--text-xs); color: var(--color-text-subtle);">
      Thank you for choosing <?= e(\App\Models\Setting::get('hotel_name')) ?>! Issued by staff user ID: <?= $invoice->issued_by ?>.
    </div>

  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
