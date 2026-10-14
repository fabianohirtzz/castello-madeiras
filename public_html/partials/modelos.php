<?php
/**
 * Grade de modelos. Espera: string $modalidade ('pronta' ou 'flex').
 *
 * Cada card tem foto, ambientes com icone, preco "a partir de" e o botao
 * Ver mais, que abre o popup (#modelbox) com a galeria (foto, planta baixa e
 * foto extra) e a descricao. O conteudo do popup de cada modelo vai num
 * <template>, para as fotos grandes so baixarem quando alguem abrir.
 *
 * Na Castello Flex a grade vira carrossel (js/main.js, .mcarrossel): sao
 * cinco modelos e, com tres por linha, o quinto quebrava sozinho.
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/conteudo.php';

$modalidade    = $modalidade ?? 'pronta';
$lista_modelos = modelos($modalidade);
$eh_flex       = $modalidade === 'flex';
$ico_ver_mais  = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 8V5a1 1 0 0 1 1-1h3M16 4h3a1 1 0 0 1 1 1v3M20 16v3a1 1 0 0 1-1 1h-3M8 20H5a1 1 0 0 1-1-1v-3"/><circle cx="12" cy="12" r="3"/></svg>';
?>
<?php if ($eh_flex): ?>
<div class="mcarrossel" data-carrossel aria-roledescription="carrossel" aria-label="Modelos Castello Flex">
        <button class="mcarrossel__nav mcarrossel__nav--prev" type="button" data-carrossel-prev aria-label="Modelo anterior">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
        </button>
        <div class="mcarrossel__viewport">
<?php endif; ?>
      <div class="grid grid--models<?= $eh_flex ? ' mcarrossel__track' : '' ?>">
<?php foreach ($lista_modelos as $m):
    $destaque = (int) $m['destaque'] === 1;
    $preco    = trim((string) $m['preco']);
    $parede   = trim((string) $m['parede']);
    $prazo    = trim((string) $m['prazo']);
    // O eyebrow e o selo cadastrado; sem ele, o prazo na Flex e o lembrete de
    // que o projeto se ajusta na Casa Pronta.
    if ($parede !== '') {
        $eyebrow = $parede;
    } elseif ($eh_flex) {
        $eyebrow = $prazo !== '' ? 'Entrega em ' . $prazo : '';
    } else {
        $eyebrow = 'Projeto personalizável';
    }
    // O rotulo do botao usa a area sem os centavos zerados, como no site atual:
    // "39,00 m²" vira "39 m²", mas "42,75 m²" fica como esta.
    $area_curta = str_replace(',00 ', ' ', (string) $m['area']);
    $rotulo     = $m['nome'] . ' · ' . $area_curta . ' · ' . ($preco !== '' ? 'R$ ' . $preco : 'Castello Flex');
    $ambientes  = ambientes_modelo($m['itens'] ?? '');
    $galeria    = galeria_modelo($m);
    $descricao  = trim((string) $m['descricao']);
    $id_popup   = 'modelo-' . (int) $m['id'];
?>
        <article class="model<?= $destaque ? ' model--featured' : '' ?><?= $eh_flex ? '' : ' reveal' ?>">
<?php if ($destaque): ?>
          <span class="model__flag">Mais escolhida</span>
<?php endif; ?>
          <div class="model__media">
            <img src="<?= e($m['foto']) ?>" alt="<?= e($m['foto_alt']) ?>" loading="lazy" />
            <button type="button" class="model__ver" data-modelo-ver="<?= e($id_popup) ?>"><?= $ico_ver_mais ?>Ver mais<span class="sr-only"> sobre o <?= e($m['nome']) ?></span></button>
          </div>
          <div class="model__body">
<?php if ($eyebrow !== ''): ?>
            <span class="eyebrow"><?= e($eyebrow) ?></span>
<?php endif; ?>
            <h3 class="model__name"><?= e($m['nome']) ?></h3>
            <p class="model__area"><?= e($m['area']) ?> de área total</p>
<?php if ($ambientes !== []): ?>
            <ul class="model__amb">
<?php foreach ($ambientes as [$svg, $texto]): ?>
              <li><?= $svg ?><span><?= e($texto) ?></span></li>
<?php endforeach; ?>
            </ul>
<?php endif; ?>
<?php if ($preco !== ''): ?>
            <div class="price"><span class="price__label">A partir de</span><span class="price__val"><span class="price__cur">R$</span> <?= e($preco) ?></span></div>
<?php else: ?>
            <div class="price">
              <span class="price__label">Valor</span>
              <span class="price__val price__val--sob">Sob consulta</span>
            </div>
<?php endif; ?>
<?php if (!$eh_flex): ?>
            <p class="model__ref">Valor de referência para o projeto inicial.</p>
<?php endif; ?>
            <button type="button" class="btn btn--primary btn--block" data-quote-open data-modelo="<?= e($rotulo) ?>">Pedir orçamento</button>
          </div>

          <template id="<?= e($id_popup) ?>">
            <div class="modelbox__galeria" data-galeria>
              <div class="modelbox__palco">
<?php foreach ($galeria as $i => $foto): ?>
                <img class="modelbox__foto<?= $foto['planta'] ? ' modelbox__foto--planta' : '' ?><?= $i === 0 ? ' is-active' : '' ?>" src="<?= e($foto['src']) ?>" alt="<?= e($foto['alt']) ?>" />
<?php endforeach; ?>
<?php if (count($galeria) > 1): ?>
                <button class="modelbox__nav modelbox__nav--prev" type="button" data-galeria-prev aria-label="Foto anterior"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg></button>
                <button class="modelbox__nav modelbox__nav--next" type="button" data-galeria-next aria-label="Próxima foto"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg></button>
<?php endif; ?>
              </div>
<?php if (count($galeria) > 1): ?>
              <div class="modelbox__miniaturas">
<?php foreach ($galeria as $i => $foto): ?>
                <button type="button" class="modelbox__mini<?= $i === 0 ? ' is-active' : '' ?>" data-galeria-ir="<?= $i ?>" aria-label="<?= e($foto['planta'] ? 'Ver a planta baixa' : 'Ver a foto ' . ($i + 1)) ?>"><img src="<?= e($foto['src']) ?>" alt="" /></button>
<?php endforeach; ?>
              </div>
<?php endif; ?>
            </div>
            <div class="modelbox__info">
<?php if ($eyebrow !== ''): ?>
              <span class="eyebrow"><?= e($eyebrow) ?></span>
<?php endif; ?>
              <h2 class="modelbox__nome"><?= e($m['nome']) ?></h2>
              <p class="model__area"><?= e($m['area']) ?> de área total</p>
<?php if ($ambientes !== []): ?>
              <ul class="model__amb">
<?php foreach ($ambientes as [$svg, $texto]): ?>
                <li><?= $svg ?><span><?= e($texto) ?></span></li>
<?php endforeach; ?>
              </ul>
<?php endif; ?>
<?php foreach (preg_split('/\R{2,}/u', $descricao) ?: [] as $paragrafo): ?>
<?php if (trim($paragrafo) !== ''): ?>
              <p class="modelbox__texto"><?= nl2br(e(trim($paragrafo)), false) ?></p>
<?php endif; ?>
<?php endforeach; ?>
<?php if ($preco !== ''): ?>
              <div class="price"><span class="price__label">A partir de</span><span class="price__val"><span class="price__cur">R$</span> <?= e($preco) ?></span></div>
<?php endif; ?>
<?php if (!$eh_flex): ?>
              <p class="model__ref">Projeto personalizável. O valor é de referência para o projeto inicial e muda conforme as suas escolhas.</p>
<?php else: ?>
              <p class="model__ref">Planta padronizada, sem alterações. Estrutura montada e fechada no seu terreno em <?= e($prazo !== '' ? $prazo : '45 dias') ?>.</p>
<?php endif; ?>
              <button type="button" class="btn btn--primary btn--block" data-quote-open data-modelo="<?= e($rotulo) ?>">Pedir orçamento deste modelo</button>
            </div>
          </template>
        </article>
<?php endforeach; ?>
      </div>
<?php if ($eh_flex): ?>
        </div>
        <button class="mcarrossel__nav mcarrossel__nav--next" type="button" data-carrossel-next aria-label="Próximo modelo">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
        </button>
      </div>
<?php endif; ?>

      <!-- ============ VER MAIS DO MODELO (galeria + descrição) ============ -->
      <div class="modelbox" id="modelbox" hidden role="dialog" aria-modal="true" aria-label="Detalhes do modelo">
        <div class="modelbox__fundo" data-modelbox-fechar></div>
        <div class="modelbox__painel">
          <button class="modelbox__fechar" type="button" data-modelbox-fechar aria-label="Fechar">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
          </button>
          <div class="modelbox__conteudo" id="modelboxConteudo"></div>
        </div>
      </div>
