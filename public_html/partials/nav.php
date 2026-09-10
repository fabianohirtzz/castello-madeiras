<?php
/**
 * Cabecalho, menu e gaveta mobile. Espera: string $pagina
 * ('home', 'pronta', 'flex', 'portfolio' ou 'contato').
 *
 * O menu lista so paginas, nunca ancora de secao: o site e multipagina e
 * cada link leva a um arquivo. A pagina atual recebe aria-current="page".
 * O logo leva sempre a home.
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/db.php';

$pagina = $pagina ?? 'home';

$ico = [
    'inicio'  => '<path d="m3 11 9-7 9 7v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1v-9Z"/>',
    'casa'    => '<path d="M3 21h18M5 21V10l7-5 7 5v11M9 21v-6h6v6"/>',
    'camadas' => '<path d="m12 3 9 5-9 5-9-5 9-5ZM3 12l9 5 9-5M3 16l9 5 9-5"/>',
    'foto'    => '<path d="M3 5h18v14H3zM3 15l5-5 4 4 3-3 6 6"/><circle cx="8.5" cy="9" r="1.4"/>',
    'fone'    => '<path d="M5 4h4l2 5-3 2a12 12 0 0 0 5 5l2-3 5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2Z"/>',
];

/* chave da pagina, href, rotulo, icone, classe extra, etiqueta */
$menu = [
    ['home',      'index.php',       'Início',       'inicio',  '',                ''],
    ['pronta',    'casa-pronta.php', 'Casa Pronta',  'casa',    '',                ''],
    ['flex',      'flex.php',        'Castelo Flex', 'camadas', 'nav__link--flex', 'Novo'],
    ['portfolio', 'portfolio.php',   'Portfólio',    'foto',    '',                ''],
    ['contato',   'contato.php',     'Contato',      'fone',    '',                ''],
];
?>
  <!-- sprite: logo do Google (multicolor) reutilizado nas avaliações -->
  <svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false">
    <symbol id="ico-google" viewBox="0 0 24 24">
      <path fill="#4285F4" d="M23.52 12.27c0-.79-.07-1.55-.2-2.27H12v4.51h6.47a5.53 5.53 0 0 1-2.4 3.62v3h3.87c2.27-2.09 3.58-5.17 3.58-8.86z"/>
      <path fill="#34A853" d="M12 24c3.24 0 5.96-1.08 7.94-2.91l-3.87-3c-1.08.72-2.45 1.16-4.07 1.16-3.13 0-5.78-2.11-6.73-4.96H1.29v3.09A12 12 0 0 0 12 24z"/>
      <path fill="#FBBC05" d="M5.27 14.29a7.21 7.21 0 0 1 0-4.58V6.62H1.29a12 12 0 0 0 0 10.76l3.98-3.09z"/>
      <path fill="#EA4335" d="M12 4.74c1.77 0 3.35.61 4.6 1.8l3.43-3.43C17.95 1.19 15.24 0 12 0A12 12 0 0 0 1.29 6.62l3.98 3.09C6.22 6.86 8.87 4.74 12 4.74z"/>
    </symbol>
  </svg>

  <!-- ============ NAV ============ -->
  <header class="nav" id="nav">
    <div class="nav__inner container">
      <a href="index.php" class="nav__logo" aria-label="Castello Casas de Madeira"<?= $pagina === 'home' ? ' aria-current="page"' : '' ?>>
        <img src="images/logo-horizontal-branco.png" alt="Castello Casas de Madeira" class="nav__logo-img nav__logo-img--light" />
        <img src="images/logo-horizontal-colorido.png" alt="Castello Casas de Madeira" class="nav__logo-img nav__logo-img--dark" />
      </a>

      <nav class="nav__links" aria-label="Navegação principal">
<?php foreach ($menu as [$chave, $href, $rotulo, $icone, $classe, $tag]): ?>
        <a href="<?= e($href) ?>"<?= $classe !== '' ? ' class="' . e($classe) . '"' : '' ?><?= $pagina === $chave ? ' aria-current="page"' : '' ?>><svg class="nav__ico" viewBox="0 0 24 24" aria-hidden="true"><?= $ico[$icone] ?></svg><?= e($rotulo) ?><?= $tag !== '' ? '<span class="nav__tag">' . e($tag) . '</span>' : '' ?></a>
<?php endforeach; ?>
      </nav>

      <div class="nav__acoes">
      <button type="button" class="btn btn--primary nav__cta" data-quote-open>
        <svg viewBox="0 0 24 24" class="ico-quote" aria-hidden="true"><path d="M4 4h16a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H8.6L4 20.5V5a1 1 0 0 1 1-1Zm3 5h10v1.7H7V9Zm0 3.6h6.6v1.7H7v-1.7Z"/></svg>
        Pedir orçamento
      </button>

      <button class="nav__burger" id="burger" aria-label="Abrir menu" aria-expanded="false">
        <span></span><span></span><span></span>
      </button>
      </div>
    </div>
  </header>

  <!-- mobile drawer -->
  <div class="drawer-backdrop" id="drawerBackdrop" hidden></div>
  <div class="drawer" id="drawer" aria-hidden="true">
<?php foreach ($menu as [$chave, $href, $rotulo]): ?>
    <a href="<?= e($href) ?>"<?= $pagina === $chave ? ' aria-current="page"' : '' ?>><?= e($rotulo) ?></a>
<?php endforeach; ?>
    <button type="button" class="btn btn--primary" data-quote-open>Pedir orçamento</button>
  </div>
