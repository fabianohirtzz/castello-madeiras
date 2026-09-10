<?php
declare(strict_types=1);

/**
 * Home. Marcacao da fase 2 (frente 2) com o conteudo vindo do banco: as
 * listas pelos partials, os textos editaveis pelos blocos.
 */

require_once __DIR__ . '/lib/conteudo.php';

$prazo_pronta = bloco('pronta_prazo', '90 a 120 dias');
$prazo_flex   = bloco('flex_prazo', '45 dias');
$flex_video   = bloco('flex_video');
$flex_poster  = bloco('flex_video_poster');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Castello Casas de Madeira | Casa Pronta e Castelo Flex em Tubarão SC</title>
  <meta name="description" content="Casas de madeira em Tubarão e região. Casa Pronta chave na mão em 90 a 120 dias e Castelo Flex semipronta em 45 dias. 5,0 estrelas no Google, 56 avaliações." />

  <!-- Open Graph -->
  <meta property="og:type" content="website" />
  <meta property="og:title" content="Castello Casas de Madeira | Casa Pronta e Castelo Flex" />
  <meta property="og:description" content="Casa Pronta chave na mão em 90 a 120 dias e Castelo Flex semipronta em 45 dias. 5,0 estrelas no Google." />
  <meta property="og:url" content="<?= CASTELLO_URL . '/' ?>" />
  <meta property="og:image" content="<?= CASTELLO_URL ?>/images/og-home.jpg" />
  <meta property="og:image:width" content="1200" />
  <meta property="og:image:height" content="630" />
  <meta property="og:site_name" content="Castello Casas de Madeira" />
  <meta name="twitter:card" content="summary_large_image" />
  <meta property="og:locale" content="pt_BR" />

  <link rel="canonical" href="<?= CASTELLO_URL . '/' ?>" />
  <link rel="icon" type="image/png" href="images/icone-colorido.png" />

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet" />

  <link rel="stylesheet" href="css/style.css?v=14" />
</head>
<body>

