<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/guards/guest.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Validator;
use App\Core\Flash;
use App\Models\User;

$user = Auth::user();
$pageTitle = 'Profile & Security';

$errors = $_SESSION['errors'] ?? [];
unset($_SESSION['errors']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();

    $action = $_POST['action_type'] ?? 'update_profile';

    if ($action === 'update_profile') {
        $v = Validator::make($_POST, [
            'full_name'    => 'required|min:3|max:120',
            'phone'        => 'required|phone',
            'nic_passport' => 'nullable|nic_passport',
            'address'      => 'nullable|max:255'
        ]);

        if ($v->fails()) {
            $_SESSION['errors'] = $v->errors();
            redirect('guest/profile.php');
        }

        $user->update([
            'full_name'    => $_POST['full_name'],
            'phone'        => $_POST['phone'],
            'nic_passport' => $_POST['nic_passport'] ?? null,
            'address'      => $_POST['address'] ?? null
        ]);

        Flash::success("Profile details updated successfully.");
        redirect('guest/profile.php');

    } elseif ($action === 'change_password') {
        $v = Validator::make($_POST, [
            'current_password' => 'required',
            // Full policy, not just a length check.
            'new_password'     => 'required|password|confirmed'
        ]);

        if ($v->fails()) {
            $_SESSION['errors'] = $v->errors();
            redirect('guest/profile.php');
        }

        if (!$user->verifyPassword($_POST['current_password'])) {
            $_SESSION['errors'] = ['current_password' => 'Current password is incorrect.'];
            redirect('guest/profile.php');
        }

        $user->update(['password_hash' => User::hashPassword($_POST['new_password'])]);
        \App\Core\Session::regenerate();

        Flash::success("Password updated successfully.");
        redirect('guest/profile.php');
    }
}

require __DIR__ . '/../includes/header.php';
?>

<div class="app-shell">
  <?php require __DIR__ . '/../includes/sidebar.php'; ?>

  <div class="app-shell__content">
    <?php require __DIR__ . '/../includes/nav.php'; ?>

    <main id="main" class="app-shell__main">
      
      <div style="max-width: 720px; margin-inline: auto;">
        
        <div style="margin-bottom: var(--space-6);">
          <h1 class="page-title">Profile & Security</h1>
          <p class="page-hint">Manage your guest personal information and update account credentials.</p>
        </div>

        <!-- PROFILE DETAILS FORM -->
        <div class="card mb-8">
          <h2>Personal Profile Information</h2>
          <form method="post" action="<?= url('guest/profile.php') ?>" style="margin-top: var(--space-4);" data-validate>
            <?= Csrf::field() ?>
            <input type="hidden" name="action_type" value="update_profile">

            <div class="form-group">
              <label class="form-label">Full Name</label>
              <input type="text" name="full_name" class="form-control" value="<?= e($user->full_name) ?>" required>
            </div>

            <div class="form-group">
              <label class="form-label">Email Address (Read-only)</label>
              <input type="email" class="form-control" value="<?= e($user->email) ?>" disabled>
            </div>

            <div class="grid grid--2">
              <div class="form-group">
                <label class="form-label">Phone Number</label>
                <input type="tel" name="phone" class="form-control" value="<?= e($user->phone) ?>" required>
              </div>

              <div class="form-group">
                <label class="form-label">NIC / Passport No.</label>
                <input type="text" name="nic_passport" class="form-control" value="<?= e($user->nic_passport) ?>">
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Residential Address</label>
              <input type="text" name="address" class="form-control" value="<?= e($user->address) ?>">
            </div>

            <button type="submit" class="btn btn--primary">Save Profile Changes &rarr;</button>
          </form>
        </div>

        <!-- CHANGE PASSWORD FORM -->
        <div class="card">
          <h2>Change Password</h2>
          <form method="post" action="<?= url('guest/profile.php') ?>" style="margin-top: var(--space-4);" data-validate>
            <?= Csrf::field() ?>
            <input type="hidden" name="action_type" value="change_password">

            <div class="form-group">
              <label class="form-label">Current Password</label>
              <input type="password" name="current_password" class="form-control" required>
              <?php if (isset($errors['current_password'])): ?><div class="form-error"><?= e($errors['current_password']) ?></div><?php endif; ?>
            </div>

            <div class="grid grid--2">
              <div class="form-group">
                <label class="form-label">New Password</label>
                <input type="password" name="new_password" class="form-control" required minlength="8">
                <?php if (isset($errors['new_password'])): ?><div class="form-error"><?= e($errors['new_password']) ?></div><?php endif; ?>
              </div>

              <div class="form-group">
                <label class="form-label">Confirm New Password</label>
                <input type="password" name="new_password_confirm" class="form-control" required minlength="8">
              </div>
            </div>

            <button type="submit" class="btn btn--secondary">Update Password &rarr;</button>
          </form>
        </div>

      </div>

    </main>
    <?php require __DIR__ . '/../includes/footer.php'; ?>
  </div>
</div>
