<?php
/**
 * Grade de modelos. Espera: string $modalidade ('pronta' ou 'flex').
 *
 * Casa Pronta imprime parede, preco e o selo "Chave na mao". Castelo Flex
 * imprime o prazo no lugar da parede, o selo "Semipronta" e, enquanto o
 * modelo nao tiver preco, "Sob consulta".
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/conteudo.php';

$modalidade    = $modalidade ?? 'pronta';
$lista_modelos = modelos($modalidade);
$selo_modelo   = $modalidade === 'flex' ? 'Semipronta' : 'Chave na mão';
?>
<div class="grid grid--models">
<?php foreach ($lista_modelos as $m):
    $destaque = (int) $m['destaque'] === 1;
    $preco    = trim((string) $m['preco']);
    $parede   = trim((string) $m['parede']);
    $prazo    = trim((string) $m['prazo']);
    // O eyebrow e a parede; sem parede cadastrada, e o prazo de entrega.
    $eyebrow  = $parede !== '' ? $parede : ($prazo !== '' ? 'Entrega em ' . $prazo : '');
    // O rotulo do botao usa a area sem os centavos zerados, como no site atual:
    // "39,00 m²" vira "39 m²", mas "42,75 m²" fica como esta.
    $area_curta = str_replace(',00 ', ' ', (string) $m['area']);
    $rotulo = $m['nome'] . ' · ' . $area_curta . ' · ' . ($preco !== '' ? 'R$ ' . $preco : 'semipronta');
?>
        <article class="model<?= $destaque ? ' model--featured' : '' ?> reveal">
<?php if ($destaque): ?>
          <span class="model__flag">Mais escolhida</span>
<?php endif; ?>
          <div class="model__media">
            <img src="<?= e($m['foto']) ?>" alt="<?= e($m['foto_alt']) ?>" loading="lazy" />
            <span class="model__badge"><?= e($selo_modelo) ?></span>
          </div>
          <div class="model__body">
<?php if ($eyebrow !== ''): ?>
            <span class="eyebrow"><?= e($eyebrow) ?></span>
<?php endif; ?>
            <h3 class="model__name"><?= e($m['nome']) ?></h3>
            <p class="model__area"><?= e($m['area']) ?> de área construída</p>
<?php if ($preco !== ''): ?>
            <div class="price"><span class="price__label">A partir de</span><span class="price__val"><span class="price__cur">R$</span> <?= e($preco) ?></span></div>
<?php else: ?>
            <div class="price">
              <span class="price__label">Valor</span>
              <span class="price__val price__val--sob">Sob consulta</span>
            </div>
<?php endif; ?>
            <button type="button" class="btn btn--primary btn--block" data-quote-open data-modelo="<?= e($rotulo) ?>">Pedir orçamento</button>
          </div>
        </article>
<?php endforeach; ?>
      </div>
