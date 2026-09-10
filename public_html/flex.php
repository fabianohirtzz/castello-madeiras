<?php
declare(strict_types=1);

/**
 * Pagina Castelo Flex. Marcacao da fase 2 (frente 2) com o conteudo vindo do
 * banco: modelos, passos e FAQ de modalidade flex pelos partials, textos
 * pelos blocos flex_* e flexpg_*. O que o cliente ainda nao cadastrou some
 * sem quebrar a pagina.
 *
 * O formulario e o do modal compartilhado (partials/modal.php).
 */

require_once __DIR__ . '/lib/conteudo.php';

$prazo_flex   = bloco('flex_prazo', '45 dias');
$flex_video   = bloco('flex_video');
$flex_poster  = bloco('flex_video_poster');
$flex_modelos = modelos('flex');
$flex_passos  = passos('flex');
$flex_faq     = faq('flex');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Castelo Flex | Casa de madeira semipronta em 45 dias | Castello</title>
  <meta name="description" content="Castelo Flex é a casa de madeira semipronta da Castello: estrutura montada, coberta e fechada no seu terreno em 45 dias. Você faz o acabamento no seu ritmo." />

  <!-- Open Graph -->
  <meta property="og:type" content="website" />
  <meta property="og:title" content="Castelo Flex | Casa de madeira semipronta em 45 dias" />
  <meta property="og:description" content="Estrutura montada, coberta e fechada no seu terreno em 45 dias. O acabamento fica no seu ritmo." />
  <meta property="og:url" content="<?= CASTELLO_URL . '/flex.php' ?>" />
  <meta property="og:image" content="<?= CASTELLO_URL ?>/images/og-flex.jpg" />
  <meta property="og:image:width" content="1200" />
  <meta property="og:image:height" content="630" />
  <meta property="og:site_name" content="Castello Casas de Madeira" />
  <meta name="twitter:card" content="summary_large_image" />
  <meta property="og:locale" content="pt_BR" />

  <link rel="canonical" href="<?= CASTELLO_URL . '/flex.php' ?>" />
  <link rel="icon" type="image/png" href="images/icone-colorido.png" />

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet" />

  <link rel="stylesheet" href="css/style.css?v=16" />
</head>
<body>

<?php $pagina = 'flex'; include __DIR__ . '/partials/nav.php'; ?>

  <!-- ============ HERO DA CASTELO FLEX ============ -->
  <!-- Copy provisória: entra pelo painel quando o material da Flex chegar. -->
  <section class="pagehero" id="topo" aria-label="Castelo Flex">
    <div class="pagehero__media">
      <img src="fotos-casas/casa7.webp" alt="Sobrado de madeira Castello com sacada e fachada de réguas" fetchpriority="high" />
    </div>
    <div class="pagehero__overlay" aria-hidden="true"></div>

    <div class="container">
      <span class="eyebrow eyebrow--light reveal">Lançamento Castello · casas semiprontas</span>
      <h1 class="pagehero__title reveal"><?= realce(bloco('flexpg_hero_titulo', 'A casa de madeira *montada e fechada* no seu terreno em 45 dias.')) ?></h1>
<?php if (bloco('flexpg_hero_texto') !== ''): ?>
      <p class="pagehero__lead reveal"><?= e(bloco('flexpg_hero_texto')) ?></p>
<?php endif; ?>
      <p class="stamp stamp--light reveal">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v4l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
        Entrega em <strong><?= e($prazo_flex) ?></strong>
      </p>
      <div class="pagehero__actions reveal">
        <button type="button" class="btn btn--primary btn--lg" data-quote-open>
          <svg viewBox="0 0 24 24" class="ico-quote" aria-hidden="true"><path d="M4 4h16a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H8.6L4 20.5V5a1 1 0 0 1 1-1Zm3 5h10v1.7H7V9Zm0 3.6h6.6v1.7H7v-1.7Z"/></svg>
          Pedir orçamento da Flex
        </button>
        <a href="#modelos-flex" class="btn btn--ghost-light btn--lg">Ver as casas Flex</a>
      </div>
    </div>
  </section>

  <!-- faceta: telhado recorta a saída do hero -->
  <div class="facet facet--bone" aria-hidden="true"></div>

  <!-- ============ O QUE É A CASTELO FLEX ============ -->
  <!-- Copy e vídeo provisórios: entram pelo painel nas chaves flexpg_oque_* e flex_video de `blocos`. -->
  <section class="section section--facetada" id="o-que-e">
    <div class="container">
      <div class="split2">
        <div>
          <span class="eyebrow reveal">O que é a Castelo Flex</span>
          <h2 class="section__title reveal"><?= e(bloco('flexpg_oque_titulo', 'A estrutura pronta. O acabamento no seu tempo.')) ?></h2>
