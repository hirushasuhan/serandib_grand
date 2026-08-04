<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/guards/guest.php';

use App\Core\Auth;
use App\Models\Booking;
use App\Models\Notification;
use App\Core\Database;

$user = Auth::user();
$pageTitle = 'Guest Dashboard';

// Fetch active upcoming stay
$sql = "SELECT * FROM bookings WHERE user_id = :uid AND status IN ('pending','confirmed','checked_in') ORDER BY check_in ASC LIMIT 1";
$upcomingRow = Database::getInstance()->query($sql, [':uid' => $user->id])->fetch();
$upcomingBooking = $upcomingRow ? new Booking($upcomingRow) : null;

// Total stays count
$totalStays = (int)Database::getInstance()->query("SELECT COUNT(*) FROM bookings WHERE user_id = :uid", [':uid' => $user->id])->fetchColumn();

// Fetch unread notifications
$notifications = Notification::where(['user_id' => $user->id, 'is_read' => 0]);

require __DIR__ . '/../includes/header.php';
?>

<div class="app-shell">
  <?php require __DIR__ . '/../includes/sidebar.php'; ?>

  <div class="app-shell__content">
    <?php require __DIR__ . '/../includes/nav.php'; ?>

    <main id="main" class="app-shell__main">
      
      <div style="margin-bottom: var(--space-8);">
        <h1 class="page-title">Welcome back, <?= e($user->full_name) ?>!</h1>
        <p class="page-hint">Manage your resort reservations, profile details, and post stay reviews.</p>
      </div>

      <!-- KPI STAT CARDS -->
      <div class="grid grid--3 mb-8">
        <div class="card card--stat">
          <div>
            <div class="stat__label">Total Reservations</div>
            <div class="stat__val"><?= $totalStays ?></div>
          </div>
          <div class="stat__icon"><?= icon('calendar', 32) ?></div>
        </div>

        <div class="card card--stat">
          <div>
            <div class="stat__label">Upcoming Stay</div>
            <div class="stat__val" style="font-size: var(--text-lg); color: var(--color-primary);">
              <?= $upcomingBooking ? formatDate($upcomingBooking->check_in) : 'None' ?>
            </div>
          </div>
          <div class="stat__icon"><?= icon('umbrella', 32) ?></div>
        </div>

        <div class="card card--stat">
          <div>
            <div class="stat__label">Unread Alerts</div>
            <div class="stat__val" style="color: var(--accent-500);"><?= count($notifications) ?></div>
          </div>
          <div class="stat__icon"><?= icon('bell', 32) ?></div>
        </div>
      </div>

      <!-- NEXT STAY BANNER -->
      <?php if ($upcomingBooking): ?>
        <div class="card mb-8" style="border-left: 4px solid var(--color-primary);">
          <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--space-4);">
            <div>
              <span class="badge badge--<?= e($upcomingBooking->status) ?>" style="margin-bottom: var(--space-2);"><?= ucfirst($upcomingBooking->status) ?></span>
              <h2>Next Stay: Ref <?= e($upcomingBooking->booking_ref) ?></h2>
              <p class="text-muted" style="display: flex; align-items: center; gap: 6px; margin: 0;">
                <?= icon('calendar', 14) ?> <strong><?= formatDate($upcomingBooking->check_in) ?></strong> to <strong><?= formatDate($upcomingBooking->check_out) ?></strong> (<?= $upcomingBooking->nights ?> nights)
                &bull; Room: <strong><?= e($upcomingBooking->room()?->room_number ?? 'Assigned at Check-in') ?></strong>
              </p>
            </div>
            <div>
              <a href="<?= url('guest/booking-view.php?id=' . $upcomingBooking->id) ?>" class="btn btn--primary">View Confirmation Slip &rarr;</a>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <!-- QUICK ACTIONS & RECENT NOTIFICATIONS -->
      <div class="grid grid--2">
        <div class="card">
          <h3>Quick Guest Actions</h3>
          <div style="display: flex; flex-direction: column; gap: var(--space-3); margin-top: var(--space-4);">
            <a href="<?= url('guest/booking-form.php') ?>" class="btn btn--primary" style="justify-content: flex-start;"><?= icon('plus', 16) ?> Reserve a New Room</a>
            <a href="<?= url('guest/my-bookings.php') ?>" class="btn btn--secondary" style="justify-content: flex-start;"><?= icon('clipboard-list', 16) ?> View My Full Booking History</a>
            <a href="<?= url('guest/profile.php') ?>" class="btn btn--secondary" style="justify-content: flex-start;"><?= icon('settings', 16) ?> Edit Profile & Password</a>
          </div>
        </div>

        <div class="card">
          <h3>Recent Notifications</h3>
          <?php if (empty($notifications)): ?>
            <p class="text-muted" style="margin-top: var(--space-4);">You have no unread notifications.</p>
          <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: var(--space-3); margin-top: var(--space-4);">
              <?php foreach ($notifications as $notif): ?>
                <div style="padding: var(--space-3); background-color: var(--color-surface-2); border-radius: var(--radius-sm); font-size: var(--text-sm);">
                  <strong><?= e($notif->title) ?></strong>
                  <p style="margin: 2px 0 0 0; font-size: var(--text-xs); color: var(--color-text-muted);"><?= e($notif->message) ?></p>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

    </main>

    <?php require __DIR__ . '/../includes/footer.php'; ?>
  </div>
</div>
