<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Csrf;
use App\Core\Validator;
use App\Models\User;
use App\Core\Database;
use App\Core\Flash;
use App\Core\Logger;
use App\Core\AuditLog;
use App\Core\RateLimiter;

$submitted = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();

    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    if (RateLimiter::tooManyRegistrations($ip)) {
        Flash::error('Too many password reset requests from this connection. Please try again later.');
        redirect('auth/forgot-password.php');
    }

    $v = Validator::make($_POST, ['email' => 'required|email']);
    if ($v->fails()) {
        $errors = $v->errors();
    } else {
        $email = strtolower(trim($_POST['email']));
        $user = User::findByEmail($email);

        if ($user && $user->status === 'active') {
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $expiresAt = date('Y-m-d H:i:s', time() + RESET_TOKEN_TTL);

            Database::getInstance()->query(
                "INSERT INTO password_resets (email, token_hash, expires_at) VALUES (:e, :t, :ex)",
                [':e' => $email, ':t' => $tokenHash, ':ex' => $expiresAt]
            );

            $resetUrl = url("auth/reset-password.php?token={$token}&email=" . urlencode($email));
            
            // In development, log the reset URL to the secure app log for testing.
            // Under NO circumstance is the link rendered to the end-user HTML page.
            if (!defined('APP_ENV') || APP_ENV !== 'production') {
                Logger::info("Development Password Reset Link for {$email}: {$resetUrl}");
            }

            AuditLog::record('auth.reset_requested', 'users', (int) $user->id, "Reset requested for {$email}");
        }

        // Anti-enumeration delay so execution time is indistinguishable
        usleep(random_int(120000, 250000));
        $submitted = true;
    }
}

$pageTitle = 'Forgot Password';

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php';
?>

<main id="main" class="main-content">
  <div class="container" style="max-width: 480px;">
    
    <div class="card" style="padding: var(--space-8);">
      <h1 style="font-size: var(--text-xl); font-weight: var(--weight-bold); margin-bottom: var(--space-2);">Reset Your Password</h1>
      <p class="text-muted" style="font-size: var(--text-sm); margin-bottom: var(--space-6);">Enter your registered account email to receive password recovery instructions.</p>

      <?php if ($submitted): ?>
        <div class="card mb-6" style="background-color: var(--success-bg); border-color: var(--success-500); font-size: var(--text-sm);">
          <strong>Instructions Dispatched:</strong><br>
          If an account exists with that email address, password reset instructions have been generated.
          <?php if (!defined('APP_ENV') || APP_ENV !== 'production'): ?>
            <p style="margin-top: 8px; font-size: var(--text-xs); color: var(--color-text-subtle);">
              (Development Note: Check <code>storage/logs/app.log</code> to view the generated link).
            </p>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <form method="post" action="<?= url('auth/forgot-password.php') ?>">
        <?= Csrf::field() ?>
        
        <div class="form-group">
          <label class="form-label" for="reset-email">Email Address</label>
          <input type="email" id="reset-email" name="email" class="form-control" placeholder="guest@hotel.test" autocomplete="email" required>
          <?php if (isset($errors['email'])): ?><div class="form-error"><?= e($errors['email']) ?></div><?php endif; ?>
        </div>

        <button type="submit" class="btn btn--primary btn--block">Send Reset Link &rarr;</button>
      </form>

      <div style="text-align: center; margin-top: var(--space-6);">
        <a href="<?= url('auth/login.php') ?>" style="font-size: var(--text-sm);">&larr; Return to Login</a>
      </div>
    </div>

  </div>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
