<?php
declare(strict_types=1);

/**
 * Conector do Agendor.
 *
 * Manda o lead do site para o CRM da Castello em tres chamadas encadeadas:
 * procura a pessoa pelo telefone, cria se nao existir, e cria o negocio nela.
 * E o negocio, nao a pessoa, que aparece no funil de vendas.
 *
 * O formato segue o que a conta da Castello ja pratica, medido em 2026-09-10:
 * titulo "[SITE] - <prazo> - <Nome>", espelhando o "[META] - ..." que a
 * integracao do Meta ja deposita no mesmo funil.
 *
 * Depende de config_ler(), definida em lib/db.php. Este arquivo nao carrega a
 * lib: quem carrega e o ponto de entrada.
 *
 * Spec: docs/superpowers/specs/2026-09-10-castello-agendor-design.md
 */

/** Endereco da API. Sobrescrito por config.crm_base, que os testes apontam
    para o servidor falso. */
const CRM_BASE_PADRAO = 'https://api.agendor.com.br/v3';

/** Teto do tempo TOTAL das tres chamadas. O contrato fixa 10 segundos. */
const CRM_TIMEOUT_MAX = 10;

/** Abaixo disto nao vale a pena comecar mais uma chamada. */
const CRM_MINIMO_POR_CHAMADA = 2;

/** Tamanho maximo da resposta guardada, para nao inchar o banco. */
const CRM_RESPOSTA_MAX = 2000;

/** Os titulos que ja existem no funil tem de 27 a 53 caracteres. */
const CRM_TITULO_MAX = 120;

/** Identificadores dos campos customizados de pessoa, como estao na conta. */
const CRM_CAMPO_CIDADE = 'cidade_da_obra';
const CRM_CAMPO_ANUNCIO = 'anuncio_de_origem';
const CRM_CAMPO_PRAZO = 'pretende_iniciar_a_obra_em';

/**
 * Trecho da mensagem com que o Agendor recusa um negocio repetido. Medido na
 * conta da Castello em 2026-09-10; a mensagem inteira e "Title There can only
 * be one deal with this title for this organization/person".
 */
const CRM_ERRO_TITULO_REPETIDO = 'only be one deal with this title';

/**
 * Token da conta.
 *
 * O ambiente vem primeiro para o teste poder trocar sem redefinir constante,
 * mesmo padrao do CASTELLO_EMAIL_DIR em lib/email.php.
 */
function crm_token(): string
{
    $doAmbiente = getenv('CASTELLO_AGENDOR_TOKEN');
    if (is_string($doAmbiente) && trim($doAmbiente) !== '') {
        return trim($doAmbiente);
    }
    return defined('CASTELLO_AGENDOR_TOKEN') ? trim((string) CASTELLO_AGENDOR_TOKEN) : '';
}

/**
 * Telefone com DDI, para contact.whatsapp.
 *
 * E o formato que faz o botao de WhatsApp funcionar dentro do Agendor.
 */
function crm_whatsapp_ddi(string $bruto): string
{
    $digitos = preg_replace('/\D+/', '', $bruto) ?? '';
    $tamanho = strlen($digitos);

    if ($tamanho === 10 || $tamanho === 11) {
        return '55' . $digitos;
    }
    if (($tamanho === 12 || $tamanho === 13) && str_starts_with($digitos, '55')) {
        return $digitos;
    }
    return '';
}

/**
 * Telefone sem DDI, para contact.mobile e para a busca de duplicata.
 *
 * Medido na conta real em 2026-09-10: GET /people?phone= devolve vazio quando
 * a busca leva o 55 na frente. Por isso a busca vai por aqui, e por isso o
 * mobile guarda este formato: e ele que faz o site reencontrar as pessoas que
 * ele mesmo criou.
 */
function crm_whatsapp_busca(string $bruto): string
{
    $digitos = preg_replace('/\D+/', '', $bruto) ?? '';

    if ((strlen($digitos) === 12 || strlen($digitos) === 13) && str_starts_with($digitos, '55')) {
        $digitos = substr($digitos, 2);
    }
    return (strlen($digitos) === 10 || strlen($digitos) === 11) ? $digitos : '';
}

