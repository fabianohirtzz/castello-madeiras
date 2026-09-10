<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../tabelas.php';

auth_exigir();

if (!csrf_validar($_POST['csrf'] ?? null)) {
    header('Location: ../painel.php?tela=config&erro=' . rawurlencode('A sessão expirou. Entre de novo e repita.'));
    exit;
}

$r = painel_config_validar($_POST);

if ($r['erros'] !== []) {
    auth_iniciar();
    $_SESSION['painel_config'] = ['valores' => $_POST, 'erros' => $r['erros']];
    header('Location: ../painel.php?tela=config&erro=' . rawurlencode('Veja os campos marcados abaixo.'));
    exit;
}

foreach ($r['valores'] as $chave => $valor) {
    config_gravar($chave, $valor);
}

header('Location: ../painel.php?tela=config&ok=' . rawurlencode('Configurações salvas.'));
exit;
