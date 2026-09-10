<?php
declare(strict_types=1);

/**
 * Conector do CRM.
 *
 * O CRM da Castello ainda nao foi escolhido. Por isso endpoint, metodo,
 * cabecalhos e mapa de campos moram na tabela config: quando o cliente
 * confirmar qual e o CRM, isso vira configuracao no painel, nao codigo.
 *
 * Depende de config_ler(), definida em lib/db.php (frente 1). Este arquivo nao
 * carrega a lib: quem carrega e o ponto de entrada.
 */

/** Segundos de espera antes de desistir. Sobrescrito por config.crm_timeout. */
const CRM_TIMEOUT_PADRAO = 10;

/**
 * Teto do tempo de espera. O contrato (secao 1.2) fixa 10 segundos, nunca
 * mais, porque max_execution_time no servidor e 60 e um CRM lento nao pode
 * segurar a resposta ao visitante.
 */
const CRM_TIMEOUT_MAX = 10;

/** Tamanho maximo da resposta guardada, para nao inchar o banco. */
const CRM_RESPOSTA_MAX = 2000;

/**
 * Envia um lead ao CRM.
 *
 * @param array $lead Lead com as chaves do site (nome, whatsapp, busca, ...).
 * @return array{ok: bool, http: int, resposta: string, erro: ?string}
 */
function crm_enviar(array $lead): array
{
    if ((string) config_ler('crm_ativo', '0') !== '1') {
        return crm_resultado(false, 0, '', 'crm_desativado');
    }

    $endpoint = trim((string) config_ler('crm_endpoint', ''));
    if ($endpoint === '' || filter_var($endpoint, FILTER_VALIDATE_URL) === false) {
        return crm_resultado(false, 0, '', 'crm_sem_endpoint');
    }

    $cabecalhosConfig = crm_json_objeto((string) config_ler('crm_cabecalhos', '{}'));
    if ($cabecalhosConfig === null) {
        return crm_resultado(false, 0, '', 'crm_cabecalhos_invalidos');
    }

    $mapa = crm_json_objeto((string) config_ler('crm_mapa_campos', '{}'));
    if ($mapa === null || $mapa === []) {
        return crm_resultado(false, 0, '', 'crm_mapa_invalido');
    }

    $metodo = strtoupper(trim((string) config_ler('crm_metodo', 'POST')));
    if (!in_array($metodo, ['POST', 'PUT', 'PATCH'], true)) {
        $metodo = 'POST';
    }

    $payload = [];
    foreach ($mapa as $campoSite => $campoCrm) {
        if (!is_string($campoCrm) || $campoCrm === '') {
            continue;
        }
        $valor = $lead[$campoSite] ?? '';
        $payload[$campoCrm] = is_scalar($valor) ? (string) $valor : '';
    }
    $corpo = (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $cabecalhos = ['Content-Type: application/json; charset=utf-8', 'Accept: application/json'];
    foreach ($cabecalhosConfig as $nome => $valor) {
        if (is_string($nome) && $nome !== '' && is_scalar($valor)) {
            $nomeLimpo = preg_replace('/[^A-Za-z0-9\-]/', '', $nome);
            $valorLimpo = str_replace(["\r", "\n"], '', (string) $valor);
            if ($nomeLimpo !== '' && $valorLimpo !== '') {
                $cabecalhos[] = $nomeLimpo . ': ' . $valorLimpo;
            }
        }
    }

    $timeout = (int) config_ler('crm_timeout', (string) CRM_TIMEOUT_PADRAO);
    $timeout = max(2, min(CRM_TIMEOUT_MAX, $timeout));

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $metodo,
        CURLOPT_POSTFIELDS     => $corpo,
        CURLOPT_HTTPHEADER     => $cabecalhos,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT      => 'castello-site/1.0',
    ]);
    $bruto = curl_exec($ch);
    $erroNum = curl_errno($ch);
    $erroTexto = curl_error($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($erroNum !== 0) {
        $erro = $erroNum === CURLE_OPERATION_TIMEDOUT ? 'crm_tempo' : 'crm_conexao';
        return crm_resultado(false, $http, 'curl ' . $erroNum . ': ' . $erroTexto, $erro);
    }

    $resposta = crm_cortar(is_string($bruto) ? $bruto : '');

    if ($http < 200 || $http >= 300) {
        return crm_resultado(false, $http, $resposta, 'crm_http');
    }

    if (!crm_e_json(is_string($bruto) ? $bruto : '')) {
        return crm_resultado(false, $http, $resposta, 'crm_resposta_invalida');
    }

    return crm_resultado(true, $http, $resposta, null);
}

/** Monta o array de retorno sempre com as mesmas quatro chaves. */
function crm_resultado(bool $ok, int $http, string $resposta, ?string $erro): array
{
    return ['ok' => $ok, 'http' => $http, 'resposta' => crm_cortar($resposta), 'erro' => $erro];
}

/** Decodifica um JSON que precisa ser objeto ou lista. Devolve null se nao for. */
function crm_json_objeto(string $bruto): ?array
{
    $bruto = trim($bruto);
    if ($bruto === '') {
        return [];
    }
    $dados = json_decode($bruto, true);
    return is_array($dados) ? $dados : null;
}

/** Diz se o corpo da resposta e JSON valido. */
function crm_e_json(string $bruto): bool
{
    if (trim($bruto) === '') {
        return false;
    }
    json_decode($bruto);
    return json_last_error() === JSON_ERROR_NONE;
}

/** Corta a resposta sem quebrar caractere UTF-8 no meio. */
function crm_cortar(string $texto): string
{
    return mb_substr($texto, 0, CRM_RESPOSTA_MAX);
}