<?php $pagina = 'home'; include __DIR__ . '/partials/nav.php'; ?>

  <!-- ============ HERO (cinematográfico — scrub de vídeo no scroll) ============ -->
  <section class="hero" id="topo" aria-label="Castello Casas de Madeira">
    <div class="hero__track" id="heroTrack">
      <div class="hero__pin">
        <div class="hero__media" id="heroMedia">
          <video id="heroVideo" class="hero__video" muted playsinline preload="metadata" disableremoteplayback poster="video-hero/poster.jpg" aria-hidden="true">
            <source src="video-hero/video-hero.mp4?v=3" data-src-mobile="video-hero/video-mobile.mp4?v=2" type="video/mp4" />
          </video>
        </div>
        <div class="hero__overlay" aria-hidden="true"></div>

        <!-- Beat 1 — 0 a ~3s -->
        <div class="hero__beat hero__beat--one" id="heroBeatOne">
          <span class="eyebrow eyebrow--light">Casas de madeira chave na mão · Tubarão SC</span>
          <h1 class="hero__headline"><?= e(bloco('hero_titulo', 'A casa dos seus sonhos')) ?></h1>
        </div>

        <!-- Beat 2 — ~3s ao fim -->
        <div class="hero__beat hero__beat--two" id="heroBeatTwo">
          <p class="hero__headline-sub"><?= realce(bloco('hero_subtitulo', 'pronta pra morar, *chave na mão*')) ?></p>
          <div class="hero__actions">
            <button type="button" class="btn btn--primary btn--lg" data-quote-open>
              <svg viewBox="0 0 24 24" class="ico-quote" aria-hidden="true"><path d="M4 4h16a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H8.6L4 20.5V5a1 1 0 0 1 1-1Zm3 5h10v1.7H7V9Zm0 3.6h6.6v1.7H7v-1.7Z"/></svg>
              Pedir orçamento
            </button>
            <a href="#modalidades" class="btn btn--ghost-light btn--lg">Ver as duas modalidades</a>
          </div>
        </div>

        <!-- Indicador de etapa -->
        <div class="hero__status" aria-hidden="true">
          <span class="hero__status-label">Etapa</span>
          <span class="hero__status-current" id="heroStatusCurrent">01</span>
          <span class="hero__status-track"><span class="hero__status-fill" id="heroStatusFill"></span></span>
          <span class="hero__status-total">02</span>
        </div>

        <!-- Hint de scroll (primeiro frame) -->
        <div class="hero__hint" id="heroHint" aria-hidden="true">
          <span>Role para ver</span>
          <span class="hero__hint-arrow">↓</span>
        </div>
      </div>
    </div>
  </section>

  <!-- faceta: telhado recorta a transição do hero para a faixa vermelha -->
  <div class="facet facet--red" aria-hidden="true"></div>

  <!-- ============ PROVA SOCIAL RÁPIDA (movida do hero) ============ -->
  <section class="proof" id="prova" aria-label="Prova social">
    <div class="container proof__wrap">
      <p class="proof__lead reveal">Projeto exclusivo, montagem completa e acabamento fino. Você sai do aluguel e entra direto no seu lar, sem dor de cabeça.</p>
      <div class="proof__inner">
        <div class="proof__item reveal">
          <div class="proof__num"><span data-count="5.0">0</span> <span class="star">★</span></div>
          <div class="proof__label">no Google</div>
        </div>
        <div class="proof__item reveal">
          <div class="proof__num" data-count="56">0</div>
          <div class="proof__label">avaliações</div>
        </div>
        <div class="proof__item reveal">
          <div class="proof__num" data-count="12" data-suffix=" anos">0</div>
          <div class="proof__label">de mercado</div>
        </div>
        <div class="proof__item reveal">
          <div class="proof__num" data-count="18000" data-prefix="+" data-suffix=" m²">0</div>
          <div class="proof__label">construídos</div>
        </div>
      </div>
    </div>
  </section>

  <!-- faceta: telhado recorta a saída da faixa vermelha -->
  <div class="facet facet--bone" aria-hidden="true"></div>

  <!-- ============ MODALIDADES (Casa Pronta x Castelo Flex) ============ -->
  <!-- Copy provisória da Flex: sai do material do Instagram até o cliente enviar o definitivo. -->
  <section class="section modalidades section--facetada" id="modalidades">
    <div class="container">
      <div class="modalidades__head">
        <span class="eyebrow reveal">Duas formas de construir</span>
        <h2 class="section__title reveal"><?= e(bloco('modalidades_titulo', 'Escolha como a sua casa sai do papel.')) ?></h2>
        <p class="section__lead reveal"><?= e(bloco('modalidades_texto')) ?></p>
      </div>

      <div class="modalidades__grid">
        <article class="modalidade reveal">
          <span class="modalidade__tag">Chave na mão</span>
          <h3 class="modalidade__name">Casa Pronta</h3>
          <p class="modalidade__prazo">Pronta em <strong><?= e($prazo_pronta) ?></strong></p>
          <p class="modalidade__text">A Castello faz tudo: projeto, fundação, estrutura, elétrica, hidráulica, revestimento e acabamento. Você recebe a chave e entra pra morar.</p>
          <ul class="checklist">
            <li>Projeto exclusivo, planta do seu jeito</li>
            <li>Obra completa do primeiro ao último dia</li>
            <li>Quatro modelos com preço de referência</li>
          </ul>
          <a href="#casa-pronta" class="btn btn--primary">Ver modelos e preços</a>
        </article>

        <article class="modalidade modalidade--flex reveal">
          <span class="modalidade__tag">Lançamento</span>
          <h3 class="modalidade__name">Castelo Flex</h3>
          <p class="modalidade__prazo">No seu terreno em <strong><?= e($prazo_flex) ?></strong></p>
          <p class="modalidade__text">A casa semipronta da Castello. Entregamos a estrutura de madeira montada, coberta e fechada no seu terreno, e você conduz o acabamento no seu tempo.</p>
          <ul class="checklist checklist--light">
            <li>Estrutura montada, coberta e fechada</li>
            <li>Entrega em <?= e($prazo_flex) ?></li>
            <li>Acabamento no seu ritmo e no seu orçamento</li>
          </ul>
          <a href="flex.php" class="btn btn--primary">Conhecer a Castelo Flex</a>
        </article>
      </div>
    </div>
  </section>

  <!-- faceta: transição para a Casa Pronta -->
  <div class="facet facet--sand" aria-hidden="true"></div>

  <!-- ============ MODELOS ============ -->
  <section class="section section--sand section--facetada modelos" id="casa-pronta">
    <div class="container">
      <div class="section__head">
        <span class="eyebrow reveal">Casa Pronta · chave na mão</span>
        <h2 class="section__title reveal"><?= e(bloco('pronta_titulo', 'Escolha o tamanho. A gente entrega completa.')) ?></h2>
        <p class="section__lead reveal"><?= e(bloco('pronta_texto')) ?></p>
        <p class="stamp reveal">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v4l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
          Pronta pra morar em <strong><?= e($prazo_pronta) ?></strong>
        </p>
      </div>

