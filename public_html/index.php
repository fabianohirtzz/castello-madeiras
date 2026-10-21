<?php
declare(strict_types=1);

/**
 * Home. Marcacao da fase 2 (frente 2) com o conteudo vindo do banco: as
 * listas pelos partials, os textos editaveis pelos blocos.
 *
 * Desde o site multipagina a home resume a Casa Pronta (a grade de modelos
 * mora em casa-pronta.php) e nao traz mais portfolio nem passo a passo, que
 * ganharam pagina propria.
 */

require_once __DIR__ . '/lib/conteudo.php';

$prazo_pronta = bloco('pronta_prazo', '90 a 120 dias');
$prazo_flex   = bloco('flex_prazo', '45 dias');

// O subtitulo do topo entra em duas partes durante a rolagem: ate a primeira
// virgula ou ponto no meio do video, o resto no fim. Sem pontuacao, entra
// tudo no fim.
$hero_sub = bloco('hero_subtitulo', 'Do seu jeito. *No seu ritmo.*');
$hero_partes = preg_split('/(?<=[,.])\s+/u', $hero_sub, 2);
if (count($hero_partes) === 2) {
    $hero_linha2 = $hero_partes[0];
    $hero_linha3 = $hero_partes[1];
} else {
    $hero_linha2 = '';
    $hero_linha3 = $hero_sub;
}