/** Texto do lead, sempre string, sempre aparado. */
function crm_campo(array $lead, string $chave): string
{
    $valor = $lead[$chave] ?? '';
    return is_scalar($valor) ? trim((string) $valor) : '';
}

/** Corpo do POST /people. */
function crm_payload_pessoa(array $lead): array
{
    $payload = [
        'leadOrigin' => (int) config_ler('crm_origem', '2656389'),
        'category'   => (int) config_ler('crm_categoria', '4187395'),
    ];
    $nome = crm_campo($lead, 'nome');
    if ($nome !== '') {
        $payload['name'] = $nome;
    }

    $contato = [];
    $telefoneBruto = crm_campo($lead, 'whatsapp');
    $comDdi = crm_whatsapp_ddi($telefoneBruto);
    if ($comDdi !== '') {
        $contato['whatsapp'] = $comDdi;
    }
    $buscaTelefone = crm_whatsapp_busca($telefoneBruto);
    if ($buscaTelefone !== '') {
        /* O bruto (como o visitante digitou) so e seguro quando os digitos
           dele batem com a chave de busca. Quando o visitante ja digita com
           o 55 na frente, whatsapp e mobile ficariam iguais e com DDI, e a
           busca (que e sempre sem DDI) nunca mais reencontraria essa pessoa:
           cada envio criaria uma pessoa nova, sem erro nenhum aparecer. Por
           isso aqui cai para os digitos normalizados quando os dois formatos
           divergem. Achado da revisao de 2026-09-10. */
        $digitosBrutos = preg_replace('/\D+/', '', $telefoneBruto) ?? '';
        $contato['mobile'] = $digitosBrutos === $buscaTelefone ? $telefoneBruto : $buscaTelefone;
    }
    if ($contato !== []) {
        $payload['contact'] = $contato;
    }

    /* Campo vazio fica de fora: string vazia sobrescreveria dado bom. */
    $customizados = [];
    $cidade = crm_campo($lead, 'cidade');
    if ($cidade !== '') {
        $customizados[CRM_CAMPO_CIDADE] = $cidade;
    }
    $prazo = crm_campo($lead, 'prazo');
    if ($prazo !== '') {
        $customizados[CRM_CAMPO_PRAZO] = $prazo;
    }

    /* A origem e sempre Site; a campanha e que muda. Ver secao 3.5 da spec. */
    $anuncio = crm_campo($lead, 'utm_campaign');
    if ($anuncio === '') {
        $anuncio = crm_campo($lead, 'utm_source');
    }
    $customizados[CRM_CAMPO_ANUNCIO] = $anuncio !== '' ? $anuncio : 'direto';

    $payload['customFields'] = $customizados;

    $responsavel = trim((string) config_ler('crm_responsavel', ''));
    if ($responsavel !== '') {
        $payload['ownerUser'] = $responsavel;
    }

    return $payload;
}

/**
 * Titulo do negocio: "[SITE] - <prazo> - <Nome>".
 *
 * O prazo e o criterio de qualificacao da Castello e por isso ocupa o meio,
 * igual aos leads do Meta. Sem prazo, a busca entra no lugar; sem os dois, o
 * titulo perde o segmento em vez de ficar com traco solto. O corte protege
 * marcador e prazo, que e o que se le na coluna do funil.
 */
function crm_titulo_negocio(array $lead): string
{
    $marcador = trim((string) config_ler('crm_marcador', '[SITE]'));
    $meio = crm_campo($lead, 'prazo');
    if ($meio === '') {
        $meio = crm_campo($lead, 'busca');
    }
    $nome = crm_campo($lead, 'nome');

    $prefixo = $marcador . ($meio !== '' ? ' - ' . $meio : '') . ' - ';
    $sobra = CRM_TITULO_MAX - mb_strlen($prefixo);

    $titulo = $prefixo . mb_substr($nome, 0, max(1, $sobra));

    /* $sobra so garante o corte quando o prefixo sozinho cabe no teto; sem
       este corte final, um marcador ou prazo compridos furavam o limite. */
    return mb_substr($titulo, 0, CRM_TITULO_MAX);
}

