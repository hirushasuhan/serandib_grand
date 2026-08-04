<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/guards/manager.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Flash;
use App\Models\Booking;
use App\Services\BookingService;

$user = Auth::user();
$pageTitle = 'Approval Queue';

$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    $approvalId = (int)($_POST['approval_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    $app = $db->query("SELECT * FROM approvals WHERE id = :id LIMIT 1", [':id' => $approvalId])->fetch();

    if (!$app) {
        Flash::error('Approval request not found.');
        redirect('manager/approvals.php');
    }

    if ($app['status'] !== 'pending') {
        // Someone else actioned it, or it was double-submitted.
        Flash::info('That request has already been ' . $app['status'] . '.');
        redirect('manager/approvals.php');
    }

    $status = ($action === 'approve') ? 'approved' : 'rejected';

    /**
     * FIX (two bugs): BookingService::transition() throws a RuntimeException
     * when the booking's current status makes the change illegal (e.g. it was
     * already checked out or cancelled by the time the manager clicks
     * Approve). Previously that was uncaught, producing a 500 page on this
     * approval queue — a page managers land on right after login.
     *
     * It also updated the `approvals` row and the `bookings` row as two
     * separate statements. If the second one threw, the request was left
     * marked "approved" while the booking was never actually cancelled — an
     * inconsistent, silently wrong state. Both writes now happen inside one
     * transaction, so a failure rolls back cleanly.
     */
    try {
        $db->transaction(function (\PDO $pdo) use ($app, $status, $approvalId) {
            $upd = $pdo->prepare(
                "UPDATE approvals SET status = :st, actioned_by = :uid, actioned_at = NOW() WHERE id = :id"
            );
            $upd->execute([':st' => $status, ':uid' => Auth::id(), ':id' => $approvalId]);

            if ($app['type'] === 'cancellation' && $status === 'approved') {
                $booking = Booking::find((int) $app['booking_id']);
                if ($booking) {
                    (new BookingService())->transition($booking, 'cancelled', 'Approved by manager');
                }
            }
        });

        Flash::success("Request #{$approvalId} marked as {$status}.");

    } catch (\RuntimeException $e) {
        Flash::error($e->getMessage());
    } catch (\Throwable $e) {
        \App\Core\Logger::error($e);
        Flash::error('Could not process that request. Please try again.');
    }

    redirect('manager/approvals.php');
}

$approvals = $db->query("
    SELECT a.*, b.booking_ref, b.total_amount, u.full_name AS requester_name
    FROM approvals a
    JOIN bookings b ON b.id = a.booking_id
    JOIN users u ON u.id = a.requested_by
    ORDER BY a.status ASC, a.created_at DESC
")->fetchAll();

require __DIR__ . '/../includes/header.php';
?>

<div class="app-shell">
  <?php require __DIR__ . '/../includes/sidebar.php'; ?>

  <div class="app-shell__content">
    <?php require __DIR__ . '/../includes/nav.php'; ?>

    <main id="main" class="app-shell__main">
      
      <div style="margin-bottom: var(--space-6);">
        <h1 class="page-title">Manager Approvals Queue</h1>
        <p class="page-hint">Approve or reject booking cancellation requests, refunds, and special discounts.</p>
      </div>

      <div class="table-container">
        <table class="table table--hoverable" data-responsive>
          <thead>
            <tr>
              <th>ID</th>
              <th>Booking Ref</th>
              <th>Type</th>
              <th>Reason / Details</th>
              <th>Requested By</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($approvals)): ?>
              <tr><td colspan="7" class="text-center text-muted" style="padding: var(--space-8);">No approval requests found.</td></tr>
            <?php else: ?>
              <?php foreach ($approvals as $a): ?>
                <tr>
                  <td>#<?= $a['id'] ?></td>
                  <td data-label="Ref"><span class="mono" style="font-weight: bold;"><?= e($a['booking_ref']) ?></span></td>
                  <td data-label="Type"><span class="badge badge--warning"><?= ucfirst($a['type']) ?></span></td>
                  <td data-label="Reason"><?= e($a['reason']) ?></td>
                  <td data-label="Requester"><?= e($a['requester_name']) ?></td>
                  <td data-label="Status"><span class="badge badge--<?= e($a['status']) ?>"><?= ucfirst($a['status']) ?></span></td>
                  <td data-label="Actions">
                    <?php if ($a['status'] === 'pending'): ?>
                      <div style="display: flex; gap: var(--space-2);">
                        <form method="post" action="<?= url('manager/approvals.php') ?>" style="display: inline;">
                          <?= Csrf::field() ?>
                          <input type="hidden" name="approval_id" value="<?= $a['id'] ?>">
                          <input type="hidden" name="action" value="approve">
                          <button type="submit" class="btn btn--primary btn--sm">Approve</button>
                        </form>

                        <form method="post" action="<?= url('manager/approvals.php') ?>" style="display: inline;">
                          <?= Csrf::field() ?>
                          <input type="hidden" name="approval_id" value="<?= $a['id'] ?>">
                          <input type="hidden" name="action" value="reject">
                          <button type="submit" class="btn btn--danger btn--sm">Reject</button>
                        </form>
                      </div>
                    <?php else: ?>
                      <span class="text-muted" style="font-size: var(--text-xs);">Actioned</span>
                    <?php endif; ?>
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
