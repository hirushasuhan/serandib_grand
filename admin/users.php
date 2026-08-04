<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/guards/admin.php';

use App\Core\Auth;
use App\Models\User;
use App\Core\Csrf;
use App\Core\Validator;
use App\Core\Database;
use App\Core\Flash;

$user = Auth::user();
$pageTitle = 'User Accounts Management';

$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();

    $action = $_POST['action_type'] ?? 'create';

    if ($action === 'create') {
        $v = Validator::make($_POST, [
            'full_name' => 'required|min:3|max:120',
            'email'     => 'required|email|unique:users,email',
            'phone'     => 'required|phone',
            'password'  => 'required|password',
            'role'      => 'required|in:guest,receptionist,manager,admin'
        ]);

        if ($v->fails()) {
            // Previously the failure was swallowed and the admin saw a blank
            // success-less redirect with no idea what went wrong.
            $_SESSION['errors'] = $v->errors();
            Flash::error('Could not create the account — please correct the highlighted fields.');
            redirect('admin/users.php');
        }

        // Explicit field list — never $_POST wholesale. `role` is settable here
        // (this is the admin screen) but NOT on guest/profile.php, so a guest
        // cannot post role=admin and escalate their own privileges.
        User::create([
            'full_name'     => $_POST['full_name'],
            'email'         => strtolower(trim($_POST['email'])),
            'phone'         => $_POST['phone'],
            'password_hash' => User::hashPassword($_POST['password']),
            'role'          => $_POST['role'],
            'status'        => 'active',
        ]);

        Flash::success('User account for ' . $_POST['email'] . ' provisioned.');

    } elseif ($action === 'toggle_status') {
        $targetId = (int)$_POST['user_id'];
        $targetUser = User::find($targetId);
        if ($targetUser && $targetUser->id !== Auth::id()) {
            $newStatus = $targetUser->status === 'active' ? 'suspended' : 'active';
            $targetUser->update(['status' => $newStatus]);
            Flash::success("Account status for {$targetUser->email} set to {$newStatus}.");
        }
    }

    redirect('admin/users.php');
}

$users = User::all(['created_at' => 'DESC']);

require __DIR__ . '/../includes/header.php';
?>

<div class="app-shell">
  <?php require __DIR__ . '/../includes/sidebar.php'; ?>

  <div class="app-shell__content">
    <?php require __DIR__ . '/../includes/nav.php'; ?>

    <main id="main" class="app-shell__main">
      
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-6);">
        <div>
          <h1 class="page-title">User Accounts & Staff Provisioning</h1>
          <p class="page-hint" style="margin-bottom: 0;">Create staff accounts, assign RBAC roles, or suspend user access.</p>
        </div>
        <button type="button" class="btn btn--primary" data-modal-target="create-user-modal"><?= icon('plus', 16) ?> Provision Staff / User</button>
      </div>

      <!-- USERS TABLE -->
      <div class="table-container">
        <table class="table table--hoverable" data-responsive>
          <thead>
            <tr>
              <th>ID</th>
              <th>Full Name</th>
              <th>Email</th>
              <th>Phone</th>
              <th>Role</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $u): ?>
              <tr>
                <td>#<?= $u->id ?></td>
                <td data-label="Name"><strong><?= e($u->full_name) ?></strong></td>
                <td data-label="Email"><?= e($u->email) ?></td>
                <td data-label="Phone"><?= e($u->phone) ?></td>
                <td data-label="Role"><span class="badge badge--info"><?= strtoupper($u->role) ?></span></td>
                <td data-label="Status"><span class="badge badge--<?= $u->status === 'active' ? 'success' : 'danger' ?>"><?= ucfirst($u->status) ?></span></td>
                <td data-label="Actions">
                  <?php if ($u->id !== Auth::id()): ?>
                    <form method="post" action="<?= url('admin/users.php') ?>" style="display: inline;">
                      <?= Csrf::field() ?>
                      <input type="hidden" name="action_type" value="toggle_status">
                      <input type="hidden" name="user_id" value="<?= $u->id ?>">
                      <button type="submit" class="btn btn--<?= $u->status === 'active' ? 'danger' : 'primary' ?> btn--sm">
                        <?= $u->status === 'active' ? 'Suspend' : 'Reactivate' ?>
                      </button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- PROVISION USER MODAL -->
      <div class="modal" id="create-user-modal">
        <div class="modal__backdrop"></div>
        <div class="modal__dialog">
          <h3>Provision Account</h3>
          
          <form method="post" action="<?= url('admin/users.php') ?>" style="margin-top: var(--space-4);">
            <?= Csrf::field() ?>
            <input type="hidden" name="action_type" value="create">

            <div class="form-group">
              <label class="form-label">Full Name</label>
              <input type="text" name="full_name" class="form-control" placeholder="e.g. John Perera" required>
            </div>

            <div class="form-group">
              <label class="form-label">Email Address</label>
              <input type="email" name="email" class="form-control" placeholder="staff@hotel.test" required>
            </div>

            <div class="form-group">
              <label class="form-label">Phone Number</label>
              <input type="tel" name="phone" class="form-control" placeholder="0771234567" required>
            </div>

            <div class="form-group">
              <label class="form-label">Role Assignment</label>
              <select name="role" class="form-select" required>
                <option value="guest">Guest</option>
                <option value="receptionist" selected>Receptionist Staff</option>
                <option value="manager">Hotel Manager</option>
                <option value="admin">System Administrator</option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">Initial Password</label>
              <input type="password" name="password" class="form-control" placeholder="Min 8 characters" required>
            </div>

            <div style="display: flex; gap: var(--space-3); justify-content: flex-end; margin-top: var(--space-6);">
              <button type="button" class="btn btn--secondary" data-modal-close>Cancel</button>
              <button type="submit" class="btn btn--primary">Provision Account</button>
            </div>
          </form>
        </div>
      </div>

    </main>
    <?php require __DIR__ . '/../includes/footer.php'; ?>
  </div>
</div>
