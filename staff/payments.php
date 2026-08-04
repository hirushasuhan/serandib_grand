<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/guards/staff.php';

use App\Core\Auth;
use App\Models\Booking;
use App\Services\PaymentService;
use App\Core\Csrf;
use App\Core\Validator;
use App\Core\Flash;

$bookingId = (int)($_GET['booking_id'] ?? $_POST['booking_id'] ?? 0);
$booking = Booking::find($bookingId);

if (!$booking) {
    Flash::error("Select a valid booking to process payments or charges.");
    redirect('staff/bookings.php');
}

$payService = new PaymentService();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();

    $action = $_POST['action_type'] ?? 'record_payment';

    if ($action === 'record_payment') {
        $v = Validator::make($_POST, [
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|in:cash,card_at_hotel,bank_transfer'
        ]);

        if (!$v->fails()) {
            /**
             * FIX: PaymentService::recordPayment() throws a RuntimeException
             * if its internal booking lookup fails or the amount is invalid
             * (e.g. the booking changed between page load and form submit).
             * This call had no try/catch, so it reached the global exception
             * handler and produced a 500 page on the payments screen — one of
             * the pages staff use constantly after logging in.
             */
            try {
                $payService->recordPayment(
                    $booking->id,
                    (float)$_POST['amount'],
                    $_POST['method'],
                    Auth::id(),
                    $_POST['reference_no'] ?? null,
                    $_POST['note'] ?? null
                );
                Flash::success("Payment of " . money($_POST['amount']) . " recorded.");
            } catch (\RuntimeException $e) {
                Flash::error($e->getMessage());
            } catch (\Throwable $e) {
                \App\Core\Logger::error($e);
                Flash::error('Could not record the payment. Please try again.');
            }
        }
    } elseif ($action === 'add_extra') {
        $v = Validator::make($_POST, [
            'description' => 'required|min:2|max:160',
            'qty'         => 'required|numeric|min:0.1',
            'unit_price'  => 'required|numeric|min:0'
        ]);

        if (!$v->fails()) {
            // FIX: same unguarded-RuntimeException issue as record_payment above.
            try {
                $payService->addExtraCharge(
                    $booking->id,
                    $_POST['description'],
                    (float)$_POST['qty'],
                    (float)$_POST['unit_price'],
                    Auth::id()
                );
                Flash::success("Extra charge added to folio.");
            } catch (\RuntimeException $e) {
                Flash::error($e->getMessage());
            } catch (\Throwable $e) {
                \App\Core\Logger::error($e);
                Flash::error('Could not add the extra charge. Please try again.');
            }
        }
    }

    redirect('staff/payments.php?booking_id=' . $bookingId);
}

$payments = $booking->payments();
$extras   = $booking->extraCharges();
$balance  = $booking->balanceDue();

$pageTitle = 'Folio & Payments - Ref ' . $booking->booking_ref;

require __DIR__ . '/../includes/header.php';
?>

