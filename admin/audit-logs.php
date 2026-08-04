<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/guards/admin.php';

use App\Core\Auth;
use App\Core\Database;

$user = Auth::user();
$pageTitle = 'Security Audit Logs';

$db = Database::getInstance();

$sql = "SELECT a.*, u.full_name, u.email
        FROM audit_logs a
        LEFT JOIN users u ON u.id = a.user_id
        ORDER BY a.created_at DESC LIMIT 100";

$logs = $db->query($sql)->fetchAll();

require __DIR__ . '/../includes/header.php';
?>

<div class="app-shell">
  <?php require __DIR__ . '/../includes/sidebar.php'; ?>

  <div class="app-shell__content">
    <?php require __DIR__ . '/../includes/nav.php'; ?>

    <main id="main" class="app-shell__main">
      
      <div style="margin-bottom: var(--space-6);">
        <h1 class="page-title">Security & System Audit Logs</h1>
        <p class="page-hint">Complete immutable audit trail recording user authentication, state changes, and IP addresses.</p>
      </div>

      <div class="table-container">
        <table class="table table--hoverable" data-responsive>
          <thead>
            <tr>
              <th>Timestamp</th>
              <th>User</th>
              <th>Action</th>
              <th>Entity</th>
              <th>Details</th>
              <th>IP Address</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($logs as $log): ?>
              <tr>
                <td data-label="Timestamp"><?= formatDate($log['created_at'], 'Y-m-d H:i:s') ?></td>
                <td data-label="User">
                  <?php if ($log['full_name']): ?>
                    <strong><?= e($log['full_name']) ?></strong><br>
                    <span class="text-muted" style="font-size: var(--text-xs);"><?= e($log['email']) ?></span>
                  <?php else: ?>
                    <span class="text-muted">Guest / System</span>
                  <?php endif; ?>
                </td>
                <td data-label="Action"><span class="badge badge--info"><?= e($log['action']) ?></span></td>
                <td data-label="Entity"><?= e($log['entity'] ?? '-') ?> #<?= e((string)$log['entity_id']) ?></td>
                <td data-label="Details" style="font-size: var(--text-xs); max-width: 250px; font-family: var(--font-mono);"><?= e($log['details']) ?></td>
                <td data-label="IP"><span class="mono"><?= inet_ntop($log['ip_address']) ?: '127.0.0.1' ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

    </main>
    <?php require __DIR__ . '/../includes/footer.php'; ?>
  </div>
</div>
