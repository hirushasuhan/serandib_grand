<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Validator;
use App\Models\User;
use App\Core\Flash;

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$email = $_GET['email'] ?? $_POST['email'] ?? '';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();

    $v = Validator::make($_POST, [
        'password'         => 'required|password|confirmed',
        'password_confirm' => 'required'
    ]);

    if ($v->fails()) {
        $errors = $v->errors();
    } else {
        $tokenHash = hash('sha256', $token);
        $db = Database::getInstance();

        $reset = $db->query(
            "SELECT * FROM password_resets WHERE email = :e AND token_hash = :th AND used_at IS NULL AND expires_at > NOW() LIMIT 1",
            [':e' => $email, ':th' => $tokenHash]
        )->fetch();

        if ($reset) {
            $user = User::findByEmail($email);
            if ($user) {
                $user->update(['password_hash' => User::hashPassword($_POST['password'])]);
                // Invalidate all tokens for this email
                $db->query("UPDATE password_resets SET used_at = NOW() WHERE email = :e", [':e' => $email]);

                \App\Core\AuditLog::record('auth.password_reset', 'users', (int) $user->id, "Password reset via token");

                Flash::success("Password reset successfully! You can now log in with your new password.");
                redirect('auth/login.php');
            }
        } else {
            $errors['token'] = "Invalid or expired password reset token.";
        }
    }
}

$pageTitle = 'Set New Password';

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php';
?>

<main id="main" class="main-content">
  <div class="container" style="max-width: 480px;">
    
    <div class="card" style="padding: var(--space-8);">
      <h1 style="font-size: var(--text-xl); font-weight: var(--weight-bold); margin-bottom: var(--space-2);">Set New Password</h1>

      <?php if (isset($errors['token'])): ?>
        <div class="card mb-6" style="background-color: var(--danger-bg); border-color: var(--danger-500); font-size: var(--text-sm);">
          <?= e($errors['token']) ?>
        </div>
      <?php endif; ?>

      <form method="post" action="<?= url('auth/reset-password.php') ?>">
        <?= Csrf::field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <input type="hidden" name="email" value="<?= e($email) ?>">

        <div class="form-group">
          <label class="form-label">New Password</label>
          <input type="password" name="password" class="form-control" required minlength="8">
          <?php if (isset($errors['password'])): ?><div class="form-error"><?= e($errors['password']) ?></div><?php endif; ?>
        </div>

        <div class="form-group">
          <label class="form-label">Confirm New Password</label>
          <input type="password" name="password_confirm" class="form-control" required minlength="8">
        </div>

        <button type="submit" class="btn btn--primary btn--block">Update Password &rarr;</button>
      </form>

    </div>

  </div>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
