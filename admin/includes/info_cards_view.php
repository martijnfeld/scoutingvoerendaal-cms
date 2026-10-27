
<p>Info-vakjes met sectie <code>lidworden</code> verschijnen op de homepage bij <a href="../#lidworden" target="_blank" rel="noopener">Doe mee!</a>. Ook kaarten met andere secties blijven hier beheerbaar; deze worden daar niet getoond.</p>
<div class="top-actions">
  <a href="<?= e($editorUrl . '&actie=nieuw') ?>" class="btn">+ Nieuw info-vakje</a>
</div>

<div class="admin-card table-responsive">
  <table class="admin-table">
    <tr><th>Volgorde</th><th>Sectie</th><th>Titel</th><th>Document</th><th>Acties</th></tr>
    <?php foreach ($cards as $card): ?>
    <tr>
      <td>
        <form method="post" style="display:inline;">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="move">
          <input type="hidden" name="id" value="<?= $card['id'] ?>">
          <input type="hidden" name="dir" value="up">
          <button type="submit" class="btn btn-secondary btn-small" title="Omhoog"><i class="fa-solid fa-arrow-up"></i></button>
        </form>
        <form method="post" style="display:inline;">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="move">
          <input type="hidden" name="id" value="<?= $card['id'] ?>">
          <input type="hidden" name="dir" value="down">
          <button type="submit" class="btn btn-secondary btn-small" title="Omlaag"><i class="fa-solid fa-arrow-down"></i></button>
        </form>
      </td>
      <td><?= e($card['sectie']) ?></td>
      <td><strong><?= e($card['titel']) ?></strong><?= $card['anchor'] ? '<br><span class="muted">#' . e($card['anchor']) . '</span>' : '' ?></td>
      <td><?= $card['document_naam'] ? e($card['document_naam']) : '<span class="muted">geen</span>' ?></td>
      <td class="actions">
        <a class="btn btn-small" href="<?= e($editorUrl . '&actie=bewerken&id=' . (int) $card['id']) ?>">Bewerken</a>
        <form method="post" data-confirm="Dit info-vakje verwijderen?" style="display:inline;">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= $card['id'] ?>">
          <button type="submit" class="btn btn-danger btn-small">Verwijderen</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$cards): ?>
    <tr><td colspan="5" class="muted">Nog geen info-vakjes.</td></tr>
    <?php endif; ?>
  </table>
</div>

