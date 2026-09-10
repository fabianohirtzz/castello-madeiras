<?php
declare(strict_types=1);

/**
 * Caso da frente 3: formulario, CRM, e-mail e reenvio.
 *
 * Roda no banco temporario do runner, com a lib real da frente 1. O CRM e
 * simulado por testes/crm-falso.php, subido num php -S proprio.
 */

require_once site() . '/lib/auth.php';
require_once site() . '/lib/leads.php';
require_once site() . '/lib/crm.php';
require_once site() . '/lib/email.php';
require_once site() . '/enviar.php';

/** Esvazia leads e config, mantendo a conexao aberta. */
function crm_teste_limpar(): void
{
    db()->exec('DELETE FROM leads');
    db()->exec('DELETE FROM config');
}

function contar_leads(): int
{
    return (int) db()->query('SELECT COUNT(*) FROM leads')->fetchColumn();
}

function lead_por_id(int $id): array
{
    $linha = db()->query('SELECT * FROM leads WHERE id = ' . $id)->fetch(PDO::FETCH_ASSOC);
    return is_array($linha) ? $linha : [];
}

const MAPA_PADRAO = '{"nome":"nome","whatsapp":"telefone","busca":"interesse","modelo":"modelo","cidade":"cidade","mensagem":"observacao","utm_source":"origem","utm_campaign":"campanha","pagina":"pagina"}';

const LEAD_EXEMPLO = [
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

/* ---------- servidor de teste que finge ser o CRM ---------- */

const CRM_FALSO_ULTIMA = '/crm-falso-ultima.json';

/* Cada bloco sobe o servidor numa porta nova, para nao esbarrar em porta
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

function crm_falso_todas(): array
{
    $arquivo = sys_get_temp_dir() . '/crm-falso-todas.json';
    $dados = json_decode(is_file($arquivo) ? (string) file_get_contents($arquivo) : '', true);
    return is_array($dados) ? $dados : [];
}

function crm_falso_subir(?int $porta = null)
{
    if ($porta === null) {
        $porta = crm_falso_porta();
    }
    $GLOBALS['crm_falso_porta'] = $porta;
    /* Limpa o estado anterior. A pessoa criada numa requisicao precisa ser
       encontrada na requisicao seguinte dentro do mesmo bloco, mas nao pode
       vazar para o bloco seguinte. */
    @unlink(crm_falso_arquivo());
    @unlink(sys_get_temp_dir() . '/crm-falso-todas.json');
    @unlink(sys_get_temp_dir() . '/crm-falso-pessoas.json');
    $comando = escapeshellarg(PHP_BINARY) . ' -S 127.0.0.1:' . $porta . ' ' . escapeshellarg(raiz() . '/testes/crm-falso.php');
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

function crm_falso_pedir(string $url, string $corpo = '{}', array $cabecalhos = [], string $metodo = 'POST'): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $metodo,
        CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json'], $cabecalhos),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
    ]);
    if ($metodo !== 'GET') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $corpo);
    }
    $resposta = (string) curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $tipo = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    return ['http' => $http, 'corpo' => $resposta, 'tipo' => $tipo];
}

function post_valido(array $troca = []): array
{
    return array_merge([
        'nome'         => 'Fabiano Hirtz',
        'whatsapp'     => '(48) 99824-4494',
        'busca'        => 'Modelo pronto do catalogo',
        'modelo'       => 'Compacta 39 m2',
        'cidade'       => 'Tubarao / SC',
        'mensagem'     => 'Tenho terreno.',
        'pagina'       => '/index.php',
        'referrer'     => 'https://www.google.com/',
        'utm_source'   => 'instagram',
        'utm_medium'   => 'social',
        'utm_campaign' => 'flex-setembro',
        'utm_term'     => '',
        'utm_content'  => 'reel-03',
        'empresa'      => '',
        'ts'           => (string) (int) round((microtime(true) - 10) * 1000),
        'csrf'         => csrf_token(),
    ], $troca);
}

