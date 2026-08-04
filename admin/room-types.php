<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/guards/admin.php';

use App\Core\Auth;
use App\Models\RoomType;
use App\Models\Amenity;
use App\Core\Uploader;
use App\Core\Csrf;
use App\Core\Validator;
use App\Core\Flash;

$user = Auth::user();
$pageTitle = 'Room Types Management';

$errors = $_SESSION['errors'] ?? [];
unset($_SESSION['errors']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();

    $action = $_POST['action_type'] ?? 'create';

    if ($action === 'create' || $action === 'update') {
        $v = Validator::make($_POST, [
            'name'       => 'required|min:3|max:80',
            'base_price' => 'required|numeric|min:0',
            'max_adults' => 'required|integer|min:1|max:10',
            'max_children' => 'integer|min:0|max:6',
            'bed_type'   => 'required|in:single,double,twin,queen,king',
        ]);

        if ($v->fails()) {
            $_SESSION['errors'] = $v->errors();
            redirect('admin/room-types.php');
        }

        $imagePath = null;
        if (!empty($_FILES['cover_image']['name'])) {
            try {
                $uploader = new Uploader();
                $imagePath = $uploader->store($_FILES['cover_image'], 'rooms');
            } catch (\App\Exceptions\ValidationException $e) {
                /**
                 * FIX: this branch set the flash message but never stopped
                 * execution, unlike the \Throwable branch right below it.
                 * Control fell straight through to RoomType::create($data)
                 * further down with $imagePath still null — so an oversized,
                 * wrong-format, or corrupt image showed a "too large"/"invalid"
                 * error toast and the room type was created anyway, silently
                 * without a photo. A photo problem must block the submission
                 * entirely so the admin re-submits with a valid image instead
                 * of ending up with a photo-less room they didn't ask for.
                 */
                $msgs = $e->errors();
                Flash::error('Image upload failed: ' . reset($msgs));
                redirect('admin/room-types.php');
            } catch (\Throwable $e) {
                \App\Core\Logger::error($e);
                Flash::error('Image upload failed. Please try a different file.');
                redirect('admin/room-types.php');
            }
        }

        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $_POST['name']), '-'));

        /**
         * FIX: `room_types.slug` has a UNIQUE key at the database level, but
         * nothing here checked for a collision before INSERT/UPDATE. Two room
         * type names that slugify to the same string (or simply re-submitting
         * the same name twice — e.g. after the image-upload error a moment
         * ago silently discarded the form) hit the raw PDOException, which
         * had no catch anywhere in this file and reached the global exception
         * handler: a 500 page instead of a normal "name already in use"
         * message on the form the admin was just filling in.
         */
        $slugCheckSql = "SELECT COUNT(*) FROM room_types WHERE slug = :slug";
        $slugCheckParams = [':slug' => $slug];
        if ($action === 'update') {
            $slugCheckSql .= " AND id != :self_id";
            $slugCheckParams[':self_id'] = (int)($_POST['room_type_id'] ?? 0);
        }
        $slugTaken = (int) \App\Core\Database::getInstance()
            ->query($slugCheckSql, $slugCheckParams)
            ->fetchColumn() > 0;

        if ($slugTaken) {
            Flash::error("A room type with a matching name ('{$_POST['name']}') already exists. Please choose a different name.");
            redirect('admin/room-types.php');
        }

        $data = [
            'name'         => $_POST['name'],
            'slug'         => $slug,
            'description'  => $_POST['description'] ?? '',
            'base_price'   => (float)$_POST['base_price'],
            'max_adults'   => (int)$_POST['max_adults'],
            'max_children' => (int)($_POST['max_children'] ?? 0),
            'bed_type'     => $_POST['bed_type'],
            'size_sqft'    => (int)($_POST['size_sqft'] ?? 300),
            'is_active'    => 1
        ];
        if ($imagePath) {
            $data['cover_image'] = $imagePath;
        }

        // Belt and braces: the slug check above catches the common case, but
        // any other unforeseen database error here (connection drop, a
        // constraint we haven't accounted for) should still land the admin
        // back on this form with a plain message instead of a 500 page.
        try {
            if ($action === 'create') {
                $type = RoomType::create($data);
                if (isset($_POST['amenities']) && is_array($_POST['amenities'])) {
                    $type->syncAmenities($_POST['amenities']);
                }
                Flash::success("New room type '{$type->name}' created.");
            } else {
                $id = (int)$_POST['room_type_id'];
                $type = RoomType::find($id);
                if ($type) {
                    $type->update($data);
                    if (isset($_POST['amenities']) && is_array($_POST['amenities'])) {
                        $type->syncAmenities($_POST['amenities']);
                    }
                    Flash::success("Room type updated successfully.");
                }
            }
        } catch (\Throwable $e) {
            \App\Core\Logger::error($e);
            Flash::error('Could not save the room type. Please try again.');
        }

    } elseif ($action === 'delete') {
        $id = (int)$_POST['room_type_id'];
        $type = RoomType::find($id);
        if ($type) {
            $type->softDelete();
            Flash::success("Room type '{$type->name}' deactivated. Use \"Show Inactive\" to reactivate it later.");
        }

    } elseif ($action === 'reactivate') {
        $id = (int)$_POST['room_type_id'];
        $type = RoomType::find($id);
        if ($type) {
            $type->reactivate();
            Flash::success("Room type '{$type->name}' reactivated and visible to guests again.");
        }
    }

    // Preserve the current active/inactive filter across the redirect so the
    // admin lands back on the same view instead of being bounced to
    // "active only" every time.
    $backTo = ($_GET['show'] ?? '') === 'all' ? '?show=all' : '';
    redirect('admin/room-types.php' . $backTo);
}

