<?php
declare(strict_types=1);

/**
 * Base compartilhada das ferramentas de linha de comando do Agendor.
 *
 * Guarda o que as tres ferramentas precisam: achar o token, falar com a API
 * e imprimir erro de forma igual. Quem decide o que pode ser feito e cada
 * ferramenta: o agendor-ler.php so faz GET numa lista fechada de rotas, o
 * agendor-conferir.php so faz GET, e o agendor-apagar.php faz DELETE e exige
 * confirmacao explicita na linha de comando.
 *
 * Este arquivo nao roda sozinho e nao le argumentos por conta propria.
 */

const AGENDOR_BASE = 'https://api.agendor.com.br/v3';

/** Limite da API e 4 requisicoes por segundo. Ficamos bem abaixo. */
const AGENDOR_PAUSA_US = 300000;

const AGENDOR_TIMEOUT = 20;

/**
 * Devolve o texto em UTF-8 sem BOM.
 *
 * O PowerShell do Windows grava com "> arquivo" em UTF-16LE com BOM, e foi
 * assim que o arquivo de credencial nasceu na primeira tentativa. Em vez de
 * pedir para o humano acertar a codificacao, as ferramentas aceitam as tres.
 */
function agendor_normalizar(string $bruto): string
{
    if (str_starts_with($bruto, "\xFF\xFE")) {
        return (string) mb_convert_encoding(substr($bruto, 2), 'UTF-8', 'UTF-16LE');
    }
    if (str_starts_with($bruto, "\xFE\xFF")) {
        return (string) mb_convert_encoding(substr($bruto, 2), 'UTF-8', 'UTF-16BE');
    }
    if (str_starts_with($bruto, "\xEF\xBB\xBF")) {
        return substr($bruto, 3);
    }
    return $bruto;
}

/** Le o token de --token=, da variavel de ambiente ou do arquivo. Vazio se nao achar. */
function agendor_token(array $argv): string
{
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--token=')) {
            return trim(substr($arg, 8));
        }
    }

    $doAmbiente = getenv('AGENDOR_TOKEN');
    if (is_string($doAmbiente) && trim($doAmbiente) !== '') {
        return trim($doAmbiente);
    }

    $arquivo = dirname(__DIR__) . '/.credenciais-agendor';
    if (is_readable($arquivo)) {
        $bruto = (string) file_get_contents($arquivo);
        foreach (preg_split('/\R/', agendor_normalizar($bruto)) ?: [] as $linha) {
            $linha = trim($linha);
            if ($linha === '' || $linha[0] === '#') {
                continue;
            }
            [$chave, $valor] = array_pad(explode('=', $linha, 2), 2, '');
            if (trim($chave) === 'AGENDOR_TOKEN') {
                return trim($valor, " \t\"'");
            }
        }
    }

    return '';
}

/**
 * Uma requisicao na API. $metodo em maiusculas, $caminho comecando com barra.
 *
 * @return array{ok: bool, http: int, dados: mixed, erro: ?string}
 */
function agendor_requisitar(string $token, string $metodo, string $caminho, string $agente): array
{
    $ch = curl_init(AGENDOR_BASE . $caminho);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $metodo,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Token ' . $token,
            'Accept: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => AGENDOR_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT      => $agente,
    ]);
    $bruto     = curl_exec($ch);
    $erroNum   = curl_errno($ch);
    $erroTexto = curl_error($ch);
    $http      = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($erroNum !== 0) {
        return ['ok' => false, 'http' => $http, 'dados' => null, 'erro' => 'curl ' . $erroNum . ': ' . $erroTexto];
    }

    $dados = json_decode(is_string($bruto) ? $bruto : '', true);

    if ($http < 200 || $http >= 300) {
        $msg = is_array($dados)
            ? (string) json_encode($dados, JSON_UNESCAPED_UNICODE)
            : (string) $bruto;
        return ['ok' => false, 'http' => $http, 'dados' => $dados, 'erro' => 'HTTP ' . $http . ' ' . $msg];
    }

    return ['ok' => true, 'http' => $http, 'dados' => $dados, 'erro' => null];
}

/** So os digitos, para comparar telefone com telefone. */
function agendor_digitos(string $bruto): string
{
    return preg_replace('/\D+/', '', $bruto) ?? '';
}
