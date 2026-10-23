<?php
declare(strict_types=1);

/**
 * /link: a pagina do link da bio do Instagram (o .htaccess serve /link com
 * este arquivo). Feita para o celular: orcamento no topo, abrindo o mesmo
 * formulario do site num popup (partials/modal.php), as duas modalidades em
 * cards com foto, os atalhos e as redes. Fica fora do sitemap e do Google.
 */

require_once __DIR__ . '/lib/conteudo.php';

/** Menor preco "a partir de" de uma modalidade, em mil reais ("72 mil"). */
function link_a_partir(string $modalidade): string
{
    $menor = null;
    foreach (modelos($modalidade) as $m) {
        $valor = (int) preg_replace('/\D+/', '', (string) $m['preco']);
        if ($valor > 0 && ($menor === null || $valor < $menor)) {
            $menor = $valor;
        }
    }
    return $menor === null ? '' : 'A partir de R$ ' . number_format(intdiv($menor, 1000), 0, ',', '.') . ' mil';
}

$link_pronta = link_a_partir('pronta');
$link_flex   = link_a_partir('flex');
$prazo_flex  = bloco('flex_prazo', '45 dias');
$link_email  = 'contato@castellomadeiras.com.br';

/* href, titulo, apoio, icone, abre fora do site */
$link_atalhos = [
    ['portfolio.php', 'Portfólio', 'Casas que a Castello já entregou',
     '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 16-5-5-9 9"/>', false],
    ['index.php#depoimentos', 'Depoimentos', 'Histórias de quem construiu com a gente',
     '<path d="M4 4h16a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H8.6L4 20.5V5a1 1 0 0 1 1-1Z"/><path d="M8 9h8M8 12.5h5"/>', false],
    [CASTELLO_MAPA_LINK, 'Avaliações no Google', '5,0 estrelas em 71 avaliações',
     '<path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9L12 3Z"/>', true],
    [CASTELLO_MAPA_ROTA, 'Como chegar', CASTELLO_ENDERECO_RUA . ', Tubarão SC',
     '<path d="M12 21s7-7.8 7-13a7 7 0 0 0-14 0c0 5.2 7 13 7 13Z"/><circle cx="12" cy="8" r="2.5"/>', true],
];
$seta = '<svg class="lk-seta" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <title>Castello Casas de Madeira | Links</title>
  <meta name="description" content="Peça seu orçamento e conheça a Casa Pronta, a Castello Flex, o portfólio e as avaliações da Castello Casas de Madeira, em Tubarão SC." />
  <meta name="robots" content="noindex, follow" />
  <meta name="theme-color" content="#141414" />
  <link rel="canonical" href="<?= CASTELLO_URL . '/link' ?>" />

  <!-- Open Graph: a miniatura do link no WhatsApp e no Instagram (logo no
       centro, para sobreviver ao corte quadrado que o WhatsApp faz) -->
  <meta property="og:type" content="website" />
  <meta property="og:title" content="Castello Casas de Madeira | Links" />
  <meta property="og:description" content="Peça seu orçamento e conheça a Casa Pronta, a Castello Flex, o portfólio e as avaliações da Castello Casas de Madeira, em Tubarão SC." />
  <meta property="og:url" content="<?= CASTELLO_URL . '/link' ?>" />
  <meta property="og:image" content="<?= CASTELLO_URL ?>/images/og-link.jpg" />
  <meta property="og:image:width" content="1200" />
  <meta property="og:image:height" content="630" />
  <meta property="og:image:alt" content="Logo da Castello Casas de Madeira em vermelho e branco sobre fundo escuro" />
  <meta property="og:site_name" content="Castello Casas de Madeira" />
  <meta property="og:locale" content="pt_BR" />
  <meta name="twitter:card" content="summary_large_image" />
  <link rel="icon" type="image/png" href="images/icone-colorido.png" />

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet" />

  <link rel="stylesheet" href="css/style.css?v=44" />
