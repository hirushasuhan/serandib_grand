<?php
declare(strict_types=1);

/**
 * ROOM DETAIL PAGE (public)
 *
 * Gallery, description, amenities, approved reviews and a sticky booking rail
 * with a live server-calculated price quote.
 *
 * Fixes applied to this page:
 *  - Accepts ?id= as well as ?slug=. The home page linked with ?id= while this
 *    page only read ?slug=, so every featured-room link 404'd.
 *  - Replaced class "grid grid--sidebar" (never defined in any stylesheet, so
 *    it silently collapsed to a single column and pushed the booking widget
 *    below the fold) with the real .detail-layout grid.
 *  - Tax and service percentages are read from settings instead of being
 *    hard-coded as "(10%)" and "(8%)" in the labels.
 */

require_once __DIR__ . '/bootstrap.php';

use App\Models\RoomType;
use App\Models\Setting;
use App\Services\AvailabilityService;
use App\Services\PricingService;
use App\Core\Database;
use App\Core\Auth;

$isPublicPage = true;

/* ── Resolve the room type from either a slug or a numeric id ── */
$slug = trim((string) ($_GET['slug'] ?? ''));
$id   = (int) ($_GET['id'] ?? 0);

$roomType = $slug !== '' ? RoomType::findBySlug($slug) : ($id > 0 ? RoomType::find($id) : null);

if (!$roomType || (int) $roomType->is_active !== 1) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$pageTitle = (string) $roomType->name;
$metaDescription = mb_substr(strip_tags((string) $roomType->description), 0, 155);

/* ── Dates — validated and clamped by the shared helper (see functions.php) ── */
$today = date('Y-m-d');

[$checkIn, $checkOut, $nights] = normaliseStay(
    $_GET['check_in']  ?? null,
    $_GET['check_out'] ?? null
);

$adults = max(1, min((int) $roomType->max_adults, (int) ($_GET['adults'] ?? 2)));

/* ── Availability & quote ── */
$availService   = new AvailabilityService();
$availableRooms = $availService->availableRooms((int) $roomType->id, $checkIn, $checkOut);
$isAvailable    = count($availableRooms) > 0;

$quote = null;
if ($isAvailable) {
    try {
        $quote = (new PricingService())->quote((int) $availableRooms[0]['id'], $checkIn, $checkOut);
    } catch (\Throwable $e) {
        // A pricing failure must not take the whole page down — the rail simply
        // shows dashes and the guest can still change dates.
        \App\Core\Logger::error($e);
    }
}

$nextFree = !$isAvailable
    ? $availService->nextAvailableDate((int) $roomType->id, $checkIn)
    : null;

$amenities = $roomType->amenities();

/* Gallery: the cover image first, then any additional gallery rows. */
$gallery = [];
if ($roomType->cover_image) {
    $gallery[] = (string) $roomType->cover_image;
}
foreach ($roomType->galleryImages() as $img) {
    $path = is_array($img) ? ($img['image_path'] ?? null) : ($img->image_path ?? null);
    if ($path) {
        $gallery[] = (string) $path;
    }
}
if ($gallery === []) {
    $gallery[] = 'assets/img/placeholders/room_placeholder.jpg';
}
$gallery = array_values(array_unique($gallery));

/* ── Approved reviews for this room type ── */
$sql = "SELECT r.rating, r.comment, r.created_at, u.full_name
        FROM reviews r
        JOIN bookings b ON b.id = r.booking_id
        JOIN rooms rm   ON rm.id = b.room_id
        JOIN users u    ON u.id = r.user_id
        WHERE rm.room_type_id = :type_id AND r.status = 'approved'
        ORDER BY r.created_at DESC
        LIMIT 20";
$reviews = Database::getInstance()->query($sql, [':type_id' => $roomType->id])->fetchAll();

$avgRating = 0.0;
if ($reviews) {
    $avgRating = round(array_sum(array_column($reviews, 'rating')) / count($reviews), 1);
}

/* Percentages come from settings — never hard-code them into a label. */
$serviceRate = (float) Setting::get('service_charge', '10.00');
$taxRate     = (float) Setting::get('tax_rate', '8.00');

/* Guests go to the booking form; everyone else is sent to log in first. */
$bookingAction = Auth::check() && Auth::role() === 'guest'
    ? url('guest/booking-form.php')
    : url('auth/login.php');

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/nav.php';
?>

