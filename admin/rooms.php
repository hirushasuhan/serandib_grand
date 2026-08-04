<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/guards/admin.php';

use App\Core\Auth;
use App\Models\Room;
use App\Models\RoomType;
use App\Core\Csrf;
use App\Core\Validator;
use App\Core\Database;
use App\Core\Flash;

$user = Auth::user();
$pageTitle = 'Physical Rooms CRUD';

$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();

    $action = $_POST['action_type'] ?? 'create';

    if ($action === 'create') {
        $v = Validator::make($_POST, [
            'room_number'  => 'required|unique:rooms,room_number',
            'room_type_id' => 'required|integer|exists:room_types,id',
            'floor'        => 'required|integer|min:0|max:50',
            'status'       => 'required|in:available,occupied,cleaning,maintenance'
        ]);

        if (!$v->fails()) {
            Room::create([
                'room_number'  => $_POST['room_number'],
                'room_type_id' => $_POST['room_type_id'],
                'floor'        => $_POST['floor'],
                'status'       => $_POST['status'],
                'is_active'    => 1
            ]);
            Flash::success("Room {$_POST['room_number']} created.");
        }

    } elseif ($action === 'bulk_create') {
        $floor    = (int)$_POST['floor'];
        $startNum = (int)$_POST['start_number'];
        $endNum   = (int)$_POST['end_number'];
        $typeId   = (int)$_POST['room_type_id'];

        $count = 0;
        for ($num = $startNum; $num <= $endNum; $num++) {
            $roomNo = (string)$num;
            $exists = $db->query("SELECT COUNT(*) FROM rooms WHERE room_number = :n", [':n' => $roomNo])->fetchColumn();
            if (!$exists) {
                Room::create([
                    'room_number'  => $roomNo,
                    'room_type_id' => $typeId,
                    'floor'        => $floor,
                    'status'       => 'available',
                    'is_active'    => 1
                ]);
                $count++;
            }
        }
        Flash::success("Bulk room generator created {$count} new rooms on Floor {$floor}.");

    } elseif ($action === 'delete') {
        $id = (int)$_POST['room_id'];
        // Pre-check active bookings to show friendly error instead of SQL FK crash (per §4.2)
        $hasActive = $db->query("SELECT COUNT(*) FROM bookings WHERE room_id = :id AND status IN ('pending','confirmed','checked_in')", [':id' => $id])->fetchColumn();
        if ($hasActive > 0) {
            Flash::error("Cannot delete room: This physical room has active reservations.");
        } else {
            $room = Room::find($id);
            if ($room) {
                $room->delete();
                Flash::success("Room deleted.");
            }
        }

    /**
     * FIX: there was no way to take a room out of service without deleting
     * it outright — and delete is a hard DELETE FROM rooms, which is
     * refused whenever the room has ever had a booking (foreign key). A room
     * being renovated, temporarily out of service, or listed by mistake had
     * no in-between state: it stayed fully bookable forever, or the admin
     * had to permanently destroy its booking history to remove it. Deactivate
     * hides it from the front desk/booking pages (r.is_active = 1 filter)
     * without touching any of that history, and can be undone.
     */
    } elseif ($action === 'deactivate') {
        $id = (int)$_POST['room_id'];
        $room = Room::find($id);
        if ($room) {
            $room->softDelete();
            Flash::success("Room {$room->room_number} deactivated. Use \"Show Inactive\" to reactivate it later.");
        }

    } elseif ($action === 'reactivate') {
        $id = (int)$_POST['room_id'];
        $room = Room::find($id);
        if ($room) {
            $room->reactivate();
            Flash::success("Room {$room->room_number} reactivated.");
        }
    }

    $backTo = ($_GET['show'] ?? '') === 'all' ? '?show=all' : '';
    redirect('admin/rooms.php' . $backTo);
}

$showAll = ($_GET['show'] ?? '') === 'all';
$sql = "SELECT r.*, rt.name AS room_type_name FROM rooms r JOIN room_types rt ON rt.id = r.room_type_id "
     . ($showAll ? '' : 'WHERE r.is_active = 1 ')
     . "ORDER BY r.floor ASC, r.room_number ASC";
