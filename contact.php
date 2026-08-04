<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use App\Models\Setting;
use App\Models\ContactMessage;
use App\Core\Validator;
use App\Core\Csrf;
use App\Core\Flash;

$pageTitle = 'Contact Front Desk';
$metaDescription = 'Contact the Serendib Grand front desk team in Bentota, Sri Lanka. 24/7 reservations and concierge assistance.';

/** Loads assets/css/public.css + public3d.js (see includes/header.php). */
$isPublicPage = true;
$errors = $_SESSION['errors'] ?? [];
unset($_SESSION['errors']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    Csrf::verify();
    
    // Throttle: limit spam submissions
    if (\App\Core\RateLimiter::tooManyRegistrations($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1')) {
        Flash::error('Too many messages sent from this connection. Please try again later.');
        redirect('contact.php');
    }

    $v = Validator::make($_POST, [
        'name'    => 'required|min:2|max:120',
        'email'   => 'required|email|max:160',
        'subject' => 'required|min:3|max:160',
        'message' => 'required|min:10|max:2000'
    ]);

    if ($v->fails()) {
        $_SESSION['errors'] = $v->errors();
        redirect('contact.php');
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $binaryIp = @inet_pton($ip) ?: @inet_pton('127.0.0.1');

    ContactMessage::create([
        'name'       => $_POST['name'],
        'email'      => $_POST['email'],
        'subject'    => $_POST['subject'],
        'message'    => $_POST['message'],
        'ip_address' => $binaryIp
    ]);

    Flash::success("Thank you for reaching out! Our front desk team has received your message.");
    redirect('contact.php');
}

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/nav.php';
?>

<main id="main">

  <!-- ═══════════════════════════════════════════════════════════
       1. LUXURY HERO BANNER
       ═══════════════════════════════════════════════════════════ -->
  <section class="hero3d scene-3d" aria-labelledby="contact-title" style="min-height: 480px;">
    
    <div class="hero3d__layer"
         style="background-image: url('<?= e(url('assets/img/hero/hero_resort.jpg')) ?>');"
         role="presentation"></div>
    <div class="hero3d__scrim" role="presentation"></div>

    <div class="hero3d__inner layer-3d" style="max-width: 900px; padding-block: var(--space-16);">
      
      <div style="display: inline-flex; align-items: center; gap: 8px; padding: 6px 18px; background: rgba(200, 150, 62, 0.22); border: 1px solid var(--accent-500); border-radius: var(--radius-full); font-size: var(--text-xs); font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #f3d79b; margin-bottom: var(--space-4); backdrop-filter: blur(10px);">
        <?= icon('sparkle', 12) ?> 24/7 Front Desk &amp; Concierge
      </div>

      <h1 class="hero3d__title" id="contact-title" style="font-size: clamp(2.4rem, 5vw, 4rem);">
        Contact <em>Our Front Desk</em>
      </h1>

      <p class="hero3d__lead" style="font-size: var(--text-lg); color: rgba(255, 255, 255, 0.92); max-width: 680px; margin-inline: auto;">
        Have questions regarding reservations, private transfers, or special oceanfront dining arrangements? We are at your service 24 hours a day.
      </p>

    </div>
  </section>

  <!-- ═══════════════════════════════════════════════════════════
       2. CONTACT INFORMATION & MESSAGE FORM
       ═══════════════════════════════════════════════════════════ -->
  <section class="section" style="padding-block: var(--space-16); background-color: var(--color-bg);">
    <div class="container">

      <div class="grid grid--2" style="gap: var(--space-12); align-items: start;">
        
        <!-- LEFT COLUMN: RESORT CONTACT CARDS -->
        <div style="display: flex; flex-direction: column; gap: var(--space-6);" data-reveal>
          
          <div class="card" style="padding: var(--space-8); border-left: 4px solid var(--accent-500);">
            <div style="display: flex; align-items: center; gap: 14px; margin-bottom: var(--space-4);">
              <div style="height: 48px; width: 48px; border-radius: 12px; background: var(--color-primary-soft); display: flex; align-items: center; justify-content: center; color: var(--color-primary);"><?= icon('map-pin', 22) ?></div>
              <div>
                <strong style="font-family: var(--font-display); font-size: var(--text-lg); display: block; color: var(--color-text);">Resort Location</strong>
                <span style="font-size: var(--text-xs); color: var(--color-text-subtle); uppercase; letter-spacing: 1px;">Bentota Sanctuary</span>
              </div>
            </div>
            <p style="font-size: var(--text-sm); line-height: 1.6; color: var(--color-text-muted); margin: 0;">
              <?= e(Setting::get('hotel_address', '100 Beach Front Drive, Bentota 80500, Sri Lanka')) ?>
            </p>
          </div>

          <div class="card" style="padding: var(--space-8); border-left: 4px solid var(--color-primary);">
            <div style="display: flex; align-items: center; gap: 14px; margin-bottom: var(--space-4);">
              <div style="height: 48px; width: 48px; border-radius: 12px; background: var(--color-primary-soft); display: flex; align-items: center; justify-content: center; color: var(--color-primary);"><?= icon('phone', 22) ?></div>
              <div>
                <strong style="font-family: var(--font-display); font-size: var(--text-lg); display: block; color: var(--color-text);">Telephone &amp; Hotline</strong>
                <span style="font-size: var(--text-xs); color: var(--color-text-subtle); uppercase; letter-spacing: 1px;">24/7 Guest Assistance</span>
              </div>
            </div>
            <p style="font-size: var(--text-sm); line-height: 1.6; color: var(--color-text-muted); margin: 0;">
              <strong>Direct Line:</strong> <?= e(Setting::get('hotel_phone', '+94 34 227 5000')) ?><br>
              <strong>Reservations Desk:</strong> +94 34 227 5001
            </p>
          </div>

          <div class="card" style="padding: var(--space-8); border-left: 4px solid var(--accent-500);">
            <div style="display: flex; align-items: center; gap: 14px; margin-bottom: var(--space-4);">
              <div style="height: 48px; width: 48px; border-radius: 12px; background: var(--color-primary-soft); display: flex; align-items: center; justify-content: center; color: var(--color-primary);"><?= icon('mail', 22) ?></div>
              <div>
                <strong style="font-family: var(--font-display); font-size: var(--text-lg); display: block; color: var(--color-text);">Email Inquiries</strong>
                <span style="font-size: var(--text-xs); color: var(--color-text-subtle); uppercase; letter-spacing: 1px;">Instant Response</span>
              </div>
            </div>
            <p style="font-size: var(--text-sm); line-height: 1.6; color: var(--color-text-muted); margin: 0;">
              <strong>General:</strong> <?= e(Setting::get('hotel_email', 'info@serendibgrand.lk')) ?><br>
              <strong>Bookings:</strong> reservations@serendibgrand.lk
            </p>
          </div>

        </div>

        <!-- RIGHT COLUMN: MESSAGE FORM -->
        <div class="card" style="padding: var(--space-8); border-top: 4px solid var(--color-primary);" data-reveal>
          
          <h2 style="font-family: var(--font-display); font-size: var(--text-2xl); font-weight: 700; margin-bottom: 4px; color: var(--color-text);">Send Us a Message</h2>
          <p class="text-muted" style="font-size: var(--text-sm); margin-bottom: var(--space-6);">Fill out the form below and our concierge team will respond promptly.</p>

          <form method="post" action="<?= url('contact.php') ?>" data-validate>
            <?= Csrf::field() ?>

            <div class="form-group">
              <label class="form-label">Full Name</label>
              <input type="text" name="name" class="form-control <?= isset($errors['name']) ? 'form-control--error' : '' ?>" value="<?= e(old('name')) ?>" placeholder="e.g. Kasun Perera" required>
              <?php if (isset($errors['name'])): ?><div class="form-error"><?= e($errors['name']) ?></div><?php endif; ?>
            </div>

            <div class="form-group">
              <label class="form-label">Email Address</label>
              <input type="email" name="email" class="form-control <?= isset($errors['email']) ? 'form-control--error' : '' ?>" value="<?= e(old('email')) ?>" placeholder="kasun@example.com" required>
              <?php if (isset($errors['email'])): ?><div class="form-error"><?= e($errors['email']) ?></div><?php endif; ?>
            </div>

            <div class="form-group">
              <label class="form-label">Subject</label>
              <input type="text" name="subject" class="form-control <?= isset($errors['subject']) ? 'form-control--error' : '' ?>" value="<?= e(old('subject')) ?>" placeholder="e.g. Airport Transfer Request" required>
              <?php if (isset($errors['subject'])): ?><div class="form-error"><?= e($errors['subject']) ?></div><?php endif; ?>
            </div>

            <div class="form-group">
              <label class="form-label">Message</label>
              <textarea name="message" rows="5" class="form-textarea <?= isset($errors['message']) ? 'form-control--error' : '' ?>" placeholder="How may we assist you with your stay?" required><?= e(old('message')) ?></textarea>
              <?php if (isset($errors['message'])): ?><div class="form-error"><?= e($errors['message']) ?></div><?php endif; ?>
            </div>

            <button type="submit" class="btn btn--gold btn--lg btn--block" style="margin-top: var(--space-6); font-weight: 700;">
              Send Message to Front Desk &rarr;
            </button>
          </form>

        </div>

      </div>

    </div>
  </section>

</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
