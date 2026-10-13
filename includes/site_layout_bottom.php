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
        <?php if ($gaId): ?>
        &middot; <button type="button" class="link-button" data-cc="show-preferencesModal">Cookie-instellingen</button>
        <?php endif; ?>
      </div>
    </div>
    <div class="footer-note">
      <?= get_setting_with_year('footer_copyright') /* HTML toegestaan */ ?>
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(asset_url('assets/js/main.js')) ?>"></script>
<?php if ($gaId):
    $ccTexts = cookie_banner_texts();
?>
<!-- Cookiebanner (orestbida/cookieconsent) + Google tag (gtag.js) met Consent Mode, zie assets/js/gtag.js -->
<script src="https://cdn.jsdelivr.net/npm/vanilla-cookieconsent@<?= COOKIECONSENT_VERSION ?>/dist/cookieconsent.umd.js"></script>
<script src="<?= e(asset_url('assets/js/gtag.js')) ?>" data-ga-id="<?= e($gaId) ?>" data-cc-texts="<?= e(json_encode([
    'title' => $ccTexts['cookie_banner_title'],
    'text' => $ccTexts['cookie_banner_text'], /* HTML toegestaan */
    'accept' => $ccTexts['cookie_banner_accept'],
    'reject' => $ccTexts['cookie_banner_reject'],
    'settings' => $ccTexts['cookie_banner_settings'],
    'privacy_link' => $ccTexts['cookie_privacy_link'],
])) ?>"></script>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($gaId) ?>"></script>
<?php endif; ?>
</body>
</html>
