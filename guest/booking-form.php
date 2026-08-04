<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/guards/guest.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Models\RoomType;
use App\Services\AvailabilityService;
use App\Services\PricingService;

$user = Auth::user();
$pageTitle = 'Reserve Room';

$roomTypeId = (int)($_GET['room_type_id'] ?? 0);

/**
 * Dates arrive from the query string, so they are validated and clamped before
 * use. PricingService now rejects malformed dates by throwing, which would show
 * the guest a 500 page if a raw ?check_in=abc were passed straight through.
 */
[$checkIn, $checkOut, $nights] = normaliseStay(
    $_GET['check_in']  ?? null,
    $_GET['check_out'] ?? null
);

$adults   = max(1, (int)($_GET['adults'] ?? 2));
$children = max(0, (int)($_GET['children'] ?? 0));

$roomTypes    = RoomType::activeOnly();
$selectedType = $roomTypeId ? RoomType::find($roomTypeId) : ($roomTypes[0] ?? null);

// Keep the occupancy selectors inside what the chosen room type actually allows.
if ($selectedType) {
    $adults   = min($adults, max(1, (int) $selectedType->max_adults));
    $children = min($children, max(0, (int) $selectedType->max_children));
}

$availService   = new AvailabilityService();
$availableRooms = $selectedType
    ? $availService->availableRooms((int) $selectedType->id, $checkIn, $checkOut)
    : [];
$isAvailable = count($availableRooms) > 0;

$quote = null;
if ($isAvailable && $selectedType) {
    try {
        // No discount argument: a guest must never be able to influence pricing.
        $quote = (new PricingService())->quote((int) $availableRooms[0]['id'], $checkIn, $checkOut);
    } catch (\Throwable $e) {
        // Show dashes rather than a 500; the guest can simply pick other dates.
        \App\Core\Logger::error($e);
    }
}

/* Percentages come from settings, not hard-coded into the labels. */
$serviceRate = (float) \App\Models\Setting::get('service_charge', '10.00');
$taxRate     = (float) \App\Models\Setting::get('tax_rate', '8.00');

$errors = $_SESSION['errors'] ?? [];
unset($_SESSION['errors']);

require __DIR__ . '/../includes/header.php';
?>

