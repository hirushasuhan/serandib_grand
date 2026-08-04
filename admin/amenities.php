<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/guards/admin.php';

use App\Core\Auth;
use App\Models\Amenity;
use App\Core\Csrf;
use App\Core\Validator;
use App\Core\Flash;

$user = Auth::user();
$pageTitle = 'Master Amenities CRUD';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    
    $v = Validator::make($_POST, [
        'name'     => 'required|min:2|max:60|unique:amenities,name',
        'category' => 'in:room,bathroom,media,services'
    ]);

    if (!$v->fails()) {
        Amenity::create([
            'name'     => $_POST['name'],
            'icon'     => $_POST['icon'] ?? 'star',
            'category' => $_POST['category'] ?? 'room'
        ]);
        Flash::success("Amenity '{$_POST['name']}' added to master list.");
    }

    redirect('admin/amenities.php');
}

$amenities = Amenity::all();

require __DIR__ . '/../includes/header.php';
?>

<div class="app-shell">
  <?php require __DIR__ . '/../includes/sidebar.php'; ?>

  <div class="app-shell__content">
    <?php require __DIR__ . '/../includes/nav.php'; ?>

    <main id="main" class="app-shell__main">
      
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-6);">
        <div>
          <h1 class="page-title">Amenities Master Catalog</h1>
          <p class="page-hint" style="margin-bottom: 0;">Add or manage hotel amenity offerings.</p>
        </div>
        <button type="button" class="btn btn--primary" data-modal-target="create-amenity-modal"><?= icon('plus', 16) ?> Add Amenity</button>
      </div>

      <div class="table-container">
        <table class="table table--hoverable" data-responsive>
          <thead>
            <tr>
              <th>ID</th>
              <th>Amenity Name</th>
              <th>Icon Slug</th>
              <th>Category</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($amenities as $am): ?>
              <tr>
                <td>#<?= $am->id ?></td>
                <td><strong><?= e($am->name) ?></strong></td>
                <td><span class="mono"><?= e($am->icon) ?></span></td>
                <td><span class="badge badge--info"><?= ucfirst($am->category) ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- CREATE AMENITY MODAL -->
      <div class="modal" id="create-amenity-modal">
        <div class="modal__backdrop"></div>
        <div class="modal__dialog">
          <h3>Add New Master Amenity</h3>
          
          <form method="post" action="<?= url('admin/amenities.php') ?>" style="margin-top: var(--space-4);">
            <?= Csrf::field() ?>

            <div class="form-group">
              <label class="form-label">Amenity Name</label>
              <input type="text" name="name" class="form-control" placeholder="e.g. Private Plunge Pool" required>
            </div>

            <div class="form-group">
              <label class="form-label">Icon Identifier</label>
              <input type="text" name="icon" class="form-control" value="star" required>
            </div>

            <div class="form-group">
              <label class="form-label">Category</label>
              <select name="category" class="form-select" required>
                <option value="room">Room Features</option>
                <option value="bathroom">Bathroom & Spa</option>
                <option value="media">Media & Tech</option>
                <option value="services">Hotel Services</option>
              </select>
            </div>

            <div style="display: flex; gap: var(--space-3); justify-content: flex-end; margin-top: var(--space-6);">
              <button type="button" class="btn btn--secondary" data-modal-close>Cancel</button>
              <button type="submit" class="btn btn--primary">Save Amenity</button>
            </div>
          </form>
        </div>
      </div>

    </main>
    <?php require __DIR__ . '/../includes/footer.php'; ?>
  </div>
</div>
