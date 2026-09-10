<?php
declare(strict_types=1);

/**
 * Smoke da frente 3 (formulario, CRM, e-mail, reenvio).
 * Rodar: php testes/smoke-f3.php
 * Na Tarefa 9 este arquivo e fundido em testes/smoke.php.
 */

require_once __DIR__ . '/apoio-f1.php';

if (!function_exists('t_ok')) {
    $GLOBALS['t_total'] = 0;
    $GLOBALS['t_falhas'] = 0;

    function t_secao(string $titulo): void
    {
        echo PHP_EOL, '== ', $titulo, ' ==', PHP_EOL;
    }

    function t_ok(string $nome, bool $condicao, string $detalhe = ''): void
    {
        $GLOBALS['t_total']++;
        if ($condicao) {
            echo '  ok    ', $nome, PHP_EOL;
            return;
        }
        $GLOBALS['t_falhas']++;
        echo '  FALHA ', $nome, ($detalhe !== '' ? '  ->  ' . $detalhe : ''), PHP_EOL;
    }

    function t_igual(string $nome, $esperado, $obtido): void
    {
        t_ok(
            $nome,
            $esperado === $obtido,
            'esperado ' . var_export($esperado, true) . ', obtido ' . var_export($obtido, true)
        );
    }

    function t_resumo(): int
    {
        $passaram = $GLOBALS['t_total'] - $GLOBALS['t_falhas'];
        echo PHP_EOL, $passaram, '/', $GLOBALS['t_total'], ' passaram', PHP_EOL;
        return $GLOBALS['t_falhas'] > 0 ? 1 : 0;
    }
}

teste_banco_apagar();

t_secao('Apoio da frente 1');
t_ok('db() abre o banco', db() instanceof PDO);
t_ok('tabela leads existe', (bool) db()->query("SELECT name FROM sqlite_master WHERE name = 'leads'")->fetchColumn());
t_ok('tabela config existe', (bool) db()->query("SELECT name FROM sqlite_master WHERE name = 'config'")->fetchColumn());
config_gravar('crm_ativo', '1');
t_igual('config_gravar e config_ler', '1', config_ler('crm_ativo'));
t_igual('config_ler devolve o padrao', 'zero', config_ler('nao_existe', 'zero'));
t_ok('agora() no formato Y-m-d H:i:s', (bool) preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', agora()));
t_ok('csrf_validar aceita o token bom', csrf_validar(csrf_token()));
t_ok('csrf_validar recusa token ruim', !csrf_validar('outro'));
t_igual('e() escapa aspas', '&quot;', e('"'));

require_once __DIR__ . '/../public_html/lib/leads.php';

t_secao('lib/leads.php: gravacao e marcacao');
teste_banco_limpar();

$id = lead_gravar([
    'nome'         => '  Fabiano Hirtz  ',
    'whatsapp'     => '(48) 99824-4494',
    'busca'        => 'Modelo pronto do catalogo',
    'modelo'       => 'Compacta 39 m2',
    'cidade'       => 'Tubarao / SC',
    'mensagem'     => 'Tenho terreno em Tubarao.',
    'pagina'       => '/index.php',
    'referrer'     => 'https://www.google.com/',
    'utm_source'   => 'instagram',
    'utm_medium'   => 'social',
    'utm_campaign' => 'flex-setembro',
    'utm_term'     => 'casa de madeira',
    'utm_content'  => 'reel-03',
]);
t_ok('lead_gravar devolve um id positivo', $id > 0, 'id = ' . var_export($id, true));

$linha = db()->query('SELECT * FROM leads WHERE id = ' . (int) $id)->fetch(PDO::FETCH_ASSOC);
t_igual('nome vem sem espaco nas pontas', 'Fabiano Hirtz', $linha['nome']);
t_igual('whatsapp gravado como veio', '(48) 99824-4494', $linha['whatsapp']);
t_igual('utm_campaign gravada', 'flex-setembro', $linha['utm_campaign']);
t_igual('pagina gravada', '/index.php', $linha['pagina']);
t_igual('status inicial e pendente', 'pendente', $linha['crm_status']);
t_igual('tentativas comecam em zero', 0, (int) $linha['crm_tentativas']);
t_ok('criado_em no formato certo', (bool) preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $linha['criado_em']));
t_ok('crm_ultima_tentativa nasce nula', $linha['crm_ultima_tentativa'] === null);

$vazio = lead_gravar(['nome' => 'So o nome']);
$linhaVazia = db()->query('SELECT * FROM leads WHERE id = ' . (int) $vazio)->fetch(PDO::FETCH_ASSOC);
t_igual('campo ausente vira string vazia', '', $linhaVazia['cidade']);
t_igual('utm ausente vira string vazia', '', $linhaVazia['utm_source']);

