<?php
declare(strict_types=1);

/**
 * Pagina Casa Pronta (chave na mao). Os quatro modelos com preco, o que esta
 * incluso no chave na mao, o passo a passo do terreno a chave e o FAQ geral.
 * Listas pelos partials, textos editaveis pelos blocos pronta_*.
 */

require_once __DIR__ . '/lib/conteudo.php';

$prazo_pronta = bloco('pronta_prazo', '90 a 120 dias');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Casa Pronta | Casa de madeira chave na mão em 90 a 120 dias | Castello</title>
  <meta name="description" content="Casa Pronta é a casa de madeira completa da Castello: quatro tamanhos a partir de R$ 69.900, com laje, elétrica, hidráulica, cerâmica e vidros inclusos. Chave na mão em 90 a 120 dias." />

  <!-- Open Graph -->
  <meta property="og:type" content="website" />
  <meta property="og:title" content="Casa Pronta | Casa de madeira chave na mão em 90 a 120 dias" />
  <meta property="og:description" content="Quatro tamanhos a partir de R$ 69.900, completos: laje, elétrica, hidráulica, cerâmica e vidros. Chave na mão em 90 a 120 dias." />
  <meta property="og:url" content="<?= CASTELLO_URL . '/casa-pronta.php' ?>" />
  <meta property="og:image" content="<?= CASTELLO_URL ?>/images/og-home.jpg" />
  <meta property="og:image:width" content="1200" />
  <meta property="og:image:height" content="630" />
  <meta property="og:site_name" content="Castello Casas de Madeira" />
  <meta name="twitter:card" content="summary_large_image" />
  <meta property="og:locale" content="pt_BR" />

  <link rel="canonical" href="<?= CASTELLO_URL . '/casa-pronta.php' ?>" />
  <link rel="icon" type="image/png" href="images/icone-colorido.png" />

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet" />

  <link rel="stylesheet" href="css/style.css?v=19" />
</head>
<body>