<?php if (bloco('flexpg_oque_texto') !== ''): ?>
          <p class="section__lead reveal"><?= e(bloco('flexpg_oque_texto')) ?></p>
<?php endif; ?>
          <ul class="checklist reveal">
            <li>A mesma madeira e o mesmo prego galvanizado das casas chave na mão</li>
            <li>Montagem pela equipe que constrói casas Castello há 12 anos</li>
            <li>Planta ajustada ao seu terreno antes da produção</li>
            <li>Você paga o acabamento por etapa, no ritmo do seu bolso</li>
          </ul>
        </div>

<?php if ($flex_video !== ''): ?>
        <figure class="vexp vexp--reel reveal">
          <div class="vexp__frame">
            <video controls playsinline preload="none" poster="<?= e($flex_poster) ?>">
              <source src="<?= e($flex_video) ?>" type="video/mp4" />
              Seu navegador não abre vídeo. <a href="<?= e($flex_video) ?>">Baixe o vídeo da Castelo Flex</a>.
            </video>
          </div>
          <figcaption class="vexp__cap">A Castello explica a Castelo Flex, do terreno à casa fechada.</figcaption>
        </figure>
<?php endif; ?>
      </div>
    </div>
  </section>

  <!-- faceta: transição para o passo a passo -->
  <div class="facet facet--sand" aria-hidden="true"></div>

  <!-- ============ PASSO A PASSO DA FLEX (com a fronteira de entrega) ============ -->
  <!-- Passos provisórios: migram para a tabela `passos` com contexto flex. -->
  <section class="section section--sand section--facetada" id="passos-flex">
    <div class="container">
      <div class="section__head">
        <span class="eyebrow reveal">Passo a passo</span>
        <h2 class="section__title reveal">O que a Castello entrega,<br>e onde a nossa entrega termina.</h2>
        <p class="section__lead reveal">Sem letra miúda. Estas são as cinco etapas que a Castello executa na Castelo Flex, e logo abaixo está a lista do que fica por sua conta depois que a gente sai do terreno.</p>
      </div>

<?php if ($flex_passos !== []): ?>
<?php $contexto = 'flex'; include __DIR__ . '/partials/passos.php'; ?>
<?php endif; ?>

      <div class="limite reveal">
        <span class="limite__txt">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v18M5 8h14M5 16h14"/></svg>
          A entrega da Castello termina aqui
        </span>
      </div>

      <div class="depois__head reveal">
        <h3 class="depois__title"><?= e(bloco('flexpg_depois_titulo', 'O que fica por sua conta')) ?></h3>
<?php if (bloco('flexpg_depois_texto') !== ''): ?>
        <p class="depois__text"><?= e(bloco('flexpg_depois_texto')) ?></p>
<?php endif; ?>
      </div>

      <div class="depois__grid">
        <div class="depois__item reveal"><strong>Elétrica e hidráulica</strong>Fiação, pontos, tubulação e louças.</div>
        <div class="depois__item reveal"><strong>Revestimentos e piso</strong>Cerâmica, forro, banheiro e cozinha.</div>
        <div class="depois__item reveal"><strong>Pintura e acabamentos</strong>Interna, externa e os detalhes finos.</div>
        <div class="depois__item reveal"><strong>Fossa e sumidouro</strong>Ligação de esgoto e de água no terreno.</div>
      </div>

      <p class="depois__nota reveal">Quer tudo isso incluso e a chave na mão no fim? Então a sua modalidade é a
        <a href="casa-pronta.php">Casa Pronta, completa em <?= e(bloco('pronta_prazo', '90 a 120 dias')) ?></a>.
      </p>
    </div>
  </section>

  <!-- faceta: transição para o catálogo Flex -->
  <div class="facet facet--bone" aria-hidden="true"></div>

  <!-- ============ CATÁLOGO DAS CASAS FLEX ============ -->
  <!-- Modelos provisórios: nome, área e preço entram pelo painel em `modelos` modalidade flex. -->
  <section class="section section--facetada" id="modelos-flex">
    <div class="container">
      <div class="section__head">
        <span class="eyebrow reveal">Casas Castelo Flex</span>
        <h2 class="section__title reveal">Três tamanhos<br>para começar.</h2>
        <p class="section__lead reveal">Cada modelo Flex sai da fábrica com a estrutura completa e é montado no seu terreno em <?= e($prazo_flex) ?>. A planta é ajustada antes da produção.</p>
      </div>

<?php if ($flex_modelos !== []): ?>
<?php $modalidade = 'flex'; include __DIR__ . '/partials/modelos.php'; ?>
<?php endif; ?>

<?php if (bloco('flexpg_catalogo_nota') !== ''): ?>
      <p class="modelos__note reveal"><?= e(bloco('flexpg_catalogo_nota')) ?></p>
