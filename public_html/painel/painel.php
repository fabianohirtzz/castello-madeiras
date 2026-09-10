<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/tabelas.php';

auth_exigir();

$abas = painel_abas() + painel_fixas();

$tela = (string) ($_GET['tela'] ?? 'modelos');
if (!isset($abas[$tela])) {
    $tela = 'modelos';
}

$def = painel_tabela($tela);

// Telas de conteudo: lista por padrao, formulario quando ha novo ou editar.
// Telas fixas (textos, config, backup, senha) tem arquivo proprio em telas/.
if ($def !== null) {
    $arquivo = (isset($_GET['novo']) || isset($_GET['editar']))
        ? __DIR__ . '/telas/form.php'
        : __DIR__ . '/telas/lista.php';
} else {
    $arquivo = __DIR__ . '/telas/' . $tela . '.php';
}

$recado = (string) ($_GET['ok'] ?? '');
$alerta = (string) ($_GET['erro'] ?? '');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex, nofollow" />
  <title><?= e($abas[$tela] ?? 'Painel') ?> | Painel Castello</title>
  <link rel="icon" type="image/png" href="../images/icone-colorido.png" />
  <link rel="stylesheet" href="assets/painel.css?v=2" />
</head>
<body class="p-app">
  <header class="p-topo">
    <button class="p-abrir" type="button" id="pAbrir" aria-controls="pLado" aria-expanded="false">
      <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 7h16"/><path d="M4 12h16"/><path d="M4 17h16"/></svg>
      <span class="p-so-leitor">Abrir o menu</span>
    </button>
    <strong><?= e($abas[$tela] ?? 'Painel') ?></strong>
  </header>

<?php include __DIR__ . '/menu.php'; ?>
  <div class="p-veu" id="pVeu" hidden></div>

  <main class="p-corpo">
<?php if ($recado !== ''): ?>
    <p class="p-aviso" role="status"><?= e($recado) ?></p>
<?php endif; ?>
<?php if ($alerta !== ''): ?>
    <p class="p-aviso p-aviso--erro" role="alert"><?= e($alerta) ?></p>
<?php endif; ?>

<?php include $arquivo; ?>
  </main>

  <script src="assets/painel.js?v=2" defer></script>
</body>
</html>