$longo = lead_gravar(['nome' => str_repeat('a', 900), 'mensagem' => str_repeat('b', 9000)]);
$linhaLonga = db()->query('SELECT * FROM leads WHERE id = ' . (int) $longo)->fetch(PDO::FETCH_ASSOC);
t_igual('nome longo cortado em 500', 500, mb_strlen((string) $linhaLonga['nome']));
t_igual('mensagem longa cortada em 4000', 4000, mb_strlen((string) $linhaLonga['mensagem']));

$acentos = lead_gravar(['nome' => 'Joao Gonçalves da Conceição']);
$linhaAcentos = db()->query('SELECT * FROM leads WHERE id = ' . (int) $acentos)->fetch(PDO::FETCH_ASSOC);
t_igual('acento sobrevive ao banco', 'Joao Gonçalves da Conceição', $linhaAcentos['nome']);

lead_marcar($id, 'enviado', 1, '{"status":"ok"}');
$marcado = db()->query('SELECT * FROM leads WHERE id = ' . (int) $id)->fetch(PDO::FETCH_ASSOC);
t_igual('lead_marcar grava o status', 'enviado', $marcado['crm_status']);
t_igual('lead_marcar grava as tentativas', 1, (int) $marcado['crm_tentativas']);
t_igual('lead_marcar grava a resposta', '{"status":"ok"}', $marcado['crm_resposta']);
t_ok('lead_marcar carimba a hora', (bool) preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $marcado['crm_ultima_tentativa']));

lead_marcar($id, 'status_inventado', 2, null);
$corrigido = db()->query('SELECT * FROM leads WHERE id = ' . (int) $id)->fetch(PDO::FETCH_ASSOC);
t_igual('status invalido vira erro', 'erro', $corrigido['crm_status']);
t_ok('resposta nula limpa o campo', $corrigido['crm_resposta'] === null);

lead_marcar($id, 'erro', 1, str_repeat('x', 5000));
$cortado = db()->query('SELECT * FROM leads WHERE id = ' . (int) $id)->fetch(PDO::FETCH_ASSOC);
t_igual('resposta longa cortada em 2000', 2000, mb_strlen((string) $cortado['crm_resposta']));

/* ---------- servidor de teste que finge ser o CRM ---------- */

const CRM_FALSO_ULTIMA = '/crm-falso-ultima.json';

/* Cada secao sobe o servidor numa porta nova, para nao esbarrar em porta
   presa em TIME_WAIT logo depois de derrubar a anterior. */
$GLOBALS['crm_falso_porta'] = 8765;

function crm_falso_porta(): int
{
    return (int) $GLOBALS['crm_falso_porta'];
}

function crm_falso_url(string $modo, array $extra = []): string
{
    return 'http://127.0.0.1:' . crm_falso_porta() . '/?' . http_build_query(['modo' => $modo] + $extra);
}

function crm_falso_arquivo(): string
{
    return sys_get_temp_dir() . CRM_FALSO_ULTIMA;
}

function crm_falso_ultima(): array
{
    $bruto = is_file(crm_falso_arquivo()) ? (string) file_get_contents(crm_falso_arquivo()) : '';
    $dados = json_decode($bruto, true);
    return is_array($dados) ? $dados : [];
}

function crm_falso_subir(?int $porta = null)
{
    if ($porta === null) {
        $porta = crm_falso_porta();
    }
    $GLOBALS['crm_falso_porta'] = $porta;
    @unlink(crm_falso_arquivo());
    $comando = escapeshellarg(PHP_BINARY) . ' -S 127.0.0.1:' . $porta . ' ' . escapeshellarg(__DIR__ . '/crm-falso.php');
    $tubos = [];
    /* bypass_shell e obrigatorio no Windows: sem ele o proc_open passa pelo
       cmd.exe, o proc_terminate mata so o cmd e o php -S fica orfao, segurando
       a porta e os handles herdados, o que trava quem espera o fim do smoke. */
    $processo = proc_open(
        $comando,
        [0 => ['pipe', 'r'], 1 => ['file', sys_get_temp_dir() . '/crm-falso-saida.log', 'a'], 2 => ['file', sys_get_temp_dir() . '/crm-falso-saida.log', 'a']],
        $tubos,
        null,
        null,
        ['bypass_shell' => true]
    );
    if (!is_resource($processo)) {
        return null;
    }
    for ($i = 0; $i < 100; $i++) {
        $socket = @fsockopen('127.0.0.1', $porta, $erroNum, $erroTexto, 0.2);
        if (is_resource($socket)) {
            fclose($socket);
            return $processo;
        }
        usleep(100000);
    }
    proc_terminate($processo);
    return null;
}

