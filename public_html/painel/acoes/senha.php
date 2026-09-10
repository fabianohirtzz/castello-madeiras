<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../tabelas.php';

auth_exigir();

if (!csrf_validar($_POST['csrf'] ?? null)) {
    header('Location: ../painel.php?tela=senha&erro=' . rawurlencode('A sessão expirou. Entre de novo e repita.'));
    exit;
}

auth_iniciar();
$usuarioId = (int) ($_SESSION['usuario_id'] ?? 0);

$r = painel_trocar_senha(
    $usuarioId,
    (string) ($_POST['atual'] ?? ''),
    (string) ($_POST['nova'] ?? ''),
    (string) ($_POST['confirma'] ?? '')
);

if (!$r['ok']) {
    header('Location: ../painel.php?tela=senha&erro=' . rawurlencode((string) $r['erro']));
    exit;
}

header('Location: ../painel.php?tela=senha&ok=' . rawurlencode('Senha trocada. Use a nova no próximo acesso.'));
exit;
