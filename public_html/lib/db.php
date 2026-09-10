<?php
declare(strict_types=1);

/**
 * Camada de acesso ao banco da Castello.
 *
 * O SQLite vive fora do public_html. Onde exatamente muda por ambiente, entao
 * o caminho e resolvido em quatro niveis por castello_caminho_config().
 *
 * A sintaxe SQL aqui e compativel com SQLite 3.26, que e a versao do servidor:
 * nada de INSERT ... RETURNING, nada de ALTER TABLE DROP COLUMN, nada de
 * tabela STRICT. UPSERT com ON CONFLICT DO UPDATE existe desde a 3.24 e e o
 * jeito de gravar em config e blocos.
 */

/**
 * Caminho absoluto da pasta de configuracao, resolvido nesta ordem:
 *
 * 1. a constante CASTELLO_CONFIG, quando ja definida (o smoke test define);
 * 2. a variavel de ambiente CASTELLO_CONFIG;
 * 3. o arquivo lib/caminho-config.php, que devolve um caminho absoluto e so
 *    existe no servidor, fora do git;
 * 4. o padrao dirname(__DIR__, 2) . '/config', que ja acerta no local.
 */
function castello_caminho_config(): string
{
    if (defined('CASTELLO_CONFIG')) {
        return (string) constant('CASTELLO_CONFIG');
    }

    $doAmbiente = getenv('CASTELLO_CONFIG');
    if (is_string($doAmbiente) && $doAmbiente !== '') {
        return rtrim($doAmbiente, "/\\");
    }

    $arquivo = __DIR__ . '/caminho-config.php';
    if (is_file($arquivo)) {
        $doArquivo = require $arquivo;
        if (is_string($doArquivo) && $doArquivo !== '') {
            return rtrim($doArquivo, "/\\");
        }
    }

    return dirname(__DIR__, 2) . '/config';
}

if (!defined('CASTELLO_CONFIG')) {
    define('CASTELLO_CONFIG', castello_caminho_config());
}

date_default_timezone_set('America/Sao_Paulo');
mb_internal_encoding('UTF-8');

if (is_file(CASTELLO_CONFIG . '/segredos.php')) {
    require_once CASTELLO_CONFIG . '/segredos.php';
}

/**
 * Colunas que o schema.sql cria mas que um banco antigo pode nao ter.
 * tabela => coluna => tipo da coluna no ALTER TABLE.
 */
const CASTELLO_COLUNAS_NOVAS = [
    'leads' => [
        'prazo'         => 'TEXT',
        'crm_pessoa_id' => 'INTEGER',
    ],
];

/**
 * Acrescenta as colunas que faltam num banco que ja existe.
 *
 * CREATE TABLE IF NOT EXISTS nao mexe em tabela ja criada, entao um banco em
 * producao nunca ganharia coluna nova so pelo schema.sql. Isto mora aqui, e
 * nao no migrar.php, porque o migrar.php e apagado do servidor depois da
 * instalacao e a migracao nunca chegaria la.
 *
 * Roda em toda conexao, entao precisa ser barato e idempotente.
 *
 * @return array<int,string> nomes das colunas acrescentadas nesta chamada
 */
function db_garantir_colunas(PDO $pdo): array
{
    $acrescentadas = [];

    foreach (CASTELLO_COLUNAS_NOVAS as $tabela => $colunas) {
        $info = $pdo->query('PRAGMA table_info(' . $tabela . ')')->fetchAll(PDO::FETCH_ASSOC);
        if ($info === []) {
            continue; // tabela ainda nao existe; o schema.sql cuida dela
        }
        $existentes = array_column($info, 'name');

        foreach ($colunas as $coluna => $tipo) {
            if (in_array($coluna, $existentes, true)) {
                continue;
            }
            try {
                $pdo->exec('ALTER TABLE ' . $tabela . ' ADD COLUMN ' . $coluna . ' ' . $tipo);
                $acrescentadas[] = $coluna;
            } catch (PDOException $ex) {
                // Outra requisicao pode ter acrescentado a coluna entre o PRAGMA e o ALTER,
                // especialmente logo apos o deploy. SQLite responde com "duplicate column name".
                if (strpos($ex->getMessage(), 'duplicate column name') === false) {
                    throw $ex; // erro de disco, permissao ou outro; precisa subir
                }
                // Se foi coluna duplicada, a coluna ja existe: outra requisicao venceu nesta.
                // Nao incluimos na lista de acrescentadas, porque nao foi esta chamada.
            }
        }
    }

    return $acrescentadas;
}

/**
 * Chaves de config criadas na primeira abertura do banco. Valores do contrato.
 * reenvio_chave nao esta aqui porque e sorteada na instalacao.
 */
const CASTELLO_CONFIG_PADRAO = [
    'videos_na_home'  => '8',
    'email_aviso'     => 'contato@castellomadeiras.com.br',
    'email_dominio'   => 'castellomadeiras.com.br',
    'crm_ativo'       => '0',
    'crm_endpoint'    => '',
    'crm_metodo'      => 'POST',
    'crm_cabecalhos'  => '{}',
    'crm_mapa_campos' => '{"nome":"nome","whatsapp":"telefone","busca":"interesse","modelo":"modelo","cidade":"cidade","mensagem":"observacao","utm_source":"origem","utm_campaign":"campanha","pagina":"pagina"}',
    'crm_timeout'     => '10',
];

/** Conexao unica. Cria o banco e aplica o schema quando faltar. */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    // 0700 porque a pasta guarda o banco e os segredos. No servidor de teste
    // ela fica um nivel acima do public_html e e o PHP quem a cria, ja que o
    // usuario de FTP nao alcanca esse nivel.
    if (!is_dir(CASTELLO_CONFIG) && !mkdir(CASTELLO_CONFIG, 0700, true) && !is_dir(CASTELLO_CONFIG)) {
        throw new RuntimeException('nao consegui criar a pasta de config em ' . CASTELLO_CONFIG);
    }

    $pdo = new PDO('sqlite:' . CASTELLO_CONFIG . '/castello.db', null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    $schema = file_get_contents(__DIR__ . '/schema.sql');
    if ($schema === false) {
        throw new RuntimeException('nao consegui ler lib/schema.sql');
    }
    $pdo->exec($schema);

    db_garantir_colunas($pdo);

    $inserir = $pdo->prepare('INSERT OR IGNORE INTO config (chave, valor) VALUES (?, ?)');
    foreach (CASTELLO_CONFIG_PADRAO as $chave => $valor) {
        $inserir->execute([$chave, $valor]);
    }
    $inserir->execute(['reenvio_chave', bin2hex(random_bytes(16))]);

    return $pdo;
}

/** Escape obrigatorio de todo dado do banco impresso em HTML. */
function e(?string $texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Data e hora atuais no formato gravado no banco. */
function agora(): string
{
    return date('Y-m-d H:i:s');
}

function config_ler(string $chave, ?string $padrao = null): ?string
{
    $st = db()->prepare('SELECT valor FROM config WHERE chave = ?');
    $st->execute([$chave]);
    $valor = $st->fetchColumn();

    return $valor === false ? $padrao : (string) $valor;
}

function config_gravar(string $chave, string $valor): void
{
    $st = db()->prepare(
        'INSERT INTO config (chave, valor) VALUES (?, ?)
         ON CONFLICT(chave) DO UPDATE SET valor = excluded.valor'
    );
    $st->execute([$chave, $valor]);
}