<?php $modalidade = 'pronta'; include __DIR__ . '/partials/modelos.php'; ?>

      <p class="modelos__note reveal">Valores de referência para casa completa. O orçamento final varia conforme a personalização, o terreno e a região. Fale com a gente e receba uma proposta sob medida.</p>
    </div>
  </section>

  <!-- faceta: transição escura para a Castelo Flex -->
  <div class="facet facet--ink" aria-hidden="true"></div>

  <!-- ============ CASTELO FLEX (resumo + vídeo explicativo) ============ -->
  <!-- Copy e vídeo provisórios: entram pelo painel nas chaves flex_* de `blocos`. -->
  <section class="section section--dark section--facetada flexhome" id="castelo-flex">
    <div class="container">
      <div class="split2">
        <div class="flexhome__text">
          <span class="eyebrow eyebrow--light reveal">Lançamento Castello</span>
          <h2 class="flexhome__title reveal"><?= e(bloco('flex_titulo', 'Castelo Flex: a casa semipronta no seu terreno em 45 dias.')) ?></h2>
          <p class="flexhome__lead reveal"><?= e(bloco('flex_texto')) ?></p>

          <div class="flexhome__facts reveal">
            <div class="flexhome__fact"><strong><?= e($prazo_flex) ?></strong><span>da assinatura à entrega</span></div>
            <div class="flexhome__fact"><strong>Casa fechada</strong><span>coberta, com portas e janelas</span></div>
            <div class="flexhome__fact"><strong>Você termina</strong><span>acabamento no seu tempo</span></div>
          </div>

          <div class="flexhome__actions reveal">
            <a href="flex.php" class="btn btn--primary btn--lg">Conhecer a Castelo Flex</a>
            <button type="button" class="btn btn--ghost-light btn--lg" data-quote-open>Pedir orçamento</button>
          </div>
        </div>

<?php if ($flex_video !== ''): ?>
        <figure class="vexp vexp--reel reveal">
          <div class="vexp__frame">
            <video controls playsinline preload="none" poster="<?= e($flex_poster) ?>">
              <source src="<?= e($flex_video) ?>" type="video/mp4" />
              Seu navegador não abre vídeo. <a href="<?= e($flex_video) ?>">Baixe o vídeo da Castelo Flex</a>.
            </video>
          </div>
          <figcaption class="vexp__cap vexp__cap--light">A Castello mostra como funciona a Castelo Flex, do terreno à casa fechada.</figcaption>
        </figure>
