<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/guards/manager.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Flash;

$user = Auth::user();
$pageTitle = 'Review Moderation';

$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    $reviewId = (int)($_POST['review_id'] ?? 0);
    $action   = $_POST['action'] ?? '';

    $status = match($action) {
        'approve' => 'approved',
        'hide'    => 'hidden',
        default   => 'pending'
    };

    $db->query("UPDATE reviews SET status = :st, moderated_by = :uid, moderated_at = NOW() WHERE id = :id", [
        ':st'  => $status,
        ':uid' => Auth::id(),
        ':id'  => $reviewId
    ]);

    Flash::success("Review status updated to {$status}.");
    redirect('manager/reviews.php');
}

$reviews = $db->query("
    SELECT r.*, u.full_name, b.booking_ref, rt.name AS room_type_name
    FROM reviews r
    JOIN users u ON u.id = r.user_id
    JOIN bookings b ON b.id = r.booking_id
    JOIN rooms rm ON rm.id = b.room_id
    JOIN room_types rt ON rt.id = rm.room_type_id
    ORDER BY r.status ASC, r.created_at DESC
")->fetchAll();

require __DIR__ . '/../includes/header.php';
?>

<div class="app-shell">
  <?php require __DIR__ . '/../includes/sidebar.php'; ?>

  <div class="app-shell__content">
    <?php require __DIR__ . '/../includes/nav.php'; ?>

    <main id="main" class="app-shell__main">
      
      <div style="margin-bottom: var(--space-6);">
        <h1 class="page-title">Guest Review Moderation</h1>
        <p class="page-hint">Approve or hide guest stay reviews before they appear on the public website.</p>
      </div>

      <div class="table-container">
        <table class="table table--hoverable" data-responsive>
          <thead>
            <tr>
              <th>Guest</th>
              <th>Room Type</th>
              <th>Rating</th>
              <th>Comment</th>
              <th>Date</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($reviews)): ?>
              <tr><td colspan="7" class="text-center text-muted" style="padding: var(--space-8);">No reviews to moderate.</td></tr>
            <?php else: ?>
              <?php foreach ($reviews as $rev): ?>
                <tr>
                  <td data-label="Guest"><strong><?= e($rev['full_name']) ?></strong></td>
                  <td data-label="Room"><?= e($rev['room_type_name']) ?></td>
                  <td data-label="Rating"><span style="display: inline-flex; color: var(--accent-500);"><?= str_repeat(icon('star', 14), (int)$rev['rating']) ?></span></td>
                  <td data-label="Comment" style="max-width: 300px;"><?= e($rev['comment']) ?></td>
                  <td data-label="Date"><?= formatDate($rev['created_at']) ?></td>
                  <td data-label="Status"><span class="badge badge--<?= e($rev['status']) ?>"><?= ucfirst($rev['status']) ?></span></td>
                  <td data-label="Actions">
                    <div style="display: flex; gap: var(--space-2);">
                      <form method="post" action="<?= url('manager/reviews.php') ?>" style="display: inline;">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="review_id" value="<?= $rev['id'] ?>">
                        <input type="hidden" name="action" value="approve">
                        <button type="submit" class="btn btn--primary btn--sm" <?= $rev['status'] === 'approved' ? 'disabled' : '' ?>>Approve</button>
                      </form>

                      <form method="post" action="<?= url('manager/reviews.php') ?>" style="display: inline;">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="review_id" value="<?= $rev['id'] ?>">
                        <input type="hidden" name="action" value="hide">
                        <button type="submit" class="btn btn--secondary btn--sm" <?= $rev['status'] === 'hidden' ? 'disabled' : '' ?>>Hide</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

    </main>
    <?php require __DIR__ . '/../includes/footer.php'; ?>
  </div>
</div>
