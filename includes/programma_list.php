<?php
/** Programmalijst met sprongnavigatie voor de programmapagina. */
?>
    <nav class="speltak-jump" aria-label="Programma per speltak">
      <?php foreach ($speltakken as $sp): ?>
      <a href="<?= e(programma_url($sp['slug'])) ?>" style="background:<?= e($sp['kleur']) ?>"<?= ga_event_attrs('speltak_select', ['speltak' => $sp['slug']]) ?>><?= e($sp['naam']) ?></a>
      <?php endforeach; ?>
    </nav>

    <?php foreach ($speltakken as $sp):
        $heading = $sp['naam'] . ($sp['leeftijd'] ? ' (' . $sp['leeftijd'] . ')' : '');
        $meta = trim(implode(' · ', array_filter([$sp['dag_tijd'], $sp['leiding']])));
    ?>
    <section class="speltak-block py-0" id="sk-<?= e($sp['slug']) ?>">
      <div class="speltak-head"><span class="speltak-dot" style="background:<?= e($sp['kleur']) ?>"></span><h2><a href="<?= e(speltak_url($sp['slug'])) ?>"><?= e($heading) ?></a></h2></div>
      <?php if (!empty($sp['feed_url'])): ?>
        <?php if ($meta !== ''): ?><p class="speltak-meta"><?= e($meta) ?></p><?php endif; ?>
        <?php if (!empty($sp['afmeld_url'])): ?>
        <p class="speltak-afmelden">Afmelden voor een opkomst? <a href="<?= e($sp['afmeld_url']) ?>" target="_blank" rel="noopener"<?= ga_event_attrs('afmeld_click', ['speltak' => $sp['slug']]) ?>>Klik hier</a>.</p>
        <?php endif; ?>
        <div class="prog-list" data-feed-slug="<?= e($sp['slug']) ?>" id="feed-<?= e($sp['slug']) ?>">
          <p class="prog-loading">Programma wordt geladen&hellip;</p>
        </div>
      <?php elseif (!empty($sp['toelichting'])): ?>
        <div class="speltak-meta"><?= add_lazy_loading($sp['toelichting']) /* HTML toegestaan */ ?></div>
      <?php else: ?>
        <p class="prog-empty">Er is op dit moment geen programma beschikbaar.</p>
      <?php endif; ?>
      <p class="speltak-more"><a href="<?= e(speltak_url($sp['slug'])) ?>">Meer over de <?= e($sp['naam']) ?> <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a></p>
    </section>
    <?php endforeach; ?>