<?php endif; ?>
      </div>
    </div>
  </section>

  <!-- faceta: volta ao canvas claro nas vantagens -->
  <div class="facet facet--bone" aria-hidden="true"></div>

  <!-- ============ POR QUE MADEIRA (editorial assimétrico) ============ -->
  <section class="section vantagens" id="vantagens">
    <div class="container">
      <div class="why__layout">
        <div class="why__intro">
          <span class="eyebrow reveal">Por que casa de madeira</span>
          <h2 class="section__title reveal">Mais rápido de construir.<br>Melhor de morar.</h2>
          <p class="why__lead reveal">A casa de madeira une obra ágil, conforto de verdade e um patrimônio que valoriza com o tempo. Veja o que muda quando você constrói com a Castello.</p>
          <button type="button" class="btn btn--primary reveal" data-quote-open>Pedir orçamento</button>
        </div>

        <div class="why__carousel reveal" id="whyCarousel" aria-roledescription="carrossel" aria-label="Vantagens da casa de madeira">
          <div class="why__viewport">
            <article class="why__slide is-active" role="group" aria-roledescription="slide" aria-label="1 de 6">
              <span class="why__card-ico"><svg viewBox="0 0 24 24"><path d="M12 8v4l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg></span>
              <h3>Pronta em 90 a 120 dias</h3>
              <p>Enquanto a obra convencional leva anos, sua casa Castello fica pronta em poucos meses, organizada do início ao fim.</p>
            </article>
            <article class="why__slide" role="group" aria-roledescription="slide" aria-label="2 de 6">
              <span class="why__card-ico"><svg viewBox="0 0 24 24"><path d="m12 3 8 4v6c0 4.4-3.4 7.4-8 8-4.6-.6-8-3.6-8-8V7l8-4Z"/><path d="m9 12 2 2 4-4"/></svg></span>
              <h3>100% personalizável</h3>
              <p>Planta, acabamentos, revestimentos, janelas, portas e piso escolhidos do seu jeito, do seu gosto e da sua rotina.</p>
            </article>
            <article class="why__slide" role="group" aria-roledescription="slide" aria-label="3 de 6">
              <span class="why__card-ico"><svg viewBox="0 0 24 24"><path d="M12 3v2M12 19v2M5 12H3M21 12h-2M6.3 6.3 4.9 4.9M19.1 19.1l-1.4-1.4M17.7 6.3l1.4-1.4M4.9 19.1l1.4-1.4M16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z"/></svg></span>
              <h3>Conforto térmico</h3>
              <p>A madeira mantém o ambiente fresco no calor e aconchegante no frio, com bem-estar em todas as estações do ano.</p>
            </article>
            <article class="why__slide" role="group" aria-roledescription="slide" aria-label="4 de 6">
              <span class="why__card-ico"><svg viewBox="0 0 24 24"><path d="M3 21h18M5 21V10l7-5 7 5v11M9 21v-6h6v6"/></svg></span>
              <h3>Patrimônio pra vida toda</h3>
              <p>Bem construída, valoriza, atravessa gerações e ainda vira fonte de renda em aluguel, revenda ou hospedagem.</p>
            </article>
            <article class="why__slide" role="group" aria-roledescription="slide" aria-label="5 de 6">
              <span class="why__card-ico"><svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg></span>
              <h3>Zero dor de cabeça</h3>
              <p>A Castello cuida de projeto, materiais, prazos e acabamento. Você acompanha tudo e no fim só pega a chave.</p>
            </article>
            <article class="why__slide" role="group" aria-roledescription="slide" aria-label="6 de 6">
              <span class="why__card-ico"><svg viewBox="0 0 24 24"><path d="m14 7 3 3M5 19l1.5-5.5L16 4l4 4-9.5 9.5L5 19Z"/></svg></span>
              <h3>Estrutura que dura</h3>
              <p>Prego galvanizado e madeira de qualidade em todas as construções, com equipe experiente na obra todo dia.</p>
            </article>
          </div>

          <button class="why__nav why__nav--prev" id="whyPrev" type="button" aria-label="Vantagem anterior">
            <svg viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
          </button>
          <button class="why__nav why__nav--next" id="whyNext" type="button" aria-label="Próxima vantagem">
            <svg viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
          </button>

          <div class="why__dots" id="whyDots" role="tablist" aria-label="Selecionar vantagem"></div>
        </div>
      </div>
    </div>
  </section>

  <!-- ============ PORTFÓLIO (galeria editorial assimétrica) ============ -->
  <section class="section portfolio" id="portfolio">
    <div class="container">
      <div class="section__head">
        <span class="eyebrow reveal">Casas reais entregues</span>
        <h2 class="section__title reveal">Projetos que já viraram&nbsp;lar.</h2>
        <p class="section__lead reveal">Cada casa abaixo foi projetada, construída e entregue pela Castello para famílias de Santa Catarina e região.</p>
      </div>