/** Corpo em texto do negocio. A conta nao tem campo customizado de negocio. */
function crm_descricao_negocio(array $lead): string
{
    $linhas = ['Lead do site.', ''];

    $rotulos = [
        'busca'    => 'O que busca',
        'modelo'   => 'Modelo de interesse',
        'cidade'   => 'Cidade / regiao do terreno',
        'prazo'    => 'Prazo para iniciar',
    ];
    foreach ($rotulos as $campo => $rotulo) {
        $valor = crm_campo($lead, $campo);
        if ($valor !== '') {
            $linhas[] = $rotulo . ': ' . $valor;
        }
    }

    $mensagem = crm_campo($lead, 'mensagem');
    if ($mensagem !== '') {
        $linhas[] = '';
        $linhas[] = 'Mensagem:';
        $linhas[] = $mensagem;
    }

    $origem = [];
    $pagina = crm_campo($lead, 'pagina');
    if ($pagina !== '') {
        $origem[] = 'Pagina: ' . $pagina;
    }
    $campanha = [];
    foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as $utm) {
        $valor = crm_campo($lead, $utm);
        if ($valor !== '') {
            $campanha[] = $valor;
        }
    }
    if ($campanha !== []) {
        $origem[] = 'Campanha: ' . implode(' / ', $campanha);
    }
    $quando = crm_campo($lead, 'criado_em');
    if ($quando !== '') {
        $origem[] = 'Enviado em: ' . $quando;
    }
    if ($origem !== []) {
        $linhas[] = '';
        $linhas[] = 'Origem';
        foreach ($origem as $linha) {
            $linhas[] = $linha;
        }
    }

    return implode("\n", $linhas);
}

/** Corpo do POST /people/{id}/deals. */
function crm_payload_negocio(array $lead): array
{
    $payload = [
        'title'       => crm_titulo_negocio($lead),
        'description' => crm_descricao_negocio($lead),
        'funnel'      => (int) config_ler('crm_funil', '904296'),
        'dealStage'   => (int) config_ler('crm_etapa', '1'),
    ];

    $responsavel = trim((string) config_ler('crm_responsavel', ''));
    if ($responsavel !== '') {
        $payload['ownerUser'] = $responsavel;
    }

    return $payload;
}

/** Monta o array de retorno sempre com as mesmas chaves. */
function crm_resultado(
    bool $ok,
    int $http,
    string $resposta,
    ?string $erro,
    ?int $pessoaId = null,
    ?string $negocioUrl = null
): array {
    return [
        'ok'          => $ok,
        'http'        => $http,
        'resposta'    => crm_cortar($resposta),
        'erro'        => $erro,
        'pessoa_id'   => $pessoaId,
        'negocio_url' => $negocioUrl,
    ];
}

/** Corta a resposta sem quebrar caractere UTF-8 no meio. */
function crm_cortar(string $texto): string
{
    return mb_substr($texto, 0, CRM_RESPOSTA_MAX);
}

/**
 * O Agendor recusou o negocio so porque ja existe um com o mesmo titulo
 * para aquela pessoa?
 *
 * Medido na conta real da Castello em 2026-09-10: a API devolve HTTP 400 com
 * "Title There can only be one deal with this title for this
 * organization/person". Como crm_titulo_negocio monta o titulo a partir de
 * marcador, prazo e nome, o mesmo visitante mandando o formulario duas vezes
 * com os mesmos dados produz o mesmo titulo e cai aqui.
 *
 * A comparacao e pelo texto porque o 400 sozinho nao distingue este caso de
 * um payload invalido de verdade, que precisa continuar sendo erro.
 *
 * O corpo e lido de 'bruto', e nao de 'dados': crm_requisitar so decodifica o
 * JSON quando a resposta foi de sucesso, entao numa recusa 'dados' vem nulo.
 */
