<?php
use App\Core\Auth;
use App\Models\Setting;

$currentUser = Auth::user();
?>
<?php
  /**
   * $hasSidebar tells this navbar whether the page it sits on is a dashboard
   * page (one that requires includes/sidebar.php). Dashboard pages get a
   * hamburger that opens the sidebar drawer; every other page gets one that
   * opens the nav links. Both are hidden above the mobile breakpoint by CSS.
   *
   * Being logged in is NOT sufficient on its own: index.php, rooms.php,
   * about.php and the other public pages include this navbar but never
   * include sidebar.php. Keying off the user alone rendered a drawer button
   * on those pages that pointed at a #app-sidebar which did not exist —
   * a visible control that did nothing when tapped. Public pages all set
   * $isPublicPage, so requiring its absence identifies a dashboard page.
   */
  $hasSidebar = !empty($currentUser) && empty($isPublicPage);
?>
<nav class="navbar">
  <div class="container navbar__inner">

    <!-- MOBILE: opens the dashboard sidebar drawer.
         Without this the sidebar is translateX(-100%) at <=1024px with no way
         to ever bring it back — dashboard navigation was unreachable on every
         phone and most tablets. -->
    <?php if ($hasSidebar): ?>
      <button type="button" class="navbar__burger" data-nav-toggle
              aria-label="Open menu" aria-expanded="false" aria-controls="app-sidebar">
        <span></span><span></span><span></span>
      </button>
    <?php endif; ?>

    <!-- BRAND LOGO WITH HIGH-RES EMBLEM IMAGE -->
    <a href="<?= url() ?>" class="navbar__brand">
      <img src="<?= url('assets/img/logo.jpg') ?>" alt="<?= e(Setting::get('hotel_name')) ?>" width="48" height="48">
      <span class="navbar__brand-text">
        <span class="navbar__brand-name">SERENDIB GRAND</span>
        <span class="navbar__brand-sub">Resort &amp; Spa &bull; Bentota</span>
      </span>
    </a>

    <!-- MOBILE: opens the public link list. Only shown where there is no
         sidebar, so the two toggles can never appear at the same time. -->
    <?php if (!$hasSidebar): ?>
      <button type="button" class="navbar__burger navbar__burger--menu" data-menu-toggle
              aria-label="Open menu" aria-expanded="false" aria-controls="navbar-menu">
        <span></span><span></span><span></span>
      </button>
    <?php endif; ?>

    <!-- NAV MENU LINKS -->
    <div class="navbar__menu" id="navbar-menu">
      <a href="<?= url('rooms.php') ?>" class="nav-link nav-link--public">Rooms &amp; Suites</a>
      <a href="<?= url('about.php') ?>" class="nav-link nav-link--public">About Resort</a>
      <a href="<?= url('contact.php') ?>" class="nav-link nav-link--public">Contact Desk</a>

      <?php if ($currentUser): ?>
        <?php
          $dashUrl = match($currentUser->role) {
            'admin'        => url('admin/dashboard.php'),
            'manager'      => url('manager/dashboard.php'),
            'receptionist' => url('staff/dashboard.php'),
            default        => url('guest/dashboard.php')
          };
        ?>
        <div class="user-dropdown">
          <a href="<?= $dashUrl ?>" class="btn btn--secondary btn--sm navbar__user">
            <svg class="icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
            </svg>
            <span class="navbar__user-name"><?= e($currentUser->full_name) ?></span>

            <?php /* FIX: on mobile the name above is hidden to save space, which
                     left this badge as the ONLY thing next to the hotel name —
                     just the bare word "guest"/"receptionist" floating in the
                     bar, which read as unfinished rather than a role label.
                     Below 768px this collapses to a small circular initial
                     (e.g. "G") instead — the same compact-avatar pattern most
                     apps use, so it reads as an identity chip, not stray text. */ ?>
            <span class="badge badge--info navbar__user-role">
              <span class="navbar__user-role-text"><?= e($currentUser->role) ?></span>
              <span class="navbar__user-role-initial" aria-hidden="true"><?= e(strtoupper(substr($currentUser->role, 0, 1))) ?></span>
            </span>
          </a>
        </div>

        <form method="post" action="<?= url('actions/auth/logout.php') ?>" class="navbar__logout">
          <?= \App\Core\Csrf::field() ?>
          <button type="submit" class="btn btn--ghost btn--sm" title="Log Out">
            <span class="navbar__logout-text">Logout</span>
            <svg class="icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
          </button>
        </form>

      <?php else: ?>
        <a href="<?= url('auth/login.php') ?>" class="btn btn--secondary btn--sm">Log In</a>
        <a href="<?= url('auth/register.php') ?>" class="btn btn--primary btn--sm">Register</a>
      <?php endif; ?>

      <?php
        /**
         * THEME TOGGLE BUTTON
         *
         * FIX: previously a single 🌙 emoji, and assets/js/theme.js tried to
         * swap it for ☀️ by looking up `toggleBtn.querySelector('.theme-icon')`
         * — but no element with that class ever existed in this markup, so
         * `icon` was always null and the swap silently did nothing. The button
         * showed 🌙 permanently regardless of which theme was active.
         *
         * Both icons are now rendered as SVGs and CSS shows/hides them purely
         * from the `data-theme` attribute already set on <html> — no JS icon
         * lookup involved, so this cannot silently break the same way again.
         */
      ?>
      <button type="button" class="btn btn--ghost btn--sm navbar__theme" data-theme-toggle title="Toggle Dark/Light Theme" aria-label="Toggle theme">
        <svg class="icon icon--sun" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <circle cx="12" cy="12" r="4"/>
          <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>
        </svg>
        <svg class="icon icon--moon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
        </svg>
      </button>

    </div>

  </div>
</nav>