function semear_lead(string $nome, string $status, int $tentativas): int
{
    $id = lead_gravar([
        'nome'     => $nome,
        'whatsapp' => '48998244494',
        'busca'    => 'Ainda estou pesquisando',
    ]);
    if ($status !== 'pendente' || $tentativas !== 0) {
        lead_marcar($id, $status, $tentativas, null);
    }
    return $id;
}

/* ================= lib/leads.php: gravacao e marcacao ================= */

teste('lead_gravar grava o lead inteiro com status pendente', function (): void {
    crm_teste_limpar();
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
    verdade($id > 0, 'lead_gravar devolve um id positivo');

    $linha = lead_por_id($id);
    igual('Fabiano Hirtz', $linha['nome'], 'nome vem sem espaco nas pontas');
    igual('(48) 99824-4494', $linha['whatsapp'], 'whatsapp gravado como veio');
    igual('flex-setembro', $linha['utm_campaign']);
    igual('/index.php', $linha['pagina']);
    igual('pendente', $linha['crm_status']);
    igual(0, (int) $linha['crm_tentativas']);
    verdade((bool) preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $linha['criado_em']), 'criado_em no formato certo');
    verdade($linha['crm_ultima_tentativa'] === null, 'crm_ultima_tentativa nasce nula');
});

teste('lead_gravar trata campo ausente, texto longo e acento', function (): void {
    $vazio = lead_por_id(lead_gravar(['nome' => 'So o nome']));
    igual('', $vazio['cidade'], 'campo ausente vira string vazia');
    igual('', $vazio['utm_source'], 'utm ausente vira string vazia');

    $longo = lead_por_id(lead_gravar(['nome' => str_repeat('a', 900), 'mensagem' => str_repeat('b', 9000)]));
    igual(500, mb_strlen((string) $longo['nome']), 'nome longo cortado em 500');
    igual(4000, mb_strlen((string) $longo['mensagem']), 'mensagem longa cortada em 4000');

    $acentos = lead_por_id(lead_gravar(['nome' => 'Joao Gonçalves da Conceição']));
    igual('Joao Gonçalves da Conceição', $acentos['nome'], 'acento sobrevive ao banco');
});

teste('lead_marcar grava status, tentativas, resposta e carimbo', function (): void {
    $id = lead_gravar(['nome' => 'Marcar']);

    lead_marcar($id, 'enviado', 1, '{"status":"ok"}');
    $marcado = lead_por_id($id);
    igual('enviado', $marcado['crm_status']);
    igual(1, (int) $marcado['crm_tentativas']);
    igual('{"status":"ok"}', $marcado['crm_resposta']);
    verdade((bool) preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $marcado['crm_ultima_tentativa']), 'lead_marcar carimba a hora');

    lead_marcar($id, 'status_inventado', 2, null);
    $corrigido = lead_por_id($id);
    igual('erro', $corrigido['crm_status'], 'status invalido vira erro');
    verdade($corrigido['crm_resposta'] === null, 'resposta nula limpa o campo');

    lead_marcar($id, 'erro', 1, str_repeat('x', 5000));
    igual(2000, mb_strlen((string) lead_por_id($id)['crm_resposta']), 'resposta longa cortada em 2000');
});

/* ================= testes/crm-falso.php ================= */

