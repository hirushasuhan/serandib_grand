<?php
use App\Models\Setting;
?>
<footer class="footer" style="background-color: var(--color-surface); border-top: 1px solid var(--color-border); padding-block: var(--space-16) var(--space-8); margin-top: auto;">
  <div class="container">
    
    <div class="grid grid--4" style="gap: var(--space-8); margin-bottom: var(--space-12);">
      
      <!-- COL 1: BRAND LOGO & TAGLINE -->
      <div>
        <a href="<?= url() ?>" style="display: flex; align-items: center; gap: 12px; text-decoration: none; margin-bottom: var(--space-4);">
          <img src="<?= url('assets/img/logo.jpg') ?>" alt="<?= e(Setting::get('hotel_name')) ?>" style="height: 48px; width: 48px; object-fit: cover; border-radius: 12px; border: 1.5px solid var(--accent-500);">
          <div style="display: flex; flex-direction: column;">
            <span style="font-family: var(--font-display); font-size: 1.2rem; font-weight: 700; color: var(--color-text); letter-spacing: 0.5px;">SERENDIB GRAND</span>
            <span style="font-size: 9px; font-weight: 700; color: var(--accent-500); letter-spacing: 2px; text-transform: uppercase;">Resort &amp; Spa &bull; Bentota</span>
          </div>
        </a>
        <p class="text-muted" style="font-size: var(--text-sm); line-height: 1.6;">
          <?= e(Setting::get('hotel_tagline', 'Serendib Grand Resort & Spa — A coastal sanctuary of luxury and tranquility.')) ?>
        </p>
      </div>

      <!-- COL 2: QUICK NAVIGATION -->
      <div>
        <h4 style="font-size: var(--text-sm); font-weight: bold; text-transform: uppercase; letter-spacing: 1px; margin-bottom: var(--space-4);">Quick Links</h4>
        <ul style="list-style: none; padding: 0; display: flex; flex-direction: column; gap: var(--space-2); font-size: var(--text-sm);">
          <li><a href="<?= url('rooms.php') ?>" class="text-muted">Rooms &amp; Suites</a></li>
          <li><a href="<?= url('about.php') ?>" class="text-muted">About the Resort</a></li>
          <li><a href="<?= url('contact.php') ?>" class="text-muted">Contact Front Desk</a></li>
          <li><a href="<?= url('auth/login.php') ?>" class="text-muted">Guest Portal Login</a></li>
        </ul>
      </div>

      <!-- COL 3: CONTACT & RECEPTION -->
      <div>
        <h4 style="font-size: var(--text-sm); font-weight: bold; text-transform: uppercase; letter-spacing: 1px; margin-bottom: var(--space-4);">Front Desk</h4>
        <p class="text-muted footer__contact-line" style="font-size: var(--text-sm); margin-bottom: 6px;"><?= icon('map-pin', 14) ?> <?= e(Setting::get('hotel_address')) ?></p>
        <p class="text-muted footer__contact-line" style="font-size: var(--text-sm); margin-bottom: 6px;"><?= icon('phone', 14) ?> <?= e(Setting::get('hotel_phone')) ?></p>
        <p class="text-muted footer__contact-line" style="font-size: var(--text-sm);"><?= icon('mail', 14) ?> <?= e(Setting::get('hotel_email')) ?></p>
      </div>

      <!-- COL 4: LUXURY CERTIFICATIONS -->
      <div>
        <h4 style="font-size: var(--text-sm); font-weight: bold; text-transform: uppercase; letter-spacing: 1px; margin-bottom: var(--space-4);">Accreditations</h4>
        <div class="footer__accreditations" style="font-size: var(--text-xs); color: var(--color-text-muted); line-height: 1.6;">
          <span class="footer__contact-line"><?= icon('award', 14) ?> 5-Star Luxury Resort Certified</span>
          <span class="footer__contact-line"><?= icon('leaf', 14) ?> Eco-Green Coastal Hospitality</span>
          <span class="footer__contact-line"><?= icon('lock', 14) ?> 24/7 Protected Guest Services</span>
        </div>
      </div>

    </div>

    <!-- COPYRIGHT BAR -->
    <div style="padding-top: var(--space-6); border-top: 1px solid var(--color-border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--space-4); font-size: var(--text-xs); color: var(--color-text-subtle);">
      <div>
        &copy; <?= date('Y') ?> <?= e(Setting::get('hotel_name')) ?>. All Rights Reserved. CST 226-2 Web Application Project.
      </div>
      <div style="display: flex; gap: var(--space-4);">
        <a href="<?= url('rooms.php') ?>" class="text-muted">Privacy Policy</a>
        <a href="<?= url('rooms.php') ?>" class="text-muted">Terms of Reservation</a>
      </div>
    </div>

  </div>
</footer>

<?php
  /**
   * All scripts are `defer`red.
   *
   * Without it the browser stops parsing at each <script> tag and downloads it
   * synchronously — eight blocking round trips before the page becomes
   * interactive. `defer` lets them download in parallel while parsing continues,
   * and they still execute in document order before DOMContentLoaded, so the
   * existing DOMContentLoaded handlers behave identically.
   */
?>
<script defer src="<?= asset('js/theme.js') ?>"></script>
<script defer src="<?= asset('js/validation.js') ?>"></script>
<script defer src="<?= asset('js/datepicker.js') ?>"></script>
<script defer src="<?= asset('js/availability.js') ?>"></script>
<script defer src="<?= asset('js/components.js') ?>"></script>
<script defer src="<?= asset('js/main.js') ?>"></script>

<?php /* charts.js is only needed where a chart is actually rendered — the
         manager reports and the dashboards. Loading it on the public site was
         dead weight on every visitor. */ ?>
<?php if (!empty($needsCharts)): ?>
  <script defer src="<?= asset('js/charts.js') ?>"></script>
<?php endif; ?>

<?php /* 3D interaction layer — public pages only, not the staff/admin dashboards. */ ?>
<?php if (!empty($isPublicPage)): ?>
  <script defer src="<?= asset('js/public3d.js') ?>"></script>
<?php endif; ?>

</body>
</html>