/**
 * FIX: this always called RoomType::activeOnly(), so a deactivated room type
 * had no way back — it vanished from this page the moment "Deactivate" was
 * clicked, with nothing left to click "Reactivate" on. ?show=all lists both
 * states with a status badge so deactivated types can be found and restored.
 */
$showAll   = ($_GET['show'] ?? '') === 'all';
$roomTypes = $showAll ? RoomType::all(['name' => 'ASC']) : RoomType::activeOnly();
$amenities = Amenity::all();

require __DIR__ . '/../includes/header.php';
?>

<div class="app-shell">
  <?php require __DIR__ . '/../includes/sidebar.php'; ?>

  <div class="app-shell__content">
    <?php require __DIR__ . '/../includes/nav.php'; ?>

    <main id="main" class="app-shell__main">
      
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-6); flex-wrap: wrap; gap: var(--space-3);">
        <div>
          <h1 class="page-title">Room Types Management</h1>
          <p class="page-hint" style="margin-bottom: 0;">Add, update, deactivate, or reactivate room categories and assign master amenities.</p>
        </div>
        <div style="display: flex; gap: var(--space-2); align-items: center;">
          <?php if ($showAll): ?>
            <a href="<?= url('admin/room-types.php') ?>" class="btn btn--secondary btn--sm"><?= icon('check-circle', 14) ?> Show Active Only</a>
          <?php else: ?>
            <a href="<?= url('admin/room-types.php?show=all') ?>" class="btn btn--secondary btn--sm"><?= icon('ban', 14) ?> Show Inactive</a>
          <?php endif; ?>
          <button type="button" class="btn btn--primary" data-modal-target="create-type-modal"><?= icon('plus', 16) ?> Create Room Type</button>
        </div>
      </div>

      <!-- ROOM TYPES TABLE -->
      <div class="table-container">
        <table class="table table--hoverable" data-responsive>
          <thead>
            <tr>
              <th>Image</th>
              <th>Category Name</th>
              <th>Base Price</th>
              <th>Max Occupancy</th>
              <th>Bed Type</th>
              <?php if ($showAll): ?><th>Status</th><?php endif; ?>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($roomTypes)): ?>
              <tr><td colspan="<?= $showAll ? 7 : 6 ?>" class="text-center text-muted" style="padding: var(--space-8);">
                <?= $showAll ? 'No room types found.' : 'No active room types. Check "Show Inactive" if you expected to see one here.' ?>
              </td></tr>
            <?php endif; ?>
            <?php foreach ($roomTypes as $t): ?>
              <tr>
                <td><img src="<?= url($t->cover_image ?? 'assets/img/placeholders/room_placeholder.jpg') ?>" style="width: 60px; height: 40px; object-fit: cover; border-radius: 4px; <?= $t->is_active ? '' : 'opacity: 0.45;' ?>"></td>
                <td data-label="Name"><strong><?= e($t->name) ?></strong></td>
                <td data-label="Base Price" style="font-weight: bold; color: var(--color-primary);"><?= money($t->base_price) ?></td>
                <td data-label="Occupancy" class="icon-heading"><?= icon('users', 14) ?> <?= $t->max_adults ?> Adults, <?= $t->max_children ?> Kids</td>
                <td data-label="Bed"><span class="badge badge--info"><?= strtoupper($t->bed_type) ?></span></td>
                <?php if ($showAll): ?>
                  <td data-label="Status">
                    <?php if ($t->is_active): ?>
                      <span class="badge badge--success"><?= icon('check-circle', 12) ?> Active</span>
                    <?php else: ?>
                      <span class="badge badge--danger"><?= icon('ban', 12) ?> Inactive</span>
                    <?php endif; ?>
                  </td>
                <?php endif; ?>
                <td data-label="Actions">
                  <div style="display: flex; gap: var(--space-2);">
                    <?php if ($t->is_active): ?>
                      <form method="post" action="<?= url('admin/room-types.php') ?>" style="display: inline;" data-confirm="Deactivate <?= e($t->name) ?>? It will stop showing on the public site until reactivated.">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="action_type" value="delete">
                        <input type="hidden" name="room_type_id" value="<?= $t->id ?>">
                        <button type="submit" class="btn btn--danger btn--sm">Deactivate</button>
                      </form>
                    <?php else: ?>
                      <form method="post" action="<?= url('admin/room-types.php?show=all') ?>" style="display: inline;">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="action_type" value="reactivate">
                        <input type="hidden" name="room_type_id" value="<?= $t->id ?>">
                        <button type="submit" class="btn btn--primary btn--sm"><?= icon('refresh-cw', 14) ?> Reactivate</button>
                      </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- CREATE ROOM TYPE MODAL -->
      <div class="modal" id="create-type-modal">
        <div class="modal__backdrop"></div>
        <div class="modal__dialog" style="max-width: 680px;">
          <h3>Create New Room Category</h3>
          
          <form method="post" action="<?= url('admin/room-types.php') ?>" enctype="multipart/form-data" style="margin-top: var(--space-4);">
            <?= Csrf::field() ?>
            <input type="hidden" name="action_type" value="create">

            <div class="grid grid--2">
              <div class="form-group">
                <label class="form-label">Category Name</label>
                <input type="text" name="name" class="form-control" placeholder="e.g. Oceanfront Suite" required>
              </div>

              <div class="form-group">
                <label class="form-label">Base Price (LKR per night)</label>
                <input type="number" step="0.01" name="base_price" class="form-control" placeholder="25000.00" required>
              </div>

              <div class="form-group">
                <label class="form-label">Max Adults</label>
                <input type="number" name="max_adults" class="form-control" value="2" required>
              </div>

              <div class="form-group">
                <label class="form-label">Max Children</label>
                <input type="number" name="max_children" class="form-control" value="1">
              </div>

              <div class="form-group">
                <label class="form-label">Bed Type</label>
                <select name="bed_type" class="form-select" required>
                  <option value="single">Single Bed</option>
                  <option value="double">Double Bed</option>
                  <option value="twin">Twin Beds</option>
                  <option value="queen">Queen Bed</option>
                  <option value="king" selected>King Bed</option>
                </select>
              </div>

              <div class="form-group">
                <label class="form-label">Size (sq ft)</label>
                <input type="number" name="size_sqft" class="form-control" value="400">
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Category Description</label>
              <textarea name="description" rows="3" class="form-textarea" required></textarea>
            </div>

            <div class="form-group">
              <label class="form-label">Upload Cover Photo (JPG/PNG/WebP, max 2MB)</label>
              <input type="file" name="cover_image" class="form-control" accept="image/*">
            </div>

            <div class="form-group">
              <label class="form-label">Assign Master Amenities</label>
              <div class="grid grid--3" style="gap: 8px; font-size: var(--text-xs);">
                <?php foreach ($amenities as $am): ?>
                  <label style="display: flex; align-items: center; gap: 4px;">
                    <input type="checkbox" name="amenities[]" value="<?= $am->id ?>">
                    <?= e($am->name) ?>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>

            <div style="display: flex; gap: var(--space-3); justify-content: flex-end; margin-top: var(--space-6);">
              <button type="button" class="btn btn--secondary" data-modal-close>Cancel</button>
              <button type="submit" class="btn btn--primary">Save Room Category</button>
            </div>
          </form>
        </div>
      </div>

    </main>
    <?php require __DIR__ . '/../includes/footer.php'; ?>
  </div>
</div>
