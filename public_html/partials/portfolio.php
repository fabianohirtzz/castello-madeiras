<?php
/**
 * Galeria acordeao do portfolio. Espera: nada.
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/conteudo.php';

$lista_portfolio = portfolio();
?>
<div class="accordion reveal" id="accordion">
<?php foreach ($lista_portfolio as $i => $p): $primeiro = $i === 0; ?>
        <button class="accordion__item<?= $primeiro ? ' is-active' : '' ?>" type="button" aria-pressed="<?= $primeiro ? 'true' : 'false' ?>">
          <img src="<?= e($p['foto']) ?>" alt="<?= e($p['foto_alt']) ?>" loading="lazy" />
          <span class="accordion__veil" aria-hidden="true"></span>
          <span class="accordion__cap">
            <span class="accordion__eyebrow"><?= e($p['categoria']) ?></span>
            <span class="accordion__title"><?= e($p['titulo']) ?></span>
          </span>
        </button>
<?php endforeach; ?>
      </div>
