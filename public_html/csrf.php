<?php
declare(strict_types=1);

// require_once, e nao require: numa requisicao web da no mesmo, mas o smoke
// test renderiza este arquivo varias vezes no mesmo processo, e um require
// simples redeclararia as funcoes de lib/auth.php.
require_once __DIR__ . '/lib/auth.php';

auth_iniciar();

// Numa requisicao web nenhum cabecalho foi enviado ainda, entao os dois
// saem sempre. A guarda existe para o smoke test, que renderiza este arquivo
// depois de ja ter impresso na linha de comando.
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store');
}

echo json_encode(['token' => csrf_token()]);