<?php include __DIR__ . '/partials/portfolio.php'; ?>
    </div>
  </section>

  <!-- faceta: transição escura para o scrollytelling -->
  <div class="facet facet--ink" aria-hidden="true"></div>

  <!-- ============ DO TERRENO À CHAVE (scrollytelling sticky) ============ -->
  <section class="section process" id="como-funciona">
    <div class="container">
      <div class="process__head">
        <span class="eyebrow eyebrow--light reveal">Do terreno à chave</span>
        <h2 class="section__title reveal">Cinco passos. Zero dor de cabeça.</h2>
        <p class="section__lead reveal">Você conta o sonho, a Castello cuida de cada etapa. Acompanhe a obra do projeto até o dia de receber a chave.</p>
      </div>

      <div class="process__inner">
<?php $contexto = 'pronta'; include __DIR__ . '/partials/passos.php'; ?>
      </div>
    </div>
  </section>

  <!-- ============ DEPOIMENTOS (carrossel) ============ -->
  <section class="section section--sand reviews" id="depoimentos">
    <!-- Fundo floresta com parallax (decorativo). Conteúdo da seção fica acima (z-index). -->
    <div class="reviews__scene" aria-hidden="true">
      <div class="sun"></div>
      <div class="cloud cloud--a"></div>
      <div class="cloud cloud--b"></div>

      <!-- massa de mata distante (vermelho claro, enevoado) -->
      <div class="tline tline--1">
        <svg viewBox="0 0 1200 400" preserveAspectRatio="none"><path d="M0,400L0,250Q50,205,100,250Q150,200,200,250Q250,210,300,250Q350,200,400,250Q450,208,500,250Q550,200,600,250Q650,210,700,250Q750,202,800,250Q850,208,900,250Q950,200,1000,250Q1050,210,1100,250Q1150,203,1200,250L1200,400Z"/></svg>
        <svg viewBox="0 0 1200 400" preserveAspectRatio="none"><path d="M0,400L0,250Q50,205,100,250Q150,200,200,250Q250,210,300,250Q350,200,400,250Q450,208,500,250Q550,200,600,250Q650,210,700,250Q750,202,800,250Q850,208,900,250Q950,200,1000,250Q1050,210,1100,250Q1150,203,1200,250L1200,400Z"/></svg>
      </div>
      <!-- coníferas escalonadas (preenchidas via JS, claro -> escuro) -->
      <div class="tline tline--2"><svg viewBox="0 0 1200 400" preserveAspectRatio="none"><path d=""/></svg><svg viewBox="0 0 1200 400" preserveAspectRatio="none"><path d=""/></svg></div>
      <div class="tline tline--3"><svg viewBox="0 0 1200 400" preserveAspectRatio="none"><path d=""/></svg><svg viewBox="0 0 1200 400" preserveAspectRatio="none"><path d=""/></svg></div>
      <div class="tline tline--4"><svg viewBox="0 0 1200 400" preserveAspectRatio="none"><path d=""/></svg><svg viewBox="0 0 1200 400" preserveAspectRatio="none"><path d=""/></svg></div>

      <!-- gramado -->
      <div class="ground">
        <svg viewBox="0 0 1200 200" preserveAspectRatio="none">
          <defs>
            <linearGradient id="rvGrass" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0" stop-color="var(--grass-top)"/>
              <stop offset="1" stop-color="var(--grass-bottom)"/>
            </linearGradient>
          </defs>
          <path class="g-fringe" d="M0,200L0,46Q150,24,300,44Q450,62,600,40Q750,20,900,44Q1050,62,1200,42L1200,200Z"/>
          <path class="g-fill"   d="M0,200L0,60Q150,40,300,58Q450,74,600,54Q750,36,900,58Q1050,74,1200,56L1200,200Z"/>
        </svg>
      </div>
    </div>

    <div class="container">
      <div class="reviews__head">
        <div class="reviews__score reveal">
          <svg class="g-logo reviews__g" aria-hidden="true"><use href="#ico-google" /></svg>
          <span class="reviews__num">5,0</span>
          <span class="reviews__stars">★★★★★</span>
          <span class="reviews__meta">56 avaliações no Google</span>
        </div>
        <div class="reveal">
          <span class="eyebrow">Quem já construiu</span>
          <h2 class="section__title">A confiança de quem já tem a chave na mão.</h2>
          <p class="section__lead">Avaliações reais de clientes Castello no Google Meu Negócio. Arraste para o lado e leia mais.</p>
        </div>
      </div>
    </div>

    <!-- Avaliações reais do Google Meu Negócio (5,0 estrelas). -->
    <div class="reviews__carousel reveal" aria-roledescription="carrossel" aria-label="Avaliações de clientes no Google">
      <button class="reviews__arrow reviews__arrow--prev" id="revPrev" type="button" aria-label="Avaliação anterior">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
      </button>

