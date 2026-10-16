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
  <meta name="description" content="Casa Pronta é a casa de madeira completa da Castello: projetos personalizáveis de 36 a 163 m², a partir de R$ 72 mil, com laje, elétrica, hidráulica, cerâmica e vidros inclusos. Chave na mão em 90 a 120 dias." />

  <!-- Open Graph -->
  <meta property="og:type" content="website" />
  <meta property="og:title" content="Casa Pronta | Casa de madeira chave na mão em 90 a 120 dias" />
  <meta property="og:description" content="Projetos personalizáveis a partir de R$ 72 mil, completos: laje, elétrica, hidráulica, cerâmica e vidros. Chave na mão em 90 a 120 dias." />
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

  <link rel="stylesheet" href="css/style.css?v=37" />
</head>
<body>

<?php $pagina = 'pronta'; include __DIR__ . '/partials/nav.php'; ?>

  <!-- ============ HERO DA CASA PRONTA ============ -->
  <section class="pagehero" id="topo" aria-label="Casa Pronta">
    <div class="pagehero__media">
      <img src="fotos-casas/hero-casa-pronta.webp" alt="Casa de madeira Castello térrea com varanda em volta, telhado de telha cerâmica e morro de mata ao fundo" fetchpriority="high" />
    </div>
    <div class="pagehero__overlay" aria-hidden="true"></div>

    <div class="container">
      <span class="eyebrow eyebrow--light reveal">Casa Pronta · casas completas</span>
      <h1 class="pagehero__title reveal">Seu projeto. Sua casa. <span class="hl">Chave na mão.</span></h1>
      <p class="pagehero__lead reveal">A Castello faz tudo, você recebe a chave e entra pra morar.</p>
      <p class="stamp stamp--light reveal">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v4l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
        Pronta para morar de <strong><?= e($prazo_pronta) ?></strong>
      </p>
      <div class="pagehero__actions reveal">
        <a href="#modelos" class="btn btn--primary btn--lg">Ver modelos e preços</a>
      </div>
    </div>
  </section>

  <!-- faceta: telhado recorta a saída do hero -->
  <div class="facet facet--bone" aria-hidden="true"></div>

  <!-- ============ MODELOS COM PREÇO ============ -->
  <section class="section section--facetada modelos" id="modelos">
    <div class="container">
      <div class="section__head">
        <span class="eyebrow reveal">Catálogo Casa Pronta</span>
        <h2 class="section__title reveal">Inspire-se nos nossos modelos.<br>Personalize o seu.</h2>
        <p class="section__lead reveal">Explore algumas das possibilidades de construção e descubra como podemos adaptar sua casa ao espaço disponível e ao que você procura.</p>
        <p class="stamp reveal">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v4l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
          Pronta para morar de <strong><?= e($prazo_pronta) ?></strong>
        </p>
      </div>

