<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Csrf;
use App\Core\Auth;
use App\Models\Setting;

if (Auth::check()) {
    redirect('guest/dashboard.php');
}

$errors = $_SESSION['errors'] ?? [];
unset($_SESSION['errors']);

$pageTitle = 'Guest Registration';
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
    <div class="container" style="position: relative; z-index: 10; max-width: 520px; margin-inline: auto;">
      
      <div class="auth-card-clean" style="border-top-color: var(--color-primary) !important;">
        
        <!-- BRAND EMBLEM LOGO & TITLE -->
        <div style="text-align: center; margin-bottom: var(--space-6);">
          <img src="<?= e(url('assets/img/logo.jpg')) ?>" alt="<?= e(Setting::get('hotel_name')) ?>" style="height: 60px; width: 60px; object-fit: cover; border-radius: 14px; border: 2px solid var(--accent-500); box-shadow: var(--shadow-md); margin-inline: auto; margin-bottom: var(--space-3);">
          
          <h1 style="font-family: var(--font-display); font-size: var(--text-2xl); font-weight: 700; color: var(--color-text); margin-bottom: 4px;">Create Guest Account</h1>
          <p style="font-size: var(--text-xs); color: var(--color-text-muted); margin: 0; letter-spacing: 0.5px;">Join Serendib Grand to book rooms and manage your stays</p>
        </div>

        <form method="post" action="<?= url('actions/auth/register.php') ?>" data-validate>
          <?= Csrf::field() ?>

          <div class="form-group">
            <label class="form-label">Full Name</label>
            <input type="text" name="full_name" class="form-control <?= isset($errors['full_name']) ? 'form-control--error' : '' ?>" value="<?= e(old('full_name')) ?>" placeholder="e.g. Kasun Perera" required autofocus>
            <?php if (isset($errors['full_name'])): ?><div class="form-error"><?= e($errors['full_name']) ?></div><?php endif; ?>
          </div>

          <div class="form-group">
            <label class="form-label">Email Address</label>
            <input type="email" name="email" class="form-control <?= isset($errors['email']) ? 'form-control--error' : '' ?>" value="<?= e(old('email')) ?>" placeholder="kasun@example.com" required>
            <?php if (isset($errors['email'])): ?><div class="form-error"><?= e($errors['email']) ?></div><?php endif; ?>
          </div>

          <div class="form-group">
            <label class="form-label">Mobile Phone Number</label>
            <input type="tel" name="phone" class="form-control <?= isset($errors['phone']) ? 'form-control--error' : '' ?>" value="<?= e(old('phone')) ?>" placeholder="0712345678" required>
            <?php if (isset($errors['phone'])): ?><div class="form-error"><?= e($errors['phone']) ?></div><?php endif; ?>
          </div>

          <div class="form-group">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control <?= isset($errors['password']) ? 'form-control--error' : '' ?>" placeholder="Min 8 chars, 1 uppercase, 1 number, 1 symbol" required>
            <?php if (isset($errors['password'])): ?><div class="form-error"><?= e($errors['password']) ?></div><?php endif; ?>
          </div>

          <div class="form-group">
            <label class="form-label">Confirm Password</label>
            <input type="password" name="password_confirm" class="form-control <?= isset($errors['password_confirm']) ? 'form-control--error' : '' ?>" placeholder="Re-enter password" required>
            <?php if (isset($errors['password_confirm'])): ?><div class="form-error"><?= e($errors['password_confirm']) ?></div><?php endif; ?>
          </div>

          <button type="submit" class="btn btn--primary btn--lg btn--block" style="margin-top: var(--space-6); font-weight: 700;">Complete Registration &rarr;</button>
        </form>

        <div style="text-align: center; margin-top: var(--space-6); padding-top: var(--space-4); border-top: 1px solid var(--color-border); font-size: var(--text-sm); color: var(--color-text-muted);">
          Already have an account? <a href="<?= url('auth/login.php') ?>" style="font-weight: 700; color: var(--color-primary); text-decoration: underline;">Log In</a>
        </div>

      </div>

    </div>

  </div>
</main>

<script nonce="<?= e(CSP_NONCE) ?>">
document.addEventListener('DOMContentLoaded', () => {
  const vid = document.getElementById('resort-bg-video');
  if (vid) {
    vid.play().catch(() => {
      console.log('Video autoplay initiated');
    });
  }
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
