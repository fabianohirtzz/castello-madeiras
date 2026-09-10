<?php
/**
 * Grade de modelos. Espera: string $modalidade ('pronta' ou 'flex').
 * Imprime a mesma marcacao que hoje esta no index.html, trocando so os valores.
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/conteudo.php';

$lista_modelos = modelos($modalidade ?? 'pronta');
?>
<div class="grid grid--models">
<?php foreach ($lista_modelos as $m):
    $destaque = (int) $m['destaque'] === 1;
    // O rotulo do botao usa a area sem os centavos zerados, como no site atual:
    // "39,00 m²" vira "39 m²", mas "42,75 m²" fica como esta.
    $rotulo = $m['nome'] . ' · ' . str_replace(',00 ', ' ', (string) $m['area']) . ' · R$ ' . $m['preco'];
?>
        <article class="model<?= $destaque ? ' model--featured' : '' ?> reveal">
<?php if ($destaque): ?>
          <span class="model__flag">Mais escolhida</span>
<?php endif; ?>
          <div class="model__media">
            <img src="<?= e($m['foto']) ?>" alt="<?= e($m['foto_alt']) ?>" loading="lazy" />
            <span class="model__badge">Chave na mão</span>
          </div>
          <div class="model__body">
            <span class="eyebrow"><?= e($m['parede']) ?></span>
            <h3 class="model__name"><?= e($m['nome']) ?></h3>
            <p class="model__area"><?= e($m['area']) ?> de área construída</p>
            <div class="price"><span class="price__label">A partir de</span><span class="price__val"><span class="price__cur">R$</span> <?= e($m['preco']) ?></span></div>
            <button type="button" class="btn btn--primary btn--block" data-quote-open data-modelo="<?= e($rotulo) ?>">Pedir orçamento</button>
          </div>
        </article>
<?php endforeach; ?>
      </div>
