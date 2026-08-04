<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Csrf;
use App\Core\Validator;
use App\Models\User;
use App\Core\Database;

$resetLink = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    
    $v = Validator::make($_POST, ['email' => 'required|email']);
    if ($v->fails()) {
        $errors = $v->errors();
    } else {
        $email = strtolower(trim($_POST['email']));
        $user = User::findByEmail($email);
        
        if ($user) {
            $token = bin2hex(random_bytes(24));
            $tokenHash = hash('sha256', $token);
            $expiresAt = date('Y-m-d H:i:s', time() + RESET_TOKEN_TTL);

            Database::getInstance()->query(
                "INSERT INTO password_resets (email, token_hash, expires_at) VALUES (:e, :t, :ex)",
                [':e' => $email, ':t' => $tokenHash, ':ex' => $expiresAt]
            );

            $resetLink = url("auth/reset-password.php?token={$token}&email=" . urlencode($email));
        } else {
            $resetLink = 'demo_mode_no_account';
        }
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
      <p class="text-muted" style="font-size: var(--text-sm); margin-bottom: var(--space-6);">Enter your registered account email to generate a single-use reset token link.</p>

      <?php if ($resetLink): ?>
        <?php if ($resetLink === 'demo_mode_no_account'): ?>
          <div class="card mb-6" style="background-color: var(--danger-bg); border-color: var(--danger-500); font-size: var(--text-sm);">
            If an account exists with that email, a password reset link has been generated. (Note: No account found for this demo email).
          </div>
        <?php else: ?>
          <div class="card mb-6" style="background-color: var(--success-bg); border-color: var(--success-500); font-size: var(--text-sm);">
            <strong>Demo Password Reset Link Generated:</strong><br>
            <p style="margin-top: 8px; word-break: break-all;"><a href="<?= e($resetLink) ?>" style="font-weight: bold; text-decoration: underline;"><?= e($resetLink) ?></a></p>
            <span style="font-size: var(--text-xs); color: var(--color-text-muted);">(Valid for 30 minutes, single-use only)</span>
          </div>
        <?php endif; ?>
      <?php endif; ?>

      <form method="post" action="<?= url('auth/forgot-password.php') ?>">
        <?= Csrf::field() ?>
        
        <div class="form-group">
          <label class="form-label">Email Address</label>
          <input type="email" name="email" class="form-control" placeholder="guest@hotel.test" required>
        </div>

        <button type="submit" class="btn btn--primary btn--block">Generate Reset Link &rarr;</button>
      </form>

      <div style="text-align: center; margin-top: var(--space-6);">
        <a href="<?= url('auth/login.php') ?>" style="font-size: var(--text-sm);">&larr; Return to Login</a>
      </div>
    </div>

  </div>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
