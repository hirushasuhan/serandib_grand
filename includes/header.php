<?php
declare(strict_types=1);
$pageTitle = $pageTitle ?? APP_NAME;

/** Pages set $isPublicPage = true to pull in the public/3D stylesheet. */
$isPublicPage = $isPublicPage ?? false;
?>
<!DOCTYPE html>
<?php /* `no-js` is removed by the first inline script below. CSS uses it as the
         failsafe that forces [data-reveal] content visible when JavaScript is
         unavailable — without the class actually being present here, that
         failsafe could never match. */ ?>
<html lang="en" class="no-js">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="<?= e($metaDescription ?? 'Serendib Grand Resort & Spa — book coastal luxury rooms and suites in Bentota, Sri Lanka.') ?>">
  <meta name="theme-color" content="#0f766e">
  <title><?= e($pageTitle) ?> — <?= e(APP_NAME) ?></title>

  <link rel="icon" href="<?= url('assets/img/logo.jpg') ?>" type="image/jpeg">

  <?php /* Runs before first paint so the correct theme is applied with no flash.
           Carries the CSP nonce so it executes under a script-src that otherwise
           forbids inline script. */ ?>
  <script nonce="<?= e(CSP_NONCE) ?>">
    (function () {
      // Drop the no-js class straight away: JavaScript is clearly running.
      document.documentElement.classList.remove('no-js');

      try {
        var saved = localStorage.getItem('hrs-theme');
        var os    = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', saved || os);
      } catch (e) {
        // Private-browsing mode can throw on localStorage access — fall back to light.
        document.documentElement.setAttribute('data-theme', 'light');
      }
    })();
  </script>

  <!-- Webfonts. font-display:swap means text renders immediately in the
       system fallback if the CDN is slow or the lab machine is offline. -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

  <!-- Stylesheet order is fixed and must not be changed:
       tokens → base → layout → components → utilities → page-specific -->
  <link rel="stylesheet" href="<?= asset('css/tokens.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/base.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/layout.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/components.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/utilities.css') ?>">
  <?php if ($isPublicPage): ?>
    <link rel="stylesheet" href="<?= asset('css/public.css') ?>">
  <?php endif; ?>
  <link rel="stylesheet" media="print" href="<?= asset('css/print.css') ?>">
</head>
<body<?= $isPublicPage ? ' class="is-public"' : '' ?>>

<!-- Keyboard users can jump straight past the navigation -->
<a href="#main" class="skip-link">Skip to main content</a>

<!-- TOP PROGRESS BAR -->
<div id="top-progress-bar"></div>

<!-- FULLPAGE SITE PRELOADER -->
<div id="site-preloader">
  <img src="<?= url('assets/img/logo.jpg') ?>" alt="" width="56" height="56" style="height: 56px; width: 56px; object-fit: cover; border-radius: 14px; border: 2px solid var(--accent-500); box-shadow: 0 4px 12px rgba(0,0,0,0.2);">
  <div class="preloader-spinner"></div>
  <div style="font-family: var(--font-display); font-size: var(--text-sm); font-weight: 700; color: var(--color-text); letter-spacing: 1px; margin-top: 4px;">
    SERENDIB GRAND
  </div>
</div>

<?php require_once __DIR__ . '/flash.php'; ?>
