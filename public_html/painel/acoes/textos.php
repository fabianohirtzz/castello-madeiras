<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../tabelas.php';

auth_exigir();

if (!csrf_validar($_POST['csrf'] ?? null)) {
    header('Location: ../painel.php?tela=textos&erro=' . rawurlencode('A sessão expirou. Entre de novo e repita.'));
    exit;
}

$quantas = painel_textos_gravar(is_array($_POST['valores'] ?? null) ? $_POST['valores'] : []);

header('Location: ../painel.php?tela=textos&ok=' . rawurlencode($quantas . ' textos salvos.'));
exit;