function crm_falso_derrubar($processo): void
{
    if (is_resource($processo)) {
        proc_terminate($processo);
        proc_close($processo);
    }
    $GLOBALS['crm_falso_porta'] = crm_falso_porta() + 1;
}

function crm_falso_pedir(string $url, string $corpo = '{}', array $cabecalhos = []): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $corpo,
        CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json'], $cabecalhos),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
    ]);
    $resposta = (string) curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $tipo = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    return ['http' => $http, 'corpo' => $resposta, 'tipo' => $tipo];
}

t_secao('testes/crm-falso.php');
$servidor = crm_falso_subir();
t_ok('servidor de teste subiu na porta ' . crm_falso_porta(), $servidor !== null);

if ($servidor !== null) {
    $r = crm_falso_pedir(crm_falso_url('ok'));
    t_igual('modo ok responde 200', 200, $r['http']);
    $j = json_decode($r['corpo'], true);
    t_ok('modo ok devolve JSON com status ok', is_array($j) && ($j['status'] ?? '') === 'ok', $r['corpo']);

    $r = crm_falso_pedir(crm_falso_url('erro500'));
    t_igual('modo erro500 responde 500', 500, $r['http']);

    $r = crm_falso_pedir(crm_falso_url('invalido'));
    t_igual('modo invalido responde 200', 200, $r['http']);
    t_ok('modo invalido devolve corpo que nao e JSON', json_decode($r['corpo'], true) === null, $r['corpo']);

    $inicio = microtime(true);
    $r = crm_falso_pedir(crm_falso_url('demora', ['seg' => 2]));
    $gasto = microtime(true) - $inicio;
    t_ok('modo demora segura a resposta', $gasto >= 1.8, 'gastou ' . round($gasto, 2) . 's');
    t_igual('modo demora responde 200 no fim', 200, $r['http']);

    $r = crm_falso_pedir(crm_falso_url('eco'), '{"nome":"Fabiano"}', ['Authorization: Bearer segredo-123']);
    $j = json_decode($r['corpo'], true);
    t_ok('modo eco devolve o que recebeu', is_array($j) && (($j['recebido']['nome'] ?? '') === 'Fabiano'), $r['corpo']);

    $ultima = crm_falso_ultima();
    t_igual('registrou o metodo', 'POST', $ultima['metodo'] ?? '');
    t_ok(
        'registrou o cabecalho Authorization',
        ($ultima['cabecalhos']['authorization'] ?? '') === 'Bearer segredo-123',
        json_encode($ultima['cabecalhos'] ?? [])
    );

    crm_falso_derrubar($servidor);
}

require_once __DIR__ . '/../public_html/lib/crm.php';

t_secao('lib/crm.php: o conector');
teste_banco_limpar();

$leadExemplo = [
    'nome'         => 'Fabiano Hirtz',
    'whatsapp'     => '(48) 99824-4494',
    'busca'        => 'Modelo pronto do catalogo',
    'modelo'       => 'Compacta 39 m2',
    'cidade'       => 'Tubarao / SC',
    'mensagem'     => 'Tenho terreno.',
    'pagina'       => '/index.php',
    'utm_source'   => 'instagram',
    'utm_campaign' => 'flex-setembro',
];

$mapaPadrao = '{"nome":"nome","whatsapp":"telefone","busca":"interesse","modelo":"modelo","cidade":"cidade","mensagem":"observacao","utm_source":"origem","utm_campaign":"campanha","pagina":"pagina"}';

/* CRM desligado: nao faz requisicao nenhuma */
config_gravar('crm_ativo', '0');
config_gravar('crm_endpoint', crm_falso_url('ok'));
config_gravar('crm_mapa_campos', $mapaPadrao);
$r = crm_enviar($leadExemplo);
t_ok('CRM desligado: ok false', $r['ok'] === false);
t_igual('CRM desligado: erro crm_desativado', 'crm_desativado', $r['erro']);
t_igual('CRM desligado: http zero', 0, $r['http']);
t_igual('CRM desligado: resposta vazia', '', $r['resposta']);

/* endpoint vazio */
config_gravar('crm_ativo', '1');
config_gravar('crm_endpoint', '');
$r = crm_enviar($leadExemplo);
t_igual('endpoint vazio: erro crm_sem_endpoint', 'crm_sem_endpoint', $r['erro']);

/* mapa de campos invalido */
config_gravar('crm_endpoint', crm_falso_url('ok'));
config_gravar('crm_mapa_campos', 'isso nao e json');
$r = crm_enviar($leadExemplo);
t_igual('mapa quebrado: erro crm_mapa_invalido', 'crm_mapa_invalido', $r['erro']);

