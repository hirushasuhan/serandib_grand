<?php
declare(strict_types=1);

/**
 * HOME PAGE (public)
 *
 * Assignment requirement: "including a home page".
 *
 * Sections: 3D parallax hero with live availability search → animated stats →
 * featured room types (tilt cards with live availability) → facilities →
 * guest testimonials → closing CTA.
 */

require_once __DIR__ . '/bootstrap.php';

use App\Models\RoomType;
use App\Models\Setting;
use App\Models\Review;
use App\Models\Room;
use App\Services\AvailabilityService;

$hotelName = Setting::get('hotel_name', 'Serendib Grand Resort & Spa');
$pageTitle = 'Luxury Coastal Sanctuary';
$metaDescription = 'Book oceanfront rooms and suites at ' . $hotelName
    . ' in Bentota, Sri Lanka. Live availability, instant confirmation.';

/** Tells includes/header.php to load assets/css/public.css. */
$isPublicPage = true;

$featuredTypes = array_slice(RoomType::activeOnly(), 0, 3);

$today    = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));

/**
 * Availability for every featured card in ONE query.
 * Calling availableCount() inside the render loop was an N+1: one extra
 * round trip per card on every page load.
 */
$availability = [];
if ($featuredTypes) {
    $availability = (new AvailabilityService())->availableCountsForTypes(
        array_map(static fn($t) => (int) $t->id, $featuredTypes),
        $today,
        $tomorrow
    );
}

$reviews = Review::approvedReviews(3);

/* ── Live figures for the counter strip ── */
$totalRooms = count(Room::where(['is_active' => 1]));
$totalTypes = count($featuredTypes) > 0 ? count(RoomType::activeOnly()) : 0;

$avgRating = 0.0;
$allApproved = Review::approvedReviews(500);
if ($allApproved) {
    $ratings   = array_map(static fn($r) => (float) ($r['rating'] ?? 0), $allApproved);
    $avgRating = round(array_sum($ratings) / max(1, count($ratings)), 1);
}

/** Splits the headline so each word can be animated separately. */
$headlineWords = ['Where', 'Tropical', 'Calm', 'Meets'];

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/nav.php';
?>