<?php include __DIR__ . '/partials/avaliacoes.php'; ?>

      <button class="reviews__arrow reviews__arrow--next" id="revNext" type="button" aria-label="Próxima avaliação">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
      </button>
    </div>
  </section>

  <!-- faceta: transição escura para o Instagram -->
  <div class="facet facet--ink" aria-hidden="true"></div>

  <!-- ============ NOSSO INSTAGRAM (galeria de vídeos) ============ -->
  <section class="section insta" id="instagram" aria-label="Nosso Instagram">
    <div class="insta__grid-bg" aria-hidden="true"></div>
    <div class="container">
      <div class="section__head insta__head">
        <span class="eyebrow eyebrow--light reveal">Nosso Instagram</span>
        <h2 class="section__title reveal">Acompanhe a obra<br>por dentro.</h2>
        <p class="section__lead reveal">Bastidores de construção, casas prontas e a rotina de quem realiza o sonho da casa de madeira. Toque para assistir, com som.</p>
        <a href="https://www.instagram.com/castellocasasdemadeira/" class="btn btn--ig reveal" target="_blank" rel="noopener">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.2c3.2 0 3.6 0 4.85.07 1.17.05 1.8.25 2.23.41.56.22.96.48 1.38.9.42.42.68.82.9 1.38.16.42.36 1.06.41 2.23.06 1.27.07 1.65.07 4.85s0 3.58-.07 4.85c-.05 1.17-.25 1.8-.41 2.23-.22.56-.48.96-.9 1.38-.42.42-.82.68-1.38.9-.42.16-1.06.36-2.23.41-1.27.06-1.65.07-4.85.07s-3.58 0-4.85-.07c-1.17-.05-1.8-.25-2.23-.41a3.7 3.7 0 0 1-1.38-.9 3.7 3.7 0 0 1-.9-1.38c-.16-.42-.36-1.06-.41-2.23C2.21 15.58 2.2 15.2 2.2 12s0-3.58.07-4.85c.05-1.17.25-1.8.41-2.23.22-.56.48-.96.9-1.38.42-.42.82-.68 1.38-.9.42-.16 1.06-.36 2.23-.41C8.42 2.21 8.8 2.2 12 2.2Zm0 1.8c-3.15 0-3.5 0-4.74.07-.9.04-1.39.2-1.72.32-.43.17-.74.37-1.06.69-.32.32-.52.63-.69 1.06-.13.33-.28.82-.32 1.72C3.4 8.5 3.4 8.85 3.4 12s0 3.5.07 4.74c.04.9.2 1.39.32 1.72.17.43.37.74.69 1.06.32.32.63.52 1.06.69.33.13.82.28 1.72.32 1.24.07 1.59.07 4.74.07s3.5 0 4.74-.07c.9-.04 1.39-.2 1.72-.32.43-.17.74-.37 1.06-.69.32-.32.52-.63.69-1.06.13-.33.28-.82.32-1.72.07-1.24.07-1.59.07-4.74s0-3.5-.07-4.74c-.04-.9-.2-1.39-.32-1.72a2.85 2.85 0 0 0-.69-1.06 2.85 2.85 0 0 0-1.06-.69c-.33-.13-.82-.28-1.72-.32C15.5 4 15.15 4 12 4Zm0 3.07a4.93 4.93 0 1 1 0 9.86 4.93 4.93 0 0 1 0-9.86Zm0 1.8a3.13 3.13 0 1 0 0 6.26 3.13 3.13 0 0 0 0-6.26Zm5.13-3.24a1.15 1.15 0 1 1 0 2.3 1.15 1.15 0 0 1 0-2.3Z"/></svg>
          Siga @castellocasasdemadeira
        </a>
      </div>
    </div>