teste('o Agendor falso responde as tres rotas e imita a busca por telefone', function (): void {
    $servidor = crm_falso_subir();
    verdade($servidor !== null, 'servidor de teste subiu na porta ' . crm_falso_porta());

    try {
        $base = 'http://127.0.0.1:' . crm_falso_porta();

        /* pessoa nova */
        $r = crm_falso_pedir($base . '/people', '{"name":"Fabiano","contact":{"mobile":"(48) 99824-4494"}}');
        igual(201, $r['http'], 'POST /people responde 201');
        $criada = json_decode($r['corpo'], true);
        verdade(is_int($criada['data']['id'] ?? null), 'devolve data.id inteiro: ' . $r['corpo']);
        $idPessoa = (int) $criada['data']['id'];

        /* busca com os digitos sem DDI: acha */
        $r = crm_falso_pedir($base . '/people?phone=48998244494', '', [], 'GET');
        $achou = json_decode($r['corpo'], true);
        igual(1, count($achou['data'] ?? []), 'busca sem DDI encontra: ' . $r['corpo']);
        igual($idPessoa, (int) ($achou['data'][0]['id'] ?? 0));

        /* busca com 55 na frente: vazio, como na conta real */
        $r = crm_falso_pedir($base . '/people?phone=5548998244494', '', [], 'GET');
        igual(0, count(json_decode($r['corpo'], true)['data'] ?? []), 'busca com DDI volta vazia');

        /* telefone desconhecido: vazio */
        $r = crm_falso_pedir($base . '/people?phone=48900000000', '', [], 'GET');
        igual(0, count(json_decode($r['corpo'], true)['data'] ?? []), 'telefone desconhecido volta vazio');

        /* negocio na pessoa */
        $r = crm_falso_pedir($base . '/people/' . $idPessoa . '/deals', '{"title":"[SITE] - Imediato - Fabiano"}');
        igual(201, $r['http'], 'POST /people/{id}/deals responde 201');
        $negocio = json_decode($r['corpo'], true);
        verdade(is_int($negocio['data']['id'] ?? null), 'negocio tem id');
        contem('web.agendor.com.br', (string) ($negocio['data']['_webUrl'] ?? ''), 'negocio traz _webUrl');

        /* modos de falha */
        igual(500, crm_falso_pedir($base . '/people', '{}', ['X-Falso-Modo: erro500'])['http']);
        igual(401, crm_falso_pedir($base . '/people', '{}', ['X-Falso-Modo: auth401'])['http']);
        igual(429, crm_falso_pedir($base . '/people', '{}', ['X-Falso-Modo: limite429'])['http']);

        $r = crm_falso_pedir($base . '/people', '{}', ['X-Falso-Modo: invalido']);
        verdade(json_decode($r['corpo'], true) === null, 'modo invalido devolve corpo que nao e JSON');

        $inicio = microtime(true);
        crm_falso_pedir($base . '/people', '{}', ['X-Falso-Modo: demora:2']);
        verdade(microtime(true) - $inicio >= 1.8, 'modo demora segura a resposta');

        /* registro */
        $ultima = crm_falso_ultima();
        igual('POST', $ultima['metodo'] ?? '', 'registrou o metodo');
        verdade(count(crm_falso_todas()) >= 8, 'registrou todas as requisicoes da rodada');
    } finally {
        crm_falso_derrubar($servidor);
    }
});

/* ================= lib/crm.php: o conector ================= */

teste('crm_enviar recusa sem fazer requisicao quando a config esta errada', function (): void {
    crm_teste_limpar();

    config_gravar('crm_ativo', '0');
    config_gravar('crm_endpoint', crm_falso_url('ok'));
    config_gravar('crm_mapa_campos', MAPA_PADRAO);
    $r = crm_enviar(LEAD_EXEMPLO);
    falso($r['ok'], 'CRM desligado: ok false');
    igual('crm_desativado', $r['erro']);
    igual(0, $r['http']);
    igual('', $r['resposta']);

    config_gravar('crm_ativo', '1');
    config_gravar('crm_endpoint', '');
    igual('crm_sem_endpoint', crm_enviar(LEAD_EXEMPLO)['erro'], 'endpoint vazio');

    config_gravar('crm_endpoint', crm_falso_url('ok'));
    config_gravar('crm_mapa_campos', 'isso nao e json');
    igual('crm_mapa_invalido', crm_enviar(LEAD_EXEMPLO)['erro'], 'mapa quebrado');

    config_gravar('crm_mapa_campos', MAPA_PADRAO);
    config_gravar('crm_cabecalhos', '{quebrado');
    igual('crm_cabecalhos_invalidos', crm_enviar(LEAD_EXEMPLO)['erro'], 'cabecalhos quebrados');
    config_gravar('crm_cabecalhos', '{"Authorization":"Bearer segredo-123"}');
});

