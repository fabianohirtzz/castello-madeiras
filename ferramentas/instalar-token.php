<?php
/**
 * Grava o token do Agendor no config/segredos.php DO SERVIDOR.
 *
 * Por que existe: o config/ fica fora do public_html e o usuario de FTP e
 * chrooted na raiz do site, entao nenhum deploy alcanca aquela pasta. Este
 * script sobe para a raiz do site por FTPS, roda uma vez e se apaga.
 *
 * Uso (da raiz do repositorio):
 *
 *   set -a; . ./.credenciais-deploy; set +a
 *   curl -sS --ssl-reqd -k -T ferramentas/instalar-token.php \
 *        "ftp://$FTP_HOST$FTP_RAIZ/instalar-token.php" --user "$FTP_USER:$FTP_PASS"
 *   curl -sS -k --resolve castello.tohospedando.com.br:443:200.11.120.114 \
 *        --data-urlencode "credenciais@.credenciais-agendor" \
 *        https://castello.tohospedando.com.br/instalar-token.php
 *
 * O token viaja no CORPO do POST, nunca na URL, para nao entrar no log de
 * acesso do servidor. O corpo e o proprio .credenciais-agendor: quem roda
 * isto nunca precisa extrair, colar ou ver o valor.
 *
 * Responde em texto puro e nunca imprime o token. A ultima linha diz se o
 * script conseguiu se apagar; se disser que NAO, apague pelo FTP na hora.
 */
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');

// GET nao faz nada e nem revela que o arquivo existe.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(404);
    exit("nao encontrado\n");
}

$credenciais = (string) ($_POST['credenciais'] ?? '');
if (preg_match('/^\s*AGENDOR_TOKEN\s*=\s*["\']?([^"\'\r\n]+)["\']?\s*$/m', $credenciais, $m) !== 1) {
    http_response_code(400);
    exit("erro: nao achei a linha AGENDOR_TOKEN no corpo enviado\n");
}
$token = trim($m[1]);

// O token do Agendor e um uuid, mas aceito qualquer coisa segura de escrever
// entre aspas simples num arquivo PHP, para o dia em que o formato mudar.
if (preg_match('/^[A-Za-z0-9._-]{16,200}$/', $token) !== 1) {
    http_response_code(400);
    exit("erro: token com formato inesperado\n");
}

$caminhoArquivo = __DIR__ . '/lib/caminho-config.php';
if (!is_file($caminhoArquivo)) {
    http_response_code(500);
    exit("erro: lib/caminho-config.php nao existe, o deploy nao rodou aqui\n");
}
$config   = (string) require $caminhoArquivo;
$segredos = $config . '/segredos.php';

if (!is_dir($config)) {
    http_response_code(500);
    exit("erro: a pasta de config nao existe em $config\n");
}

$conteudo = is_file($segredos) ? (string) file_get_contents($segredos) : "<?php\n";
$linha    = "define('CASTELLO_AGENDOR_TOKEN', '" . $token . "');";
$padrao   = "/define\(\s*'CASTELLO_AGENDOR_TOKEN'\s*,.*?\);/s";

if (preg_match($padrao, $conteudo) === 1) {
    $novo = (string) preg_replace($padrao, $linha, $conteudo, 1);
    $acao = 'substituido';
} else {
    $novo = rtrim($conteudo) . "\n\n"
          . "// Token da conta do Agendor da Castello. Fora do banco de proposito:\n"
          . "// o backup do painel empacota o banco e o cliente baixa esse zip.\n"
          . $linha . "\n";
    $acao = 'acrescentado';
}

// Guarda uma copia antes de mexer, para a chave de migracao nao sumir se
// algo der errado no meio da gravacao.
if (is_file($segredos)) {
    @copy($segredos, $segredos . '.bak');
}

if (file_put_contents($segredos, $novo, LOCK_EX) === false) {
    http_response_code(500);
    exit("erro: nao consegui gravar $segredos\n");
}
@chmod($segredos, 0600);

// Confere lendo de volta. Nunca imprime o token.
$lido = (string) file_get_contents($segredos);
echo "arquivo: $segredos\n";
echo "acao: $acao\n";
echo "confere: " . (str_contains($lido, $linha) ? 'sim' : 'NAO') . "\n";
echo "caracteres do token: " . strlen($token) . "\n";
echo "permissao: " . substr(sprintf('%o', (int) fileperms($segredos)), -4) . "\n";
echo "outros defines no arquivo: " . (int) preg_match_all('/^define\(/m', $lido) . "\n";

echo "autoapagou: " . (@unlink(__FILE__) ? 'sim' : 'NAO - APAGUE PELO FTP AGORA') . "\n";
