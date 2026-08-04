<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/guards/manager.php';

use App\Core\Auth;
use App\Services\ReportService;
use App\Core\Database;

$user = Auth::user();
$pageTitle = 'Manager Dashboard & Analytics';

$reportService = new ReportService();
$kpis = $reportService->kpiSummary();

$db = Database::getInstance();

// Pending Approvals
$pendingApprovals = $db->query("SELECT a.*, b.booking_ref, u.full_name FROM approvals a JOIN bookings b ON b.id = a.booking_id JOIN users u ON u.id = a.requested_by WHERE a.status = 'pending' ORDER BY a.created_at ASC")->fetchAll();

// Pending Reviews
$pendingReviews = $db->query("SELECT r.*, u.full_name, b.booking_ref FROM reviews r JOIN users u ON u.id = r.user_id JOIN bookings b ON b.id = r.booking_id WHERE r.status = 'pending' ORDER BY r.created_at ASC")->fetchAll();

require __DIR__ . '/../includes/header.php';
?>

<div class="app-shell">
  <?php require __DIR__ . '/../includes/sidebar.php'; ?>

  <div class="app-shell__content">
    <?php require __DIR__ . '/../includes/nav.php'; ?>

    <main id="main" class="app-shell__main">
      
      <div style="margin-bottom: var(--space-6);">
        <h1 class="page-title">Executive Operations & Analytics</h1>
        <p class="page-hint">Real-time revenue monitoring, occupancy KPIs, approval queues, and review moderation.</p>
      </div>

      <!-- KPI METRIC CARDS -->
      <div class="grid grid--4 mb-8">
        <div class="card card--stat">
          <div>
            <div class="stat__label">Occupancy Rate</div>
            <div class="stat__val" style="color: var(--color-primary);"><?= $kpis->occupancy_pct ?>%</div>
          </div>
          <div class="stat__icon"><?= icon('building', 32) ?></div>
        </div>

        <div class="card card--stat">
          <div>
            <div class="stat__label">Today's Revenue</div>
            <div class="stat__val" style="color: var(--success-500);"><?= money($kpis->today_revenue) ?></div>
          </div>
          <div class="stat__icon"><?= icon('dollar-sign', 32) ?></div>
        </div>

        <div class="card card--stat">
          <div>
            <div class="stat__label">Month-to-Date Revenue</div>
            <div class="stat__val" style="color: var(--info-500);"><?= money($kpis->month_revenue) ?></div>
          </div>
          <div class="stat__icon"><?= icon('dollar-sign', 32) ?></div>
        </div>

        <div class="card card--stat">
          <div>
            <div class="stat__label">Pending Approvals</div>
            <div class="stat__val" style="color: var(--warning-500);"><?= $kpis->pending_approvals ?></div>
          </div>
          <div style="font-size: 2rem;">⏳</div>
        </div>
      </div>

      <div class="grid grid--2 mb-8">
        
        <!-- PENDING APPROVALS QUEUE -->
        <div class="card">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-4);">
            <h3>Pending Approval Queue</h3>
            <a href="<?= url('manager/approvals.php') ?>" class="text-muted" style="font-size: var(--text-xs);">View All &rarr;</a>
          </div>

          <?php if (empty($pendingApprovals)): ?>
            <p class="text-muted">No pending manager approval requests.</p>
          <?php else: ?>
            <div class="table-container">
              <table class="table" data-responsive>
                <thead>
                  <tr>
                    <th>Ref</th>
                    <th>Type</th>
                    <th>Requester</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach (array_slice($pendingApprovals, 0, 5) as $app): ?>
                    <tr>
                      <td data-label="Ref"><span class="mono"><?= e($app['booking_ref']) ?></span></td>
                      <td data-label="Type"><span class="badge badge--warning"><?= ucfirst($app['type']) ?></span></td>
                      <td data-label="Requester"><?= e($app['full_name']) ?></td>
                      <td><a href="<?= url('manager/approvals.php') ?>" class="btn btn--primary btn--sm">Review</a></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

        <!-- PENDING REVIEWS MODERATION QUEUE -->
        <div class="card">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-4);">
            <h3>Pending Guest Reviews</h3>
            <a href="<?= url('manager/reviews.php') ?>" class="text-muted" style="font-size: var(--text-xs);">Moderate All &rarr;</a>
          </div>

          <?php if (empty($pendingReviews)): ?>
            <p class="text-muted">No guest reviews awaiting moderation.</p>
          <?php else: ?>
            <div class="table-container">
              <table class="table" data-responsive>
                <thead>
                  <tr>
                    <th>Guest</th>
                    <th>Rating</th>
                    <th>Comment</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach (array_slice($pendingReviews, 0, 5) as $rev): ?>
                    <tr>
                      <td data-label="Guest"><?= e($rev['full_name']) ?></td>
                      <td data-label="Rating"><span style="display: inline-flex; align-items: center; gap: 4px; color: var(--accent-500);"><?= $rev['rating'] ?> <?= icon('star', 14) ?></span></td>
                      <td data-label="Comment"><?= e(substr($rev['comment'], 0, 40)) ?>...</td>
                      <td><a href="<?= url('manager/reviews.php') ?>" class="btn btn--secondary btn--sm">Moderate</a></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

      </div>

    </main>

    <?php require __DIR__ . '/../includes/footer.php'; ?>
  </div>
</div>
