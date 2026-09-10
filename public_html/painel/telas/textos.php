<?php
/**
 * Textos avulsos do site (tabela blocos). Espera: nada.
 */
declare(strict_types=1);

$blocos = db()->query('SELECT chave, rotulo, valor, tipo FROM blocos ORDER BY rowid')->fetchAll();
?>
<h1>Textos</h1>
<p class="p-sub">Os textos soltos do site: título do topo, chamadas das seções e os prazos de cada modalidade.</p>

<form class="p-form" method="post" action="acoes/textos.php">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>" />

<?php foreach ($blocos as $b): $idc = 'b-' . $b['chave']; ?>
  <div class="p-campo">
    <label for="<?= e($idc) ?>"><?= e($b['rotulo']) ?></label>
<?php if ($b['tipo'] === 'texto_longo'): ?>
    <textarea id="<?= e($idc) ?>" name="valores[<?= e($b['chave']) ?>]" rows="4"><?= e((string) $b['valor']) ?></textarea>
<?php else: ?>
    <input type="text" id="<?= e($idc) ?>" name="valores[<?= e($b['chave']) ?>]" value="<?= e((string) $b['valor']) ?>" />
<?php endif; ?>
  </div>
<?php endforeach; ?>

  <button class="p-btn p-btn--forte" type="submit">Salvar textos</button>
</form>
