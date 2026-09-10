<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../tabelas.php';

auth_exigir();

if (!csrf_validar($_POST['csrf'] ?? null)) {
    header('Location: ../painel.php?tela=backup&erro=' . rawurlencode('A sessão expirou. Entre de novo e repita.'));
    exit;
}

$r = painel_backup();

if (!$r['ok']) {
    header('Location: ../painel.php?tela=backup&erro=' . rawurlencode((string) $r['erro']));
    exit;
}

$arquivo = (string) $r['arquivo'];
$nome    = 'castello-backup-' . date('Y-m-d') . '.zip';

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $nome . '"');
header('Content-Length: ' . (string) filesize($arquivo));
header('Cache-Control: private, no-store');

readfile($arquivo);
unlink($arquivo);
exit;
