<?php
declare(strict_types=1);

/**
 * Pagina Castelo Flex. Esqueleto: nav, rodape e modal ja compartilhados com a
 * home, e as oito secoes aparecendo conforme o cliente preenche os textos e
 * cadastra modelo, passo a passo e FAQ da modalidade flex.
 *
 * Nao existe formulario embutido aqui: o site tem um formulario so, no modal,
 * aberto por data-quote-open. A costura da Fase 2 troca este miolo pela
 * marcacao de front/flex.html.
 */

require_once __DIR__ . '/lib/conteudo.php';

$flex_modelos = modelos('flex');
$flex_passos  = passos('flex');
$flex_faq     = faq('flex');
$flex_video   = bloco('flex_video');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <!-- PROTÓTIPO: noindex enquanto não for produção -->
  <meta name="robots" content="noindex, nofollow" />

  <title>Castelo Flex | Castello Casas de Madeira</title>
  <meta name="description" content="Castelo Flex: casas de madeira semiprontas da Castello, com estrutura montada em 45 dias." />

  <link rel="icon" type="image/png" href="images/icone-colorido.png" />

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet" />

  <link rel="stylesheet" href="css/style.css?v=12" />
</head>
<body>

<?php include __DIR__ . '/partials/nav.php'; ?>

  <main id="topo">

    <!-- 1. Topo da pagina Flex -->
    <section class="section section--sand">
      <div class="container">
        <div class="section__head">
          <span class="eyebrow reveal">Castelo Flex</span>
          <h1 class="section__title reveal"><?= e(bloco('flexpg_hero_titulo', 'Castelo Flex')) ?></h1>
<?php if (bloco('flexpg_hero_texto') !== ''): ?>
          <p class="section__lead reveal"><?= e(bloco('flexpg_hero_texto')) ?></p>
<?php endif; ?>
        </div>
      </div>
    </section>

    <!-- 2. O que e a Castelo Flex, com o video explicativo -->
    <section class="section" id="flex-oque">
      <div class="container">
<?php if (bloco('flexpg_oque_titulo') !== ''): ?>
        <div class="section__head">
          <h2 class="section__title reveal"><?= e(bloco('flexpg_oque_titulo')) ?></h2>
<?php if (bloco('flexpg_oque_texto') !== ''): ?>
          <p class="section__lead reveal"><?= e(bloco('flexpg_oque_texto')) ?></p>
<?php endif; ?>
        </div>
<?php endif; ?>

<?php if ($flex_video !== ''): ?>
        <video class="flex__video reveal" controls playsinline preload="none" poster="<?= e(bloco('flex_video_poster')) ?>">
          <source src="<?= e($flex_video) ?>" type="video/mp4" />
        </video>
<?php endif; ?>
      </div>
    </section>

    <!-- 3. Passo a passo: ate onde vai a entrega da Castello -->
<?php if ($flex_passos !== []): ?>
    <section class="section process" id="flex-passos">
      <div class="container">
        <div class="process__inner">
<?php $contexto = 'flex'; include __DIR__ . '/partials/passos.php'; ?>
        </div>
      </div>
    </section>
<?php endif; ?>

    <!-- 4. O que fica por conta do cliente -->
<?php if (bloco('flexpg_depois_titulo') !== '' || bloco('flexpg_depois_texto') !== ''): ?>
    <section class="section section--sand" id="flex-depois">
      <div class="container">
        <div class="section__head">
          <h2 class="section__title reveal"><?= e(bloco('flexpg_depois_titulo')) ?></h2>
<?php if (bloco('flexpg_depois_texto') !== ''): ?>
          <p class="section__lead reveal"><?= e(bloco('flexpg_depois_texto')) ?></p>
<?php endif; ?>
        </div>
      </div>
    </section>
<?php endif; ?>

    <!-- 5. Catalogo das casas Flex -->
<?php if ($flex_modelos !== []): ?>
    <section class="section modelos" id="flex-catalogo">
      <div class="container">
<?php $modalidade = 'flex'; include __DIR__ . '/partials/modelos.php'; ?>
<?php if (bloco('flexpg_catalogo_nota') !== ''): ?>
        <p class="modelos__note reveal"><?= e(bloco('flexpg_catalogo_nota')) ?></p>
<?php endif; ?>
      </div>
    </section>
<?php endif; ?>

    <!-- 6. Prazo da modalidade -->
    <section class="section" id="flex-prazo">
      <div class="container">
        <p class="section__lead reveal">Estrutura montada em <?= e(bloco('flex_prazo', '45 dias')) ?>.</p>
      </div>
    </section>

    <!-- 7. FAQ da Flex -->
<?php if ($flex_faq !== []): ?>
    <section class="section faq" id="flex-faq" aria-label="Perguntas frequentes da Castelo Flex">
      <div class="container">
<?php $contexto = 'flex'; include __DIR__ . '/partials/faq.php'; ?>
      </div>
    </section>
<?php endif; ?>

    <!-- 8. Faixa de orcamento. O formulario e o modal compartilhado. -->
    <section class="cta-band">
      <div class="container cta-band__inner">
        <div class="reveal">
          <h2><?= e(bloco('flexpg_cta_titulo', 'Quer um orçamento da Castelo Flex?')) ?></h2>
<?php if (bloco('flexpg_cta_texto') !== ''): ?>
          <p><?= e(bloco('flexpg_cta_texto')) ?></p>
<?php endif; ?>
        </div>
        <button type="button" class="btn btn--light btn--lg reveal" data-quote-open>Pedir orçamento</button>
      </div>
    </section>

  </main>

<?php include __DIR__ . '/partials/rodape.php'; ?>
<?php include __DIR__ . '/partials/modal.php'; ?>

  <script src="js/main.js?v=12"></script>
</body>
</html>
