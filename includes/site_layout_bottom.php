</main>

<footer>
  <div class="container">
    <div class="footer-grid">
      <div>
        <strong><?= e(get_setting('org_naam')) ?></strong><br>
        <?= e(get_setting('org_straat')) ?>, <?= e(get_setting('org_postcode')) ?> <?= e(get_setting('org_plaats')) ?>
      </div>
      <div>
        <a href="<?= $homeHref ?>#verhuur">Verhuur</a> &middot; <a href="<?= $homeHref ?>#contact">Contact</a>
      </div>
    </div>
    <div class="footer-note">
      <?= get_setting_with_year('footer_copyright') /* HTML toegestaan */ ?>
    </div>
  </div>
</footer>

<script src="assets/js/main.js"></script>
<?php if (get_setting('ga_measurement_id')): ?>
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= e(get_setting('ga_measurement_id')) ?>"></script>
<script src="assets/js/gtag.js" data-ga-id="<?= e(get_setting('ga_measurement_id')) ?>"></script>
<?php endif; ?>
</body>
</html>
