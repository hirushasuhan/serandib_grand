<?php
declare(strict_types=1);

if (!defined('BASE_URL')) {
    require_once __DIR__ . '/bootstrap.php';
}

$pageTitle = '403 Forbidden';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/nav.php';
?>

<main id="main" class="main-content">
  <div class="container text-center" style="padding-block: var(--space-16);">
    <div style="color: var(--danger-500); margin-bottom: var(--space-4);"><?= icon('ban', 64) ?></div>
    <h1 class="display-title" style="color: var(--danger-500); margin-bottom: var(--space-2);">403 — Access Forbidden</h1>
    <p class="text-muted" style="max-width: 500px; margin-inline: auto; margin-bottom: var(--space-6);">
      You do not have the required permissions or authorization token to access this page or resource.
    </p>
    <a href="<?= url() ?>" class="btn btn--primary">Return to Safety (Home Page) &rarr;</a>
  </div>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
