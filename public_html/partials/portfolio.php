<?php
/**
 * Grade do portfolio: um botao por casa entregue, com foto, categoria e
 * titulo. O js/main.js abre a foto em tela cheia (#fotobox) ao toque.
 * A grade cresce sem quebrar: 6, 9 ou 20 itens ficam alinhados. Espera: nada.
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/conteudo.php';

$lista_portfolio = portfolio();
?>
<div class="galeria reveal" id="galeria">
<?php foreach ($lista_portfolio as $p): ?>
        <button class="galeria__item" type="button" data-categoria="<?= e($p['categoria']) ?>" data-titulo="<?= e($p['titulo']) ?>">
          <img src="<?= e($p['foto']) ?>" alt="<?= e($p['foto_alt']) ?>" loading="lazy" />
          <span class="galeria__veil" aria-hidden="true"></span>
          <span class="galeria__zoom" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M9 4H4v5M15 4h5v5M9 20H4v-5M15 20h5v-5"/></svg></span>
          <span class="galeria__cap">
            <span class="galeria__eyebrow"><?= e($p['categoria']) ?></span>
            <span class="galeria__title"><?= e($p['titulo']) ?></span>
          </span>
        </button>
<?php endforeach; ?>
      </div>
