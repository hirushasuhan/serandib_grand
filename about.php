<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
use App\Models\Setting;

$hotelName = Setting::get('hotel_name', 'Serendib Grand Resort & Spa');
$hotelTagline = Setting::get('hotel_tagline', 'A coastal sanctuary of luxury and tranquility');
$pageTitle = 'About Us — ' . $hotelName;
$metaDescription = 'Discover the story, heritage, and 5-star hospitality behind ' . $hotelName . ' in Bentota, Sri Lanka.';

/** Loads assets/css/public.css + public3d.js (see includes/header.php). */
$isPublicPage = true;

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/nav.php';
?>

<main id="main">

  <!-- ═══════════════════════════════════════════════════════════
       1. LUXURY HERO BANNER
       ═══════════════════════════════════════════════════════════ -->
  <section class="hero3d scene-3d" aria-labelledby="about-title" style="min-height: 520px;">
    
    <div class="hero3d__layer"
         style="background-image: url('<?= e(url('assets/img/hero/hero_resort.jpg')) ?>');"
         role="presentation"></div>
    <div class="hero3d__scrim" role="presentation"></div>

    <div class="hero3d__inner layer-3d" style="max-width: 920px; padding-block: var(--space-16);">
      
      <div style="display: inline-flex; align-items: center; gap: 8px; padding: 6px 18px; background: rgba(200, 150, 62, 0.22); border: 1px solid var(--accent-500); border-radius: var(--radius-full); font-size: var(--text-xs); font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #f3d79b; margin-bottom: var(--space-4); backdrop-filter: blur(10px);">
        ✦ Our Heritage &amp; Vision
      </div>

      <h1 class="hero3d__title" id="about-title" style="font-size: clamp(2.4rem, 5.5vw, 4.2rem);">
        Welcome to <em><?= e($hotelName) ?></em>
      </h1>

      <p class="hero3d__lead" style="font-size: var(--text-lg); color: rgba(255, 255, 255, 0.92); max-width: 720px; margin-inline: auto;">
        <?= e($hotelTagline) ?>
      </p>

    </div>
  </section>

  <!-- ═══════════════════════════════════════════════════════════
       2. RESORT STORY & HERITAGE
       ═══════════════════════════════════════════════════════════ -->
  <section class="section" style="padding-block: var(--space-20); background-color: var(--color-bg);">
    <div class="container">

      <div class="grid grid--2" style="align-items: center; gap: var(--space-12);">
        
        <div data-reveal>
          <span style="color: var(--accent-500); font-weight: 700; font-size: var(--text-xs); text-transform: uppercase; letter-spacing: 2px;">Tropical Luxury Redefined</span>
          <h2 style="font-family: var(--font-display); font-size: clamp(2rem, 4vw, 2.75rem); font-weight: 700; line-height: 1.15; margin-top: var(--space-2); margin-bottom: var(--space-6);">
            Serene Coastal Elegance along Bentota's Golden Shore
          </h2>
          <p style="font-size: var(--text-base); line-height: var(--leading-loose); color: var(--color-text-muted); margin-bottom: var(--space-4);">
            Nestled along Sri Lanka’s world-renowned southern coastline, <strong><?= e($hotelName) ?></strong> combines authentic Ceylonese hospitality with contemporary architectural grandeur. From our private oceanfront villas to infinity pools merging with the horizon, every corner is designed for tranquility.
          </p>
          <p style="font-size: var(--text-base); line-height: var(--leading-loose); color: var(--color-text-muted); margin-bottom: var(--space-6);">
            Whether you are seeking a restful wellness retreat, a romantic beachfront getaway, or a memorable family vacation, our dedicated staff ensures your stay is effortlessly extraordinary.
          </p>

          <div style="display: flex; gap: var(--space-6); align-items: center; padding-top: var(--space-4); border-top: 1px solid var(--color-border);">
            <div>
              <div style="font-family: var(--font-display); font-size: 1.8rem; font-weight: 700; color: var(--color-primary);">5 ★★★★★</div>
              <div style="font-size: var(--text-xs); color: var(--color-text-subtle); text-transform: uppercase; letter-spacing: 1px;">Luxury Accreditation</div>
            </div>
            <div style="width: 1px; height: 40px; background: var(--color-border);"></div>
            <div>
              <div style="font-family: var(--font-display); font-size: 1.8rem; font-weight: 700; color: var(--accent-500);">100%</div>
              <div style="font-size: var(--text-xs); color: var(--color-text-subtle); text-transform: uppercase; letter-spacing: 1px;">Oceanfront Experience</div>
            </div>
          </div>
        </div>

        <div style="position: relative;" data-reveal>
          <img src="<?= e(url('assets/img/hero/hero_resort.jpg')) ?>" alt="<?= e($hotelName) ?>" style="border-radius: var(--radius-xl); box-shadow: var(--shadow-xl); width: 100%; aspect-ratio: 4/3; object-fit: cover;">
          
          <!-- OVERLAY BRAND BADGE -->
          <div style="position: absolute; bottom: -20px; left: -20px; background: var(--color-surface); border: 1.5px solid var(--accent-500); padding: var(--space-4) var(--space-6); border-radius: var(--radius-lg); box-shadow: var(--shadow-lg); display: flex; align-items: center; gap: 12px;">
            <img src="<?= e(url('assets/img/logo.jpg')) ?>" alt="Logo" style="height: 44px; width: 44px; border-radius: 10px; object-fit: cover;">
            <div>
              <strong style="font-family: var(--font-display); font-size: var(--text-base); display: block; color: var(--color-text);">SERENDIB GRAND</strong>
              <span style="font-size: 10px; color: var(--accent-500); font-weight: 700; letter-spacing: 1px; text-transform: uppercase;">Bentota Sanctuary</span>
            </div>
          </div>
        </div>

      </div>

    </div>
  </section>

  <!-- ═══════════════════════════════════════════════════════════
       3. OUR FOUR PILLARS OF EXCELLENCE
       ═══════════════════════════════════════════════════════════ -->
  <section class="section" style="padding-block: var(--space-20); background-color: var(--color-surface); border-block: 1px solid var(--color-border);">
    <div class="container">

      <div style="text-align: center; max-width: 650px; margin-inline: auto; margin-bottom: var(--space-12);" data-reveal>
        <span style="color: var(--accent-500); font-weight: 700; font-size: var(--text-xs); text-transform: uppercase; letter-spacing: 2px;">The Serendib Standard</span>
        <h2 style="font-family: var(--font-display); font-size: var(--text-3xl); font-weight: 700; margin-top: var(--space-2);">Our Pillars of Excellence</h2>
        <p class="text-muted">The core principles that guide our commitment to world-class hospitality.</p>
      </div>

      <div class="grid grid--4">
        
        <div class="card" style="padding: var(--space-8); border-top: 4px solid var(--brand-500);" data-reveal>
          <div style="font-size: 2.8rem; margin-bottom: var(--space-4);">🏆</div>
          <h3 style="font-family: var(--font-display); font-size: var(--text-xl); margin-bottom: var(--space-2);">Uncompromising Luxury</h3>
          <p class="text-muted" style="font-size: var(--text-sm); line-height: 1.6;">
            Combining 5-star international standards with genuine Sri Lankan warmth to deliver personalized butler services and bespoke care.
          </p>
        </div>

        <div class="card" style="padding: var(--space-8); border-top: 4px solid var(--accent-500);" data-reveal>
          <div style="font-size: 2.8rem; margin-bottom: var(--space-4);">🧘‍♀️</div>
          <h3 style="font-family: var(--font-display); font-size: var(--text-xl); margin-bottom: var(--space-2);">Ayurvedic Wellness</h3>
          <p class="text-muted" style="font-size: var(--text-sm); line-height: 1.6;">
            Ancient holistic healing therapies, herbal oil treatments, and beachfront yoga pavilions supervised by certified Ayurvedic doctors.
          </p>
        </div>

        <div class="card" style="padding: var(--space-8); border-top: 4px solid var(--brand-500);" data-reveal>
          <div style="font-size: 2.8rem; margin-bottom: var(--space-4);">🍽️</div>
          <h3 style="font-family: var(--font-display); font-size: var(--text-xl); margin-bottom: var(--space-2);">Culinary Artistry</h3>
          <p class="text-muted" style="font-size: var(--text-sm); line-height: 1.6;">
            Fresh coastal seafood delicacies, authentic Ceylonese spices, and international buffet dining curated by master chefs.
          </p>
        </div>

        <div class="card" style="padding: var(--space-8); border-top: 4px solid var(--accent-500);" data-reveal>
          <div style="font-size: 2.8rem; margin-bottom: var(--space-4);">🌱</div>
          <h3 style="font-family: var(--font-display); font-size: var(--text-xl); margin-bottom: var(--space-2);">Eco-Sustainability</h3>
          <p class="text-muted" style="font-size: var(--text-sm); line-height: 1.6;">
            Dedicated to marine turtle conservation, zero single-use plastics, and supporting local Sri Lankan coastal communities.
          </p>
        </div>

      </div>

    </div>
  </section>

  <!-- ═══════════════════════════════════════════════════════════
       4. CLOSING CTA
       ═══════════════════════════════════════════════════════════ -->
  <section class="section" style="padding-block: var(--space-20);">
    <div class="container">
      <div class="cta-band" data-reveal>
        <h2>Experience the Magic of <?= e($hotelName) ?></h2>
        <p>
          Begin your journey to Bentota's finest coastal sanctuary. Reserve online or speak directly with our front desk reservations team.
        </p>
        <div style="display: flex; gap: var(--space-4); justify-content: center; flex-wrap: wrap;">
          <a href="<?= url('rooms.php') ?>" class="btn btn--gold btn--lg">Explore Rooms &amp; Suites &rarr;</a>
          <a href="<?= url('contact.php') ?>" class="btn btn--ghost btn--lg">Contact Front Desk</a>
        </div>
      </div>
    </div>
  </section>

</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
