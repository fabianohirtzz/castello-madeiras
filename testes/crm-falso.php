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
 * A busca imita o que foi OBSERVADO na conta real em 2026-09-10: uma pessoa
 * com telefone de 11 digitos foi cadastrada, e uma busca com esse mesmo
 * numero prefixado por "55" (13 digitos, o formato com DDI) voltou vazia.
 * Dai foi INFERIDO que "toda busca comecando por 55 volta vazia" - isso NAO
 * foi medido, so o caso de 13 digitos foi. E igualmente plausivel, e mais
 * simples, que a API real so faca comparacao exata de digitos (sem DDI nunca
 * bate com o que foi salvo com DDI), sem nenhum tratamento especial do
 * prefixo "55" em si.
 *
 * Esta fake escolhe de proposito a leitura mais estrita das duas, que e o
 * lado seguro para testar contra: sob comparacao exata, um telefone
 * legitimo de 11 digitos cujo DDD comeca em 55 (Santa Maria, RS, onde a
 * Castello tambem vende) seria encontrado pela API de verdade, mas nunca por
 * esta fake. Ou seja, a fake pode reprovar buscas que a API real aceitaria -
 * nunca o contrario. E por isso que ela continua servindo de teste mesmo sem
 * a segunda medicao.
 *
 * Falhas pelo cabecalho X-Falso-Modo: erro500, auth401, limite429, invalido,
 * demora:<segundos>. Qualquer modo pode ser escopado a um metodo com
 * @METODO (ex.: auth401@GET), para falhar so aquela chamada da cadeia e
 * deixar as outras passarem normalmente - e o que prova que a busca de
 * duplicata tolera falha sem contaminar a criacao que vem depois.
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

$modoCompleto = $cabecalhos['x-falso-modo'] ?? '';
$modo = $modoCompleto;
$escopoMetodo = null;
if (str_contains($modoCompleto, '@')) {
    [$modo, $escopoMetodo] = explode('@', $modoCompleto, 2);
}
/* Sem @METODO o modo vale para qualquer chamada, como sempre foi. Com
   @METODO, so essa chamada falha; as outras da cadeia seguem normais. */
$modoVale = $escopoMetodo === null || $escopoMetodo === '' || strtoupper($escopoMetodo) === $metodo;

if ($modoVale && $modo === 'erro500') {
    falso_responder(500, ['errors' => ['erro interno']]);
    return;
}
if ($modoVale && $modo === 'auth401') {
    falso_responder(401, ['errors' => ['Token could not be authenticated']]);
    return;
}
if ($modoVale && $modo === 'limite429') {
    falso_responder(429, ['errors' => ['Too many requests']]);
    return;
}
if ($modoVale && $modo === 'invalido') {
    http_response_code(200);
    header('Content-Type: text/html; charset=utf-8');
    echo '<html><body>Manutencao programada. Volte mais tarde.</body></html>';
    return;
}
if ($modoVale && str_starts_with($modo, 'demora:')) {
    sleep(max(1, min(30, (int) substr($modo, 7))));
}

/* ---------- rotas ---------- */

$pessoas = falso_ler(FALSO_PESSOAS);

if ($metodo === 'GET' && $caminho === '/people') {
    $procurado = preg_replace('/\D+/', '', (string) ($_GET['phone'] ?? '')) ?? '';

    /* So o caso de 13 digitos com "55" na frente foi medido de verdade contra
       a conta real; rejeitar TODA busca comecando por 55 e uma inferencia a
       partir dele, nao uma segunda medicao. Ver o comentario no topo do
       arquivo para a diferenca entre o que foi observado e o que foi
       deduzido, e por que a fake fica do lado estrito de proposito. */
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

    $nome = (string) ($entrada['name'] ?? '');
    $id = 70000000 + count($pessoas) + 1;

    /* So grava no estado compartilhado quando ha nome ou telefone de
       verdade. O modo demora (acima) nao interrompe a rota depois de
       dormir, de proposito: um teste de orcamento de tempo precisa que a
       chamada lenta ainda crie a pessoa de verdade quando o corpo e real.
       Mas uma chamada usada so para medir tempo, com corpo vazio, nao pode
       deixar um registro fantasma (sem nome, sem telefone) no arquivo de
       estado - isso contaminaria contagem de id e buscas de quem rodar
       depois na mesma rodada. */
    if ($nome !== '' || $telefones !== []) {
        $pessoas[] = ['id' => $id, 'name' => $nome, 'telefones' => $telefones];
        falso_gravar(FALSO_PESSOAS, $pessoas);
    }

    falso_responder(201, ['data' => ['id' => $id, 'name' => $nome]]);
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
