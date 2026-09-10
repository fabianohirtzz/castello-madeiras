<?php
declare(strict_types=1);

/**
 * Pagina Portfolio: todas as casas entregues cadastradas pelo painel, numa
 * grade que cresce com o cadastro. Cada foto abre em tela cheia.
 */

require_once __DIR__ . '/lib/conteudo.php';

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Portfólio | Casas de madeira entregues pela Castello em Santa Catarina</title>
  <meta name="description" content="Casas de madeira reais projetadas, construídas e entregues pela Castello em Tubarão e região. Sobrados, casas térreas, chalés e casas de campo, 12 anos e mais de 18.000 m² construídos." />

  <!-- Open Graph -->
  <meta property="og:type" content="website" />
  <meta property="og:title" content="Portfólio | Casas de madeira entregues pela Castello" />
  <meta property="og:description" content="Sobrados, casas térreas, chalés e casas de campo entregues pela Castello em Santa Catarina. 12 anos e mais de 18.000 m² construídos." />
  <meta property="og:url" content="<?= CASTELLO_URL . '/portfolio.php' ?>" />
  <meta property="og:image" content="<?= CASTELLO_URL ?>/images/og-home.jpg" />
  <meta property="og:image:width" content="1200" />
  <meta property="og:image:height" content="630" />
  <meta property="og:site_name" content="Castello Casas de Madeira" />
  <meta name="twitter:card" content="summary_large_image" />
  <meta property="og:locale" content="pt_BR" />

  <link rel="canonical" href="<?= CASTELLO_URL . '/portfolio.php' ?>" />
  <link rel="icon" type="image/png" href="images/icone-colorido.png" />

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet" />

  <link rel="stylesheet" href="css/style.css?v=20" />
</head>
<body>

<?php $pagina = 'portfolio'; include __DIR__ . '/partials/nav.php'; ?>

  <!-- ============ HERO DO PORTFÓLIO ============ -->
  <section class="pagehero" id="topo" aria-label="Portfólio">
    <div class="pagehero__media">
      <img src="fotos-casas/casa3.webp" alt="Sobrado de madeira Castello à beira da água com vista para a ponte" fetchpriority="high" />
    </div>
    <div class="pagehero__overlay" aria-hidden="true"></div>

    <div class="container">
      <span class="eyebrow eyebrow--light reveal">Casas reais entregues</span>
      <h1 class="pagehero__title reveal">Projetos que já viraram <span class="hl">lar</span>.</h1>
      <p class="pagehero__lead reveal">Cada casa desta página foi projetada, construída e entregue pela Castello para famílias de Santa Catarina e região. São 12 anos e mais de 18.000 m² construídos.</p>
      <div class="pagehero__actions reveal">
        <button type="button" class="btn btn--primary btn--lg" data-quote-open>
          <svg viewBox="0 0 24 24" class="ico-quote" aria-hidden="true"><path d="M4 4h16a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H8.6L4 20.5V5a1 1 0 0 1 1-1Zm3 5h10v1.7H7V9Zm0 3.6h6.6v1.7H7v-1.7Z"/></svg>
          Pedir orçamento
        </button>
        <a href="casa-pronta.php" class="btn btn--ghost-light btn--lg">Ver modelos e preços</a>
      </div>
    </div>
  </section>

  <!-- faceta: telhado recorta a saída do hero -->
  <div class="facet facet--bone" aria-hidden="true"></div>

  <!-- ============ GALERIA ============ -->
  <section class="section" id="casas">
    <div class="container">
      <div class="section__head">
        <span class="eyebrow reveal">Portfólio</span>
        <h2 class="section__title reveal">Casas que a Castello<br>já entregou.</h2>
        <p class="section__lead reveal">Sobrados, casas térreas, chalés e casas de campo. Toque numa foto para ver em tela cheia.</p>
      </div>

<?php include __DIR__ . '/partials/portfolio.php'; ?>

      <p class="galeria__nota reveal">Quer ver a sua casa nesta galeria? Conta pra gente o tamanho que você imagina e onde fica o terreno.
        <button type="button" class="galeria__nota-link" data-quote-open>Pedir um orçamento</button>
      </p>
    </div>
  </section>

  <!-- ============ CTA BAND ============ -->
  <section class="cta-band cta-band--bone" id="orcamento">
    <div class="container cta-band__inner">
      <div class="reveal">
        <h2>A próxima casa desta galeria pode ser a sua.</h2>
        <p>Peça seu orçamento sem compromisso. A Castello te acompanha do primeiro passo até a chave na mão.</p>
      </div>
      <button type="button" class="btn btn--light btn--lg reveal" data-quote-open>
        <svg viewBox="0 0 24 24" class="ico-quote" aria-hidden="true"><path d="M4 4h16a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H8.6L4 20.5V5a1 1 0 0 1 1-1Zm3 5h10v1.7H7V9Zm0 3.6h6.6v1.7H7v-1.7Z"/></svg>
        Pedir orçamento agora
      </button>
    </div>
  </section>

<?php include __DIR__ . '/partials/rodape.php'; ?>

  <!-- ============ FOTO EM TELA CHEIA ============ -->
  <div class="fotobox" id="fotobox" hidden role="dialog" aria-modal="true" aria-label="Foto em tela cheia">
    <button class="fotobox__close" id="fotoboxClose" type="button" aria-label="Fechar foto">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
    </button>

    <button class="fotobox__nav fotobox__nav--prev" id="fotoboxPrev" type="button" aria-label="Foto anterior">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
    </button>

    <div class="fotobox__stage">
      <img class="fotobox__img" id="fotoboxImg" alt="" />
      <div class="fotobox__cap">
        <span class="fotobox__cat" id="fotoboxCat"></span>
        <span class="fotobox__title" id="fotoboxTitle"></span>
        <span class="fotobox__count" id="fotoboxCount"></span>
      </div>
    </div>

    <button class="fotobox__nav fotobox__nav--next" id="fotoboxNext" type="button" aria-label="Próxima foto">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
    </button>
  </div>

<?php include __DIR__ . '/partials/modal.php'; ?>

  <script src="js/main.js?v=14"></script>
  <script src="js/formulario.js?v=2" defer></script>
</body>
</html>
