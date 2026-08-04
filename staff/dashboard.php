<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/guards/staff.php';

use App\Core\Auth;
use App\Services\ReportService;
use App\Models\Booking;
use App\Core\Database;

$user = Auth::user();
$pageTitle = 'Front Desk Dashboard';

$reportService = new ReportService();
$kpis = $reportService->kpiSummary();

$today = date('Y-m-d');
$db = Database::getInstance();

// Today's Arrivals
$sqlArrivals = "SELECT b.*, u.full_name, u.phone FROM bookings b JOIN users u ON u.id = b.user_id WHERE b.check_in = :today AND b.status IN ('confirmed','pending') ORDER BY b.check_in ASC";
$arrivals = $db->query($sqlArrivals, [':today' => $today])->fetchAll();

// Today's Departures
$sqlDepartures = "SELECT b.*, u.full_name, u.phone FROM bookings b JOIN users u ON u.id = b.user_id WHERE b.check_out = :today AND b.status = 'checked_in' ORDER BY b.check_out ASC";
$departures = $db->query($sqlDepartures, [':today' => $today])->fetchAll();

require __DIR__ . '/../includes/header.php';
?>

<div class="app-shell">
  <?php require __DIR__ . '/../includes/sidebar.php'; ?>

  <div class="app-shell__content">
    <?php require __DIR__ . '/../includes/nav.php'; ?>

    <main id="main" class="app-shell__main">
      
      <div style="margin-bottom: var(--space-6);">
        <h1 class="page-title">Front Desk Operations</h1>
        <p class="page-hint">Manage today's guest arrivals, departures, room housekeeping statuses, and payments.</p>
      </div>

      <!-- FRONT DESK KPI CARDS -->
      <div class="grid grid--4 mb-8">
        <div class="card card--stat">
          <div>
            <div class="stat__label">In-House Guests</div>
            <div class="stat__val" style="color: var(--info-500);"><?= $kpis->in_house ?></div>
          </div>
          <div class="stat__icon"><?= icon('bed', 32) ?></div>
        </div>

        <div class="card card--stat">
          <div>
            <div class="stat__label">Today's Arrivals</div>
            <div class="stat__val" style="color: var(--success-500);"><?= $kpis->today_arrivals ?></div>
          </div>
          <div class="stat__icon"><?= icon('log-in', 32) ?></div>
        </div>

        <div class="card card--stat">
          <div>
            <div class="stat__label">Today's Departures</div>
            <div class="stat__val" style="color: var(--warning-500);"><?= $kpis->today_departures ?></div>
          </div>
          <div class="stat__icon"><?= icon('log-out', 32) ?></div>
        </div>

        <div class="card card--stat">
          <div>
            <div class="stat__label">Occupancy Rate</div>
            <div class="stat__val" style="color: var(--color-primary);"><?= $kpis->occupancy_pct ?>%</div>
          </div>
          <div class="stat__icon"><?= icon('trending-up', 32) ?></div>
        </div>
      </div>

      <!-- TODAY'S ARRIVALS & DEPARTURES TABLES -->
      <div class="grid grid--2 mb-8">
        
        <!-- ARRIVALS -->
        <div class="card">
          <h3>Today's Scheduled Arrivals</h3>
          <?php if (empty($arrivals)): ?>
            <p class="text-muted" style="margin-top: var(--space-4);">No scheduled arrivals for today.</p>
          <?php else: ?>
            <div class="table-container" style="margin-top: var(--space-4);">
              <?php /* data-responsive + data-label make each row stack into a
                       labelled card below 768px (see components.css). */ ?>
              <table class="table" data-responsive>
                <thead>
                  <tr>
                    <th>Ref</th>
                    <th>Guest</th>
                    <th>Status</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($arrivals as $arr): ?>
                    <tr>
                      <td data-label="Ref"><span class="mono"><?= e($arr['booking_ref']) ?></span></td>
                      <td data-label="Guest"><?= e($arr['full_name']) ?><br><span class="text-muted" style="font-size: var(--text-xs);"><?= e($arr['phone']) ?></span></td>
                      <td data-label="Status"><span class="badge badge--<?= e($arr['status']) ?>"><?= ucfirst($arr['status']) ?></span></td>
                      <td>
                        <a href="<?= url('staff/checkin.php?booking_id=' . $arr['id']) ?>" class="btn btn--primary btn--sm">Check In</a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

        <!-- DEPARTURES -->
        <div class="card">
          <h3>Today's Scheduled Departures</h3>
          <?php if (empty($departures)): ?>
            <p class="text-muted" style="margin-top: var(--space-4);">No scheduled departures for today.</p>
          <?php else: ?>
            <div class="table-container" style="margin-top: var(--space-4);">
              <table class="table" data-responsive>
                <thead>
                  <tr>
                    <th>Ref</th>
                    <th>Guest</th>
                    <th>Status</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($departures as $dep): ?>
                    <tr>
                      <td data-label="Ref"><span class="mono"><?= e($dep['booking_ref']) ?></span></td>
                      <td data-label="Guest"><?= e($dep['full_name']) ?></td>
                      <td data-label="Status"><span class="badge badge--checked_in">In-House</span></td>
                      <td>
                        <a href="<?= url('staff/checkout.php?booking_id=' . $dep['id']) ?>" class="btn btn--secondary btn--sm">Check Out</a>
                      </td>
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