<main id="main" class="main-content">
  <div class="container">

    <!-- BREADCRUMB -->
    <nav aria-label="Breadcrumb" style="margin-bottom: var(--space-4); font-size: var(--text-sm);">
      <a href="<?= url() ?>" class="text-muted">Home</a>
      <span class="text-muted" aria-hidden="true"> / </span>
      <a href="<?= url('rooms.php') ?>" class="text-muted">Rooms</a>
      <span class="text-muted" aria-hidden="true"> / </span>
      <span><?= e($roomType->name) ?></span>
    </nav>

    <!-- TITLE BLOCK -->
    <header style="margin-bottom: var(--space-8);">
      <h1 class="page-title" style="font-family: var(--font-display); font-size: clamp(1.9rem, 4vw, var(--text-3xl));">
        <?= e($roomType->name) ?>
      </h1>

      <div style="display: flex; flex-wrap: wrap; gap: var(--space-4); align-items: center; margin-top: var(--space-2);">
        <span class="badge badge--info"><?= e(strtoupper((string) $roomType->bed_type)) ?> BED</span>
        <span class="text-muted" style="font-size: var(--text-sm);">
          <span aria-hidden="true">👤</span> Max <?= (int) $roomType->max_adults ?> adults,
          <?= (int) $roomType->max_children ?> children
        </span>
        <?php if ($roomType->size_sqft): ?>
          <span class="text-muted" style="font-size: var(--text-sm);">
            <span aria-hidden="true">📐</span> <?= (int) $roomType->size_sqft ?> sq ft
          </span>
        <?php endif; ?>
        <?php if ($avgRating > 0): ?>
          <span style="color: var(--color-accent); font-weight: var(--weight-bold); font-size: var(--text-sm);">
            <span aria-hidden="true">★</span> <?= e((string) $avgRating) ?> / 5.0
            <span class="text-muted">(<?= count($reviews) ?> review<?= count($reviews) === 1 ? '' : 's' ?>)</span>
          </span>
        <?php endif; ?>
      </div>
    </header>

    <!-- ── TWO-COLUMN LAYOUT (was the broken grid--sidebar) ── -->
    <div class="detail-layout">

      <!-- ══════════ LEFT: gallery, description, amenities, reviews ══════════ -->
      <div>

        <!-- 3D GALLERY -->
        <div class="gallery3d" data-gallery style="margin-bottom: var(--space-8);">
          <div class="gallery3d__stage">
            <?php foreach ($gallery as $i => $img): ?>
              <div class="gallery3d__slide <?= $i === 0 ? 'is-active' : '' ?>">
                <img src="<?= e(url($img)) ?>"
                     alt="<?= e($roomType->name) ?> — view <?= $i + 1 ?>"
                     <?= $i === 0 ? '' : 'loading="lazy"' ?> decoding="async">
              </div>
            <?php endforeach; ?>
          </div>

          <?php if (count($gallery) > 1): ?>
            <div class="gallery3d__thumbs" role="tablist" aria-label="Room photos">
              <?php foreach ($gallery as $i => $img): ?>
                <button type="button"
                        class="gallery3d__thumb"
                        role="tab"
                        aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"
                        aria-label="Show photo <?= $i + 1 ?> of <?= count($gallery) ?>">
                  <img src="<?= e(url($img)) ?>" alt="" loading="lazy">
                </button>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- DESCRIPTION -->
        <section class="card mb-8" data-reveal>
          <h2 style="font-family: var(--font-display);">About This Room</h2>
          <p style="line-height: var(--leading-loose); color: var(--color-text); margin-bottom: 0;">
            <?= nl2br(e((string) $roomType->description)) ?>
          </p>
        </section>

        <!-- AMENITIES -->
        <?php if (!empty($amenities)): ?>
          <section class="card mb-8" data-reveal>
            <h2 style="font-family: var(--font-display);">Included Amenities</h2>
            <ul class="grid grid--2" style="gap: var(--space-3); margin: var(--space-4) 0 0; padding: 0; list-style: none;">
              <?php foreach ($amenities as $amenity): ?>
                <li style="display: flex; align-items: center; gap: var(--space-3); font-size: var(--text-sm);">
                  <span style="color: var(--color-primary); font-weight: bold;" aria-hidden="true">✓</span>
                  <span><?= e((string) $amenity->name) ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          </section>
        <?php endif; ?>

        <!-- POLICIES — <details> works with JavaScript disabled -->
        <section class="card mb-8" data-reveal>
          <h2 style="font-family: var(--font-display);">Booking Policies</h2>
          <div style="margin-top: var(--space-4);">
            <details style="padding: var(--space-3) 0; border-bottom: 1px solid var(--color-border);">
              <summary style="cursor: pointer; font-weight: var(--weight-semi);">Check-in &amp; check-out</summary>
              <p class="text-muted" style="font-size: var(--text-sm); margin: var(--space-3) 0 0;">
                Check-in from 2:00 PM. Check-out by 11:00 AM. Early check-in is
                subject to availability — ask the front desk.
              </p>
            </details>

            <details style="padding: var(--space-3) 0; border-bottom: 1px solid var(--color-border);">
              <summary style="cursor: pointer; font-weight: var(--weight-semi);">Cancellation</summary>
              <p class="text-muted" style="font-size: var(--text-sm); margin: var(--space-3) 0 0;">
                Free cancellation up to
                <?= e((string) Setting::get('cancellation_window_hours', '24')) ?>
                hours before check-in. Later cancellations need manager approval.
              </p>
            </details>

            <details style="padding: var(--space-3) 0;">
              <summary style="cursor: pointer; font-weight: var(--weight-semi);">Payment</summary>
              <p class="text-muted" style="font-size: var(--text-sm); margin: var(--space-3) 0 0;">
                No prepayment required. Settle at the front desk on arrival or
                departure by cash, card or bank transfer. A
                <?= e((string) $serviceRate) ?>% service charge and
                <?= e((string) $taxRate) ?>% tax apply.
              </p>
            </details>
          </div>
        </section>

        <!-- REVIEWS -->
        <section class="card mb-8" data-reveal>
          <h2 style="font-family: var(--font-display);">Guest Reviews</h2>

          <?php if (empty($reviews)): ?>
            <p class="text-muted" style="margin-bottom: 0;">
              No reviews yet for this room type. Reviews appear here once a guest
              completes their stay and a manager approves the feedback.
            </p>
          <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: var(--space-4); margin-top: var(--space-4);">
              <?php foreach ($reviews as $rev): ?>
                <?php $stars = max(1, min(5, (int) $rev['rating'])); ?>
                <article style="padding: var(--space-4); background: var(--color-surface-2); border-radius: var(--radius-md);">
                  <div style="display: flex; justify-content: space-between; gap: var(--space-3); margin-bottom: var(--space-2);">
                    <strong style="color: var(--color-primary);"><?= e((string) $rev['full_name']) ?></strong>
                    <span style="color: var(--color-accent); white-space: nowrap;">
                      <span aria-hidden="true"><?= str_repeat('★', $stars) . str_repeat('☆', 5 - $stars) ?></span>
                      <span class="sr-only"><?= $stars ?> out of 5 stars</span>
                    </span>
                  </div>
                  <p style="margin: 0; font-size: var(--text-sm);"><?= e((string) $rev['comment']) ?></p>
                  <div style="font-size: var(--text-xs); color: var(--color-text-subtle); margin-top: var(--space-2);">
                    <?= e(formatDate((string) $rev['created_at'])) ?>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>

      </div>

      <!-- ══════════ RIGHT: sticky booking rail ══════════ -->
      <aside class="detail-rail" aria-label="Booking summary">
        <div class="card" style="border-top: 4px solid var(--color-primary);">

          <div style="text-align: center; padding-bottom: var(--space-4); border-bottom: 1px solid var(--color-border); margin-bottom: var(--space-5);">
            <span style="font-size: var(--text-xs); text-transform: uppercase; letter-spacing: var(--tracking-wide); color: var(--color-text-muted);">
              Nightly Rate
            </span>
            <div style="font-size: var(--text-3xl); font-weight: var(--weight-bold); color: var(--color-primary); font-variant-numeric: tabular-nums;">
              <?= e(money($roomType->base_price)) ?>
            </div>
          </div>

          <?php /* GET form: it only carries the chosen dates into the booking
                   form, which is where the CSRF-protected POST happens.
                   data-quote-* attributes drive the live AJAX quote. */ ?>
          <form method="get"
                action="<?= e($bookingAction) ?>"
                data-quote-form
                data-room-id="<?= $isAvailable ? (int) $availableRooms[0]['id'] : 0 ?>"
                data-quote-url="<?= e(url('api/price-quote.php')) ?>">

            <input type="hidden" name="room_type_id" value="<?= (int) $roomType->id ?>">
            <?php if ($isAvailable): ?>
              <input type="hidden" name="room_id" value="<?= (int) $availableRooms[0]['id'] ?>">
            <?php endif; ?>

            <div class="form-group">
              <label class="form-label" for="d-check-in">Check-In Date</label>
              <input type="date" id="d-check-in" name="check_in" class="form-control"
                     value="<?= e($checkIn) ?>" min="<?= e($today) ?>" required>
            </div>

            <div class="form-group">
              <label class="form-label" for="d-check-out">Check-Out Date</label>
              <input type="date" id="d-check-out" name="check_out" class="form-control"
                     value="<?= e($checkOut) ?>" required>
            </div>

            <div class="form-group">
              <label class="form-label" for="d-adults">Guests</label>
              <select id="d-adults" name="adults" class="form-select">
                <?php for ($i = 1; $i <= (int) $roomType->max_adults; $i++): ?>
                  <option value="<?= $i ?>" <?= $adults === $i ? 'selected' : '' ?>>
                    <?= $i ?> Adult<?= $i > 1 ? 's' : '' ?>
                  </option>
                <?php endfor; ?>
              </select>
            </div>

            <!-- LIVE QUOTE — recalculated server-side on every date change -->
            <div style="background: var(--color-surface-2); padding: var(--space-4); border-radius: var(--radius-md); margin-bottom: var(--space-5);">
              <div class="quote-row">
                <span>Stay duration</span>
                <span class="quote-row__value" data-quote-nights>
                  <?= $quote ? (int) $quote->nights . ' Night(s)' : $nights . ' Night(s)' ?>
                </span>
              </div>
              <div class="quote-row">
                <span>Service charge (<?= e((string) $serviceRate) ?>%)</span>
                <span class="quote-row__value" data-quote-service>
                  <?= $quote ? e(money($quote->service_charge)) : '—' ?>
                </span>
              </div>
              <div class="quote-row">
                <span>Tax (<?= e((string) $taxRate) ?>%)</span>
                <span class="quote-row__value" data-quote-tax>
                  <?= $quote ? e(money($quote->tax_amount)) : '—' ?>
                </span>
              </div>
              <div class="quote-row quote-row--total">
                <span>Estimated total</span>
                <span class="quote-row__value" data-quote-total>
                  <?= $quote ? e(money($quote->total_amount)) : '—' ?>
                </span>
              </div>
            </div>

            <?php if ($isAvailable): ?>
              <button type="submit" class="btn btn--primary btn--lg btn--block">
                <?= Auth::check() && Auth::role() === 'guest' ? 'Proceed to Booking' : 'Log In to Book' ?> &rarr;
              </button>
              <p class="text-muted" style="font-size: var(--text-xs); text-align: center; margin: var(--space-3) 0 0;">
                <?= count($availableRooms) ?> room<?= count($availableRooms) === 1 ? '' : 's' ?> left ·
                no prepayment required
              </p>
            <?php else: ?>
              <div class="badge badge--danger" style="display: block; text-align: center; padding: var(--space-3);">
                Fully booked for these dates
              </div>
              <?php if ($nextFree): ?>
                <p class="text-muted" style="font-size: var(--text-sm); text-align: center; margin: var(--space-3) 0 0;">
                  Next available from <strong><?= e(formatDate($nextFree)) ?></strong>.
                </p>
              <?php endif; ?>
              <a href="<?= url('rooms.php') ?>" class="btn btn--secondary btn--block" style="margin-top: var(--space-3);">
                See other rooms
              </a>
            <?php endif; ?>

          </form>
        </div>
      </aside>

    </div>

  </div>
</main>

<!-- STICKY MOBILE BOOKING BAR — the rail is far down the page on a phone -->
<div class="mobile-book-bar">
  <div>
    <div style="font-weight: var(--weight-bold); color: var(--color-primary);">
      <?= e(money($roomType->base_price)) ?>
      <span style="font-weight: var(--weight-normal); font-size: var(--text-xs); color: var(--color-text-muted);">/ night</span>
    </div>
    <div style="font-size: var(--text-xs); color: var(--color-text-muted);">
      <?= $isAvailable ? count($availableRooms) . ' available' : 'Sold out' ?>
    </div>
  </div>
  <a href="#d-check-in" class="btn btn--primary">
    <?= $isAvailable ? 'Book Now' : 'Change Dates' ?>
  </a>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