$rooms = $db->query($sql)->fetchAll();
$roomTypes = RoomType::activeOnly();

require __DIR__ . '/../includes/header.php';
?>

<div class="app-shell">
  <?php require __DIR__ . '/../includes/sidebar.php'; ?>

  <div class="app-shell__content">
    <?php require __DIR__ . '/../includes/nav.php'; ?>

    <main id="main" class="app-shell__main">
      
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-6); flex-wrap: wrap; gap: var(--space-3);">
        <div>
          <h1 class="page-title">Physical Rooms Inventory</h1>
          <p class="page-hint" style="margin-bottom: 0;">Add single rooms, deactivate rooms out of service, or use the bulk room generator for floor inventory.</p>
        </div>
        <div style="display: flex; gap: var(--space-2); flex-wrap: wrap;">
          <?php if ($showAll): ?>
            <a href="<?= url('admin/rooms.php') ?>" class="btn btn--secondary btn--sm"><?= icon('check-circle', 14) ?> Show Active Only</a>
          <?php else: ?>
            <a href="<?= url('admin/rooms.php?show=all') ?>" class="btn btn--secondary btn--sm"><?= icon('ban', 14) ?> Show Inactive</a>
          <?php endif; ?>
          <button type="button" class="btn btn--secondary" data-modal-target="bulk-modal"><?= icon('zap', 16) ?> Bulk Room Generator</button>
          <button type="button" class="btn btn--primary" data-modal-target="create-room-modal"><?= icon('plus', 16) ?> Add Single Room</button>
        </div>
      </div>

      <!-- ROOMS TABLE -->
      <div class="table-container">
        <table class="table table--hoverable" data-responsive>
          <thead>
            <tr>
              <th>Room No</th>
              <th>Category</th>
              <th>Floor</th>
              <th>Current Status</th>
              <?php if ($showAll): ?><th>Active</th><?php endif; ?>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rooms)): ?>
              <tr><td colspan="<?= $showAll ? 6 : 5 ?>" class="text-center text-muted" style="padding: var(--space-8);">
                <?= $showAll ? 'No rooms found.' : 'No active rooms. Check "Show Inactive" if you expected to see one here.' ?>
              </td></tr>
            <?php endif; ?>
            <?php foreach ($rooms as $r): ?>
              <tr>
                <td data-label="Room No"><span class="mono" style="font-weight: bold; font-size: 1.1rem;"><?= e($r['room_number']) ?></span></td>
                <td data-label="Category"><?= e($r['room_type_name']) ?></td>
                <td data-label="Floor">Floor <?= $r['floor'] ?></td>
                <td data-label="Status"><span class="badge badge--<?= e($r['status']) ?>"><?= ucfirst($r['status']) ?></span></td>
                <?php if ($showAll): ?>
                  <td data-label="Active">
                    <?php if ((int)$r['is_active'] === 1): ?>
                      <span class="badge badge--success"><?= icon('check-circle', 12) ?> Active</span>
                    <?php else: ?>
                      <span class="badge badge--danger"><?= icon('ban', 12) ?> Inactive</span>
                    <?php endif; ?>
                  </td>
                <?php endif; ?>
                <td data-label="Actions">
                  <div style="display: flex; gap: var(--space-2); flex-wrap: wrap;">
                    <?php if ((int)$r['is_active'] === 1): ?>
                      <form method="post" action="<?= url('admin/rooms.php') ?>" style="display: inline;" data-confirm="Deactivate Room <?= e($r['room_number']) ?>? It will stop being offered for new bookings until reactivated.">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="action_type" value="deactivate">
                        <input type="hidden" name="room_id" value="<?= $r['id'] ?>">
                        <button type="submit" class="btn btn--secondary btn--sm">Deactivate</button>
                      </form>
                    <?php else: ?>
                      <form method="post" action="<?= url('admin/rooms.php?show=all') ?>" style="display: inline;">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="action_type" value="reactivate">
                        <input type="hidden" name="room_id" value="<?= $r['id'] ?>">
                        <button type="submit" class="btn btn--primary btn--sm"><?= icon('refresh-cw', 14) ?> Reactivate</button>
                      </form>
                    <?php endif; ?>
                    <form method="post" action="<?= url('admin/rooms.php' . ($showAll ? '?show=all' : '')) ?>" style="display: inline;" data-confirm="Permanently delete Room <?= e($r['room_number']) ?>? This cannot be undone and only works if it has no booking history.">
                      <?= Csrf::field() ?>
                      <input type="hidden" name="action_type" value="delete">
                      <input type="hidden" name="room_id" value="<?= $r['id'] ?>">
                      <button type="submit" class="btn btn--danger btn--sm">Delete</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- BULK GENERATOR MODAL -->
      <div class="modal" id="bulk-modal">
        <div class="modal__backdrop"></div>
        <div class="modal__dialog">
          <h3 class="icon-heading"><span style="color: var(--color-primary);"><?= icon('zap', 18) ?></span> Bulk Room Generator Helper</h3>
          <p class="text-muted" style="font-size: var(--text-sm); margin-bottom: var(--space-4);">Quickly create room numbers (e.g. 301 to 310) on a floor.</p>

          <form method="post" action="<?= url('admin/rooms.php') ?>">
            <?= Csrf::field() ?>
            <input type="hidden" name="action_type" value="bulk_create">

            <div class="form-group">
              <label class="form-label">Floor Number</label>
              <input type="number" name="floor" class="form-control" value="3" required>
            </div>

            <div class="grid grid--2">
              <div class="form-group">
                <label class="form-label">Start Room No.</label>
                <input type="number" name="start_number" class="form-control" value="301" required>
              </div>

              <div class="form-group">
                <label class="form-label">End Room No.</label>
                <input type="number" name="end_number" class="form-control" value="310" required>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Assign Category</label>
              <select name="room_type_id" class="form-select" required>
                <?php foreach ($roomTypes as $t): ?>
                  <option value="<?= $t->id ?>"><?= e($t->name) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div style="display: flex; gap: var(--space-3); justify-content: flex-end; margin-top: var(--space-6);">
              <button type="button" class="btn btn--secondary" data-modal-close>Cancel</button>
              <button type="submit" class="btn btn--primary">Generate Rooms</button>
            </div>
          </form>
        </div>
      </div>

      <!-- CREATE SINGLE ROOM MODAL -->
      <div class="modal" id="create-room-modal">
        <div class="modal__backdrop"></div>
        <div class="modal__dialog">
          <h3>Add Single Physical Room</h3>
          
          <form method="post" action="<?= url('admin/rooms.php') ?>" style="margin-top: var(--space-4);">
            <?= Csrf::field() ?>
            <input type="hidden" name="action_type" value="create">

            <div class="form-group">
              <label class="form-label">Room Number</label>
              <input type="text" name="room_number" class="form-control" placeholder="e.g. 101" required>
            </div>

            <div class="form-group">
              <label class="form-label">Category</label>
              <select name="room_type_id" class="form-select" required>
                <?php foreach ($roomTypes as $t): ?>
                  <option value="<?= $t->id ?>"><?= e($t->name) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">Floor</label>
              <input type="number" name="floor" class="form-control" value="1" required>
            </div>

            <div class="form-group">
              <label class="form-label">Initial Status</label>
              <select name="status" class="form-select" required>
                <option value="available" selected>Available</option>
                <option value="cleaning">Cleaning</option>
                <option value="maintenance">Maintenance</option>
              </select>
            </div>

            <div style="display: flex; gap: var(--space-3); justify-content: flex-end; margin-top: var(--space-6);">
              <button type="button" class="btn btn--secondary" data-modal-close>Cancel</button>
              <button type="submit" class="btn btn--primary">Create Room</button>
            </div>
          </form>
        </div>
      </div>

    </main>
    <?php require __DIR__ . '/../includes/footer.php'; ?>
  </div>
</div>
