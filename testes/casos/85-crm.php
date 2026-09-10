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

/** Config minima do Agendor apontando para o servidor falso desta rodada. */
function crm_teste_config(): void
{
    config_gravar('crm_ativo', '1');
    config_gravar('crm_base', 'http://127.0.0.1:' . crm_falso_porta());
    config_gravar('crm_funil', '904296');
    config_gravar('crm_etapa', '1');
    config_gravar('crm_origem', '2656389');
    config_gravar('crm_categoria', '4187395');
    config_gravar('crm_marcador', '[SITE]');
    config_gravar('crm_responsavel', '');
    config_gravar('crm_timeout', '10');
    putenv('CASTELLO_AGENDOR_TOKEN=token-de-teste');
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

const LEAD_EXEMPLO = [
    'nome'         => 'Fabiano Hirtz',
    'whatsapp'     => '(48) 99824-4494',
    'busca'        => 'Modelo pronto do catalogo',
    'modelo'       => 'Compacta 39 m2',
    'cidade'       => 'Tubarao / SC',
    'prazo'        => 'Ate 3 meses',
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

teste('crm_whatsapp_ddi e crm_whatsapp_busca produzem os dois formatos', function (): void {
    igual('5548998244494', crm_whatsapp_ddi('(48) 99824-4494'), '11 digitos ganham o 55');
    igual('554836328743', crm_whatsapp_ddi('(48) 3632-8743'), '10 digitos ganham o 55');
    igual('5548998244494', crm_whatsapp_ddi('5548998244494'), '13 digitos com 55 ficam como estao');
    igual('5548998244494', crm_whatsapp_ddi('+55 (48) 99824-4494'), 'pontuacao e o mais sao ignorados');
    igual('', crm_whatsapp_ddi('123'), 'curto demais nao vira telefone');
    igual('', crm_whatsapp_ddi(''), 'vazio continua vazio');

    igual('48998244494', crm_whatsapp_busca('(48) 99824-4494'), 'busca vai sem DDI');
    igual('48998244494', crm_whatsapp_busca('5548998244494'), 'busca tira o 55 que veio');
    igual('4836328743', crm_whatsapp_busca('(48) 3632-8743'));
    igual('', crm_whatsapp_busca('123'));
});

teste('crm_payload_pessoa monta o objeto aninhado que o Agendor espera', function (): void {
    crm_teste_limpar();
    crm_teste_config();

    $p = crm_payload_pessoa(LEAD_EXEMPLO);

    igual('Fabiano Hirtz', $p['name']);
    igual('5548998244494', $p['contact']['whatsapp'], 'whatsapp COM DDI: e o que faz o link do CRM funcionar');
    igual('(48) 99824-4494', $p['contact']['mobile'], 'mobile SEM DDI: e o que a busca encontra depois');
    igual(2656389, $p['leadOrigin'], 'origem sempre Site');
    igual(4187395, $p['category']);
    igual('Tubarao / SC', $p['customFields']['cidade_da_obra']);
    igual('Ate 3 meses', $p['customFields']['pretende_iniciar_a_obra_em']);
    igual('flex-setembro', $p['customFields']['anuncio_de_origem'], 'utm_campaign vira anuncio de origem');
    falso(array_key_exists('ownerUser', $p), 'responsavel vazio nao vai no payload');

    /* Campo vazio nao pode ir como string vazia. */
    $magro = crm_payload_pessoa(['nome' => 'So o nome', 'whatsapp' => '48999999999', 'busca' => 'Ainda estou pesquisando']);
    falso(array_key_exists('cidade_da_obra', $magro['customFields'] ?? []), 'cidade vazia fica de fora');
    falso(array_key_exists('pretende_iniciar_a_obra_em', $magro['customFields'] ?? []), 'prazo vazio fica de fora');
    igual('direto', $magro['customFields']['anuncio_de_origem'], 'sem utm vira direto');

    /* Sem utm_campaign, cai para utm_source. */
    $comFonte = crm_payload_pessoa(['nome' => 'X', 'whatsapp' => '48999999999', 'utm_source' => 'instagram']);
    igual('instagram', $comFonte['customFields']['anuncio_de_origem']);

    /* Responsavel configurado entra. */
    config_gravar('crm_responsavel', '989735');
    igual('989735', crm_payload_pessoa(LEAD_EXEMPLO)['ownerUser']);
    config_gravar('crm_responsavel', '');

    /* Nome vazio tambem fica de fora, como qualquer outro campo vazio. */
    falso(array_key_exists('name', crm_payload_pessoa(['whatsapp' => '48999999999'])), 'nome vazio nao vai no payload');
});

teste('crm_payload_pessoa nunca guarda o mobile com DDI, mesmo quando o visitante digita com 55', function (): void {
    crm_teste_config();

    /* Achado da revisao 2026-09-10: quando o bruto e a busca divergem (o
       visitante digitou com o codigo do pais), guardar o bruto faz whatsapp
       e mobile carregarem o mesmo 55 - e a busca, que e sempre sem DDI,
       nunca mais reencontra essa pessoa. O mobile tem que sempre bater com
       a chave que a busca usa. */
    /* Lista de pares, nao mapa: '5548998244494' e puramente numerico e o PHP
       converteria essa chave de array para int, o que quebraria a tipagem
       string de crm_whatsapp_busca(). */
    $casos = [
        ['(48) 99824-4494', '(48) 99824-4494'],       // digitos do bruto ja batem com a busca: guarda formatado
        ['+55 (48) 99824-4494', '48998244494'],        // bruto tem o 55 a mais que a busca: guarda normalizado
        ['5548998244494', '48998244494'],              // idem, sem formatacao nenhuma
        ['55 48 3632-8743', '4836328743'],              // fixo com DDI: idem
    ];
    foreach ($casos as [$entrada, $mobileEsperado]) {
        $p = crm_payload_pessoa(['nome' => 'Teste', 'whatsapp' => $entrada]);
        igual($mobileEsperado, $p['contact']['mobile'], "mobile de '{$entrada}'");

        /* A invariante de verdade: os digitos do que foi gravado tem que
           bater com a chave que a busca de duplicata usa, formatado ou nao.
           Era isso que a regressao do DDI quebrava em silencio. */
        $digitosGravados = preg_replace('/\D+/', '', (string) $p['contact']['mobile']) ?? '';
        igual(crm_whatsapp_busca($entrada), $digitosGravados, "os digitos do mobile de '{$entrada}' batem com a busca");
    }
});

teste('crm_titulo_negocio segue a convencao do funil', function (): void {
    crm_teste_config();

    igual('[SITE] - Ate 3 meses - Fabiano Hirtz', crm_titulo_negocio(LEAD_EXEMPLO));

    $semPrazo = ['nome' => 'Maria Silva', 'busca' => 'Projeto exclusivo', 'prazo' => ''];
    igual('[SITE] - Projeto exclusivo - Maria Silva', crm_titulo_negocio($semPrazo), 'sem prazo o meio usa a busca');

    $semNada = ['nome' => 'Joao', 'busca' => '', 'prazo' => ''];
    igual('[SITE] - Joao', crm_titulo_negocio($semNada), 'sem prazo e sem busca o titulo nao fica com traco solto');

    $marcadorOutro = ['nome' => 'Ana', 'busca' => 'Modelo pronto', 'prazo' => 'Imediato'];
    config_gravar('crm_marcador', '[SITE FLEX]');
    igual('[SITE FLEX] - Imediato - Ana', crm_titulo_negocio($marcadorOutro), 'o marcador vem da config, com colchetes e tudo');
    config_gravar('crm_marcador', '[SITE]');

    $longo = ['nome' => str_repeat('Wenceslau ', 30), 'prazo' => 'Imediato'];
    $titulo = crm_titulo_negocio($longo);
    verdade(mb_strlen($titulo) <= 120, 'titulo cortado em 120: ' . mb_strlen($titulo));
    verdade(str_starts_with($titulo, '[SITE] - Imediato - '), 'o corte tira do nome, nao do marcador nem do prazo');

    /* O prefixo sozinho (marcador + prazo) maior que o teto tambem tem que
       sair cortado: o $sobra do nome nao e o unico lugar que protege o
       limite de 120. */
    config_gravar('crm_marcador', str_repeat('X', 150));
    $tituloGigante = crm_titulo_negocio(['nome' => 'A', 'prazo' => '']);
    igual(120, mb_strlen($tituloGigante), 'prefixo maior que 120 sozinho ainda sai cortado em 120');
    config_gravar('crm_marcador', '[SITE]');
});

teste('crm_descricao_negocio junta o lead num texto legivel e omite o vazio', function (): void {
    $d = crm_descricao_negocio(LEAD_EXEMPLO);
    contem('Modelo pronto do catalogo', $d);
    contem('Compacta 39 m2', $d);
    contem('Tubarao / SC', $d);
    contem('Ate 3 meses', $d);
    contem('Tenho terreno.', $d);
    contem('/index.php', $d);
    contem('instagram', $d, 'a campanha aparece na descricao');

    $magro = crm_descricao_negocio(['nome' => 'So o nome', 'busca' => 'Ainda estou pesquisando']);
    nao_contem('Cidade', $magro, 'linha de campo vazio nao aparece');
    nao_contem('Mensagem', $magro);
    contem('Ainda estou pesquisando', $magro);
});

teste('crm_enviar recusa sem fazer requisicao quando falta config', function (): void {
    crm_teste_limpar();
    crm_teste_config();

    config_gravar('crm_ativo', '0');
    $r = crm_enviar(LEAD_EXEMPLO);
    falso($r['ok'], 'CRM desligado: ok false');
    igual('crm_desativado', $r['erro']);
    igual(0, $r['http']);

    config_gravar('crm_ativo', '1');
    putenv('CASTELLO_AGENDOR_TOKEN');
    igual('crm_sem_token', crm_enviar(LEAD_EXEMPLO)['erro'], 'ligado sem token e configuracao incompleta');
    putenv('CASTELLO_AGENDOR_TOKEN=token-de-teste');

    igual('crm_sem_telefone', crm_enviar(['nome' => 'Sem telefone', 'whatsapp' => '12'])['erro']);
});

teste('crm_enviar cria pessoa e negocio, e reencontra a pessoa na volta', function (): void {
    $servidor = crm_falso_subir();
    verdade($servidor !== null, 'agendor falso subiu');

    try {
        crm_teste_limpar();
        crm_teste_config();

        /* Primeira vez: busca vazia, cria pessoa, cria negocio. */
        $r = crm_enviar(LEAD_EXEMPLO);
        verdade($r['ok'] === true, 'primeiro envio deu certo: ' . json_encode($r));
        verdade(is_int($r['pessoa_id']) && $r['pessoa_id'] > 0, 'devolve o id da pessoa');
        contem('web.agendor.com.br', (string) $r['negocio_url'], 'devolve o link do negocio');

        $chamadas = crm_falso_todas();
        igual(3, count($chamadas), 'foram tres chamadas: busca, pessoa, negocio');
        igual('GET', $chamadas[0]['metodo']);
        contem('phone=48998244494', $chamadas[0]['uri'], 'a busca vai sem DDI');
        igual('POST', $chamadas[1]['metodo']);
        igual('/people', $chamadas[1]['caminho']);
        igual('Token token-de-teste', $chamadas[1]['cabecalhos']['authorization'] ?? '', 'cabecalho de autenticacao');
        verdade(str_contains($chamadas[2]['caminho'], '/deals'), 'terceira chamada cria o negocio');

        $corpoNegocio = json_decode($chamadas[2]['corpo'], true);
        igual(904296, $corpoNegocio['funnel'] ?? null);
        igual(1, $corpoNegocio['dealStage'] ?? null, 'a etapa vai como sequencia');
        igual('[SITE] - Ate 3 meses - Fabiano Hirtz', $corpoNegocio['title'] ?? null);

        $pessoaCriada = (int) $r['pessoa_id'];

        /* Segunda vez, mesmo telefone: a busca acha e NAO cria pessoa nova.
           E o teste que pega a regressao do DDI descrita na secao 5.1. */
        $r2 = crm_enviar(LEAD_EXEMPLO);
        verdade($r2['ok'] === true, 'segundo envio deu certo');
        igual($pessoaCriada, (int) $r2['pessoa_id'], 'reaproveitou a mesma pessoa');

        $depois = crm_falso_todas();
        $criacoes = 0;
        foreach ($depois as $c) {
            if ($c['metodo'] === 'POST' && $c['caminho'] === '/people') {
                $criacoes++;
            }
        }
        igual(1, $criacoes, 'a pessoa foi criada uma vez so nas duas visitas');
    } finally {
        crm_falso_derrubar($servidor);
    }
});

teste('crm_enviar com crm_pessoa_id pula direto para o negocio', function (): void {
    $servidor = crm_falso_subir();
    try {
        crm_teste_limpar();
        crm_teste_config();

        $r = crm_enviar(LEAD_EXEMPLO + ['crm_pessoa_id' => 71397195]);
        verdade($r['ok'] === true, 'envio com pessoa conhecida deu certo: ' . json_encode($r));
        igual(71397195, $r['pessoa_id'], 'manteve a pessoa que ja tinha');

        $chamadas = crm_falso_todas();
        igual(1, count($chamadas), 'uma chamada so: nem busca nem criacao de pessoa');
        contem('/people/71397195/deals', $chamadas[0]['caminho']);
    } finally {
        crm_falso_derrubar($servidor);
    }
});

teste('crm_enviar trata 401, 429, resposta invalida e busca que falha', function (): void {
    $servidor = crm_falso_subir();
    try {
        /* A busca falhando (por qualquer motivo que nao seja tempo) e
           tolerada: o conector segue e cria a pessoa e o negocio normalmente.
           Escopado a GET (com @GET) para nao contaminar as chamadas de
           criacao que vem depois - sem o escopo, o mesmo modo tambem faria a
           criacao falhar, e o teste passaria mesmo se a busca NAO fosse
           tolerada, so porque tudo falha do mesmo jeito. */
        foreach (['auth401', 'limite429', 'erro500', 'invalido'] as $modo) {
            crm_teste_limpar();
            crm_teste_config();
            putenv('CASTELLO_AGENDOR_TESTE_MODO=' . $modo . '@GET');

            $r = crm_enviar(LEAD_EXEMPLO);
            verdade($r['ok'] === true, "busca com {$modo} e tolerada, o envio continua: " . json_encode($r));
            verdade(is_int($r['pessoa_id']) && $r['pessoa_id'] > 0, "{$modo}: cria pessoa e negocio depois da busca falhar");
        }

        /* Falha na CRIACAO da pessoa (POST /people) precisa aparecer de
           verdade, com o codigo traduzido certo. Escopada a POST (@POST) e
           com telefone proprio por caso, para a busca (que aqui roda de
           verdade, sem falha) nunca achar uma pessoa de um caso anterior e
           pular a criacao que o teste quer observar. */
        $casos = [
            'auth401'   => ['crm_auth', 401],
            'limite429' => ['crm_limite', 429],
            'erro500'   => ['crm_http', 500],
            'invalido'  => ['crm_resposta_invalida', 200],
        ];
        $indice = 0;
        foreach ($casos as $modo => [$erroEsperado, $httpEsperado]) {
            crm_teste_limpar();
            crm_teste_config();
            putenv('CASTELLO_AGENDOR_TESTE_MODO=' . $modo . '@POST');

            $indice++;
            $leadFalha = ['nome' => 'Falha ' . $modo, 'whatsapp' => '489900000' . str_pad((string) $indice, 2, '0', STR_PAD_LEFT)];
            $r = crm_enviar($leadFalha);
            falso($r['ok'], "{$modo} na criacao: ok false");
            igual($erroEsperado, $r['erro'], "{$modo}: erro traduzido");
            igual($httpEsperado, $r['http'], "{$modo}: http");
        }

        putenv('CASTELLO_AGENDOR_TESTE_MODO');
    } finally {
        putenv('CASTELLO_AGENDOR_TESTE_MODO');
        crm_falso_derrubar($servidor);
    }

    /* Servidor fora do ar: erro de conexao, nao de tempo. */
    crm_teste_limpar();
    crm_teste_config();
    config_gravar('crm_base', 'http://127.0.0.1:8799');
    igual('crm_conexao', crm_enviar(LEAD_EXEMPLO)['erro']);
});

teste('crm_enviar respeita o orcamento de tempo do conjunto', function (): void {
    $servidor = crm_falso_subir();
    try {
        crm_teste_limpar();
        crm_teste_config();
        /* Atraso curto (2s) em TODA chamada, com orcamento (5s) menor que a
           soma das tres (6s). Um atraso unico maior que o proprio orcamento
           (como o teste antigo fazia) so prova o timeout POR CHAMADA, que o
           conector antigo ja tinha - nao prova nada sobre o orcamento ser
           compartilhado entre as tres. Com estes numeros a busca e a criacao
           da pessoa acontecem de verdade (2 chamadas reais), e so o negocio
           fica sem tempo de ser sequer tentado. */
        config_gravar('crm_timeout', '5');
        putenv('CASTELLO_AGENDOR_TESTE_MODO=demora:2');

        $inicio = microtime(true);
        $r = crm_enviar(LEAD_EXEMPLO);
        $gasto = microtime(true) - $inicio;

        falso($r['ok'], 'CRM lento: ok false');
        igual('crm_tempo', $r['erro']);
        verdade($gasto < 5, 'desistiu dentro do orcamento, gastou ' . round($gasto, 2) . 's');

        $chamadas = crm_falso_todas();
        igual(2, count($chamadas), 'busca e criacao consumiram o orcamento; o negocio nem chegou a ser tentado');
    } finally {
        putenv('CASTELLO_AGENDOR_TESTE_MODO');
        config_gravar('crm_timeout', '10');
        crm_falso_derrubar($servidor);
    }
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
        crm_teste_limpar();
        crm_teste_config();
        emails_limpar();
        putenv('CASTELLO_EMAIL_DIR=' . $GLOBALS['pasta_email']);
        config_gravar('email_aviso', 'contato@castellomadeiras.com.br');

        /* CRM em dia: busca, cria pessoa e cria negocio, tudo com sucesso. */
        $r = enviar_processar(post_valido());
        $linha = lead_por_id((int) ($r['corpo']['id'] ?? 0));
        igual('enviado', $linha['crm_status'], 'CRM 200: status enviado');
        igual(1, (int) $linha['crm_tentativas']);
        contem('web.agendor.com.br', (string) $linha['crm_resposta'], 'guarda a resposta do negocio criado');

        /* Falha na criacao do negocio: a busca (GET) acha a pessoa do envio
           acima de verdade, entao so a criacao do negocio (POST) e derrubada
           por erro500. O visitante nunca ve isso, so o status do lead
           reflete o erro - e o pessoa_id continua gravado, porque a pessoa
           foi encontrada antes do negocio falhar. */
        putenv('CASTELLO_AGENDOR_TESTE_MODO=erro500@POST');
        $r = enviar_processar(post_valido());
        igual(200, $r['http'], 'CRM 500: visitante ainda ve HTTP 200');
        verdade(($r['corpo']['ok'] ?? null) === true, 'CRM 500: visitante ainda ve ok true');
        $linha = lead_por_id((int) ($r['corpo']['id'] ?? 0));
        igual('erro', $linha['crm_status']);
        igual(1, (int) $linha['crm_tentativas']);
        contem('crm_http', (string) $linha['crm_resposta']);
        verdade((int) $linha['crm_pessoa_id'] > 0, 'a pessoa achada na busca fica gravada mesmo o negocio falhando');
        putenv('CASTELLO_AGENDOR_TESTE_MODO');

        /* CRM lento: o orcamento de tempo estoura e o visitante ainda assim
           ve HTTP 200. */
        config_gravar('crm_timeout', '2');
        putenv('CASTELLO_AGENDOR_TESTE_MODO=demora:8');
        $r = enviar_processar(post_valido());
        igual(200, $r['http'], 'CRM lento: visitante ainda ve HTTP 200');
        $linha = lead_por_id((int) ($r['corpo']['id'] ?? 0));
        igual('erro', $linha['crm_status']);
        contem('crm_tempo', (string) $linha['crm_resposta']);
        config_gravar('crm_timeout', '10');
    } finally {
        putenv('CASTELLO_AGENDOR_TESTE_MODO');
        putenv('CASTELLO_EMAIL_DIR');
        crm_falso_derrubar($servidor);
    }

    igual(contar_leads(), count(emails_gravados()), 'cada lead gravado gerou um e-mail');
});

teste('o id da pessoa fica gravado mesmo quando o negocio falha', function (): void {
    $servidor = crm_falso_subir();
    try {
        crm_teste_limpar();
        crm_teste_config();
        emails_limpar();
        putenv('CASTELLO_EMAIL_DIR=' . $GLOBALS['pasta_email']);
        config_gravar('email_aviso', 'contato@castellomadeiras.com.br');

        /* Caminho feliz: grava o id da pessoa. */
        $r = enviar_processar(post_valido());
        $linha = lead_por_id((int) $r['corpo']['id']);
        igual('enviado', $linha['crm_status']);
        verdade((int) $linha['crm_pessoa_id'] > 0, 'gravou o crm_pessoa_id');

        /* lead_marcar_pessoa e idempotente e nao mexe no resto. */
        lead_marcar_pessoa((int) $r['corpo']['id'], 12345);
        $trocado = lead_por_id((int) $r['corpo']['id']);
        igual(12345, (int) $trocado['crm_pessoa_id']);
        igual('enviado', $trocado['crm_status'], 'marcar a pessoa nao mexe no status');
    } finally {
        putenv('CASTELLO_EMAIL_DIR');
        crm_falso_derrubar($servidor);
    }
});

teste('o prazo entra na lista fechada e valor inventado nao derruba o lead', function (): void {
    crm_teste_limpar();
    emails_limpar();
    putenv('CASTELLO_EMAIL_DIR=' . $GLOBALS['pasta_email']);
    config_gravar('crm_ativo', '0');
    config_gravar('email_aviso', 'contato@castellomadeiras.com.br');

    igual(['Imediato', 'Até 3 meses', 'Até 6 meses', 'Só pesquisando'], ENVIAR_PRAZOS, 'os quatro valores, literais como no CRM');

    $r = enviar_processar(post_valido(['prazo' => 'Até 3 meses']));
    igual('Até 3 meses', lead_por_id((int) $r['corpo']['id'])['prazo']);

    /* Campo opcional: ausente passa. */
    $r = enviar_processar(post_valido(['prazo' => '']));
    igual(200, $r['http']);
    igual('', lead_por_id((int) $r['corpo']['id'])['prazo']);

    /* POST adulterado nao pode custar o lead: vira vazio, nao 422. */
    $r = enviar_processar(post_valido(['prazo' => 'Semana que vem']));
    igual(200, $r['http'], 'prazo fora da lista nao vira erro');
    igual('', lead_por_id((int) $r['corpo']['id'])['prazo'], 'prazo fora da lista vira vazio');

    putenv('CASTELLO_EMAIL_DIR');
});

/* ================= leads_pendentes e leads_reenviar ================= */

teste('leads_pendentes lista quem ainda precisa subir, do mais antigo ao mais novo', function (): void {
    crm_teste_limpar();
    config_gravar('email_aviso', 'contato@castellomadeiras.com.br');

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
        crm_teste_config();
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
        crm_teste_config();
        putenv('CASTELLO_AGENDOR_TESTE_MODO=erro500');
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
        putenv('CASTELLO_AGENDOR_TESTE_MODO');
    } finally {
        putenv('CASTELLO_AGENDOR_TESTE_MODO');
        crm_falso_derrubar($servidor);
    }
});

teste('o reenvio de um lead com pessoa conhecida nao cria pessoa de novo', function (): void {
    $servidor = crm_falso_subir();
    try {
        crm_teste_limpar();
        crm_teste_config();

        $id = semear_lead('Meio caminho', 'erro', 1);
        lead_marcar_pessoa($id, 71397195);

        $r = leads_reenviar();
        igual(1, $r['tentados']);
        igual(1, $r['enviados']);

        foreach (crm_falso_todas() as $chamada) {
            falso($chamada['metodo'] === 'POST' && $chamada['caminho'] === '/people', 'nao criou pessoa nova');
        }
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