<div class="app-shell">
  <?php require __DIR__ . '/../includes/sidebar.php'; ?>

  <div class="app-shell__content">
    <?php require __DIR__ . '/../includes/nav.php'; ?>

    <main id="main" class="app-shell__main">
      
      <div style="max-width: 800px; margin-inline: auto;">
        
        <div style="margin-bottom: var(--space-6); text-align: center;">
          <h1 class="page-title">Create Reservation</h1>
          <p class="page-hint">Step-by-step reservation process. All totals are calculated server-side.</p>
        </div>

        <!-- STEPPER PROGRESS BAR -->
        <div class="stepper">
          <div class="stepper__step stepper__step--active">
            <div class="stepper__num">1</div>
            <span>Dates & Room</span>
          </div>
          <div class="stepper__step stepper__step--active">
            <div class="stepper__num">2</div>
            <span>Guest Information</span>
          </div>
          <div class="stepper__step stepper__step--active">
            <div class="stepper__num">3</div>
            <span>Review & Confirm</span>
          </div>
        </div>

        <div class="card" style="padding: var(--space-8);">
          
          <form method="post" action="<?= url('actions/booking/store.php') ?>" data-validate data-price-quote-form>
            <?= Csrf::field() ?>

            <div class="grid grid--2 mb-6">
              
              <div>
                <label class="form-label" for="bf-room-type">Select Room Type</label>
                <?php /* Reload handled by the delegated data-reload-param listener
                         in assets/js/components.js. The old inline onchange=
                         attribute is blocked by the Content-Security-Policy, and
                         it also built the URL by string concatenation instead of
                         encoding the values. */ ?>
                <select name="room_type_id"
                        id="bf-room-type"
                        class="form-select"
                        data-reload-param="room_type_id"
                        data-reload-url="<?= e(url('guest/booking-form.php')) ?>">
                  <?php foreach ($roomTypes as $t): ?>
                    <option value="<?= (int) $t->id ?>" <?= $selectedType && (int) $selectedType->id === (int) $t->id ? 'selected' : '' ?>>
                      <?= e($t->name) ?> (<?= e(money($t->base_price)) ?>/night)
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <?php if (!empty($availableRooms)): ?>
                <input type="hidden" name="room_id" value="<?= $availableRooms[0]['id'] ?>">
              <?php endif; ?>

              <div>
                <label class="form-label">Check-In Date</label>
                <input type="date" name="check_in" class="form-control" value="<?= e($checkIn) ?>" required>
              </div>

              <div>
                <label class="form-label">Check-Out Date</label>
                <input type="date" name="check_out" class="form-control" value="<?= e($checkOut) ?>" required>
              </div>

              <div>
                <label class="form-label">Adults</label>
                <input type="number" name="adults" class="form-control" value="<?= $adults ?>" min="1" max="<?= $selectedType?->max_adults ?? 4 ?>" required>
              </div>

              <div>
                <label class="form-label">Children</label>
                <input type="number" name="children" class="form-control" value="<?= $children ?>" min="0" max="<?= $selectedType?->max_children ?? 2 ?>">
              </div>

            </div>

            <div class="form-group">
              <label class="form-label">Special Requests (Optional)</label>
              <textarea name="special_requests" rows="3" class="form-textarea" placeholder="e.g. Quiet room away from elevator, late arrival expected..."><?= e(old('special_requests')) ?></textarea>
              <div class="form-hint">Plain text only, max 500 characters. Subject to hotel availability.</div>
            </div>

            <!-- PRICING SUMMARY BOX -->
            <div style="background-color: var(--color-surface-2); padding: var(--space-6); border-radius: var(--radius-md); margin-bottom: var(--space-6);">
              <h3 style="font-size: var(--text-lg); margin-bottom: var(--space-4);">Reservation Cost Summary</h3>
              <div style="display: flex; justify-content: space-between; margin-bottom: var(--space-2);">
                <span>Room Rate per Night:</span>
                <strong><?= $quote ? money($quote->room_rate) : '-' ?></strong>
              </div>
              <div style="display: flex; justify-content: space-between; margin-bottom: var(--space-2);">
                <span>Nights:</span>
                <strong data-quote-nights><?= $quote ? $quote->nights : '-' ?></strong>
              </div>
              <div style="display: flex; justify-content: space-between; margin-bottom: var(--space-2);">
                <span>Subtotal:</span>
                <strong><?= $quote ? money($quote->subtotal) : '-' ?></strong>
              </div>
              <div style="display: flex; justify-content: space-between; margin-bottom: var(--space-2);">
                <span>Service Charge (<?= e((string) $serviceRate) ?>%):</span>
                <span data-quote-service><?= $quote ? money($quote->service_charge) : '-' ?></span>
              </div>
              <div style="display: flex; justify-content: space-between; margin-bottom: var(--space-2);">
                <span>Government Tax (<?= e((string) $taxRate) ?>%):</span>
                <span data-quote-tax><?= $quote ? money($quote->tax_amount) : '-' ?></span>
              </div>
              <div style="display: flex; justify-content: space-between; padding-top: var(--space-3); border-top: 2px solid var(--color-border-strong); font-size: var(--text-xl); font-weight: var(--weight-bold); color: var(--color-primary);">
                <span>Grand Total:</span>
                <span data-quote-total><?= $quote ? money($quote->total_amount) : '-' ?></span>
              </div>
            </div>

            <div style="display: flex; align-items: center; gap: 8px; padding: var(--space-4); background-color: var(--info-bg); color: var(--info-500); border-radius: var(--radius-sm); font-size: var(--text-xs); margin-bottom: var(--space-6);">
              <?= icon('info', 14) ?> Payment is settled at hotel check-in / check-out. No credit card is charged online.
            </div>

            <?php if ($isAvailable): ?>
              <button type="submit" class="btn btn--primary btn--lg btn--block">Confirm & Place Reservation &rarr;</button>
            <?php else: ?>
              <div class="badge badge--danger" style="display: block; text-align: center; padding: var(--space-4); font-size: var(--text-base);">
                Selected room type is fully booked for these dates. Please change dates or select a different room type.
              </div>
            <?php endif; ?>

          </form>

        </div>

      </div>

    </main>
    <?php require __DIR__ . '/../includes/footer.php'; ?>
  </div>
</div>
