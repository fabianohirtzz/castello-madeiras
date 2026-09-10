<?php
declare(strict_types=1);

/**
 * CRM de mentira, para testar lib/crm.php sem credencial real.
 *
 * Subir:  php -S 127.0.0.1:8765 testes/crm-falso.php
 *
 * Modos, pelo parametro modo da URL:
 *   ok        200 com JSON de sucesso                (padrao)
 *   erro500   500 com JSON de erro
 *   invalido  200 com HTML no corpo, para provar o tratamento de resposta invalida
 *   demora    dorme seg segundos (padrao 5) e responde 200, para estourar o tempo
 *   eco       200 devolvendo o payload recebido, para conferir o mapa de campos
 *
 * Toda requisicao e registrada em sys_get_temp_dir()/crm-falso-ultima.json.
 */

$corpo = (string) file_get_contents('php://input');

$cabecalhos = [];
foreach ($_SERVER as $chave => $valor) {
    if (strpos($chave, 'HTTP_') === 0) {
        $nome = strtolower(str_replace('_', '-', substr($chave, 5)));
        $cabecalhos[$nome] = (string) $valor;
    }
}
if (isset($_SERVER['CONTENT_TYPE'])) {
    $cabecalhos['content-type'] = (string) $_SERVER['CONTENT_TYPE'];
}

file_put_contents(
    sys_get_temp_dir() . '/crm-falso-ultima.json',
    json_encode([
        'metodo'     => $_SERVER['REQUEST_METHOD'] ?? '',
        'uri'        => $_SERVER['REQUEST_URI'] ?? '',
        'cabecalhos' => $cabecalhos,
        'corpo'      => $corpo,
        'quando'     => date('Y-m-d H:i:s'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
);

$modo = isset($_GET['modo']) ? (string) $_GET['modo'] : 'ok';

if ($modo === 'erro500') {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['erro' => 'interno', 'detalhe' => 'o CRM caiu']);
    return;
}

if ($modo === 'invalido') {
    http_response_code(200);
    header('Content-Type: text/html; charset=utf-8');
    echo '<html><body>Manutencao programada. Volte mais tarde.</body></html>';
    return;
}

if ($modo === 'demora') {
    $segundos = isset($_GET['seg']) ? (int) $_GET['seg'] : 5;
    $segundos = max(1, min(30, $segundos));
    sleep($segundos);
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'ok', 'demorou' => $segundos]);
    return;
}

if ($modo === 'eco') {
    $recebido = json_decode($corpo, true);
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(
        ['status' => 'ok', 'recebido' => is_array($recebido) ? $recebido : null],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    return;
}

http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['status' => 'ok', 'id' => 'CRM-' . substr(md5($corpo), 0, 8)]);