/* cabecalhos invalidos */
config_gravar('crm_mapa_campos', $mapaPadrao);
config_gravar('crm_cabecalhos', '{quebrado');
$r = crm_enviar($leadExemplo);
t_igual('cabecalhos quebrados: erro crm_cabecalhos_invalidos', 'crm_cabecalhos_invalidos', $r['erro']);
config_gravar('crm_cabecalhos', '{"Authorization":"Bearer segredo-123"}');

$servidor = crm_falso_subir();
t_ok('servidor do CRM falso subiu de novo', $servidor !== null);

if ($servidor !== null) {
    /* CRM responde 200 */
    config_gravar('crm_endpoint', crm_falso_url('ok'));
    $r = crm_enviar($leadExemplo);
    t_ok('CRM 200: ok true', $r['ok'] === true, json_encode($r));
    t_igual('CRM 200: http 200', 200, $r['http']);
    t_ok('CRM 200: erro nulo', $r['erro'] === null);
    t_ok('CRM 200: guarda a resposta bruta', strpos($r['resposta'], 'CRM-') !== false, $r['resposta']);

    /* o mapa de campos e o cabecalho chegaram certos */
    config_gravar('crm_endpoint', crm_falso_url('eco'));
    $r = crm_enviar($leadExemplo);
    $ultima = crm_falso_ultima();
    $payload = json_decode($ultima['corpo'] ?? '', true);
    t_igual('mapa: whatsapp virou telefone', '(48) 99824-4494', $payload['telefone'] ?? null);
    t_igual('mapa: busca virou interesse', 'Modelo pronto do catalogo', $payload['interesse'] ?? null);
    t_igual('mapa: utm_source virou origem', 'instagram', $payload['origem'] ?? null);
    t_igual('mapa: mensagem virou observacao', 'Tenho terreno.', $payload['observacao'] ?? null);
    t_ok('mapa nao manda campo fora do mapa', !array_key_exists('utm_medium', (array) $payload));
    t_igual('cabecalho de autorizacao chegou', 'Bearer segredo-123', $ultima['cabecalhos']['authorization'] ?? null);
    t_ok(
        'content-type e json',
        strpos((string) ($ultima['cabecalhos']['content-type'] ?? ''), 'application/json') === 0,
        (string) ($ultima['cabecalhos']['content-type'] ?? '')
    );

    /* campo do mapa que o lead nao tem vira string vazia, nao some */
    $payloadMagro = null;
    $r = crm_enviar(['nome' => 'So o nome', 'whatsapp' => '48999999999', 'busca' => 'Ainda pesquisando']);
    $payloadMagro = json_decode(crm_falso_ultima()['corpo'] ?? '', true);
    t_igual('campo ausente vai vazio', '', $payloadMagro['cidade'] ?? null);

    /* CRM responde 500 */
    config_gravar('crm_endpoint', crm_falso_url('erro500'));
    $r = crm_enviar($leadExemplo);
    t_ok('CRM 500: ok false', $r['ok'] === false);
    t_igual('CRM 500: http 500', 500, $r['http']);
    t_igual('CRM 500: erro crm_http', 'crm_http', $r['erro']);
    t_ok('CRM 500: guarda o corpo do erro', strpos($r['resposta'], 'interno') !== false, $r['resposta']);

    /* CRM responde algo que nao e JSON */
    config_gravar('crm_endpoint', crm_falso_url('invalido'));
    $r = crm_enviar($leadExemplo);
    t_ok('CRM invalido: ok false', $r['ok'] === false);
    t_igual('CRM invalido: http 200', 200, $r['http']);
    t_igual('CRM invalido: erro crm_resposta_invalida', 'crm_resposta_invalida', $r['erro']);
    t_ok('CRM invalido: guarda o corpo pra diagnostico', strpos($r['resposta'], 'Manutencao') !== false, $r['resposta']);

    /* CRM estoura o tempo */
    config_gravar('crm_timeout', '2');
    config_gravar('crm_endpoint', crm_falso_url('demora', ['seg' => 8]));
    $inicio = microtime(true);
    $r = crm_enviar($leadExemplo);
    $gasto = microtime(true) - $inicio;
    t_ok('CRM lento: ok false', $r['ok'] === false);
    t_igual('CRM lento: erro crm_tempo', 'crm_tempo', $r['erro']);
    t_ok('CRM lento: desistiu perto do limite', $gasto < 6, 'gastou ' . round($gasto, 2) . 's');
    config_gravar('crm_timeout', '10');

    crm_falso_derrubar($servidor);

    /* servidor fora do ar: erro de conexao, nao de tempo */
    config_gravar('crm_endpoint', 'http://127.0.0.1:8799/nada');
    $r = crm_enviar($leadExemplo);
    t_ok('CRM fora do ar: ok false', $r['ok'] === false);
    t_igual('CRM fora do ar: erro crm_conexao', 'crm_conexao', $r['erro']);
}

exit(t_resumo());
