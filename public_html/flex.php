<?php
declare(strict_types=1);

/**
 * Pagina Castello Flex. Marcacao da fase 2 (frente 2) com o conteudo vindo do
 * banco: modelos, passos e FAQ de modalidade flex pelos partials, textos
 * pelos blocos flex_* e flexpg_*. O que o cliente ainda nao cadastrou some
 * sem quebrar a pagina.
 *
 * O formulario e o do modal compartilhado (partials/modal.php).
 */

require_once __DIR__ . '/lib/conteudo.php';

$prazo_flex   = bloco('flex_prazo', '45 dias');
$flex_modelos = modelos('flex');
$flex_passos  = passos('flex');
$flex_faq     = faq('flex');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Castello Flex | Casa de madeira montada e fechada em 45 dias | Castello</title>
  <meta name="description" content="Castello Flex é a Casa Flex da Castello: estrutura montada, coberta e fechada no seu terreno em 45 dias. Você faz o acabamento no seu ritmo." />

  <!-- Open Graph -->
  <meta property="og:type" content="website" />
  <meta property="og:title" content="Castello Flex | Casa de madeira montada e fechada em 45 dias" />
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

  <link rel="stylesheet" href="css/style.css?v=42" />
</head>
<body>

<?php $pagina = 'flex'; include __DIR__ . '/partials/nav.php'; ?>

  <!-- ============ HERO DA CASTELO FLEX ============ -->
  <!-- Copy provisória: entra pelo painel quando o material da Flex chegar. -->
  <section class="pagehero" id="topo" aria-label="Castello Flex">
    <div class="pagehero__media">
      <img src="fotos-casas/hero-flex.webp" alt="Casa Castello Flex térrea com tábuas de madeira na vertical, janela com venezianas e garagem coberta ao lado" fetchpriority="high" />
    </div>
    <div class="pagehero__overlay" aria-hidden="true"></div>

    <div class="container">
      <span class="eyebrow eyebrow--light reveal">Lançamento Castello Flex</span>
      <h1 class="pagehero__title reveal"><?= realce(bloco('flexpg_hero_titulo', 'A sua casa de madeira *montada e fechada* no seu terreno em *45 dias*.')) ?></h1>
<?php if (bloco('flexpg_hero_texto') !== ''): ?>
      <p class="pagehero__lead pagehero__lead--flex reveal"><?= e(bloco('flexpg_hero_texto')) ?></p>
<?php endif; ?>
      <div class="pagehero__actions reveal">
        <a href="#modelos-flex" class="btn btn--primary btn--lg">Ver as casas Flex</a>
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
          <span class="eyebrow reveal">O que é a Castello Flex</span>
          <h2 class="section__title reveal"><?= e(bloco('flexpg_oque_titulo', 'A qualidade da Castello, com a liberdade de construir no seu ritmo.')) ?></h2>
<?php if (bloco('flexpg_oque_texto') !== ''): ?>
          <p class="section__lead reveal"><?= e(bloco('flexpg_oque_texto')) ?></p>
<?php endif; ?>
          <ul class="checklist reveal">
            <li>Excelência nos materiais, independente do projeto que você escolher</li>
            <li>Montagem pela equipe que constrói casas Castello desde 2014</li>
            <li>Cinco modelos com planta e medidas definidas</li>
          </ul>
        </div>

<?php $vflex_escuro = false; include __DIR__ . '/partials/videos-flex.php'; ?>
      </div>
    </div>
  </section>

<?php if ($flex_passos !== []): ?>
  <!-- faceta: transição escura para o passo a passo -->
  <div class="facet facet--ink" aria-hidden="true"></div>

  <!-- ============ PASSO A PASSO DA FLEX (linha do tempo com foto, como a da Casa Pronta) ============ -->
  <!-- Etapas da tabela `passos` com contexto flex: a secao trava e a rolagem empurra os cards para o lado. -->
  <section class="section section--facetada process hscroll" id="passos-flex" data-hscroll>
    <div class="hscroll__track">
    <div class="hscroll__pin">
      <div class="container">
        <div class="process__head">
          <span class="eyebrow eyebrow--light">Passo a passo</span>
          <h2 class="section__title">O que a Castello entrega.</h2>
          <p class="section__lead">Entenda o que a Castello executa na modalidade Flex e quais etapas você poderá realizar depois da entrega.</p>
        </div>

<?php $contexto = 'flex'; include __DIR__ . '/partials/passos.php'; ?>
      </div>
    </div>
    </div>
  </section>
<?php endif; ?>

  <!-- faceta: transição para o que fica por conta do cliente -->
  <div class="facet facet--sand" aria-hidden="true"></div>

  <!-- ============ A FRONTEIRA DA ENTREGA (o que fica por sua conta) ============ -->
  <section class="section section--sand section--facetada passos-flex" id="depois-flex">
    <div class="container">

      <div class="limite reveal">
        <span class="limite__txt">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v18M5 8h14M5 16h14"/></svg>
          A entrega da Castello termina aqui
        </span>
      </div>

      <div class="depois__head depois__head--centro reveal">
        <h3 class="depois__title depois__title--grande"><?= e(bloco('flexpg_depois_titulo', 'O que fica por sua conta')) ?></h3>
<?php if (bloco('flexpg_depois_texto') !== ''): ?>
        <p class="depois__text"><?= e(bloco('flexpg_depois_texto')) ?></p>
