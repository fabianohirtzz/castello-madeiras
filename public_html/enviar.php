<?php
declare(strict_types=1);

/**
 * Recebe o formulario de orcamento.
 *
 * Ordem, conforme a secao 6 do contrato:
 *   1. honeypot        campo empresa preenchido e robo: sucesso falso, em silencio
 *   2. time-trap       envio abaixo de 3 segundos: sucesso falso, em silencio
 *   3. csrf            token invalido: HTTP 419
 *   4. campos          nome, whatsapp ou busca faltando: HTTP 422
 *   5. GRAVA O LEAD    antes de qualquer integracao
 *   6. CRM             falha nao chega ao visitante
 *   7. e-mail          sai sempre, com o resultado do CRM no corpo
 *
 * Toda a logica esta em enviar_processar(), que devolve HTTP e corpo em vez de
 * imprimir, para o smoke rodar o fluxo inteiro por linha de comando.
 */

require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/leads.php';
require_once __DIR__ . '/lib/crm.php';
require_once __DIR__ . '/lib/email.php';

/** Tempo minimo entre abrir o formulario e enviar, em milissegundos. */
const ENVIAR_TEMPO_MINIMO_MS = 3000;

/** Campos que o visitante preenche, com o limite de cada um. */
const ENVIAR_LIMITES = [
    'nome'     => 120,
    'whatsapp' => 40,
    'busca'    => 120,
    'modelo'   => 120,
    'cidade'   => 120,
    'mensagem' => 4000,
    'pagina'   => 200,
    'referrer' => 400,
];

/**
 * Roda o fluxo inteiro e devolve o que deve virar resposta HTTP.
 *
 * @return array{http: int, corpo: array}
 */
function enviar_processar(array $post): array
{
    $texto = static function ($valor): string {
        return is_scalar($valor) ? trim((string) $valor) : '';
    };
    $silencio = ['http' => 200, 'corpo' => ['ok' => true, 'id' => 0]];

    /* 1. honeypot. O contrato chama o campo de empresa; o prototipo antigo
          usava _gotcha. Os dois barram, para a marcacao poder mudar sem
          abrir buraco. */
    $isca = $texto($post['empresa'] ?? '');
    if ($isca === '') {
        $isca = $texto($post['_gotcha'] ?? '');
    }
    if ($isca !== '') {
        return $silencio;
    }

    /* 2. time-trap. Relogio adiantado do visitante da diferenca negativa e
          passa: perder lead real e pior que aceitar robo. */
    $carimbo = (int) $texto($post['ts'] ?? '');
    if ($carimbo <= 0) {
        return $silencio;
    }
    $decorrido = (int) round(microtime(true) * 1000) - $carimbo;
    if ($decorrido >= 0 && $decorrido < ENVIAR_TEMPO_MINIMO_MS) {
        return $silencio;
    }

    /* 3. csrf */
    $token = isset($post['csrf']) && is_string($post['csrf']) ? $post['csrf'] : null;
    if (!csrf_validar($token)) {
        return ['http' => 419, 'corpo' => ['ok' => false, 'erro' => 'csrf']];
    }

    /* 4. campos obrigatorios */
    $campos = [];
    foreach (ENVIAR_LIMITES as $campo => $limite) {
        $campos[$campo] = mb_substr($texto($post[$campo] ?? ''), 0, $limite);
    }
    foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as $utm) {
        $campos[$utm] = mb_substr($texto($post[$utm] ?? ''), 0, 200);
    }

    $digitos = preg_replace('/\D+/', '', $campos['whatsapp']) ?? '';
    $ruins = [];
    if (mb_strlen($campos['nome']) < 2) {
        $ruins[] = 'nome';
    }
    if (strlen($digitos) < 10 || strlen($digitos) > 13) {
        $ruins[] = 'whatsapp';
    }
    if ($campos['busca'] === '') {
        $ruins[] = 'busca';
    }
    if ($ruins !== []) {
        return ['http' => 422, 'corpo' => ['ok' => false, 'erro' => 'campos', 'campos' => $ruins]];
    }

    /* 5. grava o lead antes de tentar qualquer coisa */
    $id = lead_gravar($campos);
    $lead = $campos;
    $lead['id'] = $id;
    $lead['criado_em'] = agora();

    /* 6. so entao o CRM. O visitante nunca ve falha de integracao. */
    $resultado = crm_enviar($lead);
    if (!empty($resultado['ok'])) {
        $status = 'enviado';
        $tentativas = 1;
        $registro = (string) $resultado['resposta'];
    } elseif (($resultado['erro'] ?? '') === 'crm_desativado') {
        $status = 'desativado';
        $tentativas = 0;
        $registro = 'crm_desativado';
    } else {
        $status = 'erro';
        $tentativas = 1;
        $registro = trim((string) ($resultado['erro'] ?? '') . ' ' . (string) ($resultado['resposta'] ?? ''));
    }
    lead_marcar($id, $status, $tentativas, $registro !== '' ? $registro : null);

    /* 7. o e-mail sai sempre */
    email_lead_novo($lead, $resultado);

    return ['http' => 200, 'corpo' => ['ok' => true, 'id' => $id]];
}

/** Escreve a resposta JSON e encerra. */
function enviar_responder(int $http, array $corpo): void
{
    http_response_code($http);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($corpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/* Em linha de comando o arquivo so define as funcoes, para o smoke testar. */
if (PHP_SAPI !== 'cli') {
    auth_iniciar();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        enviar_responder(405, ['ok' => false, 'erro' => 'metodo']);
    }

    try {
        $resposta = enviar_processar($_POST);
    } catch (Throwable $falha) {
        error_log('enviar.php: ' . $falha->getMessage());
        enviar_responder(500, ['ok' => false, 'erro' => 'servidor']);
    }

    enviar_responder($resposta['http'], $resposta['corpo']);
}
