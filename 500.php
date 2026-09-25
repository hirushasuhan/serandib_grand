<?php
declare(strict_types=1);

if (!defined('BASE_URL')) {
    require_once __DIR__ . '/bootstrap.php';
}

$pageTitle = '500 System Error';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/nav.php';
?>

<main id="main" class="main-content">
  <div class="container text-center" style="padding-block: var(--space-16);">
    <div style="color: var(--danger-500); margin-bottom: var(--space-4);"><?= icon('alert-triangle', 64) ?></div>
    <h1 class="display-title" style="color: var(--danger-500); margin-bottom: var(--space-2);">500 — System Error</h1>
    <p class="text-muted" style="max-width: 500px; margin-inline: auto; margin-bottom: var(--space-6);">
      An unexpected internal system error occurred. The incident has been logged for technical investigation.
    </p>
    <?php if (defined('APP_ENV') && APP_ENV !== 'production' && !empty($GLOBALS['last_error'])): ?>
      <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); color: #dc2626; padding: 12px 16px; border-radius: 6px; text-align: left; max-width: 700px; margin: 0 auto var(--space-6); font-family: monospace; font-size: 13px; word-break: break-all;">
        <strong>[Development Debug Trace]</strong><br>
        <?= e($GLOBALS['last_error']) ?>
      </div>
    <?php endif; ?>
    <a href="<?= url() ?>" class="btn btn--primary">Return Home &rarr;</a>
  </div>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
