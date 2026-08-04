<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/guards/staff.php';

use App\Core\Auth;
use App\Models\Room;
use App\Services\FrontDeskService;
use App\Core\Csrf;
use App\Core\Database;

$user = Auth::user();
$pageTitle = 'Room Status Board';

// Handle room housekeeping status update POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    $roomId = (int)($_POST['room_id'] ?? 0);
    $newStatus = $_POST['status'] ?? '';

    /**
     * FIX: FrontDeskService::updateRoomStatus() throws a RuntimeException for
     * an unknown room ID or an invalid status string. This call had no
     * try/catch, so an invalid submission (a stale form, a room deleted by
     * another tab, a tampered value) reached bootstrap.php's global
     * set_exception_handler and produced a full 500 page instead of simply
     * reloading this board with an error message — on the page staff use the
     * most, right after logging in.
     */
    try {
        $fdService = new FrontDeskService();
        $fdService->updateRoomStatus($roomId, $newStatus);
        \App\Core\Flash::success("Room housekeeping status updated.");
    } catch (\RuntimeException $e) {
        // Written by us — safe to show as-is.
        \App\Core\Flash::error($e->getMessage());
    } catch (\Throwable $e) {
        \App\Core\Logger::error($e);
        \App\Core\Flash::error('Could not update the room status. Please try again.');
    }

    redirect('staff/frontdesk.php');
}

// Fetch all active rooms grouped by floor
$sql = "SELECT r.*, rt.name AS room_type_name FROM rooms r JOIN room_types rt ON rt.id = r.room_type_id WHERE r.is_active = 1 ORDER BY r.floor ASC, r.room_number ASC";
$rooms = Database::getInstance()->query($sql)->fetchAll();

$floors = [];
foreach ($rooms as $room) {
    $floors[$room['floor']][] = $room;
}

require __DIR__ . '/../includes/header.php';
?>

<div class="app-shell">
  <?php require __DIR__ . '/../includes/sidebar.php'; ?>

  <div class="app-shell__content">
    <?php require __DIR__ . '/../includes/nav.php'; ?>

    <main id="main" class="app-shell__main">
      
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-6);">
        <div>
          <h1 class="page-title">Room Status Board</h1>
          <p class="page-hint" style="margin-bottom: 0;">Color-coded live floor grid. Click any room tile to update housekeeping status.</p>
        </div>
        <div style="display: flex; gap: var(--space-2); font-size: var(--text-xs);">
          <span class="badge badge--available">Available</span>
          <span class="badge badge--occupied">Occupied</span>
          <span class="badge badge--warning">Cleaning</span>
          <span class="badge badge--danger">Maintenance</span>
        </div>
      </div>

      <?php foreach ($floors as $floorNum => $floorRooms): ?>
        <div class="card mb-8">
          <h3 style="margin-bottom: var(--space-4); border-bottom: 1px solid var(--color-border); padding-bottom: var(--space-2);">
            Floor <?= $floorNum ?> Inventory (<?= count($floorRooms) ?> Rooms)
          </h3>

          <div class="status-board">
            <?php foreach ($floorRooms as $r): ?>
              <div class="room-tile room-tile--<?= e($r['status']) ?>" data-modal-target="room-modal-<?= $r['id'] ?>">
                <div class="room-tile__num"><?= e($r['room_number']) ?></div>
                <div style="font-size: var(--text-xs); margin-top: 4px; font-weight: var(--weight-medium);"><?= e($r['room_type_name']) ?></div>
                <div style="font-size: var(--text-xs); text-transform: uppercase; margin-top: 4px; opacity: 0.85;"><?= ucfirst($r['status']) ?></div>
              </div>

              <!-- STATUS UPDATE MODAL FOR ROOM -->
              <div class="modal" id="room-modal-<?= $r['id'] ?>">
                <div class="modal__backdrop"></div>
                <div class="modal__dialog">
                  <h3>Update Room <?= e($r['room_number']) ?> Housekeeping Status</h3>
                  <p class="text-muted" style="font-size: var(--text-sm); margin-bottom: var(--space-4);"><?= e($r['room_type_name']) ?> &bull; Floor <?= $r['floor'] ?></p>

                  <form method="post" action="<?= url('staff/frontdesk.php') ?>">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="room_id" value="<?= $r['id'] ?>">

                    <div class="form-group">
                      <label class="form-label">Set Housekeeping Status</label>
                      <select name="status" class="form-select" required>
                        <option value="available" <?= $r['status'] === 'available' ? 'selected' : '' ?>>Available & Ready</option>
                        <option value="occupied" <?= $r['status'] === 'occupied' ? 'selected' : '' ?>>Occupied (Guest In-House)</option>
                        <option value="cleaning" <?= $r['status'] === 'cleaning' ? 'selected' : '' ?>>Cleaning / Housekeeping</option>
                        <option value="maintenance" <?= $r['status'] === 'maintenance' ? 'selected' : '' ?>>Out of Order / Maintenance</option>
                      </select>
                    </div>

                    <div style="display: flex; gap: var(--space-3); justify-content: flex-end; margin-top: var(--space-6);">
                      <button type="button" class="btn btn--secondary" data-modal-close>Cancel</button>
                      <button type="submit" class="btn btn--primary">Update Room Status</button>
                    </div>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

        </div>
      <?php endforeach; ?>

    </main>
    <?php require __DIR__ . '/../includes/footer.php'; ?>
  </div>
</div>
