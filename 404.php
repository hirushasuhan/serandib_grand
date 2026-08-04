<?php
declare(strict_types=1);

if (!defined('BASE_URL')) {
    require_once __DIR__ . '/bootstrap.php';
}

$pageTitle = '404 Page Not Found';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/nav.php';
?>

<main id="main" class="main-content">
  <div class="container text-center" style="padding-block: var(--space-16);">
    <div style="color: var(--color-primary); margin-bottom: var(--space-4);"><?= icon('compass', 64) ?></div>
    <h1 class="display-title" style="color: var(--color-primary); margin-bottom: var(--space-2);">404 — Page Not Found</h1>
    <p class="text-muted" style="max-width: 500px; margin-inline: auto; margin-bottom: var(--space-6);">
      The page or reservation record you requested could not be found or has been moved.
    </p>
    <a href="<?= url() ?>" class="btn btn--primary">Return Home &rarr;</a>
  </div>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
