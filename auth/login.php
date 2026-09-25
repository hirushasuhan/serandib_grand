<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Models\Setting;
use App\Core\Flash;

if (Auth::check()) {
    $user = Auth::user();
    $redirectPath = match($user->role) {
        'admin'        => 'admin/dashboard.php',
        'manager'      => 'manager/dashboard.php',
        'receptionist' => 'staff/dashboard.php',
        default        => 'guest/dashboard.php'
    };
    redirect($redirectPath);
}

$errors = $_SESSION['errors'] ?? [];
unset($_SESSION['errors']);

$reason = $_GET['reason'] ?? '';
if ($reason === 'idle') Flash::warning('Your session timed out due to inactivity. Please log in again.');
if ($reason === 'security') Flash::warning('Your session was reset for security reasons.');
if ($reason === 'expired') Flash::warning('Your session expired after 8 hours. Please log in again.');

$pageTitle = 'Account Login';
$isPublicPage = true;

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php';
?>

<main id="main">
  <div class="login-page-wrapper" style="position: relative; min-height: 85vh; display: flex; align-items: center; justify-content: center; overflow: hidden; padding: var(--space-12) var(--space-4);">

    <!-- 100% WORKING LOCAL 1080P SRI LANKA TOURISM VIDEO BACKGROUND -->
    <div class="login-video-wrapper" style="position: absolute; inset: 0; overflow: hidden; z-index: 0;">
      <video id="resort-bg-video" autoplay loop muted playsinline poster="<?= e(url('assets/img/hero/hero_resort.jpg')) ?>" style="position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; z-index: 1; filter: brightness(0.9) contrast(1.05);">
        <source src="<?= e(url('assets/vid/resort_video.webm')) ?>" type="video/webm">
        <source src="<?= e(url('assets/vid/resort_video.mp4')) ?>" type="video/mp4">
      </video>
    </div>

    <!-- LUXURY GRADIENT OVERLAY SCRIM -->
    <div style="position: absolute; inset: 0; z-index: 2; background: linear-gradient(135deg, rgba(15, 118, 110, 0.25) 0%, rgba(9, 12, 18, 0.5) 65%, rgba(9, 12, 18, 0.7) 100%);"></div>

    <!-- 100% VISIBLE ROCK-SOLID AUTH CARD CONTAINER -->
    <div class="container" style="position: relative; z-index: 10; max-width: 480px; margin-inline: auto;">
      
      <div class="auth-card-clean">
        
        <!-- BRAND EMBLEM LOGO & TITLE -->
        <div style="text-align: center; margin-bottom: var(--space-6);">
          <img src="<?= e(url('assets/img/logo.jpg')) ?>" alt="<?= e(Setting::get('hotel_name')) ?>" style="height: 60px; width: 60px; object-fit: cover; border-radius: 14px; border: 2px solid var(--accent-500); box-shadow: var(--shadow-md); margin-inline: auto; margin-bottom: var(--space-3);">
          
          <h1 style="font-family: var(--font-display); font-size: var(--text-2xl); font-weight: 700; color: var(--color-text); margin-bottom: 4px;">Welcome Back</h1>
          <p style="font-size: var(--text-xs); color: var(--color-text-muted); margin: 0; letter-spacing: 0.5px;">Sign in to your Serendib Grand guest or staff portal</p>
        </div>

        <form method="post" action="<?= url('actions/auth/login.php') ?>" data-validate>
          <?= Csrf::field() ?>

          <div class="form-group">
            <label class="form-label">Email Address</label>
            <input type="email" name="email" class="form-control <?= isset($errors['email']) ? 'form-control--error' : '' ?>" value="<?= e(old('email')) ?>" placeholder="yourname@domain.com" autocomplete="username" required autofocus>
            <?php if (isset($errors['email'])): ?><div class="form-error"><?= e($errors['email']) ?></div><?php endif; ?>
          </div>

          <div class="form-group">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-2);">
              <label class="form-label" style="margin-bottom: 0;">Password</label>
              <a href="<?= url('auth/forgot-password.php') ?>" style="font-size: var(--text-xs); color: var(--color-primary); font-weight: 600;">Forgot Password?</a>
            </div>
            <input type="password" name="password" class="form-control <?= isset($errors['password']) ? 'form-control--error' : '' ?>" placeholder="••••••••" autocomplete="current-password" required>
            <?php if (isset($errors['password'])): ?><div class="form-error"><?= e($errors['password']) ?></div><?php endif; ?>
          </div>

          <button type="submit" class="btn btn--gold btn--lg btn--block" style="margin-top: var(--space-6); font-weight: 700;">
            Log In to Portal &rarr;
          </button>
        </form>

        <div style="text-align: center; margin-top: var(--space-6); padding-top: var(--space-4); border-top: 1px solid var(--color-border); font-size: var(--text-sm); color: var(--color-text-muted);">
          Don't have an account? <a href="<?= url('auth/register.php') ?>" style="font-weight: 700; color: var(--color-primary); text-decoration: underline;">Register Now</a>
        </div>

      </div>

    </div>

  </div>
</main>

<?php
  /**
   * The inline autoplay-retry script that used to live here is gone.
   *
   * 1. It had no nonce, so the Content-Security-Policy in bootstrap.php
   *    (`script-src 'self' 'nonce-…'`, no unsafe-inline) silently blocked it
   *    from ever running.
   * 2. It's redundant anyway: `#resort-bg-video` carries `data-bg-video` and
   *    is picked up by `setupBackgroundVideo()` in assets/js/public3d.js,
   *    which already handles deferred loading, the autoplay retry on first
   *    user gesture, and hiding the element on error — with a real fallback
   *    poster, none of which this duplicate provided.
   */
  require __DIR__ . '/../includes/footer.php';
?>
