<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Models\Setting;

$user = Auth::user();
if (!$user) return;

$role = $user->role;
$currentScript = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');

/**
 * Helper to check if a navigation link matches the current page.
 */
function isActiveSidebar(string $path, string $currentScript): bool {
    return str_ends_with($currentScript, ltrim($path, '/'));
}
?>

<?php /* Dimmed backdrop behind the mobile drawer. Tapping it closes the menu;
         it is display:none above the mobile breakpoint. */ ?>
<div class="sidebar-backdrop" data-sidebar-backdrop hidden></div>

<aside class="app-shell__sidebar" id="app-sidebar">

  <?php /* Close button — only visible while the drawer is open on mobile. */ ?>
  <button type="button" class="sidebar__close" data-nav-close aria-label="Close menu">&times;</button>

  <!-- SIDEBAR BRAND LOGO HEADER -->
  <div style="padding: var(--space-5) var(--space-5); border-bottom: 1px solid var(--color-border);">
    <a href="<?= url() ?>" style="display: flex; align-items: center; gap: 10px; text-decoration: none;">
      <img src="<?= url('assets/img/logo.jpg') ?>" alt="<?= e(Setting::get('hotel_name')) ?>" style="height: 40px; width: 40px; object-fit: cover; border-radius: 10px; border: 1.5px solid var(--accent-500); box-shadow: 0 2px 6px rgba(0,0,0,0.15); flex-shrink: 0;">
      <div style="display: flex; flex-direction: column; overflow: hidden;">
        <span style="font-family: var(--font-display); font-size: 1rem; font-weight: 700; color: var(--color-text); letter-spacing: 0.5px; line-height: 1.1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">SERENDIB GRAND</span>
        <span style="font-size: 9px; font-weight: 700; color: var(--accent-500); letter-spacing: 1.5px; text-transform: uppercase;">Resort &amp; Spa</span>
      </div>
    </a>
  </div>

  <div style="padding: var(--space-4); flex: 1; display: flex; flex-direction: column; gap: 4px; overflow-y: auto;">
    
    <div style="font-size: 10px; font-weight: 700; color: var(--color-text-subtle); text-transform: uppercase; letter-spacing: 1.5px; padding: 6px 12px; margin-top: 4px;">
      <?= ucfirst($role) ?> Portal
    </div>

    <?php if ($role === 'guest'): ?>
      <a href="<?= url('guest/dashboard.php') ?>" class="sidebar-nav-link <?= isActiveSidebar('guest/dashboard.php', $currentScript) ? 'active' : '' ?>">
        <span><?= icon('bar-chart', 18) ?></span> <span>Overview Dashboard</span>
      </a>
      <a href="<?= url('guest/booking-form.php') ?>" class="sidebar-nav-link <?= isActiveSidebar('guest/booking-form.php', $currentScript) ? 'active' : '' ?>">
        <span><?= icon('plus', 18) ?></span> <span>New Reservation</span>
      </a>
      <a href="<?= url('guest/my-bookings.php') ?>" class="sidebar-nav-link <?= isActiveSidebar('guest/my-bookings.php', $currentScript) ? 'active' : '' ?>">
        <span><?= icon('clipboard-list', 18) ?></span> <span>My Reservations</span>
      </a>
      <a href="<?= url('guest/profile.php') ?>" class="sidebar-nav-link <?= isActiveSidebar('guest/profile.php', $currentScript) ? 'active' : '' ?>">
        <span><?= icon('user', 18) ?></span> <span>Profile &amp; Security</span>
      </a>

    <?php elseif ($role === 'receptionist'): ?>
      <a href="<?= url('staff/dashboard.php') ?>" class="sidebar-nav-link <?= isActiveSidebar('staff/dashboard.php', $currentScript) ? 'active' : '' ?>">
        <span><?= icon('bar-chart', 18) ?></span> <span>Reception Overview</span>
      </a>
      <a href="<?= url('staff/frontdesk.php') ?>" class="sidebar-nav-link <?= isActiveSidebar('staff/frontdesk.php', $currentScript) ? 'active' : '' ?>">
        <span><?= icon('bed', 18) ?></span> <span>Room Status Board</span>
      </a>
      <a href="<?= url('staff/bookings.php') ?>" class="sidebar-nav-link <?= isActiveSidebar('staff/bookings.php', $currentScript) ? 'active' : '' ?>">
        <span><?= icon('clipboard-list', 18) ?></span> <span>Reservations List</span>
      </a>
      <a href="<?= url('staff/walkin.php') ?>" class="sidebar-nav-link <?= isActiveSidebar('staff/walkin.php', $currentScript) ? 'active' : '' ?>">
        <span><?= icon('log-in', 18) ?></span> <span>Walk-in Reservation</span>
      </a>
      <a href="<?= url('staff/payments.php') ?>" class="sidebar-nav-link <?= isActiveSidebar('staff/payments.php', $currentScript) ? 'active' : '' ?>">
        <span><?= icon('credit-card', 18) ?></span> <span>Folio &amp; Payments</span>
      </a>

    <?php elseif ($role === 'manager'): ?>
      <a href="<?= url('manager/dashboard.php') ?>" class="sidebar-nav-link <?= isActiveSidebar('manager/dashboard.php', $currentScript) ? 'active' : '' ?>">
        <span><?= icon('bar-chart', 18) ?></span> <span>Executive KPIs</span>
      </a>
      <a href="<?= url('manager/approvals.php') ?>" class="sidebar-nav-link <?= isActiveSidebar('manager/approvals.php', $currentScript) ? 'active' : '' ?>">
        <span><?= icon('clock', 18) ?></span> <span>Approval Requests</span>
      </a>
      <a href="<?= url('manager/reports.php') ?>" class="sidebar-nav-link <?= isActiveSidebar('manager/reports.php', $currentScript) ? 'active' : '' ?>">
        <span><?= icon('trending-up', 18) ?></span> <span>Analytics &amp; Reports</span>
      </a>
      <a href="<?= url('manager/reviews.php') ?>" class="sidebar-nav-link <?= isActiveSidebar('manager/reviews.php', $currentScript) ? 'active' : '' ?>">
        <span><?= icon('star', 18) ?></span> <span>Review Moderation</span>
      </a>
      <a href="<?= url('staff/frontdesk.php') ?>" class="sidebar-nav-link <?= isActiveSidebar('staff/frontdesk.php', $currentScript) ? 'active' : '' ?>">
        <span><?= icon('bed', 18) ?></span> <span>Room Status Board</span>
      </a>

    <?php elseif ($role === 'admin'): ?>
      <a href="<?= url('admin/dashboard.php') ?>" class="sidebar-nav-link <?= isActiveSidebar('admin/dashboard.php', $currentScript) ? 'active' : '' ?>">
        <span><?= icon('bar-chart', 18) ?></span> <span>System Dashboard</span>
      </a>
      <a href="<?= url('admin/room-types.php') ?>" class="sidebar-nav-link <?= isActiveSidebar('admin/room-types.php', $currentScript) ? 'active' : '' ?>">
        <span><?= icon('tag', 18) ?></span> <span>Room Types CRUD</span>
      </a>
      <a href="<?= url('admin/rooms.php') ?>" class="sidebar-nav-link <?= isActiveSidebar('admin/rooms.php', $currentScript) ? 'active' : '' ?>">
        <span><?= icon('door-open', 18) ?></span> <span>Physical Rooms CRUD</span>
      </a>
      <a href="<?= url('admin/amenities.php') ?>" class="sidebar-nav-link <?= isActiveSidebar('admin/amenities.php', $currentScript) ? 'active' : '' ?>">
        <span><?= icon('sparkle', 18) ?></span> <span>Amenities Master</span>
      </a>
      <a href="<?= url('admin/users.php') ?>" class="sidebar-nav-link <?= isActiveSidebar('admin/users.php', $currentScript) ? 'active' : '' ?>">
        <span><?= icon('users', 18) ?></span> <span>User Accounts</span>
      </a>
      <a href="<?= url('admin/settings.php') ?>" class="sidebar-nav-link <?= isActiveSidebar('admin/settings.php', $currentScript) ? 'active' : '' ?>">
        <span><?= icon('settings', 18) ?></span> <span>System Settings</span>
      </a>
      <a href="<?= url('admin/audit-logs.php') ?>" class="sidebar-nav-link <?= isActiveSidebar('admin/audit-logs.php', $currentScript) ? 'active' : '' ?>">
        <span><?= icon('shield', 18) ?></span> <span>Security Audit Logs</span>
      </a>
    <?php endif; ?>

    <div style="margin-top: auto; padding-top: var(--space-4); border-top: 1px solid var(--color-border);">
      <a href="<?= url() ?>" class="sidebar-nav-link">
        <span><?= icon('globe', 18) ?></span> <span>Return to Public Site</span>
      </a>
    </div>

  </div>
</aside>

<style>
.sidebar-nav-link {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 0.65rem 0.9rem;
  font-size: var(--text-sm);
  font-weight: var(--weight-medium);
  color: var(--color-text-muted);
  text-decoration: none;
  border-radius: var(--radius-md);
  transition: all var(--dur-fast) var(--ease-out);
  border-left: 3px solid transparent;
}

.sidebar-nav-link:hover {
  background-color: var(--color-surface-2);
  color: var(--color-text);
}

.sidebar-nav-link.active {
  background-color: var(--color-primary-soft);
  color: var(--color-primary);
  font-weight: var(--weight-bold);
  border-left-color: var(--color-primary);
}
</style>