<div class="app-shell">
  <?php require __DIR__ . '/../includes/sidebar.php'; ?>

  <div class="app-shell__content">
    <?php require __DIR__ . '/../includes/nav.php'; ?>

    <main id="main" class="app-shell__main">
      
      <div style="margin-bottom: var(--space-6);">
        <h1 class="page-title">Billing Folio & Payment Ledger</h1>
        <p class="page-hint">Booking Ref: <strong><?= e($booking->booking_ref) ?></strong> &bull; Guest: <strong><?= e($booking->guest()?->full_name) ?></strong></p>
      </div>

      <div class="grid grid--2 mb-8">
        
        <!-- SUMMARY CARD -->
        <div class="card">
          <h3>Folio Summary</h3>
          <div style="font-size: var(--text-sm); margin-top: var(--space-4);">
            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
              <span>Total Bill Amount:</span>
              <strong><?= money($booking->total_amount) ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
              <span>Total Payments Received:</span>
              <strong style="color: var(--success-500);"><?= money($booking->totalPaid()) ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between; padding-top: 8px; border-top: 2px solid var(--color-border); font-size: var(--text-lg); font-weight: bold; color: <?= $balance > 0 ? 'var(--danger-500)' : 'var(--success-500)' ?>;">
              <span>Outstanding Balance:</span>
              <span><?= money($balance) ?></span>
            </div>
          </div>
          <div style="margin-top: var(--space-6);">
            <a href="<?= url('staff/invoice.php?booking_id=' . $booking->id) ?>" class="btn btn--secondary btn--sm">View / Print Invoice &rarr;</a>
          </div>
        </div>

        <!-- RECORD PAYMENT FORM -->
        <div class="card">
          <h3>Record Payment</h3>
          <form method="post" action="<?= url('staff/payments.php?booking_id=' . $booking->id) ?>" style="margin-top: var(--space-4);">
            <?= Csrf::field() ?>
            <input type="hidden" name="action_type" value="record_payment">

            <div class="form-group">
              <label class="form-label">Payment Amount</label>
              <input type="number" step="0.01" name="amount" class="form-control" value="<?= $balance > 0 ? $balance : '' ?>" placeholder="0.00" required>
            </div>

            <div class="grid grid--2">
              <div class="form-group">
                <label class="form-label">Method</label>
                <select name="method" class="form-select" required>
                  <option value="cash">Cash</option>
                  <option value="card_at_hotel">Card at Hotel (POS)</option>
                  <option value="bank_transfer">Bank Transfer</option>
                </select>
              </div>

              <div class="form-group">
                <label class="form-label">Reference No.</label>
                <input type="text" name="reference_no" class="form-control" placeholder="TXN / Receipt no.">
              </div>
            </div>

            <button type="submit" class="btn btn--primary btn--block">Record Payment &rarr;</button>
          </form>
        </div>

      </div>

      <!-- ADD EXTRA CHARGES & HISTORY -->
      <div class="grid grid--2">
        
        <!-- ADD EXTRA CHARGE FORM -->
        <div class="card">
          <h3>Add Extra Charge to Folio</h3>
          <form method="post" action="<?= url('staff/payments.php?booking_id=' . $booking->id) ?>" style="margin-top: var(--space-4);">
            <?= Csrf::field() ?>
            <input type="hidden" name="action_type" value="add_extra">

            <div class="form-group">
              <label class="form-label">Description</label>
              <input type="text" name="description" class="form-control" placeholder="e.g. Minibar - 2x Soft Drinks" required>
            </div>

            <div class="grid grid--2">
              <div class="form-group">
                <label class="form-label">Quantity</label>
                <input type="number" step="1" name="qty" class="form-control" value="1" required>
              </div>

              <div class="form-group">
                <label class="form-label">Unit Price</label>
                <input type="number" step="0.01" name="unit_price" class="form-control" placeholder="0.00" required>
              </div>
            </div>

            <button type="submit" class="btn btn--secondary btn--block">Add Charge to Bill</button>
          </form>
        </div>

        <!-- PAYMENT HISTORY TABLE -->
        <div class="card">
          <h3>Payment History Ledger</h3>
          <?php if (empty($payments)): ?>
            <p class="text-muted" style="margin-top: var(--space-4);">No payments recorded yet.</p>
          <?php else: ?>
            <div class="table-container" style="margin-top: var(--space-4);">
              <table class="table" data-responsive>
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Method</th>
                    <th>Amount</th>
                    <th>Type</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($payments as $p): ?>
                    <tr>
                      <td data-label="Date"><?= formatDate($p->paid_at, 'd M, H:i') ?></td>
                      <td data-label="Method"><?= strtoupper($p->method) ?></td>
                      <td data-label="Amount" style="font-weight: bold; color: <?= $p->amount < 0 ? 'var(--danger-500)' : 'var(--success-500)' ?>;">
                        <?= money($p->amount) ?>
                      </td>
                      <td data-label="Type"><span class="badge badge--<?= $p->type === 'refund' ? 'danger' : 'success' ?>"><?= ucfirst($p->type) ?></span></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

      </div>

    </main>
    <?php require __DIR__ . '/../includes/footer.php'; ?>
  </div>
</div>
