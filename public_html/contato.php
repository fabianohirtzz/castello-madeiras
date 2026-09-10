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
  <meta name="description" content="Peça seu orçamento de casa de madeira à Castello. WhatsApp (48) 99824-4494, telefone (48) 3632-8743, Av. Patrício Lima, 843, Tubarão SC. Resposta em até 1 dia útil." />

  <!-- Open Graph -->
  <meta property="og:type" content="website" />
  <meta property="og:title" content="Contato e orçamento | Castello Casas de Madeira" />
  <meta property="og:description" content="Peça seu orçamento de casa de madeira. WhatsApp (48) 99824-4494, Av. Patrício Lima, 843, Tubarão SC. Resposta em até 1 dia útil." />
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

  <link rel="stylesheet" href="css/style.css?v=21" />
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
      <p class="pagehero__lead reveal">Conta pra gente o que você procura. A Castello responde em até 1 dia útil com uma proposta sob medida, sem compromisso.</p>
      <div class="pagehero__actions reveal">
        <a href="https://wa.me/5548998244494" class="btn btn--primary btn--lg" target="_blank" rel="noopener">
          <svg viewBox="0 0 24 24" class="ico-wpp" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1-.2.2-.6.8-.8 1-.1.2-.3.2-.5.1-.7-.3-1.4-.7-2-1.4-.4-.5-.8-1.1-.9-1.3-.1-.2 0-.4.1-.5l.4-.4c.1-.2.2-.3.2-.5 0-.2 0-.3-.1-.5l-.8-1.9c-.2-.5-.4-.4-.6-.4h-.5c-.2 0-.4.1-.6.3-.2.2-.8.8-.8 2 0 1.2.8 2.3 1 2.5.1.2 1.7 2.6 4 3.5 1.4.6 1.9.6 2.6.5.4 0 1.3-.5 1.5-1.1.2-.5.2-1 .1-1.1-.1-.1-.2-.1-.4-.2Z"/></svg>
          Chamar no WhatsApp
        </a>
        <button type="button" class="btn btn--ghost-light btn--lg" data-quote-open>Preencher o formulário</button>
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
              <span><strong>56</strong> avaliações</span>
              <span><strong>12 anos</strong> de mercado</span>
            </div>
          </div>

<?php $form_modalidade = 'pronta'; $form_opcoes_extra = ['Quero a Castelo Flex, semipronta']; include __DIR__ . '/partials/formulario.php'; ?>
        </div>

        <aside class="contato__canais" id="canais" aria-label="Canais de atendimento">
          <a href="https://wa.me/5548998244494" class="canal canal--wpp reveal" target="_blank" rel="noopener">
            <span class="canal__ico"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1-.2.2-.6.8-.8 1-.1.2-.3.2-.5.1-.7-.3-1.4-.7-2-1.4-.4-.5-.8-1.1-.9-1.3-.1-.2 0-.4.1-.5l.4-.4c.1-.2.2-.3.2-.5 0-.2 0-.3-.1-.5l-.8-1.9c-.2-.5-.4-.4-.6-.4h-.5c-.2 0-.4.1-.6.3-.2.2-.8.8-.8 2 0 1.2.8 2.3 1 2.5.1.2 1.7 2.6 4 3.5 1.4.6 1.9.6 2.6.5.4 0 1.3-.5 1.5-1.1.2-.5.2-1 .1-1.1-.1-.1-.2-.1-.4-.2Z"/></svg></span>
            <span>
              <span class="canal__label">WhatsApp</span>
              <span class="canal__val">(48) 99824-4494</span>
              <span class="canal__sub">O jeito mais rápido de falar com a gente.</span>
            </span>
          </a>

          <a href="tel:+554836328743" class="canal reveal">
            <span class="canal__ico"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.6 10.8a15.1 15.1 0 0 0 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.1.4 2.4.6 3.6.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1A17 17 0 0 1 3 4c0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.3.2 2.5.6 3.6.1.4 0 .8-.2 1l-2.3 2.2Z"/></svg></span>
            <span>
              <span class="canal__label">Telefone</span>
              <span class="canal__val">(48) 3632-8743</span>
              <span class="canal__sub">Ligue no horário de atendimento.</span>
            </span>
          </a>

          <div class="canal reveal">
            <span class="canal__ico canal__ico--line"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v4l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg></span>
            <span>
              <span class="canal__label">Horário de atendimento</span>
              <span class="canal__val">Segunda a sexta, 8h às 12h e 13h30 às 18h</span>
              <span class="canal__sub">Sábado, 8h30 às 12h.</span>
            </span>
          </div>

          <a href="https://www.google.com/maps/search/?api=1&query=Av.+Patr%C3%ADcio+Lima+843+Tubar%C3%A3o+SC" class="canal reveal" target="_blank" rel="noopener">
            <span class="canal__ico"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a7 7 0 0 0-7 7c0 5.2 7 13 7 13s7-7.8 7-13a7 7 0 0 0-7-7Zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5Z"/></svg></span>
            <span>
              <span class="canal__label">Endereço</span>
              <span class="canal__val">Av. Patrício Lima, 843</span>
              <span class="canal__sub">Tubarão, Santa Catarina. Abrir no Google Maps.</span>
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
          <h2 class="section__title reveal">Venha conhecer a Castello<br>em Tubarão.</h2>
          <p class="section__lead reveal">Av. Patrício Lima, 843, Tubarão, Santa Catarina. A Castello atende toda a região, com casas já entregues em diversas cidades do estado.</p>
        </div>
      </div>

      <div class="contato__mapa reveal">
        <iframe
          src="https://www.google.com/maps?q=Av.%20Patr%C3%ADcio%20Lima%20843%20Tubar%C3%A3o%20SC&output=embed"
          title="Mapa com a localização da Castello Casas de Madeira em Tubarão, SC"
          loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
      </div>
    </div>
  </section>

<?php include __DIR__ . '/partials/rodape.php'; ?>

  <script src="js/main.js?v=14"></script>
  <script src="js/formulario.js?v=2" defer></script>
</body>
</html>
