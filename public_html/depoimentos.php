<?php
declare(strict_types=1);

/**
 * Pagina Depoimentos: os videos de clientes da Castello na mesma trilha da
 * secao do Instagram da home (partials/videos.php com contexto depoimentos),
 * com a tela cheia do partials/modal.php. Os videos entram e saem pelo painel,
 * em Videos, filtro "Pagina Depoimentos"; aqui aparecem todos.
 */

require_once __DIR__ . '/lib/conteudo.php';

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Depoimentos | Clientes que construíram com a Castello Casas de Madeira</title>
  <meta name="description" content="Vídeos de clientes que construíram a casa de madeira com a Castello, em Tubarão e região. Histórias reais de quem já mora numa Castello. 5,0 estrelas no Google." />

  <!-- Open Graph -->
  <meta property="og:type" content="website" />
  <meta property="og:title" content="Depoimentos | Clientes que construíram com a Castello" />
  <meta property="og:description" content="Histórias reais de quem já mora numa casa de madeira Castello, contadas pelos próprios clientes." />
  <meta property="og:url" content="<?= CASTELLO_URL . '/depoimentos.php' ?>" />
  <meta property="og:image" content="<?= CASTELLO_URL ?>/images/og-home.jpg" />
  <meta property="og:image:width" content="1200" />
  <meta property="og:image:height" content="630" />
  <meta property="og:site_name" content="Castello Casas de Madeira" />
  <meta name="twitter:card" content="summary_large_image" />
  <meta property="og:locale" content="pt_BR" />

  <link rel="canonical" href="<?= CASTELLO_URL . '/depoimentos.php' ?>" />
  <link rel="icon" type="image/png" href="images/icone-colorido.png" />

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet" />

  <link rel="stylesheet" href="css/style.css?v=37" />
</head>
<body>

<?php $pagina = 'depoimentos'; include __DIR__ . '/partials/nav.php'; ?>

  <!-- ============ HERO DOS DEPOIMENTOS ============ -->
  <section class="pagehero" id="topo" aria-label="Depoimentos">
    <div class="pagehero__media">
      <img src="fotos-casas/casa5-ia.webp" alt="Casa de madeira Castello com varanda em volta, gramado e mata ao fundo" fetchpriority="high" />
    </div>
    <div class="pagehero__overlay" aria-hidden="true"></div>

    <div class="container">
      <span class="eyebrow eyebrow--light reveal">Quem já construiu com a Castello</span>
      <h1 class="pagehero__title reveal">Histórias de quem já <span class="hl">mora</span> numa Castello.</h1>
      <p class="pagehero__lead pagehero__lead--largo reveal">Ninguém conta melhor como é construir com a Castello do que quem já viveu cada etapa. Assista aos depoimentos dos nossos clientes.</p>
    </div>
  </section>

  <!-- faceta: transição escura para os vídeos -->
  <div class="facet facet--ink" aria-hidden="true"></div>

  <!-- ============ DEPOIMENTOS EM VÍDEO (mesma trilha do Instagram da home) ============ -->
  <section class="section insta" id="videos" aria-label="Depoimentos em vídeo">
    <div class="insta__grid-bg" aria-hidden="true"></div>
    <div class="container">
      <div class="section__head insta__head">
        <span class="eyebrow eyebrow--light reveal">Depoimentos em vídeo</span>
        <h2 class="section__title reveal">Sonhos realizados, contados por quem viveu.</h2>
        <p class="section__lead reveal">Toque em um vídeo para assistir em tela cheia, com som.</p>
        <a href="<?= e(CASTELLO_MAPA_LINK) ?>" class="btn btn--ghost-light reveal" target="_blank" rel="noopener">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9L12 3Z"/></svg>
          5,0 no Google, 71 avaliações
        </a>
      </div>
    </div>

<?php $videos_contexto = 'depoimentos'; $videos_limite = 0; include __DIR__ . '/partials/videos.php'; ?>
  </section>

  <!-- faceta: volta ao claro para a chamada final -->
  <div class="facet facet--bone" aria-hidden="true"></div>

  <!-- ============ CTA BAND ============ -->
  <section class="cta-band cta-band--bone cta-band--largo" id="orcamento">
    <div class="container cta-band__inner">
      <div class="reveal">
        <h2>A próxima história pode ser a sua.</h2>
        <p>Conte pra gente o tamanho que você imagina e onde fica o terreno. Nossa equipe vai orientar você com atenção e dedicação.</p>
      </div>
      <button type="button" class="btn btn--light btn--lg reveal" data-quote-open>
        <svg viewBox="0 0 24 24" class="ico-quote" aria-hidden="true"><path d="M4 4h16a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H8.6L4 20.5V5a1 1 0 0 1 1-1Zm3 5h10v1.7H7V9Zm0 3.6h6.6v1.7H7v-1.7Z"/></svg>
        Quero construir a minha
      </button>
    </div>
  </section>

<?php include __DIR__ . '/partials/rodape.php'; ?>

<?php include __DIR__ . '/partials/modal.php'; ?>

  <script src="js/main.js?v=23"></script>
  <script src="js/formulario.js?v=4" defer></script>
</body>
</html>