teste('crm_enviar conversa com o CRM: sucesso, mapa, erro, resposta invalida e tempo', function (): void {
    $servidor = crm_falso_subir();
    verdade($servidor !== null, 'servidor do CRM falso subiu de novo');

    try {
        config_gravar('crm_ativo', '1');
        config_gravar('crm_mapa_campos', MAPA_PADRAO);
        config_gravar('crm_cabecalhos', '{"Authorization":"Bearer segredo-123"}');

        /* CRM responde 200 */
        config_gravar('crm_endpoint', crm_falso_url('ok'));
        $r = crm_enviar(LEAD_EXEMPLO);
        verdade($r['ok'] === true, 'CRM 200: ok true: ' . json_encode($r));
        igual(200, $r['http']);
        verdade($r['erro'] === null, 'CRM 200: erro nulo');
        contem('CRM-', $r['resposta'], 'CRM 200: guarda a resposta bruta');

        /* o mapa de campos e o cabecalho chegaram certos */
        config_gravar('crm_endpoint', crm_falso_url('eco'));
        crm_enviar(LEAD_EXEMPLO);
        $ultima = crm_falso_ultima();
        $payload = json_decode($ultima['corpo'] ?? '', true);
        igual('(48) 99824-4494', $payload['telefone'] ?? null, 'mapa: whatsapp virou telefone');
        igual('Modelo pronto do catalogo', $payload['interesse'] ?? null, 'mapa: busca virou interesse');
        igual('instagram', $payload['origem'] ?? null, 'mapa: utm_source virou origem');
        igual('Tenho terreno.', $payload['observacao'] ?? null, 'mapa: mensagem virou observacao');
        falso(array_key_exists('utm_medium', (array) $payload), 'mapa nao manda campo fora do mapa');
        igual('Bearer segredo-123', $ultima['cabecalhos']['authorization'] ?? null, 'cabecalho de autorizacao chegou');
        verdade(str_starts_with((string) ($ultima['cabecalhos']['content-type'] ?? ''), 'application/json'), 'content-type e json');

        /* campo do mapa que o lead nao tem vira string vazia, nao some */
        crm_enviar(['nome' => 'So o nome', 'whatsapp' => '48999999999', 'busca' => 'Ainda pesquisando']);
        $payloadMagro = json_decode(crm_falso_ultima()['corpo'] ?? '', true);
        igual('', $payloadMagro['cidade'] ?? null, 'campo ausente vai vazio');

        /* CRM responde 500 */
        config_gravar('crm_endpoint', crm_falso_url('erro500'));
        $r = crm_enviar(LEAD_EXEMPLO);
        falso($r['ok'], 'CRM 500: ok false');
        igual(500, $r['http']);
        igual('crm_http', $r['erro']);
        contem('interno', $r['resposta'], 'CRM 500: guarda o corpo do erro');

        /* CRM responde algo que nao e JSON */
        config_gravar('crm_endpoint', crm_falso_url('invalido'));
        $r = crm_enviar(LEAD_EXEMPLO);
        falso($r['ok'], 'CRM invalido: ok false');
        igual(200, $r['http']);
        igual('crm_resposta_invalida', $r['erro']);
        contem('Manutencao', $r['resposta'], 'CRM invalido: guarda o corpo pra diagnostico');

        /* CRM estoura o tempo */
        config_gravar('crm_timeout', '2');
        config_gravar('crm_endpoint', crm_falso_url('demora', ['seg' => 8]));
        $inicio = microtime(true);
        $r = crm_enviar(LEAD_EXEMPLO);
        $gasto = microtime(true) - $inicio;
        falso($r['ok'], 'CRM lento: ok false');
        igual('crm_tempo', $r['erro']);
        verdade($gasto < 6, 'CRM lento: desistiu perto do limite, gastou ' . round($gasto, 2) . 's');
        config_gravar('crm_timeout', '10');
    } finally {
        crm_falso_derrubar($servidor);
    }

    /* servidor fora do ar: erro de conexao, nao de tempo */
    config_gravar('crm_endpoint', 'http://127.0.0.1:8799/nada');
    $r = crm_enviar(LEAD_EXEMPLO);
    falso($r['ok'], 'CRM fora do ar: ok false');
    igual('crm_conexao', $r['erro']);
});

/* ================= lib/email.php: aviso de lead novo ================= */

$GLOBALS['pasta_email'] = sys_get_temp_dir() . '/castello-emails-' . getmypid();