<main id="main">

  <!-- ═══════════════════════════════════════════════════════════
       1. HERO — drone video background behind a glass search panel

       NOTE: the opening <section class="hero3d"> tag below is REQUIRED.
       It went missing when the video was added, which left 5 opening
       <section> tags against 6 closing ones. Consequences:
         · no .hero3d means no min-height, no centring, no overflow:hidden
           and no `isolation`, so the hero collapsed to content height;
         · the absolutely-positioned video wrapper had no positioned
           ancestor, so it sized against the page instead of the hero;
         · the stray </section> closed <main> early, corrupting the DOM
           for everything after it.
       ═══════════════════════════════════════════════════════════ -->
  <section class="hero3d scene-3d" aria-labelledby="hero-title">

    <?php
      /**
       * AERIAL DRONE VIDEO BACKGROUND — deliberately NOT loaded during page load.
       *
       * The hero image is painted immediately as a CSS background on the wrapper.
       * The <video> carries NO src attributes: the real URLs sit in data-src and
       * are only attached by public3d.js after window 'load' fires, and only when
       * the connection looks capable of it.
       *
       * Why: the original markup pointed at a 482 MB WebM with a <source src>.
       * The browser began that download as part of page load and appeared frozen
       * — no hero, no interaction, a spinning tab for minutes. `preload="none"`
       * alone is only a hint and Chrome often ignores it for autoplay video, so
       * the source is withheld from the DOM entirely until we choose to add it.
       *
       * public3d.js checks data-src-webm first when present (smaller for the
       * same quality) and falls back to data-src-mp4 for browsers that don't
       * support WebM. Current encodes: hero.webm ~3.8 MB, hero.mp4 ~1.7 MB —
       * still two orders of magnitude below the 82 MB raw source.
       */
    ?>
    <div class="hero3d__video-wrapper"
         data-parallax-speed="0.22"
         style="background-image: url('<?= e(url('assets/img/hero/hero_resort.jpg')) ?>');">
      <?php /*
       * FIX: this had regressed back to exactly the bug the comment above
       * describes — `src` pointed straight at hero_drone.mp4 (the raw 82 MB
       * source), which loads eagerly and blocks/freezes the page the same
       * way the original 482 MB file did. `data-src-mp4` also pointed at the
       * same 82 MB file, so even the deferred path would have downloaded it
       * once attached. There is no `src` attribute now — only the deferred
       * data-* attributes, pointing at the actual compressed encodes.
       */ ?>
      <video id="hero-drone-video"
             autoplay loop muted playsinline
             poster="<?= e(url('assets/img/hero/hero_resort.jpg')) ?>"
             aria-hidden="true" tabindex="-1"
             data-src-mp4="<?= e(url('assets/vid/hero.mp4')) ?>"
             data-src-webm="<?= e(url('assets/vid/hero.webm')) ?>"></video>
    </div>

    <div class="hero3d__scrim" role="presentation"></div>

    <div class="hero3d__inner layer-3d">

      <p class="hero3d__eyebrow">
        <span aria-hidden="true"><?= icon('sparkle', 12) ?></span> 5-Star Oceanfront Sanctuary &bull; Bentota
      </p>

      <h1 class="hero3d__title" id="hero-title">
        <?php foreach ($headlineWords as $i => $word): ?>
          <span class="word-rise" style="animation-delay: <?= 80 * $i ?>ms"><?= e($word) ?></span>
        <?php endforeach; ?>
        <span class="word-rise" style="animation-delay: <?= 80 * count($headlineWords) ?>ms"><em>Timeless Luxury</em></span>
      </h1>

      <p class="hero3d__lead">
        Private infinity pools, Ayurvedic spa wellness and ocean-view dining along
        Bentota's golden coastline. Check live availability and reserve in seconds.
      </p>

      <!-- ── Availability search ──
           A GET form so results are linkable and shareable. It needs no CSRF
           token because it changes nothing on the server. -->
      <form class="hero3d__search" method="get" action="<?= url('rooms.php') ?>" role="search">
        <div class="hero3d__search-grid">

          <div>
            <label for="hero-check-in">Check In</label>
            <input type="date" id="hero-check-in" name="check_in" class="form-control"
                   value="<?= e($today) ?>" min="<?= e($today) ?>" required>
          </div>

          <div>
            <label for="hero-check-out">Check Out</label>
            <input type="date" id="hero-check-out" name="check_out" class="form-control"
                   value="<?= e($tomorrow) ?>" min="<?= e($tomorrow) ?>" required>
          </div>

          <div>
            <label for="hero-adults">Guests</label>
            <select id="hero-adults" name="adults" class="form-select">
              <?php for ($i = 1; $i <= 6; $i++): ?>
                <option value="<?= $i ?>" <?= $i === 2 ? 'selected' : '' ?>>
                  <?= $i ?> Adult<?= $i > 1 ? 's' : '' ?>
                </option>
              <?php endfor; ?>
            </select>
          </div>

          <div>
            <label for="hero-bed">Bed Type</label>
            <select id="hero-bed" name="bed_type" class="form-select">
              <option value="">Any</option>
              <option value="single">Single</option>
              <option value="double">Double</option>
              <option value="twin">Twin</option>
              <option value="queen">Queen</option>
              <option value="king">King</option>
            </select>
          </div>

          <div>
            <button type="submit" class="btn btn--primary btn--block btn--lg">
              Search Availability
            </button>
          </div>

        </div>
      </form>

    </div>
  </section>

  <!-- ═══════════════════════════════════════════════════════════
       2. LIVE STATS — count up when scrolled into view
       ═══════════════════════════════════════════════════════════ -->
  <section class="section" style="padding-top: var(--space-8); padding-bottom: var(--space-12);">
    <div class="container">
      <div class="counter-strip" data-reveal>

        <div class="counter">
          <div class="counter__value"
               data-count-to="<?= (int) $totalRooms ?>"
               data-count-suffix="">0</div>
          <div class="counter__label">Rooms &amp; Suites</div>
        </div>

        <div class="counter">
          <div class="counter__value"
               data-count-to="<?= (int) $totalTypes ?>"
               data-count-suffix="">0</div>
          <div class="counter__label">Room Categories</div>
        </div>

        <div class="counter">
          <div class="counter__value"
               data-count-to="<?= $avgRating > 0 ? e((string) $avgRating) : '5' ?>"
               data-count-suffix="/5">0</div>
          <div class="counter__label">Guest Rating</div>
        </div>

        <div class="counter">
          <div class="counter__value" data-count-to="24" data-count-suffix="/7">0</div>
          <div class="counter__label">Front Desk</div>
        </div>

      </div>
    </div>
  </section>

  <!-- ═══════════════════════════════════════════════════════════
       3. FEATURED ROOMS — 3D tilt cards
       ═══════════════════════════════════════════════════════════ -->
  <section class="section section--tinted scene-3d" aria-labelledby="rooms-heading">
    <div class="container">

      <div class="section__head" data-reveal>
        <span class="section__eyebrow">Accommodation</span>
        <h2 class="section__title" id="rooms-heading">Rooms &amp; Suites</h2>
        <p class="section__lead">
          Every room opens onto the Indian Ocean. Availability below is live for
          tonight — pick your own dates to see more.
        </p>
      </div>

      <?php if (empty($featuredTypes)): ?>
        <div class="empty-state" data-reveal>
          <div class="empty-state__icon" aria-hidden="true"><?= icon('building', 40) ?></div>
          <h3>No room types published yet</h3>
          <p class="text-muted">Our team is preparing the collection. Please check back shortly.</p>
        </div>
      <?php else: ?>
        <div class="grid grid--3">
          <?php foreach ($featuredTypes as $index => $type): ?>
            <?php
              // Pre-computed above in a single query.
              $freeRooms = $availability[(int) $type->id] ?? 0;

              // http_build_query escapes every value, so no request data is ever
              // concatenated raw into an href.
              $detailUrl = urlWithQuery('room-details.php', [
                  'slug'      => $type->slug,
                  'check_in'  => $today,
                  'check_out' => $tomorrow,
              ]);

              $image = $type->cover_image ?: 'assets/img/placeholders/room_placeholder.jpg';
            ?>
            <article class="room-card tilt"
                     data-tilt
                     data-reveal
                     data-reveal-delay="<?= 90 * $index ?>">

              <span class="tilt__glare" aria-hidden="true"></span>

              <div class="room-card__media">
                <img src="<?= e(url($image)) ?>"
                     alt="<?= e($type->name) ?> at <?= e($hotelName) ?>"
                     loading="lazy" decoding="async" width="640" height="480">

                <div class="room-card__badges tilt__depth-2">
                  <?php if ($freeRooms > 0): ?>
                    <span class="badge badge--success"><?= (int) $freeRooms ?> available tonight</span>
                  <?php else: ?>
                    <span class="badge badge--danger">Fully booked tonight</span>
                  <?php endif; ?>
                </div>

                <div class="room-card__price-tag tilt__depth-1">
                  <?= e(money($type->base_price)) ?> <small>/ night</small>
                </div>
              </div>

              <div class="room-card__body">
                <h3 class="room-card__title tilt__depth-1"><?= e($type->name) ?></h3>

                <div class="room-card__meta">
                  <span class="icon-heading"><?= icon('user', 14) ?> <?= (int) $type->max_adults ?> adults</span>
                  <span class="icon-heading"><?= icon('bed', 14) ?> <?= e(ucfirst((string) $type->bed_type)) ?></span>
                  <?php if ($type->size_sqft): ?>
                    <span class="icon-heading"><?= icon('maximize', 14) ?> <?= (int) $type->size_sqft ?> sq ft</span>
                  <?php endif; ?>
                </div>

                <?php /* mb_substr, not substr — substr cuts mid-character on
                         multibyte text and produces mojibake. */ ?>
                <p class="room-card__desc">
                  <?= e(mb_substr((string) $type->description, 0, 150)) ?><?= mb_strlen((string) $type->description) > 150 ? '…' : '' ?>
                </p>

                <div class="room-card__foot">
                  <a href="<?= e($detailUrl) ?>" class="btn btn--primary btn--sm tilt__depth-1">
                    View &amp; Book
                  </a>
                  <span class="text-muted" style="font-size: var(--text-xs);">
                    Free cancellation &middot; 24h
                  </span>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>

        <div class="text-center" style="margin-top: var(--space-10);" data-reveal>
          <a href="<?= url('rooms.php') ?>" class="btn btn--secondary btn--lg">
            Browse all rooms &amp; suites &rarr;
          </a>
        </div>
      <?php endif; ?>

    </div>
  </section>

  <!-- ═══════════════════════════════════════════════════════════
       4. FACILITIES
       ═══════════════════════════════════════════════════════════ -->
  <section class="section scene-3d" aria-labelledby="facilities-heading">
    <div class="container">

      <div class="section__head" data-reveal>
        <span class="section__eyebrow">The Resort</span>
        <h2 class="section__title" id="facilities-heading">Everything Within Reach</h2>
        <p class="section__lead">Included with every stay, at no extra charge.</p>
      </div>

      <?php
        $facilities = [
            ['icon' => 'droplet',  'title' => 'Infinity Pools',   'text' => 'Two oceanfront pools plus a shaded children\'s pool, open from 6am.'],
            ['icon' => 'heart',    'title' => 'Ayurvedic Spa',    'text' => 'Traditional Sri Lankan treatments by certified therapists.'],
            ['icon' => 'utensils', 'title' => 'Ocean Dining',     'text' => 'Three restaurants serving Sri Lankan, Asian and Continental menus.'],
            ['icon' => 'wifi',     'title' => 'Fast Wi-Fi',        'text' => 'Complimentary high-speed fibre throughout the resort.'],
            ['icon' => 'activity', 'title' => 'Fitness Centre',   'text' => 'Fully equipped gym with a personal trainer on request.'],
            ['icon' => 'truck',    'title' => 'Airport Transfer',  'text' => 'Private air-conditioned pickup from Colombo (BIA) on request.'],
        ];
      ?>

      <div class="grid grid--3">
        <?php foreach ($facilities as $i => $facility): ?>
          <div class="facility" data-reveal data-reveal-delay="<?= 70 * $i ?>">
            <div class="facility__icon" aria-hidden="true"><?= icon($facility['icon'], 28) ?></div>
            <h3><?= e($facility['title']) ?></h3>
            <p><?= e($facility['text']) ?></p>
          </div>
        <?php endforeach; ?>
      </div>

    </div>
  </section>

  <!-- ═══════════════════════════════════════════════════════════
       5. GUEST REVIEWS — only manager-approved ones are public
       ═══════════════════════════════════════════════════════════ -->
  <?php if (!empty($reviews)): ?>
    <section class="section section--tinted" aria-labelledby="reviews-heading">
      <div class="container">

        <div class="section__head" data-reveal>
          <span class="section__eyebrow">Guest Stories</span>
          <h2 class="section__title" id="reviews-heading">What Our Guests Say</h2>
          <p class="section__lead">
            Verified reviews from guests who completed a stay with us.
          </p>
        </div>

        <div class="grid grid--3">
          <?php foreach ($reviews as $i => $review): ?>
            <?php
              $name    = (string) ($review['full_name'] ?? 'Guest');
              $initial = mb_strtoupper(mb_substr($name, 0, 1));
              $rating  = max(1, min(5, (int) ($review['rating'] ?? 5)));
            ?>
            <figure class="quote-card" data-reveal data-reveal-delay="<?= 90 * $i ?>">
              <blockquote class="quote-card__text">
                <?= e((string) ($review['comment'] ?? '')) ?>
              </blockquote>

              <figcaption class="quote-card__who">
                <span class="quote-card__avatar" aria-hidden="true"><?= e($initial) ?></span>
                <span>
                  <strong style="display: block; font-size: var(--text-sm);"><?= e($name) ?></strong>
                  <?php /* Rating is written out as text as well as stars, so it is
                           not conveyed by a glyph alone. */ ?>
                  <span style="display: inline-flex; align-items: center; color: var(--color-accent); font-size: var(--text-xs);">
                    <span aria-hidden="true" style="display: inline-flex;"><?= str_repeat(icon('star', 14), $rating) . str_repeat(icon('star-outline', 14), 5 - $rating) ?></span>
                    <span class="sr-only"><?= $rating ?> out of 5 stars</span>
                  </span>
                </span>
              </figcaption>
            </figure>
          <?php endforeach; ?>
        </div>

      </div>
    </section>
  <?php endif; ?>

  <!-- ═══════════════════════════════════════════════════════════
       6. CLOSING CTA
       ═══════════════════════════════════════════════════════════ -->
  <section class="section">
    <div class="container">
      <div class="cta-band" data-reveal>
        <h2>Your Coastal Escape Awaits</h2>
        <p>
          Reserve online in under two minutes. No prepayment required —
          settle at the front desk on arrival.
        </p>
        <div style="display: flex; gap: var(--space-4); justify-content: center; flex-wrap: wrap;">
          <a href="<?= url('rooms.php') ?>" class="btn btn--primary btn--lg">Check Availability</a>
          <a href="<?= url('contact.php') ?>" class="btn btn--ghost btn--lg">
            Talk to the Front Desk
          </a>
        </div>
      </div>
    </div>
  </section>

</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