</head>
<body class="lk-corpo">
  <main class="lk">
    <div class="lk__brilho" aria-hidden="true"></div>

    <header class="lk__topo lk-entra" style="--i:0">
      <a href="index.php" class="lk__logo-link"><img src="images/logo-vertical-vermelho-branco.png" alt="Castello Casas de Madeira" class="lk__logo" width="512" height="512" /></a>
      <a href="<?= e(CASTELLO_MAPA_LINK) ?>" class="lk__nota" target="_blank" rel="noopener">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9L12 3Z"/></svg>
        <strong>5,0</strong> no Google <span class="lk__nota-sep" aria-hidden="true">·</span> 71 avaliações
      </a>
      <p class="lk__lead">Casas de madeira em Tubarão e região desde 2014. Do jeito que você sonhou, no ritmo que faz sentido para você.</p>
    </header>

    <button type="button" class="lk-cta lk-entra" style="--i:1" data-quote-open>
      <span class="lk-cta__ico"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H8.6L4 20.5V5a1 1 0 0 1 1-1Zm3 5h10v1.7H7V9Zm0 3.6h6.6v1.7H7v-1.7Z"/></svg></span>
      <span class="lk-cta__txt">
        <strong>Pedir orçamento</strong>
        <small>Nossa equipe estará pronta para orientar você com atenção e dedicação.</small>
      </span>
      <?= $seta ?>
    </button>

    <section class="lk-modos" aria-label="Modalidades">
      <a href="casa-pronta.php" class="lk-modo lk-entra" style="--i:2">
        <img src="fotos-casas/hero-casa-pronta.webp" alt="" loading="eager" />
        <span class="lk-modo__veu" aria-hidden="true"></span>
        <span class="lk-modo__txt">
          <span class="lk-modo__tag">Personalizável</span>
          <strong>Casa Pronta</strong>
<?php if ($link_pronta !== ''): ?>
          <small><?= e($link_pronta) ?></small>
<?php endif; ?>
        </span>
      </a>
      <a href="flex.php" class="lk-modo lk-entra" style="--i:3">
        <img src="fotos-casas/hero-flex.webp" alt="" loading="eager" />
        <span class="lk-modo__veu" aria-hidden="true"></span>
        <span class="lk-modo__txt">
          <span class="lk-modo__tag lk-modo__tag--novo">Novo · <?= e($prazo_flex) ?></span>
          <strong>Castello Flex</strong>
<?php if ($link_flex !== ''): ?>
          <small><?= e($link_flex) ?></small>
<?php endif; ?>
        </span>
      </a>
    </section>

    <nav class="lk-lista" aria-label="Atalhos da Castello">
<?php foreach ($link_atalhos as $i => [$href, $titulo, $apoio, $icone, $fora]): ?>
      <a class="lk-item lk-entra" style="--i:<?= $i + 4 ?>" href="<?= e($href) ?>"<?= $fora ? ' target="_blank" rel="noopener"' : '' ?>>
        <span class="lk-item__ico"><svg viewBox="0 0 24 24" aria-hidden="true"><?= $icone ?></svg></span>
        <span class="lk-item__txt"><strong><?= e($titulo) ?></strong><small><?= e($apoio) ?></small></span>
        <?= $seta ?>
      </a>
<?php endforeach; ?>
    </nav>

    <div class="lk-redes lk-entra" style="--i:8">
      <a href="https://www.instagram.com/castellocasasdemadeira/" target="_blank" rel="noopener" aria-label="Instagram da Castello">
        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></svg>
      </a>
      <a href="https://www.facebook.com/castellomadeiras" target="_blank" rel="noopener" aria-label="Facebook da Castello">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 3h-2.2A3.8 3.8 0 0 0 9 6.8V10H6.5v3.3H9V21h3.4v-7.7h2.7l.4-3.3h-3.1V7.3c0-.6.4-1 1-1H15V3Z"/></svg>
      </a>
      <a href="mailto:<?= e($link_email) ?>" aria-label="E-mail da Castello">
        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 6.5 8.5 6.5 8.5-6.5"/></svg>
      </a>
    </div>

    <p class="lk-assina lk-entra" style="--i:9">
      <span><span aria-hidden="true">🏡</span> Não construímos casas, mas construímos sites!</span>
      <span><span aria-hidden="true">🏗️</span> Construído por: <a href="https://www.freelainhome.com.br/" target="_blank" rel="noopener">Freela In Home</a>.</span>
    </p>
  </main>

<?php $pagina = 'link'; include __DIR__ . '/partials/modal.php'; ?>

  <script src="js/main.js?v=24"></script>
  <script src="js/formulario.js?v=4" defer></script>
</body>
</html>