<?php $modalidade = 'pronta'; include __DIR__ . '/partials/modelos.php'; ?>

      <p class="modelos__note reveal">Todos os projetos são personalizáveis. Os valores são de referência para o projeto inicial e o orçamento final varia conforme as suas escolhas, o terreno e a região. Fale com a gente e receba uma proposta sob medida.</p>
    </div>
  </section>

  <!-- faceta: transição para o que está incluso -->
  <div class="facet facet--sand" aria-hidden="true"></div>

  <!-- ============ O QUE ESTÁ INCLUSO NO CHAVE NA MÃO ============ -->
  <section class="section section--sand section--facetada incluso" id="incluso">
    <div class="container">
      <div class="section__head">
        <span class="eyebrow reveal">Chave na mão</span>
        <h2 class="section__title reveal">O que está incluso<br>na sua Casa Pronta?</h2>
        <p class="section__lead reveal">Da fundação aos acabamentos, a Castello cuida de cada etapa da construção para entregar sua casa de madeira pronta para morar, com qualidade, planejamento e atenção aos detalhes.</p>
      </div>

      <div class="incluso__grid">
        <div class="incluso__card reveal">
          <h3>Sua casa completa, do início ao fim.</h3>
          <p>Na modalidade Casa Pronta, a Castello executa todas as etapas previstas na construção, desde a fundação até os acabamentos, proporcionando mais praticidade e tranquilidade para você.</p>
          <ul class="incluso__lista">
            <li>Fundação e laje de piso</li>
            <li>Estrutura de madeira e cobertura</li>
            <li>Instalações elétricas e hidráulicas</li>
            <li>Revestimentos cerâmicos</li>
            <li>Portas, janelas e vidros</li>
            <li>Fossa e sumidouro</li>
            <li>Execução e acompanhamento da obra</li>
          </ul>
        </div>

        <div class="incluso__side">
          <article class="bene reveal">
            <span class="bene__ico"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 8 4v6c0 4.4-3.4 7.4-8 8-4.6-.6-8-3.6-8-8V7l8-4Z"/><path d="m9 12 2 2 4-4"/></svg></span>
            <div>
              <h3>Qualidade em cada detalhe.</h3>
              <p>Utilizamos pregos galvanizados na montagem da estrutura, proporcionando maior resistência à corrosão e contribuindo para a durabilidade da construção.</p>
            </div>
          </article>
          <article class="bene reveal">
            <span class="bene__ico"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m14 7 3 3M5 19l1.5-5.5L16 4l4 4-9.5 9.5L5 19Z"/></svg></span>
            <div>
              <h3>Uma casa pensada para você.</h3>
              <p>Na Casa Pronta, você tem liberdade para personalizar a distribuição dos ambientes, as dimensões e os acabamentos, criando uma casa que se adapta às suas necessidades, preferências e características do terreno.</p>
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

  <!-- faceta: transição escura para a linha do tempo -->
  <div class="facet facet--ink" aria-hidden="true"></div>

  <!-- ============ DO TERRENO À CHAVE (linha do tempo da obra) ============ -->
  <!-- Etapas do carrossel "Como tudo acontece" do Instagram da Castello: a secao trava e a rolagem empurra os cards para o lado. -->
  <section class="section section--facetada process hscroll" id="como-funciona" data-hscroll>
    <div class="hscroll__track">
    <div class="hscroll__pin">
      <div class="container">
        <div class="process__head">
          <span class="eyebrow eyebrow--light">Do terreno à chave</span>
          <h2 class="section__title">Como tudo acontece.</h2>
          <p class="section__lead">Existe uma ordem certa para cada etapa da obra. Acompanhe o caminho da sua casa, da primeira conversa até a entrega da chave.</p>
        </div>

<?php $contexto = 'pronta'; include __DIR__ . '/partials/passos.php'; ?>
      </div>
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
        <p class="section__lead reveal">Reunimos as dúvidas que mais ouvimos de quem está prestes a realizar o sonho da casa de madeira. Ficou com outra pergunta? É só falar com a gente pelo formulário.</p>
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
        <h2>O próximo capítulo da sua história pode começar aqui.</h2>
        <p>Imagine cada detalhe da sua futura casa de madeira. A Castello transforma suas ideias em uma construção personalizada, pronta para receber novos momentos e histórias.</p>
      </div>
      <button type="button" class="btn btn--light btn--lg reveal" data-quote-open>
        <svg viewBox="0 0 24 24" class="ico-quote" aria-hidden="true"><path d="M4 4h16a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H8.6L4 20.5V5a1 1 0 0 1 1-1Zm3 5h10v1.7H7V9Zm0 3.6h6.6v1.7H7v-1.7Z"/></svg>
        Quero planejar minha casa
      </button>
    </div>
  </section>

<?php include __DIR__ . '/partials/rodape.php'; ?>

<?php include __DIR__ . '/partials/modal.php'; ?>

  <script src="js/main.js?v=23"></script>
  <script src="js/formulario.js?v=4" defer></script>
</body>
</html>