function crm_negocio_repetido(array $resposta): bool
{
    if (($resposta['http'] ?? 0) !== 400) {
        return false;
    }

    $bruto = (string) ($resposta['bruto'] ?? '');
    $dados = json_decode($bruto, true);
    $erros = is_array($dados) ? ($dados['errors'] ?? null) : null;

    if (is_array($erros)) {
        foreach ($erros as $erro) {
            if (is_string($erro) && stripos($erro, CRM_ERRO_TITULO_REPETIDO) !== false) {
                return true;
            }
        }
        return false;
    }

    /* Corpo em formato inesperado: cai para o texto cru em vez de desistir,
       porque errar aqui custa um lead marcado como falha eterna. */
    return stripos($bruto, CRM_ERRO_TITULO_REPETIDO) !== false;
}

/** Traduz o HTTP num codigo de erro nosso. */
function crm_erro_http(int $http): string
{
    if ($http === 401 || $http === 403) {
        return 'crm_auth';
    }
    if ($http === 429) {
        return 'crm_limite';
    }
    return 'crm_http';
}

/**
 * Uma chamada a API, dentro do que sobrou do orcamento de tempo.
 *
 * @param float $prazoFinal instante (microtime) em que o conjunto expira
 * @return array{ok: bool, http: int, bruto: string, dados: ?array, erro: ?string}
 */