<?php endif; ?>
    </div>
  </section>

  <!-- faceta: transição escura para os diferenciais -->
  <div class="facet facet--ink" aria-hidden="true"></div>

  <!-- ============ PRAZO E DIFERENCIAIS DA FLEX ============ -->
  <section class="section section--dark section--facetada" id="diferenciais">
    <div class="container">
      <div class="section__head">
        <span class="eyebrow eyebrow--light reveal">Por que a Flex</span>
        <h2 class="section__title reveal">Quarenta e cinco dias<br>e a casa é sua.</h2>
        <p class="section__lead reveal">A Castelo Flex encurta a parte difícil da obra. O que costuma levar meses de canteiro sai em 45 dias, com a estrutura fechada e a casa protegida.</p>
        <p class="stamp stamp--light reveal">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v4l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
          Entrega em <strong><?= e($prazo_flex) ?></strong>
        </p>
      </div>

      <div class="bene__grid">
        <article class="bene reveal">
          <span class="bene__ico"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v4l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg></span>
          <h3>45 dias de obra</h3>
          <p>Da assinatura à casa fechada no seu terreno, com data combinada em contrato.</p>
        </article>
        <article class="bene reveal">
          <span class="bene__ico"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 8h15a3 3 0 0 1 3 3v6a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V8Zm0 0a3 3 0 0 1 3-3h9"/><circle cx="17" cy="14" r="1.4" fill="currentColor" stroke="none"/></svg></span>
          <h3>Você paga por etapa</h3>
          <p>O acabamento vem depois, no seu ritmo. Sem obra inteira travando o seu orçamento.</p>
        </article>
        <article class="bene reveal">
          <span class="bene__ico"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 8 4v6c0 4.4-3.4 7.4-8 8-4.6-.6-8-3.6-8-8V7l8-4Z"/><path d="m9 12 2 2 4-4"/></svg></span>
          <h3>Mesma madeira, mesma equipe</h3>
          <p>Prego galvanizado e madeira de qualidade, montados por quem constrói Castello há 12 anos.</p>
        </article>
        <article class="bene reveal">
          <span class="bene__ico"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 21h18M5 21V10l7-5 7 5v11M9 21v-6h6v6"/></svg></span>
          <h3>Pode virar renda</h3>
          <p>Muita gente fecha a Flex para aluguel, hospedagem ou revenda, e termina o acabamento depois.</p>
        </article>
      </div>
    </div>
  </section>

  <!-- faceta: transição para as perguntas -->
  <div class="facet facet--sand" aria-hidden="true"></div>

  <!-- ============ FAQ DA CASTELO FLEX ============ -->
  <!-- Perguntas provisórias: migram para `faq` com contexto flex. Ícones do conjunto fechado. -->
  <section class="section faq" id="faq" aria-label="Perguntas frequentes sobre a Castelo Flex">
    <div class="container">
      <div class="section__head faq__head">
        <span class="eyebrow reveal">Perguntas frequentes</span>
        <h2 class="section__title reveal">O que todo mundo pergunta<br>antes de fechar a Flex.</h2>
        <p class="section__lead reveal">Reunimos as dúvidas que mais aparecem sobre a modalidade semipronta. Ficou com outra pergunta? É só chamar a gente.</p>
      </div>

<?php if ($flex_faq !== []): ?>
<?php $contexto = 'flex'; include __DIR__ . '/partials/faq.php'; ?>
<?php endif; ?>

      <p class="faq__foot reveal">A Castello fica em Tubarão e atende toda a região, com casas já entregues em diversas cidades de Santa Catarina.
        <button type="button" class="faq__foot-link" data-quote-open>Tirar uma dúvida sobre a Flex</button>
      </p>
    </div>
  </section>

  <!-- ============ CTA BAND / FORMULÁRIO DA FLEX ============ -->
  <section class="cta-band" id="orcamento">
    <div class="container cta-band__inner">
      <div class="reveal">
        <h2><?= e(bloco('flexpg_cta_titulo', 'Quer a Castelo Flex no seu terreno?')) ?></h2>
<?php if (bloco('flexpg_cta_texto') !== ''): ?>
        <p><?= e(bloco('flexpg_cta_texto')) ?></p>
<?php endif; ?>
      </div>
      <button type="button" class="btn btn--light btn--lg reveal" data-quote-open>
        <svg viewBox="0 0 24 24" class="ico-quote" aria-hidden="true"><path d="M4 4h16a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H8.6L4 20.5V5a1 1 0 0 1 1-1Zm3 5h10v1.7H7V9Zm0 3.6h6.6v1.7H7v-1.7Z"/></svg>
        Pedir orçamento da Flex
      </button>
    </div>
  </section>

<?php include __DIR__ . '/partials/rodape.php'; ?>

<?php include __DIR__ . '/partials/modal.php'; ?>

  <script src="js/main.js?v=14"></script>
  <script src="js/formulario.js?v=2" defer></script>
</body>
</html>