// A foto do resumo da Casa Pronta, num card vertical: o projeto escolhido no
// bloco pronta_home_modelo (o 7, terreo com o morro ao fundo, rende bem no
// corte vertical); sem ele, o modelo em destaque; sem destaque, o primeiro.
$pronta_modelos  = modelos('pronta');
$pronta_destaque = null;
$pronta_home     = bloco('pronta_home_modelo', 'Projeto 7');
foreach ($pronta_modelos as $m) {
    if ($m['nome'] === $pronta_home) {
        $pronta_destaque = $m;
        break;
    }
}
foreach ($pronta_destaque === null ? $pronta_modelos : [] as $m) {
    if ((int) $m['destaque'] === 1) {
        $pronta_destaque = $m;
        break;
    }
}
if ($pronta_destaque === null && $pronta_modelos !== []) {
    $pronta_destaque = $pronta_modelos[0];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Castello Casas de Madeira | Casa Pronta e Castello Flex em Tubarão SC</title>
  <meta name="description" content="Casas de madeira em Tubarão e região, desde 2014. Casa Pronta, completa em 90 a 120 dias, e Castello Flex, montada e fechada no seu terreno em 45 dias. 5,0 estrelas no Google, 71 avaliações." />

  <!-- Open Graph -->
  <meta property="og:type" content="website" />
  <meta property="og:title" content="Castello Casas de Madeira | Casa Pronta e Castello Flex" />
  <meta property="og:description" content="Casa Pronta, completa em 90 a 120 dias, e Castello Flex, montada e fechada no seu terreno em 45 dias. 5,0 estrelas no Google." />
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

  <link rel="stylesheet" href="css/style.css?v=43" />
</head>
<body>

<?php $pagina = 'home'; include __DIR__ . '/partials/nav.php'; ?>

  <!-- ============ HERO (cinematográfico — scrub de vídeo no scroll) ============ -->
  <section class="hero" id="topo" aria-label="Castello Casas de Madeira">
    <div class="hero__track" id="heroTrack">
      <div class="hero__pin">
        <div class="hero__media" id="heroMedia">
          <video id="heroVideo" class="hero__video" muted playsinline preload="none" disableremoteplayback poster="video-hero/poster.jpg" aria-hidden="true">
            <source src="video-hero/video-hero.mp4?v=4" data-src-mobile="video-hero/video-mobile.mp4?v=3" type="video/mp4" />
          </video>
        </div>
        <div class="hero__overlay" aria-hidden="true"></div>

        <!-- Texto do topo: o titulo fica parado a rolagem inteira; a segunda
             linha entra no meio do video e a terceira, com os botoes, no fim. -->
        <div class="hero__beat hero__texto is-active" id="heroTexto">
          <span class="eyebrow eyebrow--light">Casas de madeira · Tubarão SC</span>
          <h1 class="hero__headline"><?= e(bloco('hero_titulo', 'Sua casa.')) ?><?php if ($hero_linha2 !== ''): ?>
            <span class="hero__linha" data-hero-etapa="2"><?= realce($hero_linha2) ?></span><?php endif; ?>
            <span class="hero__linha" data-hero-etapa="3"><?= realce($hero_linha3) ?></span></h1>
          <div class="hero__actions" data-hero-etapa="3">
            <a href="casa-pronta.php" class="btn btn--primary btn--lg">Casa Pronta</a>
            <a href="flex.php" class="btn btn--ghost-light btn--lg">Casa Flex</a>
          </div>
        </div>

        <!-- Indicador de etapa -->
        <div class="hero__status" aria-hidden="true">
          <span class="hero__status-label">Etapa</span>
          <span class="hero__status-current" id="heroStatusCurrent">01</span>
          <span class="hero__status-track"><span class="hero__status-fill" id="heroStatusFill"></span></span>
          <span class="hero__status-total">03</span>
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
      <div class="proof__head">
        <h2 class="proof__title reveal">Cada projeto tem um propósito. Cada casa, uma história para contar.</h2>
        <p class="proof__lead reveal">Seja para morar, descansar ou investir, a Castello transforma projetos em realidade com experiência, qualidade e diferentes formas de construir.</p>
      </div>
      <div class="proof__inner">
        <div class="proof__item reveal">
          <div class="proof__num"><span data-count="5.0">0</span> <span class="star">★</span></div>
          <div class="proof__label">no Google</div>
        </div>
        <div class="proof__item reveal">
          <div class="proof__num" data-count="71">0</div>
          <div class="proof__label">avaliações</div>
        </div>
        <div class="proof__item reveal">
          <div class="proof__num"><span class="proof__pre">Desde</span> 2014</div>
          <div class="proof__label">no mercado</div>
        </div>
        <div class="proof__item reveal">
          <div class="proof__num" data-count="19000" data-prefix="+" data-suffix=" m²">0</div>
          <div class="proof__label">construídos</div>
        </div>
      </div>
    </div>
  </section>

  <!-- faceta: telhado recorta a saída da faixa vermelha -->
  <div class="facet facet--bone" aria-hidden="true"></div>

  <!-- ============ MODALIDADES (Casa Pronta x Castello Flex) ============ -->
  <!-- Copy provisória da Flex: sai do material do Instagram até o cliente enviar o definitivo. -->
  <section class="section modalidades section--facetada" id="modalidades">
    <div class="container">
      <div class="modalidades__head">
        <span class="eyebrow reveal">Conheça nossas modalidades</span>
        <h2 class="section__title modalidades__titulo reveal"><?= preg_replace('/\.\s+/u', '.<br>', e(bloco('modalidades_titulo', 'Seu sonho, suas escolhas. Duas formas de construir.')), 1) ?></h2>
        <p class="section__lead reveal"><?= e(bloco('modalidades_texto')) ?></p>
      </div>

      <div class="modalidades__grid">
        <article class="modalidade reveal">
          <span class="modalidade__tag">Construção completa</span>
          <h3 class="modalidade__name">Casa Pronta</h3>
          <p class="modalidade__prazo">Entrega de <strong><?= e($prazo_pronta) ?></strong></p>
          <p class="modalidade__text">Da definição dos ambientes à execução da obra, a Castello cuida da construção da sua casa de madeira, incluindo fundação, estrutura, instalações e acabamentos conforme o contrato. Tudo pensado para você receber seu novo lar pronto para morar.</p>
          <p class="modalidade__sub">Diferenciais</p>
          <ul class="checklist">
            <li>Planejamento personalizado dos ambientes</li>
            <li>Execução da obra do início à entrega</li>
            <li>Casa entregue com os acabamentos contratados</li>
          </ul>
          <a href="casa-pronta.php" class="btn btn--primary">Ver modelos e preços</a>
        </article>

        <article class="modalidade modalidade--flex reveal">
          <span class="modalidade__tag">Lançamento</span>
          <h3 class="modalidade__name">Castello Flex</h3>
          <p class="modalidade__prazo">No seu terreno em <strong><?= e($prazo_flex) ?></strong></p>
          <p class="modalidade__text">Da fundação à montagem da estrutura, a Castello cuida das principais etapas da construção da sua casa de madeira, incluindo cobertura e fechamento externo. Você tem a liberdade de realizar os acabamentos conforme seu estilo, planejamento e orçamento.</p>
          <ul class="checklist checklist--light">
            <li>Fundação com laje aérea e estrutura de madeira executadas</li>
            <li>Cobertura e fechamento externo</li>
            <li>Liberdade para personalizar os acabamentos</li>
          </ul>
          <a href="flex.php" class="btn btn--primary">Conhecer a Castello Flex</a>
        </article>
      </div>
    </div>
  </section>

  <!-- faceta: transição para a Casa Pronta -->
  <div class="facet facet--sand" aria-hidden="true"></div>

  <!-- ============ CASA PRONTA (resumo; a grade de modelos fica em casa-pronta.php) ============ -->
  <section class="section section--sand section--facetada prontahome" id="casa-pronta">
    <div class="container">
      <div class="split2">
        <div>
          <span class="eyebrow reveal">Casa Pronta · chave na mão</span>
          <h2 class="section__title prontahome__titulo reveal"><?= e(bloco('pronta_titulo', 'Sua casa não precisa seguir um padrão. Ela precisa combinar com você.')) ?></h2>
          <p class="section__lead reveal"><?= e(bloco('pronta_texto')) ?></p>
          <p class="stamp reveal">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v4l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
            Pronta para morar de <strong><?= e($prazo_pronta) ?></strong>
          </p>
          <ul class="checklist reveal">
            <li>Fundação com laje aérea, estrutura de madeira, elétrica, hidráulica, cerâmica, fossa, sumidouro, vidros e aberturas inclusos</li>
            <li>Projetos a partir de 36 m²</li>
          </ul>
          <div class="prontahome__actions reveal">
            <a href="casa-pronta.php" class="btn btn--primary btn--lg">Ver a Casa Pronta</a>
          </div>
        </div>

<?php if ($pronta_destaque !== null): ?>
        <figure class="prontahome__media reveal reveal--mask">
          <img src="<?= e($pronta_destaque['foto']) ?>" alt="<?= e($pronta_destaque['foto_alt']) ?>" loading="lazy" />
          <figcaption class="prontahome__cap">
            <span class="prontahome__cap-eyebrow">Modelo <?= e($pronta_destaque['nome']) ?> · <?= e(str_replace(',00 ', ' ', (string) $pronta_destaque['area'])) ?></span>
<?php if (trim((string) $pronta_destaque['preco']) !== ''): ?>
            <span class="prontahome__cap-price">A partir de <strong>R$ <?= e($pronta_destaque['preco']) ?></strong></span>
<?php endif; ?>
          </figcaption>
        </figure>
<?php endif; ?>
      </div>
    </div>
  </section>

  <!-- faceta: transição escura para a Castello Flex -->
  <div class="facet facet--ink" aria-hidden="true"></div>

  <!-- ============ CASTELO FLEX (resumo + vídeo explicativo) ============ -->
  <!-- Copy e vídeo provisórios: entram pelo painel nas chaves flex_* de `blocos`. -->
  <section class="section section--dark section--facetada flexhome" id="castello-flex">
    <div class="container">
      <div class="split2">
        <div class="flexhome__text">
          <span class="eyebrow eyebrow--light reveal">Lançamento Castello Flex</span>
          <h2 class="flexhome__title reveal"><?= preg_replace('/\.\s+/u', '.<br>', e(bloco('flex_titulo', 'Sua casa de madeira ganha forma em 45 dias. Você dá o toque final.')), 1) ?></h2>
          <p class="flexhome__lead reveal"><?= e(bloco('flex_texto')) ?></p>

          <div class="flexhome__facts reveal">
            <div class="flexhome__fact"><strong><?= e($prazo_flex) ?></strong><span>Prazo de entrega</span></div>
            <div class="flexhome__fact"><strong>Estrutura</strong><span>Fundação, montagem, cobertura e fechamento</span></div>
            <div class="flexhome__fact"><strong>Liberdade</strong><span>Personalize os acabamentos no seu ritmo</span></div>
          </div>

          <div class="flexhome__actions reveal">
            <a href="flex.php" class="btn btn--primary btn--lg">Conhecer a Castello Flex</a>
            <button type="button" class="btn btn--ghost-light btn--lg" data-quote-open>Pedir orçamento</button>
          </div>
        </div>

<?php $vflex_escuro = true; include __DIR__ . '/partials/videos-flex.php'; ?>
      </div>
    </div>
  </section>

  <!-- faceta: volta ao canvas claro nas vantagens -->
  <div class="facet facet--bone" aria-hidden="true"></div>

  <!-- ============ POR QUE MADEIRA (editorial assimétrico) ============ -->
  <section class="section section--facetada vantagens" id="vantagens">
    <div class="container">
      <div class="why__layout">
        <div class="why__intro">
          <span class="eyebrow reveal">Por que escolher uma casa de madeira?</span>
          <h2 class="section__title why__titulo reveal">O charme da madeira.<br>O conforto de um verdadeiro lar.</h2>
          <p class="why__lead reveal">Beleza natural, aconchego e personalidade são apenas alguns dos motivos para escolher uma casa de madeira. Conheça os diferenciais desse estilo de construção.</p>
          <button type="button" class="btn btn--primary reveal" data-quote-open>Pedir orçamento</button>
        </div>

        <div class="why__carousel reveal" id="whyCarousel" aria-roledescription="carrossel" aria-label="Vantagens da casa de madeira">
          <div class="why__viewport">
            <article class="why__slide is-active" role="group" aria-roledescription="slide" aria-label="1 de 4">
              <span class="why__card-ico"><svg viewBox="0 0 24 24"><path d="M14 14.8V5a2 2 0 1 0-4 0v9.8a4 4 0 1 0 4 0Z"/><path d="M12 11v6M17 6h3M17 10h3"/></svg></span>
              <h3>Conforto térmico</h3>
              <p>A madeira possui propriedades naturais de isolamento térmico, contribuindo para ambientes mais confortáveis em diferentes estações do ano, quando combinada a um projeto e isolamento adequados.</p>
            </article>
            <article class="why__slide" role="group" aria-roledescription="slide" aria-label="2 de 4">
              <span class="why__card-ico"><svg viewBox="0 0 24 24"><path d="M12 8v4l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg></span>
              <h3>Agilidade na construção</h3>
              <p>O sistema construtivo em madeira permite uma execução organizada e eficiente, com prazos definidos conforme a modalidade contratada.</p>
            </article>
            <article class="why__slide" role="group" aria-roledescription="slide" aria-label="3 de 4">
              <span class="why__card-ico"><svg viewBox="0 0 24 24"><path d="M3 21h18M5 21V10l7-5 7 5v11M9 21v-6h6v6"/></svg></span>
              <h3>Beleza e personalidade</h3>
              <p>A textura e os tons naturais da madeira criam ambientes acolhedores, com charme e identidade própria.</p>
            </article>
            <article class="why__slide" role="group" aria-roledescription="slide" aria-label="4 de 4">
              <span class="why__card-ico"><svg viewBox="0 0 24 24"><path d="m14 7 3 3M5 19l1.5-5.5L16 4l4 4-9.5 9.5L5 19Z"/><path d="M3 3h6v6H3z"/></svg></span>
              <h3>Versatilidade de projetos</h3>
              <p>Possibilidade de personalizar a distribuição dos ambientes e as dimensões da casa conforme suas necessidades e características do terreno.</p>
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

  <!-- faceta: transição para as avaliações (o topo da cena é branco) -->
  <div class="facet facet--white" aria-hidden="true"></div>

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
          <span class="reviews__meta">71 avaliações no Google</span>
        </div>
        <div class="reveal">
          <span class="eyebrow">Quem já construiu com a Castello</span>
          <h2 class="section__title">Sonhos realizados.<br>Histórias que inspiram confiança.</h2>
          <p class="section__lead">A melhor forma de conhecer nosso trabalho é ouvir quem já confiou na Castello. Confira as avaliações reais dos nossos clientes no Google.</p>
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
        <h2 class="section__title reveal">Por trás de cada casa, existe uma história sendo construída.</h2>
        <p class="section__lead reveal">Acompanhe nossas obras, conheça nossa equipe e veja de perto o cuidado dedicado a cada construção.</p>
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
        <p class="section__lead reveal">Reunimos as dúvidas que mais ouvimos de quem está prestes a realizar o sonho da casa de madeira. Ficou com alguma dúvida? É só falar com a gente pelo formulário.</p>
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
        <h2>Sua nova história<br class="br-desk"> pode começar aqui.</h2>
        <p>Seja para morar, descansar ou investir, a Castello oferece diferentes<br class="br-desk"> possibilidades para realizar seu sonho de ter uma casa de madeira.<br class="br-desk"> Converse com nossa equipe e solicite seu orçamento.</p>
      </div>
      <button type="button" class="btn btn--light btn--lg reveal" data-quote-open>
        <svg viewBox="0 0 24 24" class="ico-quote" aria-hidden="true"><path d="M4 4h16a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H8.6L4 20.5V5a1 1 0 0 1 1-1Zm3 5h10v1.7H7V9Zm0 3.6h6.6v1.7H7v-1.7Z"/></svg>
        Quero realizar meu sonho
      </button>
    </div>
  </section>

<?php include __DIR__ . '/partials/rodape.php'; ?>

<?php include __DIR__ . '/partials/modal.php'; ?>

  <script src="js/main.js?v=24"></script>
  <script src="js/formulario.js?v=4" defer></script>
</body>
</html>
