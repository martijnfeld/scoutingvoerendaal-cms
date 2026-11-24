  </main>
</div>
<?php if ($loadRichTextEditor ?? true): ?>
<?php // Shortcodes voor de autocomplete in de editor (gegevens, geen script: CSP-veilig). ?>
<div id="shortcode-suggestions" hidden data-items="<?= e((string) json_encode(shortcode_suggestions(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"></div>
<?php // "Super-build": alle open-source CKEditor-plugins; welke aan staan, zie assets/js/admin.js. ?>
<script src="https://cdn.ckeditor.com/ckeditor5/41.4.2/super-build/ckeditor.js"></script>
<script src="https://cdn.ckeditor.com/ckeditor5/41.4.2/super-build/translations/nl.js"></script>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(asset_url('../assets/js/admin.js')) ?>"></script>
</body>
</html>
