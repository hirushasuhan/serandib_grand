<?php
declare(strict_types=1);

/**
 * ROOMS LISTING (public) — the assignment's "information display page".
 *
 * Shows every active room type with LIVE availability for the chosen dates,
 * plus filters, sorting and pagination.
 *
 * Security notes for this page:
 *  - Every date from the query string is validated before use. Previously the
 *    raw value was concatenated into a link, which allowed a crafted
 *    ?check_in=" onmouseover=... to break out of the href attribute (XSS).
 *    All links are now built with urlWithQuery(), which URL-encodes each value.
 *  - The ORDER BY column comes from a whitelist, never from input, because a
 *    column name cannot be a bound parameter.
 */

require_once __DIR__ . '/bootstrap.php';

use App\Models\RoomType;
use App\Models\Amenity;
use App\Services\AvailabilityService;
use App\Core\Paginator;
use App\Core\Database;

$pageTitle = 'Rooms & Availability';
$metaDescription = 'Browse rooms and suites with live availability, filter by price, capacity and bed type.';
$isPublicPage = true;

$today = date('Y-m-d');

/**
 * normaliseStay() (src/Helpers/functions.php) validates both dates strictly as
 * Y-m-d, rejects past check-ins, guarantees at least one night and caps the
 * stay at the 30-night business rule. It replaces the previous inline handling,
 * where an unvalidated date was concatenated straight into a link.
 */
[$checkIn, $checkOut, $nights] = normaliseStay(
    $_GET['check_in']  ?? null,
    $_GET['check_out'] ?? null
);

$adults   = max(1, min(6, (int) ($_GET['adults'] ?? 1)));
$maxPrice = isset($_GET['max_price']) ? max(0.0, (float) $_GET['max_price']) : 0.0;

// Whitelist the bed type against the ENUM instead of trusting the input.
$allowedBeds = ['single', 'double', 'twin', 'queen', 'king'];
$bedType     = in_array($_GET['bed_type'] ?? '', $allowedBeds, true) ? $_GET['bed_type'] : '';

$allowedSorts = ['price_asc', 'price_desc', 'capacity', 'name'];
$sort         = in_array($_GET['sort'] ?? '', $allowedSorts, true) ? $_GET['sort'] : 'price_asc';

$page = max(1, (int) ($_GET['page'] ?? 1));

/* ── Build the query ── */
$where  = ['is_active = 1', 'max_adults >= :adults'];
$params = [':adults' => $adults];

if ($maxPrice > 0) {
    $where[] = 'base_price <= :max_price';
    $params[':max_price'] = $maxPrice;
}

if ($bedType !== '') {
    $where[] = 'bed_type = :bed_type';
    $params[':bed_type'] = $bedType;
}

// Column names cannot be bound, so they are mapped from a fixed whitelist.
$orderBy = match ($sort) {
    'price_desc' => 'base_price DESC',
    'capacity'   => 'max_adults DESC, base_price ASC',
    'name'       => 'name ASC',
    default      => 'base_price ASC',
};

$db        = Database::getInstance();
$whereSql  = implode(' AND ', $where);

$totalCount = (int) $db->query("SELECT COUNT(*) FROM room_types WHERE {$whereSql}", $params)->fetchColumn();
$paginator  = new Paginator($totalCount, 9, $page);

// LIMIT/OFFSET are integers derived inside Paginator, never raw input.
$sql = "SELECT * FROM room_types
        WHERE {$whereSql}
        ORDER BY {$orderBy}
        LIMIT {$paginator->perPage} OFFSET {$paginator->offset}";

$roomTypes = array_map(
    static fn(array $row) => new RoomType($row),
    $db->query($sql, $params)->fetchAll()
);

/* Availability for the whole page in ONE query instead of one per card. */
$availService = new AvailabilityService();
$availability = $roomTypes
    ? $availService->availableCountsForTypes(
        array_map(static fn($t) => (int) $t->id, $roomTypes),
        $checkIn,
        $checkOut
      )
    : [];

/** Current filter state, reused when building every link on the page. */
$filterState = [
    'check_in'  => $checkIn,
    'check_out' => $checkOut,
    'adults'    => $adults,
    'bed_type'  => $bedType,
    'sort'      => $sort,
    'max_price' => $maxPrice > 0 ? $maxPrice : null,
];

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/nav.php';
?>

