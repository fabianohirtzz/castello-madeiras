<?php
declare(strict_types=1);

/**
 * Agendor de mentira, para testar lib/crm.php sem tocar na conta do cliente.
 *
 * Subir:  php -S 127.0.0.1:8765 testes/crm-falso.php
 *
 * Rotas imitadas:
 *   GET  /people?phone=<digitos>   busca de duplicata
 *   POST /people                   cria pessoa,  201 {"data":{"id":N}}
 *   POST /people/<id>/deals        cria negocio, 201 {"data":{"id":M,"_webUrl":...}}
 *
 * A busca imita o comportamento MEDIDO na conta real em 2026-09-10: encontra
 * pelos digitos sem DDI e devolve vazio quando a busca chega com 55 na frente.
 * E justamente isso que o conector precisa acertar.
 *
 * Falhas pelo cabecalho X-Falso-Modo: erro500, auth401, limite429, invalido,
 * demora:<segundos>.
 *
 * Estado das pessoas em sys_get_temp_dir()/crm-falso-pessoas.json, para a
 * pessoa criada numa requisicao ser encontrada na seguinte.
 */

const FALSO_PESSOAS = '/crm-falso-pessoas.json';
const FALSO_ULTIMA  = '/crm-falso-ultima.json';
const FALSO_TODAS   = '/crm-falso-todas.json';

function falso_arquivo(string $nome): string
{
    return sys_get_temp_dir() . $nome;
}

function falso_ler(string $nome): array
{
    $bruto = is_file(falso_arquivo($nome)) ? (string) file_get_contents(falso_arquivo($nome)) : '';
    $dados = json_decode($bruto, true);
    return is_array($dados) ? $dados : [];
}

function falso_gravar(string $nome, array $dados): void
{
    file_put_contents(
        falso_arquivo($nome),
        (string) json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
    );
}

function falso_responder(int $http, array $corpo): void
{
    http_response_code($http);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($corpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/* ---------- registro da requisicao ---------- */

$corpo = (string) file_get_contents('php://input');

$cabecalhos = [];
foreach ($_SERVER as $chave => $valor) {
    if (strpos($chave, 'HTTP_') === 0) {
        $cabecalhos[strtolower(str_replace('_', '-', substr($chave, 5)))] = (string) $valor;
    }
}
if (isset($_SERVER['CONTENT_TYPE'])) {
    $cabecalhos['content-type'] = (string) $_SERVER['CONTENT_TYPE'];
}

$metodo  = (string) ($_SERVER['REQUEST_METHOD'] ?? '');
$uri     = (string) ($_SERVER['REQUEST_URI'] ?? '');
$caminho = (string) parse_url($uri, PHP_URL_PATH);

$registro = [
    'metodo'     => $metodo,
    'uri'        => $uri,
    'caminho'    => $caminho,
    'cabecalhos' => $cabecalhos,
    'corpo'      => $corpo,
    'quando'     => date('Y-m-d H:i:s'),
];
falso_gravar(FALSO_ULTIMA, $registro);
$todas = falso_ler(FALSO_TODAS);
$todas[] = $registro;
falso_gravar(FALSO_TODAS, $todas);

/* ---------- modos de falha ---------- */

$modo = $cabecalhos['x-falso-modo'] ?? '';

if ($modo === 'erro500') {
    falso_responder(500, ['errors' => ['erro interno']]);
    return;
}
if ($modo === 'auth401') {
    falso_responder(401, ['errors' => ['Token could not be authenticated']]);
    return;
}
if ($modo === 'limite429') {
    falso_responder(429, ['errors' => ['Too many requests']]);
    return;
}
if ($modo === 'invalido') {
    http_response_code(200);
    header('Content-Type: text/html; charset=utf-8');
    echo '<html><body>Manutencao programada. Volte mais tarde.</body></html>';
    return;
}
if (str_starts_with($modo, 'demora:')) {
    sleep(max(1, min(30, (int) substr($modo, 7))));
}

/* ---------- rotas ---------- */

$pessoas = falso_ler(FALSO_PESSOAS);

if ($metodo === 'GET' && $caminho === '/people') {
    $procurado = preg_replace('/\D+/', '', (string) ($_GET['phone'] ?? '')) ?? '';

    /* Comportamento medido na conta real: com 55 na frente nao acha nada. */
    $achadas = [];
    if ($procurado !== '' && !str_starts_with($procurado, '55')) {
        foreach ($pessoas as $pessoa) {
            if (in_array($procurado, $pessoa['telefones'] ?? [], true)) {
                $achadas[] = ['id' => $pessoa['id'], 'name' => $pessoa['name']];
            }
        }
    }
    falso_responder(200, ['data' => $achadas]);
    return;
}

if ($metodo === 'POST' && $caminho === '/people') {
    $entrada = json_decode($corpo, true);
    $entrada = is_array($entrada) ? $entrada : [];

    /* Guarda todo telefone que veio, em digitos, para a busca seguinte. */
    $telefones = [];
    foreach (['whatsapp', 'mobile', 'work'] as $campo) {
        $valor = (string) ($entrada['contact'][$campo] ?? '');
        $digitos = preg_replace('/\D+/', '', $valor) ?? '';
        if ($digitos !== '') {
            $telefones[] = $digitos;
        }
    }

    $id = 70000000 + count($pessoas) + 1;
    $pessoas[] = ['id' => $id, 'name' => (string) ($entrada['name'] ?? ''), 'telefones' => $telefones];
    falso_gravar(FALSO_PESSOAS, $pessoas);

    falso_responder(201, ['data' => ['id' => $id, 'name' => (string) ($entrada['name'] ?? '')]]);
    return;
}

if ($metodo === 'POST' && preg_match('#^/people/(\d+)/deals$#', $caminho, $partes) === 1) {
    $entrada = json_decode($corpo, true);
    $id = 90000000 + count(falso_ler(FALSO_TODAS));
    falso_responder(201, ['data' => [
        'id'      => $id,
        'title'   => (string) (is_array($entrada) ? ($entrada['title'] ?? '') : ''),
        '_webUrl' => 'https://web.agendor.com.br/negocio/' . $id,
    ]]);
    return;
}

falso_responder(404, ['errors' => ['rota nao encontrada: ' . $metodo . ' ' . $caminho]]);