function emails_gravados(): array
{
    return (array) glob($GLOBALS['pasta_email'] . '/*.txt');
}

function emails_limpar(): void
{
    if (!is_dir($GLOBALS['pasta_email'])) {
        mkdir($GLOBALS['pasta_email'], 0777, true);
    }
    foreach (emails_gravados() as $velho) {
        @unlink($velho);
    }
}

register_shutdown_function(static function (): void {
    emails_limpar();
    @rmdir($GLOBALS['pasta_email']);
});

const LEAD_EMAIL = [
    'id'           => 42,
    'nome'         => 'Fabiano Hirtz',
    'whatsapp'     => '(48) 99824-4494',
    'busca'        => 'Modelo pronto do catalogo',
    'modelo'       => 'Compacta 39 m2',
    'cidade'       => 'Tubarao / SC',
    'mensagem'     => 'Tenho terreno em Tubarao.',
    'pagina'       => '/index.php',
    'referrer'     => 'https://www.google.com/',
    'utm_source'   => 'instagram',
    'utm_campaign' => 'flex-setembro',
    'criado_em'    => '2026-09-09 14:30:00',
];

teste('o corpo do e-mail traz o lead e o resultado do CRM', function (): void {
    $corpoOk = email_corpo_lead(LEAD_EMAIL, ['ok' => true, 'http' => 200, 'resposta' => '{"id":"CRM-1"}', 'erro' => null]);
    contem('Fabiano Hirtz', $corpoOk);
    contem('(48) 99824-4494', $corpoOk);
    contem('Tubarao / SC', $corpoOk);
    contem('instagram', $corpoOk);
    contem('42', $corpoOk);
    contem('CRM: entregue', $corpoOk);
    contem('wa.me/5548998244494', $corpoOk, 'link de resposta no whatsapp');

    $corpoErro = email_corpo_lead(LEAD_EMAIL, ['ok' => false, 'http' => 500, 'resposta' => 'interno', 'erro' => 'crm_http']);
    contem('CRM: falhou', $corpoErro);
    contem('crm_http', $corpoErro);
    contem('500', $corpoErro);

    $corpoOff = email_corpo_lead(LEAD_EMAIL, ['ok' => false, 'http' => 0, 'resposta' => '', 'erro' => 'crm_desativado']);
    contem('CRM: desligado', $corpoOff);
});

teste('email_lead_novo grava em arquivo no modo de teste e recusa destino invalido', function (): void {
    crm_teste_limpar();
    emails_limpar();
    putenv('CASTELLO_EMAIL_DIR=' . $GLOBALS['pasta_email']);

    try {
        config_gravar('email_aviso', 'contato@castellomadeiras.com.br');
        verdade(email_lead_novo(LEAD_EMAIL, ['ok' => true, 'http' => 200, 'resposta' => '{}', 'erro' => null]) === true);
        $arquivos = emails_gravados();
        igual(1, count($arquivos), 'gravou exatamente um arquivo');
        $gravado = (string) file_get_contents($arquivos[0]);
        contem('contato@castellomadeiras.com.br', $gravado);
        contem('Lead novo no site: Fabiano Hirtz', $gravado);
        contem('charset=UTF-8', $gravado);

        config_gravar('email_aviso', 'isso-nao-e-email');
        falso(email_lead_novo(LEAD_EMAIL, ['ok' => true, 'http' => 200, 'resposta' => '{}', 'erro' => null]), 'destino invalido devolve false');
        igual(1, count(emails_gravados()), 'destino invalido nao grava arquivo novo');

        config_gravar('email_aviso', 'contato@castellomadeiras.com.br');
        verdade((bool) filter_var(email_remetente(), FILTER_VALIDATE_EMAIL), 'remetente e um e-mail valido: ' . email_remetente());
    } finally {
        putenv('CASTELLO_EMAIL_DIR');
    }
});

/* ================= enviar.php: o caminho do lead ================= */

