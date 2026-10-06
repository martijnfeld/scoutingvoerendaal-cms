<?php
require_once __DIR__ . '/includes/functions.php';

$speltakken = get_speltakken(true);
$lidworden_cards = get_info_cards('lidworden');
$isHome = true;

function doc_url(?array $doc): string
{
    return $doc ? rtrim(UPLOAD_URL, '/') . '/' . $doc['bestand'] : '#';
}

include __DIR__ . '/includes/site_layout_top.php';
?>
<section class="hero" id="top">
  <div class="container">
    <h1><?= e(get_setting('hero_title')) ?></h1>
    <p><?= e(get_setting('hero_text')) ?></p>
    <div class="hero-buttons">
      <a href="<?= e(get_setting('hero_btn1_link')) ?>" class="btn btn-light"><?= e(get_setting('hero_btn1_text')) ?></a>
      <a href="<?= e(get_setting('hero_btn2_link')) ?>" class="btn btn-outline"><?= e(get_setting('hero_btn2_text')) ?></a>
    </div>
  </div>
</section>

<h2 class="sr-only">Waarom <?= e(get_setting('org_naam')) ?></h2>
<div class="feature-strip">
  <?php for ($i = 1; $i <= 3; $i++): ?>
  <div class="feature-card">
    <?php if (get_setting("feature{$i}_image")): ?>
    <img src="<?= e(get_setting("feature{$i}_image")) ?>" alt="<?= e(get_setting("feature{$i}_alt")) ?>">
    <?php endif; ?>
    <div class="feature-card-body">
      <h3><?= e(get_setting("feature{$i}_title")) ?></h3>
      <p><?= get_setting("feature{$i}_text") /* HTML toegestaan */ ?></p>
    </div>
  </div>
  <?php endfor; ?>
</div>

<!-- ================= PROGRAMMA ================= -->
<section id="programma">
  <div class="container">
    <h2 class="section-title">Programma</h2>
    <p class="section-sub"><?= e(get_setting('programma_intro')) ?></p>

    <div class="speltak-jump">
      <?php foreach ($speltakken as $sp): ?>
      <a href="#sk-<?= e($sp['slug']) ?>" style="background:<?= e($sp['kleur']) ?>"><?= e($sp['naam']) ?></a>
      <?php endforeach; ?>
    </div>

    <?php foreach ($speltakken as $sp):
        $heading = $sp['naam'] . ($sp['leeftijd'] ? ' (' . $sp['leeftijd'] . ')' : '');
        $meta = trim(implode(' &middot; ', array_filter([$sp['dag_tijd'], $sp['leiding']])));
    ?>
    <div class="speltak-block" id="sk-<?= e($sp['slug']) ?>">
      <div class="speltak-head"><span class="speltak-dot" style="background:<?= e($sp['kleur']) ?>"></span><h3><?= e($heading) ?></h3></div>
      <?php if (!empty($sp['feed_url'])): ?>
        <?php if ($meta !== ''): ?><p class="speltak-meta"><?= e($meta) ?></p><?php endif; ?>
        <?php if (!empty($sp['afmeld_url'])): ?>
        <p class="speltak-afmelden">Afmelden voor een opkomst? <a href="<?= e($sp['afmeld_url']) ?>" target="_blank" rel="noopener">Klik hier</a>.</p>
        <?php endif; ?>
        <div class="prog-list" data-feed-slug="<?= e($sp['slug']) ?>" id="feed-<?= e($sp['slug']) ?>">
          <p class="prog-loading">Programma wordt geladen&hellip;</p>
        </div>
      <?php elseif (!empty($sp['toelichting'])): ?>
        <p class="speltak-meta"><?= $sp['toelichting'] /* HTML toegestaan */ ?></p>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <p style="text-align:center; color:#777; font-size:0.9rem;"><?= e(get_setting('programma_footer_note')) ?></p>
  </div>
</section>