<?php endif; ?>
      </div>

      <div class="depois__grid">
        <div class="depois__item reveal"><strong>Elétrica e hidráulica</strong>Fiação, pontos, tubulação, louças e o balcão do banheiro.</div>
        <div class="depois__item reveal"><strong>Vidros das esquadrias</strong>As janelas vão instaladas. O vidro é por sua conta.</div>
        <div class="depois__item reveal"><strong>Revestimento e pintura</strong>Cerâmica, azulejo e a pintura interna e externa.</div>
        <div class="depois__item reveal"><strong>Fossa e sumidouro</strong>Ligação de esgoto e de água no terreno.</div>
      </div>

      <p class="depois__nota depois__nota--centro reveal">Prefere receber sua casa com os acabamentos concluídos? Conheça a modalidade
        <a href="casa-pronta.php">Casa Pronta da Castello</a>, uma solução completa para quem deseja receber sua casa pronta para morar.
      </p>
    </div>
  </section>

  <!-- faceta: transição para o catálogo Flex -->
  <div class="facet facet--bone" aria-hidden="true"></div>

  <!-- ============ CATÁLOGO DAS CASAS FLEX ============ -->
  <!-- Modelos reais da Flex: render, área e preço de setembro de 2026, editáveis pelo painel. -->
  <section class="section section--facetada" id="modelos-flex">
    <div class="container">
      <div class="section__head">
        <span class="eyebrow reveal">Casas Castello Flex</span>
        <h2 class="section__title ntitulo reveal"><span class="ntitulo__num"><?= count($flex_modelos) ?></span><span class="ntitulo__resto">modelos,<br>diferentes possibilidades.</span></h2>
        <p class="section__lead reveal">Conheça os <?= count($flex_modelos) ?> modelos exclusivos da Castello Flex, com tamanhos de 36 m² a 57,75 m². Todos possuem plantas padronizadas, sem alterações ou personalizações, oferecendo uma solução prática e econômica para construir sua casa de madeira.</p>
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
        <h2 class="section__title ntitulo reveal"><span class="ntitulo__num">45</span><span class="ntitulo__resto">dias e a<br>casa é sua.</span></h2>
        <p class="section__lead reveal">Mais agilidade na construção, com a qualidade que o seu projeto merece!</p>
      </div>

      <div class="bene__grid">
        <article class="bene reveal">
          <span class="bene__ico"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v4l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg></span>
          <h3>Prazo garantido em contrato</h3>
          <p>Da assinatura do contrato até a entrega da casa, honramos os prazos estabelecidos.</p>
        </article>
        <article class="bene reveal">
          <span class="bene__ico"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h13v5H4zM17 6.5h3v5l-8 1.5V16M10.5 16h3v5h-3z"/></svg></span>
          <h3>Liberdade para finalizar</h3>
          <p>Após a entrega, você tem a liberdade de contratar os profissionais de sua preferência e realizar os acabamentos conforme seu planejamento e orçamento.</p>
        </article>
        <article class="bene reveal">
          <span class="bene__ico"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 8 4v6c0 4.4-3.4 7.4-8 8-4.6-.6-8-3.6-8-8V7l8-4Z"/><path d="m9 12 2 2 4-4"/></svg></span>
          <h3>Qualidade Castello</h3>
          <p>A mesma qualidade de madeira, pregos galvanizados e experiência de uma equipe que constrói casas de madeira desde 2014.</p>
        </article>
        <article class="bene reveal">
          <span class="bene__ico"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 21h18M5 21V10l7-5 7 5v11M9 21v-6h6v6"/></svg></span>
          <h3>Versatilidade de uso</h3>
          <p>Uma solução para diferentes objetivos: moradia, casa de campo, hospedagem ou investimento. Você escolhe como aproveitar seu espaço.</p>
        </article>
      </div>
    </div>
  </section>

  <!-- faceta: transição para as perguntas -->
  <div class="facet facet--sand" aria-hidden="true"></div>

  <!-- ============ FAQ DA CASTELO FLEX ============ -->
  <!-- Perguntas provisórias: migram para `faq` com contexto flex. Ícones do conjunto fechado. -->
  <section class="section faq" id="faq" aria-label="Perguntas frequentes sobre a Castello Flex">
    <div class="container">
      <div class="section__head faq__head">
        <span class="eyebrow reveal">Perguntas frequentes</span>
        <h2 class="section__title reveal">Tudo o que você precisa saber sobre a Castello&nbsp;Flex.</h2>
        <p class="section__lead reveal">Todas as respostas para você dar o próximo passo.</p>
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
        <h2><?= quebras_desktop(bloco('flexpg_cta_titulo', 'A casa que você tanto sonhou começa com uma escolha.'), 'A casa que você', 'tanto sonhou começa') ?></h2>
<?php if (bloco('flexpg_cta_texto') !== ''): ?>
        <p><?= quebras_desktop(bloco('flexpg_cta_texto'), 'mais perto do que você imagina.') ?></p>
<?php endif; ?>
      </div>
      <button type="button" class="btn btn--light btn--lg reveal" data-quote-open>
        <svg viewBox="0 0 24 24" class="ico-quote" aria-hidden="true"><path d="M4 4h16a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H8.6L4 20.5V5a1 1 0 0 1 1-1Zm3 5h10v1.7H7V9Zm0 3.6h6.6v1.7H7v-1.7Z"/></svg>
        Quero escolher minha casa
      </button>
    </div>
  </section>

<?php include __DIR__ . '/partials/rodape.php'; ?>

<?php include __DIR__ . '/partials/modal.php'; ?>

  <script src="js/main.js?v=23"></script>
  <script src="js/formulario.js?v=4" defer></script>
</body>
</html>
