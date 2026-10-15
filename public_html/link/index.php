<?php
declare(strict_types=1);

/**
 * /link: a pagina do link da bio do Instagram. Uma coluna de botoes para as
 * paginas do site, as avaliacoes e o mapa no Google e o formulario de
 * orcamento. Fica fora do sitemap e do Google (noindex): so faz sentido para
 * quem chega pelo Instagram.
 */

require_once __DIR__ . '/../lib/conteudo.php';

/* href, titulo, linha de apoio, icone, abre fora do site, destaque */
$links = [
    ['../casa-pronta.php', 'Casa Pronta', 'Projetos personalizáveis, prontos para morar', '<path d="M3 21h18M5 21V10l7-5 7 5v11M9 21v-6h6v6"/>', false, false],
    ['../flex.php', 'Castello Flex', 'Montada e fechada no seu terreno em 45 dias', '<path d="M3 21h18M5 21V10l7-5 7 5v11"/><path d="m9 14 2 2 4-4"/>', false, false],
    ['../portfolio.php', 'Portfólio', 'Casas que a Castello já entregou', '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 16-5-5-9 9"/>', false, false],
    ['../index.php#depoimentos', 'Depoimentos', 'Histórias de quem construiu com a Castello', '<path d="M4 4h16a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H8.6L4 20.5V5a1 1 0 0 1 1-1Z"/>', false, false],
    [CASTELLO_MAPA_LINK, 'Avaliações no Google', '5,0 estrelas em 71 avaliações', '<path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9L12 3Z"/>', true, false],
    [CASTELLO_MAPA_ROTA, 'Localização', CASTELLO_ENDERECO_RUA . ', Tubarão SC', '<path d="M12 21s7-7.8 7-13a7 7 0 0 0-14 0c0 5.2 7 13 7 13Z"/><circle cx="12" cy="8" r="2.5"/>', true, false],
    ['../contato.php#formulario', 'Pedir orçamento', 'Preencha o formulário e a Castello responde', '<path d="M4 4h16v12H8.6L4 20.5V4Z"/><path d="M8 9h8M8 12.5h5"/>', false, true],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Castello Casas de Madeira | Links</title>
  <meta name="description" content="Casa Pronta, Castello Flex, portfólio, avaliações, localização e orçamento da Castello Casas de Madeira, em Tubarão SC." />
  <meta name="robots" content="noindex, follow" />
  <link rel="canonical" href="<?= CASTELLO_URL . '/link/' ?>" />
  <link rel="icon" type="image/png" href="../images/icone-colorido.png" />

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet" />

  <link rel="stylesheet" href="../css/style.css?v=23" />
</head>
<body>
  <main class="linkpg">
    <div class="linkpg__wrap">
      <a href="../index.php"><img src="../images/logo-vertical-branco.png" alt="Castello Casas de Madeira" class="linkpg__logo" /></a>
      <p class="linkpg__lead">Casas de madeira em Tubarão e região desde 2014. Escolha por onde começar.</p>

      <nav class="linkpg__lista" aria-label="Links da Castello">
<?php foreach ($links as [$href, $titulo, $apoio, $icone, $fora, $forte]): ?>
        <a class="linkpg__item<?= $forte ? ' linkpg__item--forte' : '' ?>" href="<?= e($href) ?>"<?= $fora ? ' target="_blank" rel="noopener"' : '' ?>>
          <svg viewBox="0 0 24 24" aria-hidden="true"><?= $icone ?></svg>
          <span><?= e($titulo) ?><small><?= e($apoio) ?></small></span>
        </a>
<?php endforeach; ?>
      </nav>

      <p class="linkpg__rodape">@castellocasasdemadeira</p>
    </div>
  </main>
</body>
</html>