<!-- ================= LID WORDEN ================= -->
<section class="alt" id="lidworden">
  <div class="container">
    <h2 class="section-title">Doe mee!</h2>
    <p class="section-sub"><?= e(get_setting('lidworden_intro')) ?></p>

    <p><?= get_setting('lidworden_tekst_1') ?></p>
    <p><?= get_setting('lidworden_tekst_2') ?></p>

    <table class="leeftijden">
      <tr><th>Speltak</th><th>Leeftijd</th><th>Wanneer</th></tr>
      <?php foreach ($speltakken as $sp): ?>
      <tr>
        <td><?= e($sp['naam']) ?></td>
        <td><?= e($sp['leeftijd'] ?: '-') ?></td>
        <td><?= e($sp['dag_tijd'] ?: '-') ?></td>
      </tr>
      <?php endforeach; ?>
    </table>

    <div class="info-cards">
      <?php foreach ($lidworden_cards as $card): $doc = get_document($card['document_id']); ?>
      <div class="info-card"<?= $card['anchor'] ? ' id="' . e($card['anchor']) . '"' : '' ?>>
        <?php if (!empty($card['afbeelding'])): ?>
        <img src="<?= e($card['afbeelding']) ?>" alt="<?= e($card['titel']) ?>">
        <?php endif; ?>
        <h3><?= $card['titel'] /* mag eenvoudige HTML zoals &amp; bevatten */ ?></h3>
        <?= $card['tekst'] /* HTML toegestaan, beheerd via CMS */ ?>
        <?php if ($doc): ?>
        <a class="btn-download" href="<?= e(doc_url($doc)) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-file-pdf"></i> <?= e($card['knop_tekst'] ?: $doc['naam']) ?></a>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ================= VERHUUR ================= -->
<section id="verhuur">
  <div class="container">
    <h2 class="section-title">Verhuur</h2>
    <p class="section-sub"><?= e(get_setting('verhuur_intro')) ?></p>

    <div class="verhuur-grid">
      <div>
        <?php if (get_setting('verhuur_photo')): ?>
        <img class="verhuur-photo" src="<?= e(get_setting('verhuur_photo')) ?>" alt="Clubgebouw van <?= e(get_setting('org_naam')) ?>">
        <?php endif; ?>
        <h3>Voorzieningen</h3>
        <?= get_setting('verhuur_voorzieningen') /* HTML toegestaan, beheerd via CMS */ ?>
        <p><?= e(get_setting('verhuur_adres_tekst')) ?></p>
      </div>
      <div class="voorwaarden">
        <h3>Voorwaarden</h3>
        <?= get_setting('verhuur_voorwaarden') /* HTML toegestaan, beheerd via CMS */ ?>
        <p><?= e(get_setting('verhuur_contact_tekst')) ?><br>
        Mail naar <a href="mailto:<?= e(get_setting('verhuur_email')) ?>"><?= e(get_setting('verhuur_email')) ?></a></p>
      </div>
    </div>
  </div>
</section>

<!-- ================= CONTACT ================= -->
<section class="alt" id="contact">
  <div class="container">
    <h2 class="section-title">Contact</h2>
    <p class="section-sub"><?= e(get_setting('contact_intro')) ?></p>

    <div class="contact-grid">
      <div class="contact-card">
        <h3>Clubgebouw</h3>
        <address>
          <a href="<?= e(get_setting('org_maps_link')) ?>" target="_blank" rel="noopener noreferrer"><?= e(get_setting('org_straat')) ?><br><?= e(get_setting('org_postcode')) ?> <?= e(get_setting('org_plaats')) ?></a>
        </address>
        <?php if (get_setting('org_gebouw_extra')): ?>
        <p><small><i><?= e(get_setting('org_gebouw_extra')) ?></i></small></p>
        <?php endif; ?>
        <p>Tel: <?= e(get_setting('org_telefoon_display')) ?> <small><i><?= e(get_setting('org_telefoon_note')) ?></i></small></p>
      </div>
      <div class="contact-card">
        <h3>Correspondentieadres</h3>
        <p><?= get_setting('correspondentie_adres') /* HTML toegestaan (regeleinde) */ ?></p>
        <p><a href="mailto:<?= e(get_setting('org_email')) ?>"><?= e(get_setting('org_email')) ?></a></p>
        <hr>
        <h3>Declaraties</h3>
        <p>Wil je gemaakte onkosten declareren? Dat kan digitaal <a href="<?= e(get_setting('declaratie_link')) ?>" target="_blank">via deze link.</a></p>
      </div>
      <div class="contact-card">
        <h3>Volg ons</h3>
        <p>Blijf op de hoogte via social media.</p>
        <div class="social-row">
          <a href="<?= e(get_setting('social_facebook')) ?>" target="_blank" rel="noopener" aria-label="Facebook">
            <i class="fa-brands fa-facebook-f"></i>
          </a>
          <a href="<?= e(get_setting('social_instagram')) ?>" target="_blank" rel="noopener" aria-label="Instagram">
            <i class="fa-brands fa-instagram"></i>
          </a>
          <a href="<?= e(get_setting('social_youtube')) ?>" target="_blank" rel="noopener" aria-label="YouTube">
            <i class="fa-brands fa-youtube"></i>
          </a>
        </div>
      </div>
    </div>
  </div>
</section>
<?php include __DIR__ . '/includes/site_layout_bottom.php'; ?>