<?php $pagina = 'pronta'; include __DIR__ . '/partials/nav.php'; ?>

  <!-- ============ HERO DA CASA PRONTA ============ -->
  <section class="pagehero" id="topo" aria-label="Casa Pronta">
    <div class="pagehero__media">
      <img src="fotos-casas/casa2.webp" alt="Casa de madeira Castello térrea com telhado de telhas e varanda" fetchpriority="high" />
    </div>
    <div class="pagehero__overlay" aria-hidden="true"></div>

    <div class="container">
      <span class="eyebrow eyebrow--light reveal">Casa Pronta · casas completas</span>
      <h1 class="pagehero__title reveal">A casa completa, <span class="hl">chave na mão</span>, pronta pra morar.</h1>
      <p class="pagehero__lead reveal">Projeto, fundação, estrutura, elétrica, hidráulica, revestimentos e acabamento. A Castello faz tudo, você recebe a chave e entra pra morar.</p>
      <p class="stamp stamp--light reveal">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v4l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
        Pronta pra morar em <strong><?= e($prazo_pronta) ?></strong>
      </p>
      <div class="pagehero__actions reveal">
        <button type="button" class="btn btn--primary btn--lg" data-quote-open>
          <svg viewBox="0 0 24 24" class="ico-quote" aria-hidden="true"><path d="M4 4h16a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H8.6L4 20.5V5a1 1 0 0 1 1-1Zm3 5h10v1.7H7V9Zm0 3.6h6.6v1.7H7v-1.7Z"/></svg>
          Pedir orçamento
        </button>
        <a href="#modelos" class="btn btn--ghost-light btn--lg">Ver modelos e preços</a>
      </div>
    </div>
  </section>

  <!-- faceta: telhado recorta a saída do hero -->
  <div class="facet facet--bone" aria-hidden="true"></div>

  <!-- ============ MODELOS COM PREÇO ============ -->
  <section class="section section--facetada modelos" id="modelos">
    <div class="container">
      <div class="section__head">
        <span class="eyebrow reveal">Quatro tamanhos</span>
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

  <!-- faceta: transição para o que está incluso -->
  <div class="facet facet--sand" aria-hidden="true"></div>

  <!-- ============ O QUE ESTÁ INCLUSO NO CHAVE NA MÃO ============ -->
  <section class="section section--sand section--facetada incluso" id="incluso">
    <div class="container">
      <div class="section__head">
        <span class="eyebrow reveal">O que está incluso</span>
        <h2 class="section__title reveal">Completa quer dizer<br>completa.</h2>
        <p class="section__lead reveal">Sem letra miúda. Tudo isto entra em qualquer Casa Pronta, no preço de referência de cada modelo.</p>
      </div>

      <div class="incluso__grid">
        <div class="incluso__card reveal">
          <h3>Entregue pronta pra morar</h3>
          <p>O chave na mão da Castello cobre a casa inteira, da base ao vidro da janela.</p>
          <ul class="incluso__lista">
            <li>Laje aérea</li>
            <li>Instalação elétrica</li>
            <li>Instalação hidráulica</li>
            <li>Cerâmica</li>
            <li>Fossa</li>
            <li>Sumidouro</li>
            <li>Vidros e aberturas</li>
            <li>Projeto e acompanhamento da obra</li>
          </ul>
        </div>

        <div class="incluso__side">
          <article class="bene reveal">
            <span class="bene__ico"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 8 4v6c0 4.4-3.4 7.4-8 8-4.6-.6-8-3.6-8-8V7l8-4Z"/><path d="m9 12 2 2 4-4"/></svg></span>
            <div>
              <h3>Prego galvanizado</h3>
              <p>Em toda a estrutura, com madeira de qualidade. A casa atravessa gerações e valoriza.</p>
            </div>
          </article>
          <article class="bene reveal">
            <span class="bene__ico"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m14 7 3 3M5 19l1.5-5.5L16 4l4 4-9.5 9.5L5 19Z"/></svg></span>
            <div>
              <h3>100% personalizável</h3>
              <p>Planta, acabamentos, revestimentos, janelas, portas e piso escolhidos do seu jeito.</p>
            </div>
          </article>
          <article class="bene reveal">
            <span class="bene__ico"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v4l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg></span>
            <div>
              <h3>Pronta em <?= e($prazo_pronta) ?></h3>
              <p>Prazo combinado em contrato. Obra organizada do primeiro ao último dia.</p>
            </div>
          </article>
        </div>
      </div>
    </div>
  </section>

  <!-- faceta: transição escura para o scrollytelling -->
  <div class="facet facet--ink" aria-hidden="true"></div>

  <!-- ============ DO TERRENO À CHAVE (scrollytelling sticky) ============ -->
  <section class="section section--facetada process" id="como-funciona">
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

  <!-- faceta: transição do escuro para o FAQ creme -->
  <div class="facet facet--sand" aria-hidden="true"></div>

  <!-- ============ FAQ ============ -->
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

  <!-- ============ CTA BAND ============ -->
  <section class="cta-band" id="orcamento">
    <div class="container cta-band__inner">
      <div class="reveal">
        <h2>Quer a sua casa pronta em <?= e($prazo_pronta) ?>?</h2>
        <p>Peça seu orçamento sem compromisso. A Castello cuida de tudo, do projeto até a chave na mão.</p>
      </div>
      <button type="button" class="btn btn--light btn--lg reveal" data-quote-open>
        <svg viewBox="0 0 24 24" class="ico-quote" aria-hidden="true"><path d="M4 4h16a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H8.6L4 20.5V5a1 1 0 0 1 1-1Zm3 5h10v1.7H7V9Zm0 3.6h6.6v1.7H7v-1.7Z"/></svg>
        Pedir orçamento agora
      </button>
    </div>
  </section>

<?php include __DIR__ . '/partials/rodape.php'; ?>

<?php include __DIR__ . '/partials/modal.php'; ?>

  <script src="js/main.js?v=14"></script>
  <script src="js/formulario.js?v=2" defer></script>
</body>
</html>