teste('enviar.php e reenviar.php usam a lib real, sem guarda de function_exists', function (): void {
    foreach (['enviar.php', 'reenviar.php'] as $arquivo) {
        $fonte = (string) file_get_contents(site() . '/' . $arquivo);
        nao_contem('function_exists', $fonte, $arquivo . ' ainda tem require guardado');
        nao_contem('apoio-f1', $fonte, $arquivo . ' ainda cita o apoio da frente 3');
        nao_contem('is_file($caminhoLib)', $fonte, $arquivo . ' ainda testa se a lib existe');
    }
    contem("require_once __DIR__ . '/lib/auth.php';", (string) file_get_contents(site() . '/enviar.php'));
    contem("require_once __DIR__ . '/lib/db.php';", (string) file_get_contents(site() . '/reenviar.php'));
    falso(is_file(raiz() . '/testes/apoio-f1.php'), 'o apoio da frente 3 tem que ter sido removido');
});

teste('enviar_processar no caminho feliz grava, marca desativado e dispara o e-mail', function (): void {
    crm_teste_limpar();
    emails_limpar();
    putenv('CASTELLO_EMAIL_DIR=' . $GLOBALS['pasta_email']);
    config_gravar('crm_ativo', '0');
    config_gravar('email_aviso', 'contato@castellomadeiras.com.br');

    $r = enviar_processar(post_valido());
    igual(200, $r['http']);
    verdade(($r['corpo']['ok'] ?? null) === true, 'ok true: ' . json_encode($r['corpo']));
    verdade((int) ($r['corpo']['id'] ?? 0) > 0, 'devolve o id do lead');
    igual(1, contar_leads());

    $gravado = db()->query('SELECT * FROM leads ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    igual('flex-setembro', $gravado['utm_campaign']);
    igual('/index.php', $gravado['pagina']);
    igual('desativado', $gravado['crm_status'], 'CRM desligado deixa o status desativado');
    igual(0, (int) $gravado['crm_tentativas']);
    igual(1, count(emails_gravados()), 'CRM desligado disparou o e-mail assim mesmo');
});

teste('honeypot e time-trap respondem sucesso falso sem gravar', function (): void {
    $antes = contar_leads();

    $r = enviar_processar(post_valido(['empresa' => 'Loja do Robo']));
    igual(200, $r['http']);
    verdade(($r['corpo']['ok'] ?? null) === true, 'honeypot: responde sucesso falso');
    igual(0, (int) ($r['corpo']['id'] ?? -1), 'honeypot: id zero');
    igual($antes, contar_leads(), 'honeypot: nao gravou lead');

    $r = enviar_processar(post_valido(['empresa' => '', '_gotcha' => 'robo']));
    verdade(($r['corpo']['ok'] ?? null) === true, 'honeypot _gotcha: responde sucesso falso');
    igual($antes, contar_leads(), 'honeypot _gotcha: nao gravou lead');

    $r = enviar_processar(post_valido(['ts' => (string) (int) round((microtime(true) - 1) * 1000)]));
    igual(200, $r['http']);
    verdade(($r['corpo']['ok'] ?? null) === true, 'rapido demais: responde sucesso falso');
    igual(0, (int) ($r['corpo']['id'] ?? -1), 'rapido demais: id zero');
    igual($antes, contar_leads(), 'rapido demais: nao gravou lead');

    $r = enviar_processar(post_valido(['ts' => '']));
    verdade(($r['corpo']['ok'] ?? null) === true, 'sem ts: responde sucesso falso');
    igual($antes, contar_leads(), 'sem ts: nao gravou lead');

    $r = enviar_processar(post_valido(['ts' => (string) (int) round((microtime(true) + 600) * 1000)]));
    verdade((int) ($r['corpo']['id'] ?? 0) > 0, 'relogio adiantado passa: ' . json_encode($r['corpo']));
});

teste('csrf invalido devolve 419 e campos ruins devolvem 422', function (): void {
    $antes = contar_leads();

    $r = enviar_processar(post_valido(['csrf' => 'token-errado']));
    igual(419, $r['http']);
    verdade(($r['corpo']['ok'] ?? null) === false);
    igual('csrf', $r['corpo']['erro'] ?? null);
    igual($antes, contar_leads(), 'csrf invalido: nao gravou lead');
    igual(419, enviar_processar(post_valido(['csrf' => '']))['http'], 'csrf ausente');

    $r = enviar_processar(post_valido(['nome' => '', 'whatsapp' => '', 'busca' => '']));
    igual(422, $r['http']);
    verdade(($r['corpo']['ok'] ?? null) === false);
    igual('campos', $r['corpo']['erro'] ?? null);
    igual(['nome', 'whatsapp', 'busca'], $r['corpo']['campos'] ?? null);
    igual($antes, contar_leads(), 'campos vazios: nao gravou lead');

    igual(['nome'], enviar_processar(post_valido(['nome' => '   ']))['corpo']['campos'] ?? null, 'so o nome faltando');

    $r = enviar_processar(post_valido(['whatsapp' => '(48) 9982']));
    igual(422, $r['http']);
    igual(['whatsapp'], $r['corpo']['campos'] ?? null);
    igual($antes, contar_leads(), 'whatsapp curto: nao gravou lead');

    $r = enviar_processar(post_valido(['whatsapp' => '(48) 3632-8743']));
    verdade((int) ($r['corpo']['id'] ?? 0) > 0, 'whatsapp de 10 digitos passa: ' . json_encode($r['corpo']));
});

teste('com o CRM ligado o visitante nunca ve falha de integracao', function (): void {
    $servidor = crm_falso_subir();
    verdade($servidor !== null, 'servidor do CRM falso subiu para o enviar.php');

    try {
        config_gravar('crm_ativo', '1');
        config_gravar('crm_mapa_campos', MAPA_PADRAO);
        config_gravar('crm_endpoint', crm_falso_url('ok'));
        $r = enviar_processar(post_valido());
        $linha = lead_por_id((int) ($r['corpo']['id'] ?? 0));
        igual('enviado', $linha['crm_status'], 'CRM 200: status enviado');
        igual(1, (int) $linha['crm_tentativas']);
        contem('CRM-', (string) $linha['crm_resposta']);

        config_gravar('crm_endpoint', crm_falso_url('erro500'));
        $r = enviar_processar(post_valido());
        igual(200, $r['http'], 'CRM 500: visitante ainda ve HTTP 200');
        verdade(($r['corpo']['ok'] ?? null) === true, 'CRM 500: visitante ainda ve ok true');
        $linha = lead_por_id((int) ($r['corpo']['id'] ?? 0));
        igual('erro', $linha['crm_status']);
        igual(1, (int) $linha['crm_tentativas']);
        contem('crm_http', (string) $linha['crm_resposta']);

        config_gravar('crm_timeout', '2');
        config_gravar('crm_endpoint', crm_falso_url('demora', ['seg' => 8]));
        $r = enviar_processar(post_valido());
        igual(200, $r['http'], 'CRM lento: visitante ainda ve HTTP 200');
        $linha = lead_por_id((int) ($r['corpo']['id'] ?? 0));
        igual('erro', $linha['crm_status']);
        contem('crm_tempo', (string) $linha['crm_resposta']);
        config_gravar('crm_timeout', '10');
    } finally {
        crm_falso_derrubar($servidor);
    }

    igual(contar_leads(), count(emails_gravados()), 'cada lead gravado gerou um e-mail');
    putenv('CASTELLO_EMAIL_DIR');
});

/* ================= leads_pendentes e leads_reenviar ================= */

teste('leads_pendentes lista quem ainda precisa subir, do mais antigo ao mais novo', function (): void {
    crm_teste_limpar();
    config_gravar('email_aviso', 'contato@castellomadeiras.com.br');
    config_gravar('crm_mapa_campos', MAPA_PADRAO);

    $GLOBALS['ids'] = [
        'pendente'   => semear_lead('Pendente', 'pendente', 0),
        'erro'       => semear_lead('Com erro', 'erro', 2),
        'desativado' => semear_lead('CRM desligado', 'desativado', 0),
        'enviado'    => semear_lead('Ja entregue', 'enviado', 1),
        'desistido'  => semear_lead('Cansou de tentar', 'erro', LEAD_TENTATIVAS_MAX),
    ];
    $ids = $GLOBALS['ids'];

    $pendentes = leads_pendentes();
    $lista = array_map('intval', array_column($pendentes, 'id'));
    verdade(in_array($ids['pendente'], $lista, true), 'pendente entra na lista');
    verdade(in_array($ids['erro'], $lista, true), 'erro entra na lista');
    verdade(in_array($ids['desativado'], $lista, true), 'desativado entra na lista');
    falso(in_array($ids['enviado'], $lista, true), 'enviado fica de fora');
    falso(in_array($ids['desistido'], $lista, true), 'quem estourou as tentativas fica de fora');
    igual(3, count($pendentes));
    igual($ids['pendente'], (int) $pendentes[0]['id'], 'o mais antigo vem primeiro');
    igual(2, count(leads_pendentes(2)), 'o limite e respeitado');

    config_gravar('crm_ativo', '0');
    $r = leads_reenviar();
    igual(['tentados' => 0, 'enviados' => 0, 'falhas' => 0], $r, 'CRM desligado nao mexe em nada');
    igual(3, count(leads_pendentes()));
});

teste('leads_reenviar sobe a fila e desiste depois do limite de tentativas', function (): void {
    $ids = $GLOBALS['ids'];
    $servidor = crm_falso_subir();
    verdade($servidor !== null, 'servidor do CRM falso subiu para o reenvio');

    try {
        config_gravar('crm_ativo', '1');
        config_gravar('crm_endpoint', crm_falso_url('ok'));
        $r = leads_reenviar();
        igual(3, $r['tentados']);
        igual(3, $r['enviados']);
        igual(0, $r['falhas']);
        igual(0, count(leads_pendentes()), 'nao sobrou pendente');

        $subiu = lead_por_id($ids['erro']);
        igual('enviado', $subiu['crm_status']);
        igual(3, (int) $subiu['crm_tentativas'], 'tentativa somou uma');
        igual(LEAD_TENTATIVAS_MAX, (int) lead_por_id($ids['desistido'])['crm_tentativas'], 'nao mexeu em quem desistiu');

        crm_teste_limpar();
        config_gravar('crm_ativo', '1');
        config_gravar('crm_mapa_campos', MAPA_PADRAO);
        config_gravar('crm_endpoint', crm_falso_url('erro500'));
        $idFalha = semear_lead('Vai falhar', 'pendente', 0);
        $r = leads_reenviar();
        igual(1, $r['tentados']);
        igual(0, $r['enviados']);
        igual(1, $r['falhas']);
        $falhou = lead_por_id($idFalha);
        igual('erro', $falhou['crm_status']);
        igual(1, (int) $falhou['crm_tentativas']);
        contem('crm_http', (string) $falhou['crm_resposta']);
        igual(1, count(leads_pendentes()), 'continua na fila');

        for ($volta = 0; $volta < LEAD_TENTATIVAS_MAX; $volta++) {
            leads_reenviar();
        }
        igual(0, count(leads_pendentes()), 'depois do limite o lead sai da fila');
    } finally {
        crm_falso_derrubar($servidor);
    }
});

/* ================= reenviar.php ================= */

teste('reenviar.php compila, compara a chave com hash_equals e tem modo cli', function (): void {
    verdade(is_file(site() . '/reenviar.php'));
    $saida = [];
    $codigo = 0;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg(site() . '/reenviar.php') . ' 2>&1', $saida, $codigo);
    igual(0, $codigo, 'reenviar.php compila: ' . implode(' | ', $saida));
    $fonte = (string) file_get_contents(site() . '/reenviar.php');
    contem('hash_equals', $fonte);
    contem("PHP_SAPI === 'cli'", $fonte);
});

/* ================= js/formulario.js pelo Node ================= */

teste('node testes/formulario.test.js passa', function (): void {
    $ondeNode = trim((string) shell_exec(PHP_OS_FAMILY === 'Windows' ? 'where node 2>NUL' : 'command -v node 2>/dev/null'));
    if ($ondeNode === '') {
        pular('node nao encontrado no PATH');
    }
    $saidaNode = [];
    $codigoNode = 0;
    exec('node ' . escapeshellarg(raiz() . '/testes/formulario.test.js') . ' 2>&1', $saidaNode, $codigoNode);
    igual(0, $codigoNode, implode(' | ', $saidaNode));
});