<main id="main" class="main-content">
  <div class="container">

    <!-- PAGE HEADER -->
    <div style="margin-bottom: var(--space-6);">
      <h1 class="page-title" style="font-family: var(--font-display);">Rooms &amp; Suites</h1>
      <p class="page-hint">
        Showing live availability for
        <strong><?= e(formatDate($checkIn)) ?></strong> &rarr;
        <strong><?= e(formatDate($checkOut)) ?></strong>
        (<?= $nights ?> night<?= $nights === 1 ? '' : 's' ?>).
        Change the dates below to search other stays.
      </p>
    </div>

    <!-- ── FILTER BAR ──
         GET so results stay linkable and the browser back button works.
         No CSRF token: it changes nothing on the server. -->
    <form class="filter-bar" method="get" action="<?= url('rooms.php') ?>" role="search">
      <div class="filter-bar__grid">

        <div>
          <label class="form-label" for="f-check-in">Check In</label>
          <input type="date" id="f-check-in" name="check_in" class="form-control"
                 value="<?= e($checkIn) ?>" min="<?= e($today) ?>" required>
        </div>

        <div>
          <label class="form-label" for="f-check-out">Check Out</label>
          <input type="date" id="f-check-out" name="check_out" class="form-control"
                 value="<?= e($checkOut) ?>" required>
        </div>

        <div>
          <label class="form-label" for="f-adults">Min Adults</label>
          <select id="f-adults" name="adults" class="form-select">
            <?php for ($i = 1; $i <= 6; $i++): ?>
              <option value="<?= $i ?>" <?= $adults === $i ? 'selected' : '' ?>>
                <?= $i ?> Adult<?= $i > 1 ? 's' : '' ?>
              </option>
            <?php endfor; ?>
          </select>
        </div>

        <div>
          <label class="form-label" for="f-bed">Bed Type</label>
          <select id="f-bed" name="bed_type" class="form-select">
            <option value="">All bed types</option>
            <?php foreach ($allowedBeds as $bed): ?>
              <option value="<?= e($bed) ?>" <?= $bedType === $bed ? 'selected' : '' ?>>
                <?= e(ucfirst($bed)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="form-label" for="f-price">Max Price / Night</label>
          <input type="number" id="f-price" name="max_price" class="form-control"
                 min="0" step="500" placeholder="Any"
                 value="<?= $maxPrice > 0 ? e((string) (int) $maxPrice) : '' ?>">
        </div>

        <div>
          <label class="form-label" for="f-sort">Sort By</label>
          <select id="f-sort" name="sort" class="form-select">
            <option value="price_asc"  <?= $sort === 'price_asc'  ? 'selected' : '' ?>>Price: low to high</option>
            <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: high to low</option>
            <option value="capacity"   <?= $sort === 'capacity'   ? 'selected' : '' ?>>Largest capacity</option>
            <option value="name"       <?= $sort === 'name'       ? 'selected' : '' ?>>Name (A–Z)</option>
          </select>
        </div>

        <div>
          <button type="submit" class="btn btn--primary btn--block">Apply Filters</button>
        </div>

      </div>
    </form>

    <!-- RESULT COUNT -->
    <?php if ($totalCount > 0): ?>
      <p class="text-muted" style="font-size: var(--text-sm); margin-bottom: var(--space-5);">
        Showing <?= $paginator->offset + 1 ?>–<?= min($paginator->offset + $paginator->perPage, $totalCount) ?>
        of <?= $totalCount ?> room type<?= $totalCount === 1 ? '' : 's' ?>.
      </p>
    <?php endif; ?>

    <!-- ── ROOM GRID ── -->
    <?php if (empty($roomTypes)): ?>
      <div class="empty-state">
        <div class="empty-state__icon" aria-hidden="true">🔍</div>
        <h3>No room types match those filters</h3>
        <p class="text-muted" style="margin-bottom: var(--space-5);">
          Try widening your price range, lowering the guest count, or choosing “All bed types”.
        </p>
        <a href="<?= url('rooms.php') ?>" class="btn btn--secondary">Clear all filters</a>
      </div>
    <?php else: ?>

      <div class="grid grid--3 scene-3d">
        <?php foreach ($roomTypes as $index => $type): ?>
          <?php
            $freeRooms = $availability[(int) $type->id] ?? 0;
            $image     = $type->cover_image ?: 'assets/img/placeholders/room_placeholder.jpg';

            // Built with http_build_query so each value is URL-encoded. This is
            // the line that used to be vulnerable to attribute-breakout XSS.
            $detailUrl = urlWithQuery('room-details.php', [
                'slug'      => $type->slug,
                'check_in'  => $checkIn,
                'check_out' => $checkOut,
                'adults'    => $adults,
            ]);

            // When sold out, tell the guest when it frees up instead of
            // showing a dead end.
            $nextFree = $freeRooms === 0
                ? $availService->nextAvailableDate((int) $type->id, $checkIn)
                : null;
          ?>
          <article class="room-card tilt"
                   data-tilt
                   data-reveal
                   data-reveal-delay="<?= 70 * ($index % 3) ?>">

            <span class="tilt__glare" aria-hidden="true"></span>

            <div class="room-card__media">
              <img src="<?= e(url($image)) ?>"
                   alt="<?= e($type->name) ?>"
                   loading="lazy" decoding="async" width="640" height="480">

              <div class="room-card__badges tilt__depth-2">
                <?php if ($freeRooms > 0): ?>
                  <span class="badge badge--success">
                    <?= (int) $freeRooms ?> room<?= $freeRooms === 1 ? '' : 's' ?> available
                  </span>
                <?php else: ?>
                  <span class="badge badge--danger">Sold out for these dates</span>
                <?php endif; ?>
              </div>

              <div class="room-card__price-tag tilt__depth-1">
                <?= e(money($type->base_price)) ?> <small>/ night</small>
              </div>
            </div>

            <div class="room-card__body">
              <h2 class="room-card__title tilt__depth-1" style="font-size: var(--text-lg);">
                <?= e($type->name) ?>
              </h2>

              <div class="room-card__meta">
                <span><span aria-hidden="true">👤</span> Max <?= (int) $type->max_adults ?> adults</span>
                <?php if ((int) $type->max_children > 0): ?>
                  <span><span aria-hidden="true">🧒</span> <?= (int) $type->max_children ?> children</span>
                <?php endif; ?>
                <span><span aria-hidden="true">🛏️</span> <?= e(ucfirst((string) $type->bed_type)) ?></span>
                <?php if ($type->size_sqft): ?>
                  <span><span aria-hidden="true">📐</span> <?= (int) $type->size_sqft ?> sq ft</span>
                <?php endif; ?>
              </div>

              <?php /* mb_substr so multibyte descriptions are not cut mid-character. */ ?>
              <p class="room-card__desc">
                <?= e(mb_substr((string) $type->description, 0, 140)) ?><?= mb_strlen((string) $type->description) > 140 ? '…' : '' ?>
              </p>

              <?php if ($nextFree): ?>
                <p style="font-size: var(--text-xs); color: var(--warning-500); margin: 0;">
                  Next available from <strong><?= e(formatDate($nextFree)) ?></strong>
                </p>
              <?php endif; ?>

              <div class="room-card__foot">
                <div>
                  <div style="font-size: var(--text-lg); font-weight: var(--weight-bold); color: var(--color-primary);">
                    <?= e(money((float) $type->base_price * $nights)) ?>
                  </div>
                  <span style="font-size: var(--text-xs); color: var(--color-text-subtle);">
                    total for <?= $nights ?> night<?= $nights === 1 ? '' : 's' ?>, excl. tax
                  </span>
                </div>

                <a href="<?= e($detailUrl) ?>" class="btn btn--primary btn--sm tilt__depth-1">
                  <?= $freeRooms > 0 ? 'Book Now' : 'View Room' ?>
                </a>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>

      <!-- PAGINATION — Paginator carries the current $_GET filters forward -->
      <div style="margin-top: var(--space-10);">
        <?= $paginator->links(url('rooms.php')) ?>
      </div>

    <?php endif; ?>

  </div>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
