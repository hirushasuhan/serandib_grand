<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/guards/manager.php';

use App\Core\Auth;
use App\Services\ReportService;
use App\Core\Database;

$user = Auth::user();
$pageTitle = 'Analytics & Reports';

/** Tells includes/footer.php to load assets/js/charts.js on this page only. */
$needsCharts = true;

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-t');

$reportService = new ReportService();

$occData     = $reportService->occupancyByDay($from, $to);
$revMonth    = $reportService->revenueByMonth(2026);
$revRoomType = $reportService->revenueByRoomType($from, $to);
$statusData  = $reportService->bookingsByStatus();

/**
 * Export CSV handler.
 *
 * FIX: this always called exportCsv([]) — an empty array — so the button did
 * nothing at all; ReportService::exportCsv() returns immediately when its
 * input is empty. $revRoomType is a plain array of DB rows (not App\Contracts\
 * Reportable objects, which is what exportCsv() expects), so rather than force
 * every report row into a model just to satisfy that interface, this writes
 * the CSV directly from the array the page already queried.
 */
if (isset($_GET['export_csv']) && !empty($revRoomType)) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="revenue-by-room-type-' . date('Y-m-d') . '.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Room Type', 'Bookings', 'Revenue', 'Average Rate']);

    foreach ($revRoomType as $row) {
        fputcsv($out, [
            $row['name'],
            $row['bookings_count'],
            number_format((float) $row['revenue'], 2, '.', ''),
            number_format((float) $row['avg_rate'], 2, '.', ''),
        ]);
    }

    fclose($out);
    exit;
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
          <h1 class="page-title">Executive Hotel Analytics</h1>
          <p class="page-hint" style="margin-bottom: 0;">Comprehensive reporting on occupancy rates, monthly revenue, and room category performance.</p>
        </div>
      </div>

      <!-- DATE FILTER BAR -->
      <div class="card mb-8">
        <form method="get" action="<?= url('manager/reports.php') ?>" style="display: flex; gap: var(--space-4); align-items: flex-end; flex-wrap: wrap;">
          <div>
            <label class="form-label">Date From</label>
            <input type="date" name="from" class="form-control" value="<?= e($from) ?>">
          </div>

          <div>
            <label class="form-label">Date To</label>
            <input type="date" name="to" class="form-control" value="<?= e($to) ?>">
          </div>

          <button type="submit" class="btn btn--primary">Filter Analytics</button>
        </form>
      </div>

      <!-- VISUAL SVG CHART 1: MONTHLY REVENUE -->
      <div class="card mb-8">
        <h3>1. Monthly Revenue Analytics (2026)</h3>
        <div id="revenue-chart" style="margin-top: var(--space-6); min-height: 260px;"></div>
      </div>

      <!-- REPORT 2: REVENUE BY ROOM TYPE TABLE -->
      <div class="card mb-8">
        <h3>2. Revenue Performance by Room Category</h3>
        <div class="table-container" style="margin-top: var(--space-4);">
          <table class="table table--hoverable" data-responsive>
            <thead>
              <tr>
                <th>Room Type</th>
                <th>Total Bookings</th>
                <th>Avg Nightly Rate</th>
                <th>Total Revenue Generated</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($revRoomType)): ?>
                <tr><td colspan="4" class="text-center text-muted">No completed stay data for selected range.</td></tr>
              <?php else: ?>
                <?php foreach ($revRoomType as $r): ?>
                  <tr>
                    <td data-label="Room Type"><strong><?= e($r['name']) ?></strong></td>
                    <td data-label="Bookings"><?= $r['bookings_count'] ?></td>
                    <td data-label="Avg Rate"><?= money($r['avg_rate']) ?></td>
                    <td data-label="Revenue" style="font-weight: bold; color: var(--color-primary);"><?= money($r['revenue']) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- REPORT 3: BOOKINGS BY STATUS -->
      <div class="card">
        <h3>3. Reservations Distribution by Status</h3>
        <div class="grid grid--4" style="margin-top: var(--space-4);">
          <?php foreach ($statusData as $st): ?>
            <div class="card card--stat">
              <div>
                <div class="stat__label"><?= ucfirst(str_replace('_', ' ', $st['status'])) ?></div>
                <div class="stat__val"><?= $st['count'] ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

    </main>
    <?php require __DIR__ . '/../includes/footer.php'; ?>
  </div>
</div>

<?php
  /**
   * Chart bootstrap.
   *
   * The nonce is REQUIRED. The Content-Security-Policy set in bootstrap.php uses
   * `script-src 'self' 'nonce-…'` with no 'unsafe-inline', so without it the
   * browser silently refuses to run this block and the chart never appears —
   * the only clue being a CSP violation in the DevTools console.
   *
   * The JSON_HEX_* flags escape the data for a <script> context, so a value
   * containing "</script>" cannot break out of the block.
   */
  $jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
<script nonce="<?= e(CSP_NONCE) ?>">
document.addEventListener('DOMContentLoaded', function () {
  var target = document.getElementById('revenue-chart');
  if (!target) return;

  var months = <?= json_encode(array_column($revMonth, 'month'), $jsonFlags) ?>;
  var revs   = <?= json_encode(array_map('floatval', array_column($revMonth, 'total_revenue')), $jsonFlags) ?>;

  // charts.js is loaded with `defer`, so it has already executed by the time
  // DOMContentLoaded fires. Guard anyway: if it failed to load, show a message
  // rather than throwing an uncaught ReferenceError.
  if (typeof renderBarChart !== 'function') {
    target.textContent = 'Chart could not be loaded.';
    return;
  }

  if (months.length > 0) {
    renderBarChart('revenue-chart', months, revs, 'Monthly Revenue');
  } else {
    target.textContent = 'No monthly revenue data available.';
  }
});
</script>