<?php include __DIR__ . '/partials/videos.php'; ?>
  </section>

  <!-- faceta: transição do Instagram escuro para o FAQ creme -->
  <div class="facet facet--sand" aria-hidden="true"></div>

  <!-- ============ FAQ (tabs verticais: lista lateral + painel) ============ -->
  <section class="section faq" id="faq" aria-label="Perguntas frequentes">
    <div class="container">
      <div class="section__head faq__head">
        <span class="eyebrow reveal">Perguntas frequentes</span>
        <h2 class="section__title reveal">Tudo que você precisa<br>saber antes de construir.</h2>
        <p class="section__lead reveal">Reunimos as dúvidas que mais ouvimos de quem está prestes a realizar o sonho da casa de madeira. Ficou com outra pergunta? É só chamar no WhatsApp.</p>
      </div>

<?php $contexto = 'geral'; include __DIR__ . '/partials/faq.php'; ?>

      <p class="faq__foot reveal">A Castello fica em Tubarão e atende toda a região, com casas já entregues em diversas cidades de Santa Catarina.
        <button type="button" class="faq__foot-link" data-quote-open>Tirar uma dúvida com a gente</button>
      </p>
    </div>
  </section>

  <!-- ============ CTA BAND (faceta vermelha) ============ -->
  <section class="cta-band">
    <div class="container cta-band__inner">
      <div class="reveal">
        <h2>Pronto pra sair do aluguel?</h2>
        <p>Peça seu orçamento sem compromisso. A Castello te acompanha do primeiro passo até a chave na mão.</p>
      </div>
      <button type="button" class="btn btn--light btn--lg reveal" data-quote-open>
        <svg viewBox="0 0 24 24" class="ico-quote" aria-hidden="true"><path d="M4 4h16a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H8.6L4 20.5V5a1 1 0 0 1 1-1Zm3 5h10v1.7H7V9Zm0 3.6h6.6v1.7H7v-1.7Z"/></svg>
        Pedir orçamento agora
      </button>
    </div>
  </section>

<?php include __DIR__ . '/partials/rodape.php'; ?>

<?php include __DIR__ . '/partials/modal.php'; ?>

  <script src="js/main.js?v=13"></script>
  <script src="js/formulario.js?v=1" defer></script>
</body>
</html>
