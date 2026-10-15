<?php
declare(strict_types=1);

/**
 * Pagina Contato: o formulario de orcamento embutido na pagina (o mesmo do
 * modal, pelo partials/formulario.php), os canais de atendimento e o mapa.
 *
 * Esta pagina NAO inclui o partials/modal.php: o formulario ja esta aqui e
 * os ids duplicariam. Todo [data-quote-open] rola ate o formulario
 * (js/main.js) e o token de csrf vem no carregamento (js/formulario.js).
 */

require_once __DIR__ . '/lib/conteudo.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Contato e orçamento | Castello Casas de Madeira em Tubarão SC</title>
  <meta name="description" content="Peça seu orçamento de casa de madeira à Castello pelo formulário. Av. Severiano Albino Corrêa, 729, Tubarão SC. Nossa equipe entra em contato com você com todo o carinho." />

  <!-- Open Graph -->
  <meta property="og:type" content="website" />
  <meta property="og:title" content="Contato e orçamento | Castello Casas de Madeira" />
  <meta property="og:description" content="Peça seu orçamento de casa de madeira pelo formulário. Av. Severiano Albino Corrêa, 729, Tubarão SC. Nossa equipe entra em contato com você com todo o carinho." />
  <meta property="og:url" content="<?= CASTELLO_URL . '/contato.php' ?>" />
  <meta property="og:image" content="<?= CASTELLO_URL ?>/images/og-home.jpg" />
  <meta property="og:image:width" content="1200" />
  <meta property="og:image:height" content="630" />
  <meta property="og:site_name" content="Castello Casas de Madeira" />
  <meta name="twitter:card" content="summary_large_image" />
  <meta property="og:locale" content="pt_BR" />

  <link rel="canonical" href="<?= CASTELLO_URL . '/contato.php' ?>" />
  <link rel="icon" type="image/png" href="images/icone-colorido.png" />

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet" />

  <link rel="stylesheet" href="css/style.css?v=29" />
</head>
<body>

<?php $pagina = 'contato'; include __DIR__ . '/partials/nav.php'; ?>

  <!-- ============ HERO DO CONTATO ============ -->
  <section class="pagehero" id="topo" aria-label="Contato">
    <div class="pagehero__media">
      <img src="fotos-casas/casa6.webp" alt="Casa de madeira Castello térrea com varanda ampla e garagem coberta" fetchpriority="high" />
    </div>
    <div class="pagehero__overlay" aria-hidden="true"></div>

    <div class="container">
      <span class="eyebrow eyebrow--light reveal">Fale com a Castello</span>
      <h1 class="pagehero__title reveal">Vamos falar da sua <span class="hl">casa</span>.</h1>
      <p class="pagehero__lead pagehero__lead--largo reveal">Seu sonho merece ser ouvido com carinho. Após preencher o formulário, nossa equipe estará pronta para orientar você com atenção e dedicação.</p>
      <div class="pagehero__actions reveal">
        <button type="button" class="btn btn--primary btn--lg" data-quote-open>Preencher o formulário</button>
      </div>
    </div>
  </section>

  <!-- faceta: telhado recorta a saída do hero -->
  <div class="facet facet--bone" aria-hidden="true"></div>

  <!-- ============ FORMULÁRIO EMBUTIDO + CANAIS ============ -->
  <section class="section section--facetada contato" id="formulario">
    <div class="container">
      <div class="contato__grid">
        <div class="contato__card reveal">
          <div class="qmodal__head">
            <span class="eyebrow">Orçamento sem compromisso</span>
            <h2>Peça o seu orçamento</h2>
            <p>Conta pra gente o que você procura. A Castello volta com uma proposta sob medida.</p>
            <div class="qmodal__trust">
              <span><strong>5,0 ★</strong> no Google</span>
              <span><strong>71</strong> avaliações</span>
              <span><strong>Desde 2014</strong> no mercado</span>
            </div>
          </div>

<?php $form_modalidade = 'pronta'; $form_opcoes_extra = ['Quero a Castello Flex']; include __DIR__ . '/partials/formulario.php'; ?>
        </div>

        <aside class="contato__canais" id="canais" aria-label="Canais de atendimento">
          <div class="canal reveal">
            <span class="canal__ico canal__ico--line"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v4l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg></span>
            <span>
              <span class="canal__label">Horário de atendimento</span>
              <span class="canal__val">Segunda a sexta, 8h às 12h e 13h30 às 18h</span>
              <span class="canal__sub">Sábado, 8h30 às 12h.</span>
            </span>
          </div>

          <a href="<?= e(CASTELLO_MAPA_LINK) ?>" class="canal reveal" target="_blank" rel="noopener">
            <span class="canal__ico"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a7 7 0 0 0-7 7c0 5.2 7 13 7 13s7-7.8 7-13a7 7 0 0 0-7-7Zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5Z"/></svg></span>
            <span>
              <span class="canal__label">Endereço</span>
              <span class="canal__val"><?= e(CASTELLO_ENDERECO_RUA) ?></span>
              <span class="canal__sub"><?= e(CASTELLO_ENDERECO_BAIRRO) ?>, <?= e(CASTELLO_ENDERECO_CIDADE) ?>. Abrir no Google Maps.</span>
            </span>
          </a>
        </aside>
      </div>
    </div>
  </section>

  <!-- faceta: transição para o mapa -->
  <div class="facet facet--sand" aria-hidden="true"></div>

  <!-- ============ MAPA ============ -->
  <section class="section section--sand" id="mapa">
    <div class="container">
      <div class="contato__mapa-head">
        <div class="section__head">
          <span class="eyebrow reveal">Onde estamos</span>
          <h2 class="section__title reveal">Venha conhecer<br>a Castello em<br>Tubarão/SC.</h2>
          <p class="section__lead reveal"><?= e(CASTELLO_ENDERECO_RUA) ?>, <?= e(CASTELLO_ENDERECO_BAIRRO) ?>, <?= e(CASTELLO_ENDERECO_CIDADE) ?>. A Castello atende toda a região, com casas já entregues em diversas cidades do estado.</p>
        </div>
      </div>

      <div class="contato__mapa reveal">
        <iframe
          src="<?= e(mapa_embed()) ?>"
          title="Mapa com a localização da Castello Casas de Madeira em Tubarão, SC"
          loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
      </div>
    </div>
  </section>

<?php include __DIR__ . '/partials/rodape.php'; ?>

  <script src="js/main.js?v=19"></script>
  <script src="js/formulario.js?v=4" defer></script>
</body>
</html>
