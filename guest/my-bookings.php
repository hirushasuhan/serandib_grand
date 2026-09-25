<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/guards/guest.php';

use App\Core\Auth;
use App\Models\Booking;
use App\Core\Database;
use App\Core\Csrf;

$user = Auth::user();
$pageTitle = 'My Reservations';

$sql = "SELECT * FROM bookings WHERE user_id = :uid ORDER BY created_at DESC";
$rows = Database::getInstance()->query($sql, [':uid' => $user->id])->fetchAll();
$allBookings = array_map(fn($r) => new Booking($r), $rows);

$upcoming  = array_filter($allBookings, fn($b) => in_array($b->status, ['pending', 'confirmed', 'checked_in', 'cancel_requested']));
$past      = array_filter($allBookings, fn($b) => $b->status === 'checked_out');
$cancelled = array_filter($allBookings, fn($b) => in_array($b->status, ['cancelled', 'rejected', 'no_show']));

require __DIR__ . '/../includes/header.php';
?>

<div class="app-shell">
  <?php require __DIR__ . '/../includes/sidebar.php'; ?>

  <div class="app-shell__content">
    <?php require __DIR__ . '/../includes/nav.php'; ?>

    <main id="main" class="app-shell__main">
      
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-6);">
        <div>
          <h1 class="page-title">My Reservations History</h1>
          <p class="page-hint" style="margin-bottom: 0;">View upcoming stays, printable confirmation slips, or request booking cancellations.</p>
        </div>
        <a href="<?= url('guest/booking-form.php') ?>" class="btn btn--primary"><?= icon('plus', 16) ?> New Booking</a>
      </div>

      <div class="tabs-wrapper">
        
        <div style="display: flex; gap: var(--space-4); border-bottom: 2px solid var(--color-border); margin-bottom: var(--space-6);">
          <a href="#tab-upcoming" class="tab-link active" style="padding: var(--space-3) var(--space-4); font-weight: var(--weight-bold); border-bottom: 3px solid var(--color-primary); color: var(--color-primary);">
            Upcoming / Active (<?= count($upcoming) ?>)
          </a>
          <a href="#tab-past" class="tab-link" style="padding: var(--space-3) var(--space-4); font-weight: var(--weight-medium); color: var(--color-text-muted);">
            Past Stays (<?= count($past) ?>)
          </a>
          <a href="#tab-cancelled" class="tab-link" style="padding: var(--space-3) var(--space-4); font-weight: var(--weight-medium); color: var(--color-text-muted);">
            Cancelled / Rejected (<?= count($cancelled) ?>)
          </a>
        </div>

        <!-- TAB 1: UPCOMING -->
        <div id="tab-upcoming" class="tab-content active">
          <?php if (empty($upcoming)): ?>
            <div class="card text-center" style="padding: var(--space-12);">
              <h3>No active upcoming reservations.</h3>
              <p class="text-muted">Browse our rooms and plan your next luxurious stay.</p>
              <a href="<?= url('rooms.php') ?>" class="btn btn--primary" style="margin-top: var(--space-4);">Explore Rooms & Suites</a>
            </div>
          <?php else: ?>
            <div class="table-container">
              <table class="table table--hoverable" data-responsive>
                <thead>
                  <tr>
                    <th>Reference</th>
                    <th>Room Type / Room</th>
                    <th>Check-In</th>
                    <th>Check-Out</th>
                    <th>Nights</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($upcoming as $b): ?>
                    <tr>
                      <td data-label="Reference"><span class="mono" style="font-weight: bold;"><?= e($b->booking_ref) ?></span></td>
                      <td data-label="Room"><?= e($b->room()?->roomType()?->name ?? 'Deluxe Room') ?> (Room <?= e($b->room()?->room_number) ?>)</td>
                      <td data-label="Check-In"><?= formatDate($b->check_in) ?></td>
                      <td data-label="Check-Out"><?= formatDate($b->check_out) ?></td>
                      <td data-label="Nights"><?= $b->nights ?></td>
                      <td data-label="Total" style="font-weight: bold; color: var(--color-primary);"><?= money($b->total_amount) ?></td>
                      <td data-label="Status"><span class="badge badge--<?= e($b->status) ?>"><?= ucfirst(str_replace('_', ' ', $b->status)) ?></span></td>
                      <td data-label="Actions">
                        <div style="display: flex; gap: var(--space-2);">
                          <a href="<?= url('guest/booking-view.php?id=' . $b->id) ?>" class="btn btn--secondary btn--sm">View Slip</a>
                          
                          <?php if ($b->status === 'pending'): ?>
                            <a href="<?= url('guest/booking-form.php?edit_id=' . $b->id) ?>" class="btn btn--secondary btn--sm">Edit</a>
                            
                            <form method="post" action="<?= url('actions/booking/cancel.php') ?>" style="display: inline;" data-confirm="Are you sure you want to request cancellation for booking <?= e($b->booking_ref) ?>?" >
                              <?= Csrf::field() ?>
                              <input type="hidden" name="booking_id" value="<?= $b->id ?>">
                              <button type="submit" class="btn btn--danger btn--sm">Cancel</button>
                            </form>
                          <?php elseif ($b->status === 'confirmed'): ?>
                            <form method="post" action="<?= url('actions/booking/cancel.php') ?>" style="display: inline;" data-confirm="Request cancellation? Manager approval required." >
                              <?= Csrf::field() ?>
                              <input type="hidden" name="booking_id" value="<?= $b->id ?>">
                              <button type="submit" class="btn btn--danger btn--sm">Cancel Request</button>
                            </form>
                          <?php endif; ?>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

        <!-- TAB 2: PAST STAYS -->
        <div id="tab-past" class="tab-content" style="display: none;">
          <?php if (empty($past)): ?>
            <div class="card text-center" style="padding: var(--space-12);">
              <p class="text-muted">No past stay history found.</p>
            </div>
          <?php else: ?>
            <div class="table-container">
              <table class="table table--hoverable" data-responsive>
                <thead>
                  <tr>
                    <th>Reference</th>
                    <th>Room</th>
                    <th>Stay Dates</th>
                    <th>Total</th>
                    <th>Review Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($past as $b): ?>
                    <tr>
                      <td data-label="Reference"><span class="mono"><?= e($b->booking_ref) ?></span></td>
                      <td data-label="Room"><?= e($b->room()?->roomType()?->name) ?></td>
                      <td data-label="Dates"><?= formatDate($b->check_in) ?> to <?= formatDate($b->check_out) ?></td>
                      <td data-label="Total"><?= money($b->total_amount) ?></td>
                      <td data-label="Review">
                        <?php if ($b->review()): ?>
                          <span class="badge badge--success"><?= icon('check', 12) ?> Reviewed (<?= $b->review()->rating ?><?= icon('star', 12) ?>)</span>
                        <?php else: ?>
                          <a href="<?= url('guest/review.php?booking_id=' . $b->id) ?>" class="btn btn--primary btn--sm"><?= icon('star', 16) ?> Leave Review</a>
                        <?php endif; ?>
                      </td>
                      <td data-label="Actions">
                        <a href="<?= url('guest/booking-view.php?id=' . $b->id) ?>" class="btn btn--secondary btn--sm">View Invoice</a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

        <!-- TAB 3: CANCELLED -->
        <div id="tab-cancelled" class="tab-content" style="display: none;">
          <?php if (empty($cancelled)): ?>
            <div class="card text-center" style="padding: var(--space-12);">
              <p class="text-muted">No cancelled reservations.</p>
            </div>
          <?php else: ?>
            <div class="table-container">
              <table class="table" data-responsive>
                <thead>
                  <tr>
                    <th>Reference</th>
                    <th>Room</th>
                    <th>Cancelled Dates</th>
                    <th>Reason</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($cancelled as $b): ?>
                    <tr>
                      <td data-label="Reference"><span class="mono"><?= e($b->booking_ref) ?></span></td>
                      <td data-label="Room"><?= e($b->room()?->roomType()?->name) ?></td>
                      <td data-label="Dates"><?= formatDate($b->check_in) ?> to <?= formatDate($b->check_out) ?></td>
                      <td data-label="Reason"><?= e($b->cancel_reason ?? 'Cancelled by user') ?></td>
                      <td data-label="Status"><span class="badge badge--danger"><?= ucfirst($b->status) ?></span></td>
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

<script nonce="<?= e(CSP_NONCE) ?>">
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.tab-link').forEach(link => {
    link.addEventListener('click', (e) => {
      e.preventDefault();
      document.querySelectorAll('.tab-link').forEach(l => {
        l.classList.remove('active');
        l.style.borderBottom = 'none';
        l.style.color = 'var(--color-text-muted)';
      });
      document.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');

      link.classList.add('active');
      link.style.borderBottom = '3px solid var(--color-primary)';
      link.style.color = 'var(--color-primary)';

      const target = document.querySelector(link.getAttribute('href'));
      if (target) target.style.display = 'block';
    });
  });
});
</script>
