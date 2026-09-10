<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';

auth_iniciar();

if (auth_logado()) {
    header('Location: painel.php');
    exit;
}

$ip   = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$erro = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_validar($_POST['csrf'] ?? null)) {
        $erro = 'A sessão expirou nesta página. Tente entrar de novo.';
    } elseif (auth_bloqueado($ip)) {
        $erro = 'Muitas tentativas erradas. Espere 15 minutos e tente de novo.';
    } elseif (auth_entrar(
        trim((string) ($_POST['login'] ?? '')),
        (string) ($_POST['senha'] ?? ''),
        isset($_POST['lembrar'])
    )) {
        header('Location: painel.php');
        exit;
    } elseif (auth_bloqueado($ip)) {
        $erro = 'Muitas tentativas erradas. Espere 15 minutos e tente de novo.';
    } else {
        $erro = 'Login ou senha incorretos.';
    }
}
// O campo volta com o que a pessoa digitou quando o envio falhou, e com o
// login salvo quando ela esta chegando agora.
$loginSalvo = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    ? trim((string) ($_POST['login'] ?? ''))
    : auth_login_salvo();

$lembrarMarcado = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    ? isset($_POST['lembrar'])
    : auth_login_salvo() !== '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex, nofollow" />
  <title>Painel Castello</title>
  <link rel="icon" type="image/png" href="../images/icone-colorido.png" />
  <link rel="stylesheet" href="assets/painel.css?v=1" />
</head>
<body>
  <main class="p-login">
    <img src="../images/logo-vertical-branco.png" alt="Castello Casas de Madeira" style="filter: invert(1)" />

<?php if ($erro !== ''): ?>
    <p class="p-aviso p-aviso--erro"><?= e($erro) ?></p>
<?php endif; ?>

    <form class="p-form" method="post" action="index.php">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>" />

      <div class="p-campo">
        <label for="login">Login</label>
        <input type="text" id="login" name="login" value="<?= e($loginSalvo) ?>" autocomplete="username" autocapitalize="none" required />
      </div>

      <div class="p-campo">
        <label for="senha">Senha</label>
        <input type="password" id="senha" name="senha" autocomplete="current-password" required />
      </div>

      <div class="p-campo p-campo--marca">
        <label for="lembrar">
          <input type="checkbox" id="lembrar" name="lembrar" value="1"<?= $lembrarMarcado ? ' checked' : '' ?> />
          <span>Salvar meu login e continuar conectado</span>
        </label>
        <span class="p-ajuda">Vale por 30 dias neste aparelho. Não marque em computador compartilhado.</span>
      </div>

      <button class="p-btn p-btn--forte" type="submit">Entrar</button>
    </form>
  </main>
</body>
</html>