function crm_requisitar(string $metodo, string $caminho, ?array $corpo, float $prazoFinal): array
{
    $restante = $prazoFinal - microtime(true);
    if ($restante < CRM_MINIMO_POR_CHAMADA) {
        return ['ok' => false, 'http' => 0, 'bruto' => '', 'dados' => null, 'erro' => 'crm_tempo'];
    }

    $base = rtrim((string) config_ler('crm_base', CRM_BASE_PADRAO), '/');
    $cabecalhos = [
        'Authorization: Token ' . crm_token(),
        'Accept: application/json',
        'Content-Type: application/json; charset=utf-8',
    ];

    /* So existe em teste: manda o servidor falso simular uma falha. */
    $modoTeste = getenv('CASTELLO_AGENDOR_TESTE_MODO');
    if (is_string($modoTeste) && $modoTeste !== '') {
        $cabecalhos[] = 'X-Falso-Modo: ' . $modoTeste;
    }

    /* Piso, nao teto: arredondar para cima furava o orcamento total em ate
       1s. O minimo de 1 evita CURLOPT_TIMEOUT=0, que para o curl e "sem
       limite". */
    $timeoutChamada = max(1, (int) floor($restante));

    $ch = curl_init($base . $caminho);
    $opcoes = [
        CURLOPT_CUSTOMREQUEST  => $metodo,
        CURLOPT_HTTPHEADER     => $cabecalhos,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeoutChamada,
        CURLOPT_CONNECTTIMEOUT => min(5, $timeoutChamada),
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT      => 'castello-site/1.0',
    ];
    if ($corpo !== null) {
        $opcoes[CURLOPT_POSTFIELDS] = (string) json_encode($corpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    curl_setopt_array($ch, $opcoes);

    $bruto = curl_exec($ch);
    $erroNum = curl_errno($ch);
    $erroTexto = curl_error($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($erroNum !== 0) {
        $erro = $erroNum === CURLE_OPERATION_TIMEDOUT ? 'crm_tempo' : 'crm_conexao';
        return ['ok' => false, 'http' => $http, 'bruto' => 'curl ' . $erroNum . ': ' . $erroTexto, 'dados' => null, 'erro' => $erro];
    }

    $texto = is_string($bruto) ? $bruto : '';

    if ($http < 200 || $http >= 300) {
        return ['ok' => false, 'http' => $http, 'bruto' => $texto, 'dados' => null, 'erro' => crm_erro_http($http)];
    }

    $dados = json_decode($texto, true);
    if (!is_array($dados)) {
        return ['ok' => false, 'http' => $http, 'bruto' => $texto, 'dados' => null, 'erro' => 'crm_resposta_invalida'];
    }

    return ['ok' => true, 'http' => $http, 'bruto' => $texto, 'dados' => $dados, 'erro' => null];
}

/**
 * Manda o lead para o Agendor.
 *
 * Tres passos: procura a pessoa pelo telefone, cria se nao achar, cria o
 * negocio nela. Lead que ja tem crm_pessoa_id pula os dois primeiros, para o
 * reenvio nao duplicar pessoa quando a primeira tentativa criou a pessoa e
 * morreu antes do negocio.
 *
 * A busca e o unico passo tolerante a falha: um lead duplicado no CRM e menos
 * grave que um lead perdido.
 */
function crm_enviar(array $lead): array
{
    if ((string) config_ler('crm_ativo', '0') !== '1') {
        return crm_resultado(false, 0, '', 'crm_desativado');
    }
    if (crm_token() === '') {
        return crm_resultado(false, 0, '', 'crm_sem_token');
    }

    $telefone = crm_whatsapp_busca(crm_campo($lead, 'whatsapp'));
    $pessoaId = (int) ($lead['crm_pessoa_id'] ?? 0);

    if ($pessoaId <= 0 && $telefone === '') {
        return crm_resultado(false, 0, '', 'crm_sem_telefone');
    }

    $timeout = (int) config_ler('crm_timeout', (string) CRM_TIMEOUT_MAX);
    $timeout = max(CRM_MINIMO_POR_CHAMADA, min(CRM_TIMEOUT_MAX, $timeout));
    $prazoFinal = microtime(true) + $timeout;

    /* 1. procura duplicata. Falha aqui nao interrompe: segue e cria. */
    if ($pessoaId <= 0) {
        $busca = crm_requisitar('GET', '/people?phone=' . rawurlencode($telefone), null, $prazoFinal);
        if ($busca['ok']) {
            $primeira = $busca['dados']['data'][0]['id'] ?? null;
            if (is_int($primeira) || (is_string($primeira) && ctype_digit($primeira))) {
                $pessoaId = (int) $primeira;
            }
        } elseif ($busca['erro'] === 'crm_tempo') {
            return crm_resultado(false, $busca['http'], $busca['bruto'], 'crm_tempo');
        }
    }

    /* 2. cria a pessoa quando nao existe */
    if ($pessoaId <= 0) {
        $criacao = crm_requisitar('POST', '/people', crm_payload_pessoa($lead), $prazoFinal);
        if (!$criacao['ok']) {
            return crm_resultado(false, $criacao['http'], $criacao['bruto'], $criacao['erro']);
        }
        $novo = $criacao['dados']['data']['id'] ?? null;
        if (!is_int($novo) && !(is_string($novo) && ctype_digit($novo))) {
            return crm_resultado(false, $criacao['http'], $criacao['bruto'], 'crm_resposta_invalida');
        }
        $pessoaId = (int) $novo;
    }

    /* 3. cria o negocio. E ele que aparece no funil. */
    $negocio = crm_requisitar('POST', '/people/' . $pessoaId . '/deals', crm_payload_negocio($lead), $prazoFinal);
    if (!$negocio['ok']) {
        /* Negocio repetido nao e falha: a pessoa esta no CRM e o negocio com
           aquele titulo ja esta no funil. Reenviar nunca ia adiantar, porque
           o titulo e sempre o mesmo, entao tratar como erro so queimaria as
           cinco tentativas da fila e deixaria o lead marcado em vermelho para
           sempre. A equipe fica sabendo do novo contato pelo e-mail de aviso,
           que sai sempre. Guardamos o corpo da recusa na resposta para a
           auditoria enxergar que nenhum negocio novo nasceu. */
        if (crm_negocio_repetido($negocio)) {
            return crm_resultado(true, $negocio['http'], $negocio['bruto'], null, $pessoaId);
        }
        return crm_resultado(false, $negocio['http'], $negocio['bruto'], $negocio['erro'], $pessoaId);
    }

    $url = $negocio['dados']['data']['_webUrl'] ?? null;

    return crm_resultado(
        true,
        $negocio['http'],
        $negocio['bruto'],
        null,
        $pessoaId,
        is_string($url) && $url !== '' ? $url : null
    );
}
