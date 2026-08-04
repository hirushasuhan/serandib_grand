<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/guards/admin.php';

use App\Core\Auth;
use App\Models\Setting;
use App\Core\Csrf;
use App\Core\Flash;

$user = Auth::user();
$pageTitle = 'System Settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();

    $settingsKeys = ['hotel_name', 'hotel_tagline', 'hotel_address', 'hotel_phone', 'hotel_email', 'tax_rate', 'service_charge', 'cancellation_window_hours'];

    foreach ($settingsKeys as $key) {
        if (isset($_POST[$key])) {
            $group = in_array($key, ['tax_rate', 'service_charge']) ? 'finance' : 'general';
            Setting::set($key, (string)$_POST[$key], $group);
        }
    }

    Flash::success("System settings updated successfully.");
    redirect('admin/settings.php');
}

require __DIR__ . '/../includes/header.php';
?>

<div class="app-shell">
  <?php require __DIR__ . '/../includes/sidebar.php'; ?>

  <div class="app-shell__content">
    <?php require __DIR__ . '/../includes/nav.php'; ?>

    <main id="main" class="app-shell__main">
      
      <div style="max-width: 720px; margin-inline: auto;">
        
        <div style="margin-bottom: var(--space-6);">
          <h1 class="page-title">Global System Settings</h1>
          <p class="page-hint">Configure hotel profile information, tax rates, service charges, and reservation policies.</p>
        </div>

        <div class="card">
          <form method="post" action="<?= url('admin/settings.php') ?>" data-validate>
            <?= Csrf::field() ?>

            <h3 style="margin-bottom: var(--space-4);">Hotel Profile</h3>
            <div class="form-group">
              <label class="form-label">Hotel Name</label>
              <input type="text" name="hotel_name" class="form-control" value="<?= e(Setting::get('hotel_name')) ?>" required>
            </div>

            <div class="form-group">
              <label class="form-label">Tagline</label>
              <input type="text" name="hotel_tagline" class="form-control" value="<?= e(Setting::get('hotel_tagline')) ?>">
            </div>

            <div class="grid grid--2">
              <div class="form-group">
                <label class="form-label">Contact Phone</label>
                <input type="text" name="hotel_phone" class="form-control" value="<?= e(Setting::get('hotel_phone')) ?>" required>
              </div>

              <div class="form-group">
                <label class="form-label">Contact Email</label>
                <input type="email" name="hotel_email" class="form-control" value="<?= e(Setting::get('hotel_email')) ?>" required>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Physical Address</label>
              <input type="text" name="hotel_address" class="form-control" value="<?= e(Setting::get('hotel_address')) ?>" required>
            </div>

            <h3 style="margin-top: var(--space-8); margin-bottom: var(--space-4);">Financial Rates & Policies</h3>
            <div class="grid grid--2">
              <div class="form-group">
                <label class="form-label">Government Tax Rate (%)</label>
                <input type="number" step="0.01" name="tax_rate" class="form-control" value="<?= e(Setting::get('tax_rate', '8.00')) ?>" required>
              </div>

              <div class="form-group">
                <label class="form-label">Service Charge Rate (%)</label>
                <input type="number" step="0.01" name="service_charge" class="form-control" value="<?= e(Setting::get('service_charge', '10.00')) ?>" required>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Cancellation Window (Hours)</label>
              <input type="number" name="cancellation_window_hours" class="form-control" value="<?= e(Setting::get('cancellation_window_hours', '24')) ?>" required>
              <div class="form-hint">Hours before check-in when guests can cancel for free without manager approval.</div>
            </div>

            <button type="submit" class="btn btn--primary btn--lg" style="margin-top: var(--space-6);">Save System Settings &rarr;</button>
          </form>
        </div>

      </div>

    </main>
    <?php require __DIR__ . '/../includes/footer.php'; ?>
  </div>
</div>
