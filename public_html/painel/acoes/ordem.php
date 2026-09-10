<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../tabelas.php';

header('Content-Type: application/json; charset=utf-8');

if (!auth_logado()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'erro' => 'sessao']);
    exit;
}

$corpo = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($corpo)) {
    $corpo = $_POST;
}

if (!csrf_validar(isset($corpo['csrf']) ? (string) $corpo['csrf'] : null)) {
    http_response_code(419);
    echo json_encode(['ok' => false, 'erro' => 'csrf']);
    exit;
}

$tela = (string) ($corpo['tela'] ?? '');
if (painel_tabela($tela) === null) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'erro' => 'tela']);
    exit;
}

$ids = is_array($corpo['ids'] ?? null) ? $corpo['ids'] : [];

echo json_encode(['ok' => true, 'atualizados' => painel_reordenar($tela, $ids)]);
