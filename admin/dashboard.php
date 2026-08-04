<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/guards/admin.php';

use App\Core\Auth;
use App\Core\Database;

$user = Auth::user();
$pageTitle = 'Admin Dashboard';

$db = Database::getInstance();
$userCount  = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$typeCount  = (int)$db->query("SELECT COUNT(*) FROM room_types WHERE is_active = 1")->fetchColumn();
$roomCount  = (int)$db->query("SELECT COUNT(*) FROM rooms WHERE is_active = 1")->fetchColumn();
$auditCount = (int)$db->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();

require __DIR__ . '/../includes/header.php';
?>

<div class="app-shell">
  <?php require __DIR__ . '/../includes/sidebar.php'; ?>

  <div class="app-shell__content">
    <?php require __DIR__ . '/../includes/nav.php'; ?>

    <main id="main" class="app-shell__main">
      
      <div style="margin-bottom: var(--space-6);">
        <h1 class="page-title">System Administration & Control</h1>
        <p class="page-hint">Manage staff/guest accounts, configure room inventory, hotel settings, and review security audit logs.</p>
      </div>

      <!-- ADMIN KPI METRIC CARDS -->
      <div class="grid grid--4 mb-8">
        <div class="card card--stat">
          <div>
            <div class="stat__label">Total User Accounts</div>
            <div class="stat__val"><?= $userCount ?></div>
          </div>
          <div class="stat__icon"><?= icon('users', 32) ?></div>
        </div>

        <div class="card card--stat">
          <div>
            <div class="stat__label">Active Room Types</div>
            <div class="stat__val"><?= $typeCount ?></div>
          </div>
          <div class="stat__icon"><?= icon('tag', 32) ?></div>
        </div>

        <div class="card card--stat">
          <div>
            <div class="stat__label">Physical Inventory</div>
            <div class="stat__val"><?= $roomCount ?> Rooms</div>
          </div>
          <div class="stat__icon"><?= icon('door-open', 32) ?></div>
        </div>

        <div class="card card--stat">
          <div>
            <div class="stat__label">Audit Logs Recorded</div>
            <div class="stat__val"><?= $auditCount ?></div>
          </div>
          <div class="stat__icon"><?= icon('shield', 32) ?></div>
        </div>
      </div>

      <!-- ADMIN MODULE LINKS -->
      <div class="grid grid--3">
        <div class="card card--interactive">
          <h3 class="icon-heading"><span style="color: var(--color-primary);"><?= icon('tag', 20) ?></span> Room Types Management</h3>
          <p class="text-muted" style="font-size: var(--text-sm);">Create, edit, soft-delete room categories, set base prices, upload cover photos, and assign amenities.</p>
          <a href="<?= url('admin/room-types.php') ?>" class="btn btn--primary btn--sm" style="margin-top: auto;">Manage Room Types &rarr;</a>
        </div>

        <div class="card card--interactive">
          <h3 class="icon-heading"><span style="color: var(--color-primary);"><?= icon('door-open', 20) ?></span> Physical Rooms CRUD</h3>
          <p class="text-muted" style="font-size: var(--text-sm);">Manage room numbers, assigned floors, and use the bulk room generator helper for floors.</p>
          <a href="<?= url('admin/rooms.php') ?>" class="btn btn--primary btn--sm" style="margin-top: auto;">Manage Physical Rooms &rarr;</a>
        </div>

        <div class="card card--interactive">
          <h3 class="icon-heading"><span style="color: var(--color-primary);"><?= icon('users', 20) ?></span> Staff & User Accounts</h3>
          <p class="text-muted" style="font-size: var(--text-sm);">Provision receptionist/manager staff accounts, assign RBAC roles, or suspend abusive accounts.</p>
          <a href="<?= url('admin/users.php') ?>" class="btn btn--primary btn--sm" style="margin-top: auto;">Manage User Accounts &rarr;</a>
        </div>
      </div>

    </main>

    <?php require __DIR__ . '/../includes/footer.php'; ?>
  </div>
</div>
