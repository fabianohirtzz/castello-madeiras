# Castello Fase 2 — Frente 3: Formulário, CRM e reenvio — Plano de implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Entregar o caminho completo do lead: o formulário posta em `enviar.php`, o lead é gravado no SQLite antes de qualquer integração, o conector configurável tenta o CRM, um e-mail avisa a Castello, e uma rotina reenvia os pendentes.

**Architecture:** PHP 8.3 puro, sem framework e sem Composer. `enviar.php` é a única porta de entrada do formulário e expõe a função `enviar_processar()` para que todo o fluxo seja testável por linha de comando, sem servidor HTTP. As três bibliotecas da frente (`leads.php`, `crm.php`, `email.php`) não carregam nada da frente 1: elas apenas **chamam** `db()`, `e()`, `agora()`, `config_ler()` e `csrf_validar()`, e quem carrega essas funções são os pontos de entrada. Enquanto a frente 1 não terminar, `testes/apoio-f1.php` fornece essas funções contra um banco temporário. O JS do formulário vive em `js/formulario.js`, carregado à parte, e intercepta o `submit` na fase de captura do `document` para desligar o envio antigo do `js/main.js` sem editar aquele arquivo.

**Tech Stack:** PHP 8.3.32 (`pdo_sqlite`, `curl`, `mbstring`, `openssl`, `fileinfo`), SQLite, JavaScript ES5 sem dependência, Node 24 apenas como executor dos testes do JS.

**Spec:** [docs/superpowers/specs/2026-09-09-castello-fase2-design.md](../specs/2026-09-09-castello-fase2-design.md) (seção 6)
**Contrato:** [docs/superpowers/plans/2026-09-09-castello-contrato.md](2026-09-09-castello-contrato.md) (seções 4.3, 4.4, 4.5 e 6) — **fonte da verdade**

## Global Constraints

- PHP 8.3, sem framework, sem Composer, sem dependência externa.
- Todo arquivo PHP começa com `<?php` e **não** tem `?>` no final.
- `declare(strict_types=1);` em toda a `lib/`.
- Codificação UTF-8 sem BOM. Fuso `America/Sao_Paulo`. Datas em `Y-m-d H:i:s`.
- Nomes de função, tabela e coluna em português, sem acento, `snake_case`.
- Toda saída de dado do banco no HTML passa por `e()`. Sem exceção.
- Copy sem travessões, sem emojis, números concretos, frases diretas.
- Nenhuma frente escreve arquivo de outra. A frente 3 escreve **somente**: `public_html/enviar.php`, `public_html/reenviar.php`, `public_html/lib/leads.php`, `public_html/lib/crm.php`, `public_html/lib/email.php`, `public_html/js/formulario.js`, `testes/crm-falso.php`, `testes/smoke-f3.php`, `testes/apoio-f1.php`, `testes/formulario.test.js`.
- **Fora de escopo, não editar em nenhuma hipótese:** `lib/db.php`, `lib/conteudo.php`, `lib/auth.php`, `lib/upload.php`, `partials/`, `painel/`, `index.php`, `flex.php`, `css/style.css`, `js/main.js`, `front/`.
- Não existe painel de leads e não deve existir. A gravação local é rede de segurança.
- Status válidos de `leads.crm_status`: `pendente`, `enviado`, `erro`, `desativado`. Nada além disso.
- Resposta do `enviar.php`: sempre JSON, sempre `Content-Type: application/json; charset=utf-8`.
- `mail()` não funciona na máquina local. O modo de arquivo (variável de ambiente `CASTELLO_EMAIL_DIR`) é o caminho de teste; o envio real só se valida no servidor.
- Ciclo de teste: `php testes/smoke-f3.php`, que na Tarefa 9 vira o caso `testes/casos/85-crm.php` do runner da frente 1. **Nunca acrescentar `require` no topo de `testes/smoke.php`:** ele despacha cada caso num processo separado e não carrega `lib/` nenhuma.
- Mensagens de commit em português, terminando com a linha `Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>`.

---

## Estrutura de arquivos

| Arquivo | Responsabilidade |
|---|---|
| `public_html/enviar.php` | Porta de entrada do formulário. Defesas, validação, gravação, CRM, e-mail, resposta JSON. Expõe `enviar_processar()` para teste por CLI. |
| `public_html/reenviar.php` | Rotina de reenvio dos pendentes. Modo CLI para o cron e modo web protegido por chave. |
| `public_html/lib/leads.php` | Gravação, marcação, listagem de pendentes e laço de reenvio. Só fala com o banco. |
| `public_html/lib/crm.php` | Conector configurável. Lê `config`, monta e envia a requisição, devolve o resultado bruto. |
| `public_html/lib/email.php` | Monta e dispara o aviso de lead novo. Modo de arquivo para teste local. |
| `public_html/js/formulario.js` | UTM, campos ocultos, máscara, validação e envio. Expõe as funções puras em `window.CastelloFormulario` para teste. |
| `testes/crm-falso.php` | Servidor de teste que finge ser o CRM: sucesso, erro HTTP, resposta inválida, demora, eco. |
| `testes/apoio-f1.php` | Apoio temporário: define `db()`, `e()`, `agora()`, `config_ler()`, `config_gravar()`, `csrf_token()` e `csrf_validar()` enquanto a lib da frente 1 não existe. **Removido na Tarefa 9.** |
| `testes/smoke-f3.php` | Runner por linha de comando. Vira `testes/casos/85-crm.php` na Tarefa 9. |
| `testes/formulario.test.js` | Testes das funções puras do JS, executados por Node. |

---

## Ordem das tarefas

1. Harness de teste e apoio da frente 1
2. `lib/leads.php` — gravação e marcação
3. `testes/crm-falso.php` — o CRM de mentira
4. `lib/crm.php` — o conector
5. `lib/email.php` — aviso de lead novo
6. `enviar.php` — o caminho completo do lead
7. Reenvio dos pendentes
8. `js/formulario.js` — UTM, campos ocultos e envio
9. Costura: virar caso do runner (`testes/casos/85-crm.php`) e remover o apoio

---

### Tarefa 1: Harness de teste e apoio da frente 1

Cria o runner por linha de comando e o arquivo que fornece as funções da frente 1 contra um banco temporário, para que a frente 3 rode sozinha.

**Files:**
- Create: `testes/apoio-f1.php`
- Create: `testes/smoke-f3.php`
- Create (pastas): `public_html/lib/`, `public_html/js/`

**Interfaces:**
- Consumes: nada.
- Produces: `db(): PDO`, `e(?string): string`, `agora(): string`, `config_ler(string, ?string): ?string`, `config_gravar(string, string): void`, `csrf_token(): string`, `csrf_validar(?string): bool`, a constante `CASTELLO_DB_TESTE`, e os helpers de teste `t_secao(string)`, `t_ok(string, bool, string)`, `t_igual(string, mixed, mixed)`, `t_resumo(): int`, `teste_banco_apagar(): void`, `teste_banco_limpar(): void`. Toda função é definida dentro de `if (!function_exists(...))`, então quando a lib real da frente 1 for carregada antes, nada aqui entra em ação.

- [x] **Passo 1: criar as pastas**

```bash
cd "E:/Clientes/Castello Madeiras/prototipo-site-castello"
mkdir -p public_html/lib public_html/js testes
```

Se `public_html/` ainda não existir porque a frente 1 não fez o `git mv` da seção 3.1 do contrato, criar assim mesmo. Os arquivos da frente 3 já nascem no lugar definitivo e o `git mv` da frente 1 não conflita com eles.

- [x] **Passo 2: escrever o apoio da frente 1**

Criar `testes/apoio-f1.php`:

```php
<?php
declare(strict_types=1);

/**
 * Apoio de testes da frente 3.
 *
 * Fornece db(), e(), agora(), config_ler(), config_gravar(), csrf_token() e
 * csrf_validar() enquanto lib/db.php e lib/auth.php da frente 1 nao existem.
 * Cada funcao so e definida se ainda nao existir: quando a lib real for
 * carregada antes deste arquivo, nada aqui entra em acao.
 *
 * ARQUIVO TEMPORARIO. Removido na Tarefa 9 do plano da frente 3.
 */

date_default_timezone_set('America/Sao_Paulo');

if (!defined('CASTELLO_DB_TESTE')) {
    define('CASTELLO_DB_TESTE', sys_get_temp_dir() . '/castello-teste-f3.db');
}

if (!function_exists('db')) {
    function db(): PDO
    {
        static $pdo = null;
        if ($pdo instanceof PDO) {
            return $pdo;
        }
        $pdo = new PDO('sqlite:' . CASTELLO_DB_TESTE);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('CREATE TABLE IF NOT EXISTS config (chave TEXT PRIMARY KEY, valor TEXT)');
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS leads (
               id                   INTEGER PRIMARY KEY,
               nome                 TEXT,
               whatsapp             TEXT,
               busca                TEXT,
               modelo               TEXT,
               cidade               TEXT,
               mensagem             TEXT,
               pagina               TEXT,
               referrer             TEXT,
               utm_source           TEXT,
               utm_medium           TEXT,
               utm_campaign         TEXT,
               utm_term             TEXT,
               utm_content          TEXT,
               criado_em            TEXT NOT NULL,
               crm_status           TEXT NOT NULL DEFAULT 'pendente'
                                    CHECK (crm_status IN ('pendente','enviado','erro','desativado')),
               crm_tentativas       INTEGER NOT NULL DEFAULT 0,
               crm_ultima_tentativa TEXT,
               crm_resposta         TEXT
             )"
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_leads_pendentes ON leads (crm_status, criado_em)');
        return $pdo;
    }
}

if (!function_exists('e')) {
    function e(?string $texto): string
    {
        return htmlspecialchars((string) $texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('agora')) {
    function agora(): string
    {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('config_ler')) {
    function config_ler(string $chave, ?string $padrao = null): ?string
    {
        $st = db()->prepare('SELECT valor FROM config WHERE chave = :c');
        $st->execute([':c' => $chave]);
        $valor = $st->fetchColumn();
        return $valor === false ? $padrao : (string) $valor;
    }
}

if (!function_exists('config_gravar')) {
    function config_gravar(string $chave, string $valor): void
    {
        $st = db()->prepare(
            'INSERT INTO config (chave, valor) VALUES (:c, :v)
             ON CONFLICT (chave) DO UPDATE SET valor = excluded.valor'
        );
        $st->execute([':c' => $chave, ':v' => $valor]);
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return 'token-de-teste-frente-3';
    }
}

if (!function_exists('csrf_validar')) {
    function csrf_validar(?string $token): bool
    {
        return is_string($token) && $token !== '' && hash_equals(csrf_token(), $token);
    }
}

/** Apaga o arquivo do banco de teste. So faz efeito antes da primeira chamada a db(). */
if (!function_exists('teste_banco_apagar')) {
    function teste_banco_apagar(): void
    {
        foreach ([CASTELLO_DB_TESTE, CASTELLO_DB_TESTE . '-wal', CASTELLO_DB_TESTE . '-shm'] as $arquivo) {
            if (is_file($arquivo)) {
                @unlink($arquivo);
            }
        }
    }
}

/** Esvazia leads e config, mantendo a conexao aberta. */
if (!function_exists('teste_banco_limpar')) {
    function teste_banco_limpar(): void
    {
        db()->exec('DELETE FROM leads');
        db()->exec('DELETE FROM config');
        db()->exec("DELETE FROM sqlite_sequence WHERE name = 'leads'");
    }
}
```

- [x] **Passo 3: escrever o runner com uma asserção que falha de propósito**

Criar `testes/smoke-f3.php`. A última asserção está errada de propósito, para provar que o runner acusa falha e devolve código de saída 1:

```php
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
t_igual('FALHA PROPOSITAL', 'a', 'b');

exit(t_resumo());
```

- [x] **Passo 4: rodar e ver o runner acusar a falha**

```bash
cd "E:/Clientes/Castello Madeiras/prototipo-site-castello"
php testes/smoke-f3.php; echo "codigo de saida: $?"
```

Esperado: a linha `FALHA FALHA PROPOSITAL  ->  esperado 'a', obtido 'b'`, o resumo `9/10 passaram` e `codigo de saida: 1`.

- [x] **Passo 5: remover a asserção falsa**

Apagar de `testes/smoke-f3.php` a linha `t_igual('FALHA PROPOSITAL', 'a', 'b');`.

- [x] **Passo 6: rodar e ver passar**

```bash
php testes/smoke-f3.php; echo "codigo de saida: $?"
```

Esperado: `9/9 passaram` e `codigo de saida: 0`.

- [x] **Passo 7: commitar**

```bash
git add testes/apoio-f1.php testes/smoke-f3.php
git commit -F - <<'MSG'
test: harness da frente 3 e apoio temporario da frente 1

Runner por linha de comando com t_ok, t_igual e resumo com codigo de saida.
O apoio define db, e, agora, config_ler, config_gravar e csrf contra um banco
temporario, sempre atras de function_exists, para sumir sozinho quando a lib
real da frente 1 for carregada antes.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
MSG
```

---

### Tarefa 2: `lib/leads.php` — gravação e marcação

A rede de segurança. Grava o lead antes de qualquer integração e registra o resultado da tentativa de CRM. As funções de pendentes e reenvio ficam para a Tarefa 7, porque dependem do conector.

**Files:**
- Create: `public_html/lib/leads.php`
- Test: `testes/smoke-f3.php` (nova seção)

**Interfaces:**
- Consumes: `db(): PDO` e `agora(): string` da frente 1. `leads.php` **não** faz `require` de `lib/db.php`: quem carrega é o ponto de entrada (`enviar.php`, `reenviar.php` ou o smoke).
- Produces:
  - `lead_gravar(array $dados): int` — devolve o id do lead recém-gravado, sempre com `crm_status = 'pendente'` e `crm_tentativas = 0`.
  - `lead_marcar(int $id, string $status, int $tentativas, ?string $resposta): void`
  - Constantes `LEAD_CAMPOS` (array com os 13 campos de conteúdo, na ordem do schema), `LEAD_TENTATIVAS_MAX` (5) e `LEAD_RESPOSTA_MAX` (2000).

- [x] **Passo 1: escrever os testes que falham**

Inserir em `testes/smoke-f3.php`, **antes** da linha `exit(t_resumo());`:

```php
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
```

- [x] **Passo 2: rodar e ver falhar**

```bash
cd "E:/Clientes/Castello Madeiras/prototipo-site-castello"
php testes/smoke-f3.php; echo "codigo de saida: $?"
```

Esperado: erro fatal `Failed opening required '.../public_html/lib/leads.php'`.

- [x] **Passo 3: escrever `public_html/lib/leads.php`**

```php
<?php
declare(strict_types=1);

/**
 * Rede de segurança dos leads.
 *
 * O lead e gravado aqui ANTES de qualquer tentativa de integracao. Se o CRM
 * cair, o contato continua no banco e a rotina de reenvio (reenviar.php) o
 * empurra depois. Nao existe tela de leads no painel, por decisao da spec.
 *
 * Depende de db() e agora(), definidas em lib/db.php (frente 1). Este arquivo
 * nao carrega a lib: quem carrega e o ponto de entrada.
 */

/** Campos de conteudo do lead, na ordem do schema. */
const LEAD_CAMPOS = [
    'nome', 'whatsapp', 'busca', 'modelo', 'cidade', 'mensagem',
    'pagina', 'referrer',
    'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
];

/** Depois de 5 tentativas malsucedidas o lead para de ser reenviado. */
const LEAD_TENTATIVAS_MAX = 5;

/** Tamanho maximo guardado em crm_resposta, para nao inchar o banco. */
const LEAD_RESPOSTA_MAX = 2000;

/** Status aceitos pela coluna crm_status (CHECK do schema). */
const LEAD_STATUS = ['pendente', 'enviado', 'erro', 'desativado'];

/**
 * Grava o lead com status pendente e devolve o id.
 *
 * Campo ausente vira string vazia. Valores muito longos sao cortados, porque
 * o formulario e publico e o banco e a rede de segurança, nao um deposito.
 */
function lead_gravar(array $dados): int
{
    $valores = [];
    foreach (LEAD_CAMPOS as $campo) {
        $bruto = $dados[$campo] ?? '';
        $texto = is_scalar($bruto) ? trim((string) $bruto) : '';
        $limite = $campo === 'mensagem' ? 4000 : 500;
        $valores[$campo] = mb_substr($texto, 0, $limite);
    }
    $valores['criado_em'] = agora();

    $colunas = implode(', ', LEAD_CAMPOS);
    $marcas  = ':' . implode(', :', LEAD_CAMPOS);

    $st = db()->prepare(
        "INSERT INTO leads ({$colunas}, criado_em, crm_status, crm_tentativas)
         VALUES ({$marcas}, :criado_em, 'pendente', 0)"
    );
    $st->execute($valores);

    return (int) db()->lastInsertId();
}

/**
 * Registra o resultado de uma tentativa de envio ao CRM.
 *
 * Status fora da lista vira 'erro', para nunca esbarrar no CHECK do schema e
 * derrubar o enviar.php por causa de um valor inesperado.
 */
function lead_marcar(int $id, string $status, int $tentativas, ?string $resposta): void
{
    if (!in_array($status, LEAD_STATUS, true)) {
        $status = 'erro';
    }
    if ($resposta !== null) {
        $resposta = mb_substr(trim($resposta), 0, LEAD_RESPOSTA_MAX);
        if ($resposta === '') {
            $resposta = null;
        }
    }

    $st = db()->prepare(
        'UPDATE leads
            SET crm_status = :status,
                crm_tentativas = :tentativas,
                crm_ultima_tentativa = :quando,
                crm_resposta = :resposta
          WHERE id = :id'
    );
    $st->execute([
        ':status'     => $status,
        ':tentativas' => max(0, $tentativas),
        ':quando'     => agora(),
        ':resposta'   => $resposta,
        ':id'         => $id,
    ]);
}
```

- [x] **Passo 4: rodar e ver passar**

```bash
php testes/smoke-f3.php; echo "codigo de saida: $?"
```

Esperado: todas as linhas da seção `lib/leads.php: gravacao e marcacao` com `ok` e `codigo de saida: 0`.

- [x] **Passo 5: commitar**

```bash
git add public_html/lib/leads.php testes/smoke-f3.php
git commit -F - <<'MSG'
feat(leads): grava o lead no SQLite antes de qualquer integracao

lead_gravar corta valores longos, transforma campo ausente em string vazia e
nasce sempre com status pendente. lead_marcar registra status, tentativas,
hora e resposta bruta do CRM, com o status invalido caindo em erro para nunca
esbarrar no CHECK do schema.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
MSG
```

---

### Tarefa 3: `testes/crm-falso.php` — o CRM de mentira

O conector precisa de um alvo real para ser testado de verdade, com socket, cabeçalho e tempo. Este servidor finge ser o CRM e responde do jeito que o teste pedir.

**Files:**
- Create: `testes/crm-falso.php`
- Modify: `testes/smoke-f3.php` (nova seção e os helpers de subir e derrubar o servidor)

**Interfaces:**
- Consumes: nada.
- Produces:
  - Servidor HTTP levantado com `php -S 127.0.0.1:8765 testes/crm-falso.php`.
  - Modos, escolhidos pelo parâmetro `modo` da URL: `ok` (200 JSON), `erro500` (500 JSON), `invalido` (200 com HTML no corpo), `demora` (dorme `seg` segundos, padrão 5, e responde 200), `eco` (200 JSON devolvendo o que recebeu). Sem `modo`, vale `ok`.
  - Arquivo `sys_get_temp_dir() . '/crm-falso-ultima.json'` com a última requisição recebida: `metodo`, `uri`, `cabecalhos`, `corpo`.
  - Helpers no smoke: `crm_falso_subir(int $porta = 8765)`, `crm_falso_derrubar($processo): void`, `crm_falso_ultima(): array`, `crm_falso_url(string $modo, array $extra = []): string`.

- [x] **Passo 1: escrever os testes que falham**

Inserir em `testes/smoke-f3.php`, antes de `exit(t_resumo());`:

```php
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
    $processo = proc_open(
        $comando,
        [0 => ['pipe', 'r'], 1 => ['file', sys_get_temp_dir() . '/crm-falso-saida.log', 'a'], 2 => ['file', sys_get_temp_dir() . '/crm-falso-saida.log', 'a']],
        $tubos
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
```

- [x] **Passo 2: rodar e ver falhar**

```bash
cd "E:/Clientes/Castello Madeiras/prototipo-site-castello"
php testes/smoke-f3.php; echo "codigo de saida: $?"
```

Esperado: `FALHA servidor de teste subiu na porta 8765` (o `php -S` não acha `testes/crm-falso.php`) e `codigo de saida: 1`.

- [x] **Passo 3: escrever `testes/crm-falso.php`**

```php
<?php
declare(strict_types=1);

/**
 * CRM de mentira, para testar lib/crm.php sem credencial real.
 *
 * Subir:  php -S 127.0.0.1:8765 testes/crm-falso.php
 *
 * Modos, pelo parametro modo da URL:
 *   ok        200 com JSON de sucesso                (padrao)
 *   erro500   500 com JSON de erro
 *   invalido  200 com HTML no corpo, para provar o tratamento de resposta invalida
 *   demora    dorme seg segundos (padrao 5) e responde 200, para estourar o tempo
 *   eco       200 devolvendo o payload recebido, para conferir o mapa de campos
 *
 * Toda requisicao e registrada em sys_get_temp_dir()/crm-falso-ultima.json.
 */

$corpo = (string) file_get_contents('php://input');

$cabecalhos = [];
foreach ($_SERVER as $chave => $valor) {
    if (strpos($chave, 'HTTP_') === 0) {
        $nome = strtolower(str_replace('_', '-', substr($chave, 5)));
        $cabecalhos[$nome] = (string) $valor;
    }
}
if (isset($_SERVER['CONTENT_TYPE'])) {
    $cabecalhos['content-type'] = (string) $_SERVER['CONTENT_TYPE'];
}

file_put_contents(
    sys_get_temp_dir() . '/crm-falso-ultima.json',
    json_encode([
        'metodo'     => $_SERVER['REQUEST_METHOD'] ?? '',
        'uri'        => $_SERVER['REQUEST_URI'] ?? '',
        'cabecalhos' => $cabecalhos,
        'corpo'      => $corpo,
        'quando'     => date('Y-m-d H:i:s'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
);

$modo = isset($_GET['modo']) ? (string) $_GET['modo'] : 'ok';

if ($modo === 'erro500') {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['erro' => 'interno', 'detalhe' => 'o CRM caiu']);
    return;
}

if ($modo === 'invalido') {
    http_response_code(200);
    header('Content-Type: text/html; charset=utf-8');
    echo '<html><body>Manutencao programada. Volte mais tarde.</body></html>';
    return;
}

if ($modo === 'demora') {
    $segundos = isset($_GET['seg']) ? (int) $_GET['seg'] : 5;
    $segundos = max(1, min(30, $segundos));
    sleep($segundos);
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'ok', 'demorou' => $segundos]);
    return;
}

if ($modo === 'eco') {
    $recebido = json_decode($corpo, true);
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(
        ['status' => 'ok', 'recebido' => is_array($recebido) ? $recebido : null],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    return;
}

http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['status' => 'ok', 'id' => 'CRM-' . substr(md5($corpo), 0, 8)]);
```

O `return` no fim de cada modo funciona porque o arquivo é o script roteador do servidor embutido do PHP, executado no escopo global.

- [x] **Passo 4: rodar e ver passar**

```bash
php testes/smoke-f3.php; echo "codigo de saida: $?"
```

Esperado: as onze asserções da seção `testes/crm-falso.php` com `ok` e `codigo de saida: 0`. O teste do modo `demora` faz o smoke levar uns 2 segundos a mais, o que é esperado.

Se `servidor de teste subiu` falhar, conferir `sys_get_temp_dir()/crm-falso-saida.log` e, no Windows, se a porta 8765 já está ocupada (`netstat -ano | findstr 8765`).

- [x] **Passo 5: commitar**

```bash
git add testes/crm-falso.php testes/smoke-f3.php
git commit -F - <<'MSG'
test(crm): servidor de teste que finge ser o CRM

Cinco modos: sucesso, erro 500, resposta que nao e JSON, demora que estoura o
tempo e eco do payload recebido. Registra a ultima requisicao em arquivo, com
metodo, cabecalhos e corpo, para o teste conferir o mapa de campos e o
cabecalho de autorizacao.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
MSG
```

---

### Tarefa 4: `lib/crm.php` — o conector configurável

Uma função só. Lê endpoint, método, cabeçalhos e mapa de campos da tabela `config`, monta a requisição, e devolve sucesso ou erro com a resposta bruta. Trocar de CRM é trocar configuração, não código.

**Files:**
- Create: `public_html/lib/crm.php`
- Modify: `testes/smoke-f3.php` (nova seção)

**Interfaces:**
- Consumes: `config_ler(string, ?string): ?string` da frente 1. Não faz `require` de `lib/db.php`.
- Produces: `crm_enviar(array $lead): array{ok: bool, http: int, resposta: string, erro: ?string}`.
  Valores possíveis de `erro`: `null` (sucesso), `crm_desativado`, `crm_sem_endpoint`, `crm_cabecalhos_invalidos`, `crm_mapa_invalido`, `crm_tempo`, `crm_conexao`, `crm_http`, `crm_resposta_invalida`.
  Constantes `CRM_TIMEOUT_PADRAO` (10) e `CRM_RESPOSTA_MAX` (2000).

**Chave de `config` nova:** `crm_timeout`, em segundos, padrão `10`. Não está na tabela 2.1 do contrato. O código funciona sem ela porque `config_ler` devolve o padrão; ela existe para o teste de tempo esgotado não precisar esperar 10 segundos. Ver "Pontos em que o contrato ficou curto", no fim do plano.

- [x] **Passo 1: escrever os testes que falham**

Inserir em `testes/smoke-f3.php`, antes de `exit(t_resumo());`:

```php
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
```

- [x] **Passo 2: rodar e ver falhar**

```bash
cd "E:/Clientes/Castello Madeiras/prototipo-site-castello"
php testes/smoke-f3.php; echo "codigo de saida: $?"
```

Esperado: erro fatal `Failed opening required '.../public_html/lib/crm.php'`.

- [x] **Passo 3: escrever `public_html/lib/crm.php`**

```php
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
    $timeout = max(2, min(60, $timeout));

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
```

- [x] **Passo 4: rodar e ver passar**

```bash
php testes/smoke-f3.php; echo "codigo de saida: $?"
```

Esperado: toda a seção `lib/crm.php: o conector` com `ok` e `codigo de saida: 0`. O teste de tempo esgotado leva cerca de 2 segundos.

- [x] **Passo 5: commitar**

```bash
git add public_html/lib/crm.php testes/smoke-f3.php
git commit -F - <<'MSG'
feat(crm): conector configuravel lido da tabela config

crm_enviar le endpoint, metodo, cabecalhos e mapa de campos da config, monta o
payload com os nomes que o CRM espera e devolve ok, http, resposta bruta e
erro. Cobre CRM desligado, endpoint vazio, mapa e cabecalhos quebrados, HTTP
de erro, resposta que nao e JSON, tempo esgotado e falha de conexao.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
MSG
```

---

### Tarefa 5: `lib/email.php` — aviso de lead novo

O e-mail é a garantia de que a Castello vê o contato mesmo quando o CRM falha. Como `mail()` não funciona na máquina local, existe um modo de arquivo: com a variável de ambiente `CASTELLO_EMAIL_DIR` apontando para uma pasta, a mensagem é gravada lá em vez de enviada. O envio real só se valida no servidor.

**Files:**
- Create: `public_html/lib/email.php`
- Modify: `testes/smoke-f3.php` (nova seção)

**Interfaces:**
- Consumes: `config_ler(string, ?string): ?string` da frente 1.
- Produces:
  - `email_lead_novo(array $lead, array $resultado_crm): bool`
  - `email_corpo_lead(array $lead, array $resultado_crm): string` (usada pelo teste para conferir o texto)
  - `email_remetente(): string`

- [x] **Passo 1: escrever os testes que falham**

Inserir em `testes/smoke-f3.php`, antes de `exit(t_resumo());`:

```php
require_once __DIR__ . '/../public_html/lib/email.php';

t_secao('lib/email.php: aviso de lead novo');
teste_banco_limpar();

$pastaEmail = sys_get_temp_dir() . '/castello-emails';
if (!is_dir($pastaEmail)) {
    mkdir($pastaEmail, 0777, true);
}
foreach ((array) glob($pastaEmail . '/*.txt') as $velho) {
    @unlink($velho);
}
putenv('CASTELLO_EMAIL_DIR=' . $pastaEmail);

$leadEmail = [
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

/* corpo do e-mail com CRM entregue */
$corpoOk = email_corpo_lead($leadEmail, ['ok' => true, 'http' => 200, 'resposta' => '{"id":"CRM-1"}', 'erro' => null]);
t_ok('corpo traz o nome', strpos($corpoOk, 'Fabiano Hirtz') !== false);
t_ok('corpo traz o whatsapp', strpos($corpoOk, '(48) 99824-4494') !== false);
t_ok('corpo traz a cidade', strpos($corpoOk, 'Tubarao / SC') !== false);
t_ok('corpo traz a origem utm', strpos($corpoOk, 'instagram') !== false);
t_ok('corpo traz o id do lead', strpos($corpoOk, '42') !== false);
t_ok('corpo diz que o CRM recebeu', strpos($corpoOk, 'CRM: entregue') !== false, $corpoOk);
t_ok('corpo traz o link de resposta no whatsapp', strpos($corpoOk, 'wa.me/5548998244494') !== false, $corpoOk);

/* corpo do e-mail com CRM falhando */
$corpoErro = email_corpo_lead($leadEmail, ['ok' => false, 'http' => 500, 'resposta' => 'interno', 'erro' => 'crm_http']);
t_ok('corpo diz que o CRM falhou', strpos($corpoErro, 'CRM: falhou') !== false, $corpoErro);
t_ok('corpo mostra o codigo do erro', strpos($corpoErro, 'crm_http') !== false);
t_ok('corpo mostra o http do erro', strpos($corpoErro, '500') !== false);

/* corpo do e-mail com CRM desligado */
$corpoOff = email_corpo_lead($leadEmail, ['ok' => false, 'http' => 0, 'resposta' => '', 'erro' => 'crm_desativado']);
t_ok('corpo diz que o CRM esta desligado', strpos($corpoOff, 'CRM: desligado') !== false, $corpoOff);

/* modo de arquivo grava em vez de enviar */
config_gravar('email_aviso', 'contato@castellomadeiras.com.br');
$enviou = email_lead_novo($leadEmail, ['ok' => true, 'http' => 200, 'resposta' => '{}', 'erro' => null]);
t_ok('email_lead_novo devolve true no modo de arquivo', $enviou === true);
$arquivos = (array) glob($pastaEmail . '/*.txt');
t_igual('gravou exatamente um arquivo', 1, count($arquivos));
$gravado = $arquivos ? (string) file_get_contents($arquivos[0]) : '';
t_ok('arquivo tem o destinatario', strpos($gravado, 'contato@castellomadeiras.com.br') !== false, $gravado);
t_ok('arquivo tem o assunto com o nome', strpos($gravado, 'Lead novo no site: Fabiano Hirtz') !== false, $gravado);
t_ok('arquivo declara charset UTF-8', strpos($gravado, 'charset=UTF-8') !== false, $gravado);

/* e-mail de destino invalido nao tenta enviar */
config_gravar('email_aviso', 'isso-nao-e-email');
t_ok('destino invalido devolve false', email_lead_novo($leadEmail, ['ok' => true, 'http' => 200, 'resposta' => '{}', 'erro' => null]) === false);
t_igual('destino invalido nao grava arquivo novo', 1, count((array) glob($pastaEmail . '/*.txt')));

/* remetente sai de um dominio limpo */
config_gravar('email_aviso', 'contato@castellomadeiras.com.br');
t_ok('remetente e um e-mail valido', (bool) filter_var(email_remetente(), FILTER_VALIDATE_EMAIL), email_remetente());

putenv('CASTELLO_EMAIL_DIR');
```

- [x] **Passo 2: rodar e ver falhar**

```bash
cd "E:/Clientes/Castello Madeiras/prototipo-site-castello"
php testes/smoke-f3.php; echo "codigo de saida: $?"
```

Esperado: erro fatal `Failed opening required '.../public_html/lib/email.php'`.

- [x] **Passo 3: escrever `public_html/lib/email.php`**

```php
<?php
declare(strict_types=1);

/**
 * Aviso de lead novo para a Castello.
 *
 * O e-mail e a garantia de que o contato chega mesmo quando o CRM falha. Ele
 * sai sempre, com o resultado da tentativa de CRM escrito no corpo.
 *
 * mail() nao funciona na maquina local. Com a variavel de ambiente
 * CASTELLO_EMAIL_DIR apontando para uma pasta existente, a mensagem e gravada
 * em arquivo em vez de enviada. O envio real so se valida no servidor.
 *
 * Depende de config_ler(), definida em lib/db.php (frente 1).
 */

/** Rotulos dos campos do lead no corpo do e-mail, na ordem de leitura. */
const EMAIL_ROTULOS = [
    'nome'         => 'Nome',
    'whatsapp'     => 'WhatsApp',
    'busca'        => 'O que busca',
    'modelo'       => 'Modelo de interesse',
    'cidade'       => 'Cidade ou regiao',
    'mensagem'     => 'Mensagem',
    'pagina'       => 'Pagina de origem',
    'referrer'     => 'Veio de',
    'utm_source'   => 'utm_source',
    'utm_medium'   => 'utm_medium',
    'utm_campaign' => 'utm_campaign',
    'utm_term'     => 'utm_term',
    'utm_content'  => 'utm_content',
];

/**
 * Manda o aviso de lead novo. Devolve true quando a mensagem saiu (ou foi
 * gravada, no modo de arquivo).
 */
function email_lead_novo(array $lead, array $resultado_crm): bool
{
    $destino = trim((string) config_ler('email_aviso', 'contato@castellomadeiras.com.br'));
    if ($destino === '' || filter_var($destino, FILTER_VALIDATE_EMAIL) === false) {
        return false;
    }

    $nome = trim((string) ($lead['nome'] ?? ''));
    if ($nome === '') {
        $nome = 'sem nome';
    }
    $assunto = 'Lead novo no site: ' . $nome;
    $corpo = email_corpo_lead($lead, $resultado_crm);

    $cabecalhos = implode("\r\n", [
        'From: Site Castello <' . email_remetente() . '>',
        'Reply-To: ' . $destino,
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'X-Mailer: castello-site',
    ]);

    $pasta = (string) getenv('CASTELLO_EMAIL_DIR');
    if ($pasta !== '' && is_dir($pasta)) {
        $arquivo = rtrim(str_replace('\\', '/', $pasta), '/')
            . '/email-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.txt';
        $conteudo = 'Para: ' . $destino . "\r\n"
            . 'Assunto: ' . $assunto . "\r\n"
            . $cabecalhos . "\r\n\r\n"
            . $corpo;
        return file_put_contents($arquivo, $conteudo) !== false;
    }

    return @mail($destino, mb_encode_mimeheader($assunto, 'UTF-8', 'B'), $corpo, $cabecalhos);
}

/** Monta o texto do e-mail. Texto puro, sem HTML, para chegar em qualquer cliente. */
function email_corpo_lead(array $lead, array $resultado_crm): string
{
    $linhas = [];
    $linhas[] = 'Chegou um pedido de orcamento pelo site.';
    $linhas[] = '';
    $linhas[] = 'Lead numero: ' . (int) ($lead['id'] ?? 0);
    $linhas[] = 'Recebido em: ' . (string) ($lead['criado_em'] ?? '');
    $linhas[] = '';

    foreach (EMAIL_ROTULOS as $campo => $rotulo) {
        $valor = $lead[$campo] ?? '';
        $valor = is_scalar($valor) ? trim((string) $valor) : '';
        if ($valor !== '') {
            $linhas[] = $rotulo . ': ' . $valor;
        }
    }

    $digitos = preg_replace('/\D+/', '', (string) ($lead['whatsapp'] ?? '')) ?? '';
    if (strlen($digitos) >= 10) {
        $numero = strlen($digitos) <= 11 ? '55' . $digitos : $digitos;
        $linhas[] = '';
        $linhas[] = 'Responder no WhatsApp: https://wa.me/' . $numero;
    }

    $linhas[] = '';
    $linhas[] = '-----';
    $linhas[] = email_linha_crm($resultado_crm);
    $linhas[] = '';
    $linhas[] = 'Este lead esta gravado no banco do site. Se o CRM falhou, a rotina de';
    $linhas[] = 'reenvio tenta de novo sozinha. Nenhum contato se perde.';

    return implode("\r\n", $linhas);
}

/** Resume o resultado da tentativa de CRM em uma linha legivel. */
function email_linha_crm(array $resultado_crm): string
{
    $erro = $resultado_crm['erro'] ?? null;

    if (!empty($resultado_crm['ok'])) {
        return 'CRM: entregue (HTTP ' . (int) ($resultado_crm['http'] ?? 0) . ')';
    }
    if ($erro === 'crm_desativado') {
        return 'CRM: desligado nas configuracoes. O lead ficou guardado para reenvio.';
    }

    return 'CRM: falhou (' . (string) $erro . ', HTTP ' . (int) ($resultado_crm['http'] ?? 0) . '). '
        . 'Resposta: ' . mb_substr(trim((string) ($resultado_crm['resposta'] ?? '')), 0, 300);
}

/** Remetente no proprio dominio, porque hospedagem compartilhada recusa From de fora. */
function email_remetente(): string
{
    $host = (string) config_ler('email_dominio', '');
    if ($host === '') {
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    }
    $host = strtolower(preg_replace('/[^a-z0-9.\-]/i', '', $host) ?? '');
    $host = preg_replace('/^www\./', '', $host) ?? '';
    if ($host === '' || strpos($host, '.') === false) {
        $host = 'castellomadeiras.com.br';
    }

    return 'site@' . $host;
}
```

- [x] **Passo 4: rodar e ver passar**

```bash
php testes/smoke-f3.php; echo "codigo de saida: $?"
```

Esperado: toda a seção `lib/email.php: aviso de lead novo` com `ok` e `codigo de saida: 0`.

- [x] **Passo 5: conferir o e-mail gravado com os próprios olhos**

```bash
CASTELLO_EMAIL_DIR="$(php -r 'echo sys_get_temp_dir();')/castello-emails"
ls -1 "$CASTELLO_EMAIL_DIR" | tail -1
cat "$CASTELLO_EMAIL_DIR/$(ls -1 "$CASTELLO_EMAIL_DIR" | tail -1)"
```

Conferir que o texto está legível, com acento correto, sem travessão e sem emoji. O envio real por `mail()` fica pendente de validação no servidor da EreHost.

- [x] **Passo 6: commitar**

```bash
git add public_html/lib/email.php testes/smoke-f3.php
git commit -F - <<'MSG'
feat(email): aviso de lead novo com o resultado do CRM no corpo

email_lead_novo manda texto puro em UTF-8 para o endereco de config.email_aviso,
com todos os campos preenchidos, link pronto de resposta no WhatsApp e uma
linha dizendo se o CRM recebeu, falhou ou esta desligado. Com a variavel de
ambiente CASTELLO_EMAIL_DIR a mensagem e gravada em arquivo, porque mail() nao
funciona na maquina local.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
MSG
```

---

### Tarefa 6: `enviar.php` — o caminho completo do lead

A porta de entrada. Honeypot, time-trap, CSRF, validação, gravação, CRM, e-mail, resposta JSON. Toda a lógica mora em `enviar_processar()`, que devolve o par HTTP mais corpo, para que o fluxo inteiro seja testável por linha de comando, sem servidor.

**Files:**
- Create: `public_html/enviar.php`
- Modify: `testes/smoke-f3.php` (nova seção)

**Interfaces:**
- Consumes: `db()`, `agora()`, `config_ler()` e `csrf_validar()` da frente 1; `lead_gravar()` e `lead_marcar()` da Tarefa 2; `crm_enviar()` da Tarefa 4; `email_lead_novo()` da Tarefa 5.
- Produces:
  - `enviar_processar(array $post): array{http: int, corpo: array}`
  - `enviar_responder(int $http, array $corpo): void` (escreve o JSON e encerra)
  - Constante `ENVIAR_TEMPO_MINIMO_MS` (3000)

**Ordem das defesas, conforme a seção 6.2 do contrato:** honeypot, depois time-trap, depois CSRF, depois campos. O honeypot e o time-trap recusam em silêncio, respondendo `{"ok":true,"id":0}` com HTTP 200, para não ensinar o robô.

**Decisão sobre relógio adiantado:** o `ts` é o `Date.now()` do navegador, comparado com o relógio do servidor. Quando o relógio do visitante está adiantado, a diferença dá negativa. Nesse caso o envio **passa**, porque perder um lead real é pior que aceitar um envio de robô que já passou pelo honeypot. Só a faixa `0 <= decorrido < 3000` é recusada.

- [x] **Passo 1: escrever os testes que falham**

Inserir em `testes/smoke-f3.php`, antes de `exit(t_resumo());`:

```php
require_once __DIR__ . '/../public_html/enviar.php';

t_secao('enviar.php: o caminho do lead');
teste_banco_limpar();
config_gravar('crm_ativo', '0');
config_gravar('email_aviso', 'contato@castellomadeiras.com.br');
putenv('CASTELLO_EMAIL_DIR=' . $pastaEmail);
foreach ((array) glob($pastaEmail . '/*.txt') as $velho) {
    @unlink($velho);
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

function contar_leads(): int
{
    return (int) db()->query('SELECT COUNT(*) FROM leads')->fetchColumn();
}

/* caminho feliz com o CRM desligado */
$r = enviar_processar(post_valido());
t_igual('caminho feliz: HTTP 200', 200, $r['http']);
t_ok('caminho feliz: ok true', ($r['corpo']['ok'] ?? null) === true, json_encode($r['corpo']));
t_ok('caminho feliz: devolve o id do lead', (int) ($r['corpo']['id'] ?? 0) > 0);
t_igual('caminho feliz: gravou um lead', 1, contar_leads());

$gravado = db()->query('SELECT * FROM leads ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
t_igual('gravou a utm_campaign', 'flex-setembro', $gravado['utm_campaign']);
t_igual('gravou a pagina', '/index.php', $gravado['pagina']);
t_igual('CRM desligado deixa o status desativado', 'desativado', $gravado['crm_status']);
t_igual('CRM desligado nao conta tentativa', 0, (int) $gravado['crm_tentativas']);
t_igual('CRM desligado disparou o e-mail assim mesmo', 1, count((array) glob($pastaEmail . '/*.txt')));

/* honeypot preenchido: sucesso falso, nada gravado */
$antes = contar_leads();
$r = enviar_processar(post_valido(['empresa' => 'Loja do Robo']));
t_igual('honeypot: HTTP 200', 200, $r['http']);
t_ok('honeypot: responde sucesso falso', ($r['corpo']['ok'] ?? null) === true, json_encode($r['corpo']));
t_igual('honeypot: id zero', 0, (int) ($r['corpo']['id'] ?? -1));
t_igual('honeypot: nao gravou lead', $antes, contar_leads());

/* honeypot com o nome antigo do campo, _gotcha, tambem barra */
$r = enviar_processar(post_valido(['empresa' => '', '_gotcha' => 'robo']));
t_ok('honeypot _gotcha: responde sucesso falso', ($r['corpo']['ok'] ?? null) === true);
t_igual('honeypot _gotcha: nao gravou lead', $antes, contar_leads());

/* envio em menos de 3 segundos */
$antes = contar_leads();
$r = enviar_processar(post_valido(['ts' => (string) (int) round((microtime(true) - 1) * 1000)]));
t_igual('rapido demais: HTTP 200', 200, $r['http']);
t_ok('rapido demais: responde sucesso falso', ($r['corpo']['ok'] ?? null) === true);
t_igual('rapido demais: id zero', 0, (int) ($r['corpo']['id'] ?? -1));
t_igual('rapido demais: nao gravou lead', $antes, contar_leads());

/* sem carimbo de tempo nenhum */
$r = enviar_processar(post_valido(['ts' => '']));
t_ok('sem ts: responde sucesso falso', ($r['corpo']['ok'] ?? null) === true);
t_igual('sem ts: nao gravou lead', $antes, contar_leads());

/* relogio do visitante adiantado nao pode barrar */
$r = enviar_processar(post_valido(['ts' => (string) (int) round((microtime(true) + 600) * 1000)]));
t_ok('relogio adiantado passa', (int) ($r['corpo']['id'] ?? 0) > 0, json_encode($r['corpo']));
$antes = contar_leads();

/* CSRF invalido */
$r = enviar_processar(post_valido(['csrf' => 'token-errado']));
t_igual('csrf invalido: HTTP 419', 419, $r['http']);
t_ok('csrf invalido: ok false', ($r['corpo']['ok'] ?? null) === false);
t_igual('csrf invalido: erro csrf', 'csrf', $r['corpo']['erro'] ?? null);
t_igual('csrf invalido: nao gravou lead', $antes, contar_leads());

$r = enviar_processar(post_valido(['csrf' => '']));
t_igual('csrf ausente: HTTP 419', 419, $r['http']);

/* campos obrigatorios vazios */
$r = enviar_processar(post_valido(['nome' => '', 'whatsapp' => '', 'busca' => '']));
t_igual('campos vazios: HTTP 422', 422, $r['http']);
t_ok('campos vazios: ok false', ($r['corpo']['ok'] ?? null) === false);
t_igual('campos vazios: erro campos', 'campos', $r['corpo']['erro'] ?? null);
t_igual('campos vazios: lista os tres', ['nome', 'whatsapp', 'busca'], $r['corpo']['campos'] ?? null);
t_igual('campos vazios: nao gravou lead', $antes, contar_leads());

/* so o nome faltando */
$r = enviar_processar(post_valido(['nome' => '   ']));
t_igual('nome vazio: lista so nome', ['nome'], $r['corpo']['campos'] ?? null);

/* whatsapp com menos de 10 digitos */
$r = enviar_processar(post_valido(['whatsapp' => '(48) 9982']));
t_igual('whatsapp curto: HTTP 422', 422, $r['http']);
t_igual('whatsapp curto: lista whatsapp', ['whatsapp'], $r['corpo']['campos'] ?? null);
t_igual('whatsapp curto: nao gravou lead', $antes, contar_leads());

/* whatsapp com 10 digitos passa (fixo com DDD) */
$r = enviar_processar(post_valido(['whatsapp' => '(48) 3632-8743']));
t_ok('whatsapp de 10 digitos passa', (int) ($r['corpo']['id'] ?? 0) > 0, json_encode($r['corpo']));
$antes = contar_leads();

/* CRM ligado e respondendo 200 */
$servidor = crm_falso_subir();
t_ok('servidor do CRM falso subiu para o enviar.php', $servidor !== null);

if ($servidor !== null) {
    config_gravar('crm_ativo', '1');
    config_gravar('crm_mapa_campos', $mapaPadrao);
    config_gravar('crm_endpoint', crm_falso_url('ok'));
    $r = enviar_processar(post_valido());
    $id = (int) ($r['corpo']['id'] ?? 0);
    $linha = db()->query('SELECT * FROM leads WHERE id = ' . $id)->fetch(PDO::FETCH_ASSOC);
    t_igual('CRM 200: status enviado', 'enviado', $linha['crm_status']);
    t_igual('CRM 200: uma tentativa', 1, (int) $linha['crm_tentativas']);
    t_ok('CRM 200: guardou a resposta', strpos((string) $linha['crm_resposta'], 'CRM-') !== false);

    /* CRM ligado e respondendo 500: o visitante nao ve erro */
    config_gravar('crm_endpoint', crm_falso_url('erro500'));
    $r = enviar_processar(post_valido());
    t_igual('CRM 500: visitante ainda ve HTTP 200', 200, $r['http']);
    t_ok('CRM 500: visitante ainda ve ok true', ($r['corpo']['ok'] ?? null) === true);
    $id = (int) ($r['corpo']['id'] ?? 0);
    $linha = db()->query('SELECT * FROM leads WHERE id = ' . $id)->fetch(PDO::FETCH_ASSOC);
    t_igual('CRM 500: status erro', 'erro', $linha['crm_status']);
    t_igual('CRM 500: uma tentativa', 1, (int) $linha['crm_tentativas']);
    t_ok('CRM 500: guardou o motivo', strpos((string) $linha['crm_resposta'], 'crm_http') !== false, (string) $linha['crm_resposta']);

    /* CRM ligado e estourando o tempo */
    config_gravar('crm_timeout', '2');
    config_gravar('crm_endpoint', crm_falso_url('demora', ['seg' => 8]));
    $r = enviar_processar(post_valido());
    t_igual('CRM lento: visitante ainda ve HTTP 200', 200, $r['http']);
    $id = (int) ($r['corpo']['id'] ?? 0);
    $linha = db()->query('SELECT * FROM leads WHERE id = ' . $id)->fetch(PDO::FETCH_ASSOC);
    t_igual('CRM lento: status erro', 'erro', $linha['crm_status']);
    t_ok('CRM lento: guardou crm_tempo', strpos((string) $linha['crm_resposta'], 'crm_tempo') !== false, (string) $linha['crm_resposta']);
    config_gravar('crm_timeout', '10');

    crm_falso_derrubar($servidor);
}

/* o e-mail saiu em todos os envios que viraram lead */
t_ok('cada lead gravado gerou um e-mail', count((array) glob($pastaEmail . '/*.txt')) === contar_leads(), 'emails=' . count((array) glob($pastaEmail . '/*.txt')) . ' leads=' . contar_leads());

putenv('CASTELLO_EMAIL_DIR');
```

- [x] **Passo 2: rodar e ver falhar**

```bash
cd "E:/Clientes/Castello Madeiras/prototipo-site-castello"
php testes/smoke-f3.php; echo "codigo de saida: $?"
```

Esperado: erro fatal `Failed opening required '.../public_html/enviar.php'`.

- [x] **Passo 3: escrever `public_html/enviar.php`**

```php
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

/* A lib da frente 1 pode nao existir ainda. Enquanto nao existir, quem fornece
   db(), agora(), config_ler() e csrf_validar() e testes/apoio-f1.php, carregado
   pelo smoke antes deste arquivo. Na Tarefa 9 estes requires viram diretos. */
foreach (['db.php', 'auth.php'] as $arquivoLib) {
    $caminhoLib = __DIR__ . '/lib/' . $arquivoLib;
    if (is_file($caminhoLib)) {
        require_once $caminhoLib;
    }
}
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

    /* 1. honeypot. O contrato chama o campo de empresa; o protótipo antigo
          usava _gotcha. Os dois barram, para a marcação poder mudar sem
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
    if (function_exists('auth_iniciar')) {
        auth_iniciar();
    } elseif (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        enviar_responder(405, ['ok' => false, 'erro' => 'metodo']);
    }

    foreach (['db', 'agora', 'config_ler', 'csrf_validar'] as $obrigatoria) {
        if (!function_exists($obrigatoria)) {
            error_log('enviar.php: falta a funcao ' . $obrigatoria . ' da lib');
            enviar_responder(500, ['ok' => false, 'erro' => 'servidor']);
        }
    }

    try {
        $resposta = enviar_processar($_POST);
    } catch (Throwable $falha) {
        error_log('enviar.php: ' . $falha->getMessage());
        enviar_responder(500, ['ok' => false, 'erro' => 'servidor']);
    }

    enviar_responder($resposta['http'], $resposta['corpo']);
}
```

- [x] **Passo 4: rodar e ver passar**

```bash
php testes/smoke-f3.php; echo "codigo de saida: $?"
```

Esperado: toda a seção `enviar.php: o caminho do lead` com `ok` e `codigo de saida: 0`.

- [x] **Passo 5: conferir que o arquivo não quebra o interpretador**

```bash
php -l public_html/enviar.php
php -l public_html/lib/leads.php
php -l public_html/lib/crm.php
php -l public_html/lib/email.php
```

Esperado: `No syntax errors detected` nos quatro.

- [x] **Passo 6: commitar**

```bash
git add public_html/enviar.php testes/smoke-f3.php
git commit -F - <<'MSG'
feat(form): enviar.php grava o lead antes de tentar o CRM

Honeypot e time-trap recusam em silencio com sucesso falso, csrf invalido
devolve 419 e campo obrigatorio faltando devolve 422 com a lista. Passando por
tudo, o lead e gravado, so entao o CRM e chamado e o e-mail sai com o
resultado. Falha de integracao nunca chega ao visitante. A logica vive em
enviar_processar, que devolve HTTP e corpo, para o smoke rodar o fluxo inteiro
sem servidor.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
MSG
```

---

### Tarefa 7: reenvio dos pendentes

Fecha a rede de segurança: o que não entrou no CRM na hora entra depois, por cron da hospedagem ou por URL protegida por chave.

**Files:**
- Modify: `public_html/lib/leads.php` (acrescenta `leads_pendentes()`, `leads_reenviar()` e o `require_once` de `crm.php`)
- Create: `public_html/reenviar.php`
- Modify: `testes/smoke-f3.php` (nova seção)

**Interfaces:**
- Consumes: `db()`, `agora()`, `config_ler()` da frente 1; `crm_enviar()` da Tarefa 4; `lead_marcar()` da Tarefa 2.
- Produces:
  - `leads_pendentes(int $limite = 20): array` — linhas com `crm_status` em `pendente`, `erro` ou `desativado` e `crm_tentativas < LEAD_TENTATIVAS_MAX`, mais antigas primeiro.
  - `leads_reenviar(): array{tentados: int, enviados: int, falhas: int}`
  - `public_html/reenviar.php`, com modo CLI (para o cron) e modo web protegido por `config.reenvio_chave`.

**Chave de `config` nova:** `reenvio_chave`. Não está na tabela 2.1 do contrato. Enquanto estiver vazia ou com menos de 16 caracteres, o modo web fica **fechado** e só o cron por linha de comando roda. É o padrão seguro. Ver "Pontos em que o contrato ficou curto".

**Por que `desativado` entra na lista de pendentes:** um lead que chegou com o CRM desligado precisa subir quando o CRM for ligado. `leads_reenviar()` sai na hora quando `crm_ativo` não é `1`, então esses leads não gastam tentativa enquanto o conector está desligado.

- [ ] **Passo 1: escrever os testes que falham**

Inserir em `testes/smoke-f3.php`, antes de `exit(t_resumo());`:

```php
t_secao('leads_pendentes e leads_reenviar');
teste_banco_limpar();
config_gravar('email_aviso', 'contato@castellomadeiras.com.br');
config_gravar('crm_mapa_campos', $mapaPadrao);

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

$idPendente   = semear_lead('Pendente', 'pendente', 0);
$idErro       = semear_lead('Com erro', 'erro', 2);
$idDesativado = semear_lead('CRM desligado', 'desativado', 0);
$idEnviado    = semear_lead('Ja entregue', 'enviado', 1);
$idDesistido  = semear_lead('Cansou de tentar', 'erro', LEAD_TENTATIVAS_MAX);

$pendentes = leads_pendentes();
$ids = array_map('intval', array_column($pendentes, 'id'));
t_ok('pendente entra na lista', in_array($idPendente, $ids, true));
t_ok('erro entra na lista', in_array($idErro, $ids, true));
t_ok('desativado entra na lista', in_array($idDesativado, $ids, true));
t_ok('enviado fica de fora', !in_array($idEnviado, $ids, true));
t_ok('quem estourou as tentativas fica de fora', !in_array($idDesistido, $ids, true));
t_igual('a lista tem exatamente tres', 3, count($pendentes));
t_igual('o mais antigo vem primeiro', $idPendente, (int) $pendentes[0]['id']);
t_igual('o limite e respeitado', 2, count(leads_pendentes(2)));

/* CRM desligado: nao mexe em nada */
config_gravar('crm_ativo', '0');
$r = leads_reenviar();
t_igual('CRM desligado: nada tentado', 0, $r['tentados']);
t_igual('CRM desligado: nada enviado', 0, $r['enviados']);
t_igual('CRM desligado: nada falhou', 0, $r['falhas']);
t_igual('CRM desligado: os pendentes continuam la', 3, count(leads_pendentes()));

$servidor = crm_falso_subir();
t_ok('servidor do CRM falso subiu para o reenvio', $servidor !== null);

if ($servidor !== null) {
    /* CRM ligado e respondendo: todos sobem */
    config_gravar('crm_ativo', '1');
    config_gravar('crm_endpoint', crm_falso_url('ok'));
    $r = leads_reenviar();
    t_igual('reenvio: tentou os tres', 3, $r['tentados']);
    t_igual('reenvio: enviou os tres', 3, $r['enviados']);
    t_igual('reenvio: nenhuma falha', 0, $r['falhas']);
    t_igual('reenvio: nao sobrou pendente', 0, count(leads_pendentes()));

    $subiu = db()->query('SELECT * FROM leads WHERE id = ' . (int) $idErro)->fetch(PDO::FETCH_ASSOC);
    t_igual('reenvio: status virou enviado', 'enviado', $subiu['crm_status']);
    t_igual('reenvio: tentativa somou uma', 3, (int) $subiu['crm_tentativas']);

    $intacto = db()->query('SELECT * FROM leads WHERE id = ' . (int) $idDesistido)->fetch(PDO::FETCH_ASSOC);
    t_igual('reenvio: nao mexeu em quem desistiu', LEAD_TENTATIVAS_MAX, (int) $intacto['crm_tentativas']);

    /* CRM ligado e falhando: soma tentativa e continua pendente */
    teste_banco_limpar();
    config_gravar('crm_ativo', '1');
    config_gravar('crm_mapa_campos', $mapaPadrao);
    config_gravar('crm_endpoint', crm_falso_url('erro500'));
    $idFalha = semear_lead('Vai falhar', 'pendente', 0);
    $r = leads_reenviar();
    t_igual('reenvio com falha: tentou um', 1, $r['tentados']);
    t_igual('reenvio com falha: enviou zero', 0, $r['enviados']);
    t_igual('reenvio com falha: contou uma falha', 1, $r['falhas']);
    $falhou = db()->query('SELECT * FROM leads WHERE id = ' . (int) $idFalha)->fetch(PDO::FETCH_ASSOC);
    t_igual('reenvio com falha: status erro', 'erro', $falhou['crm_status']);
    t_igual('reenvio com falha: uma tentativa', 1, (int) $falhou['crm_tentativas']);
    t_ok('reenvio com falha: guardou o motivo', strpos((string) $falhou['crm_resposta'], 'crm_http') !== false);
    t_igual('reenvio com falha: continua na fila', 1, count(leads_pendentes()));

    /* insistindo ate estourar o limite, o lead sai da fila sozinho */
    for ($volta = 0; $volta < LEAD_TENTATIVAS_MAX; $volta++) {
        leads_reenviar();
    }
    t_igual('depois do limite o lead sai da fila', 0, count(leads_pendentes()));

    crm_falso_derrubar($servidor);
}

t_secao('reenviar.php');
t_ok('reenviar.php existe', is_file(__DIR__ . '/../public_html/reenviar.php'));
$saida = [];
$codigo = 0;
exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg(__DIR__ . '/../public_html/reenviar.php') . ' 2>&1', $saida, $codigo);
t_igual('reenviar.php compila', 0, $codigo, implode(' | ', $saida));
$fonte = (string) file_get_contents(__DIR__ . '/../public_html/reenviar.php');
t_ok('reenviar.php compara a chave com hash_equals', strpos($fonte, 'hash_equals') !== false);
t_ok('reenviar.php tem modo de linha de comando', strpos($fonte, "PHP_SAPI === 'cli'") !== false);
```

- [ ] **Passo 2: rodar e ver falhar**

```bash
cd "E:/Clientes/Castello Madeiras/prototipo-site-castello"
php testes/smoke-f3.php; echo "codigo de saida: $?"
```

Esperado: erro fatal `Call to undefined function leads_pendentes()`.

- [ ] **Passo 3: acrescentar as duas funções em `public_html/lib/leads.php`**

No topo do arquivo, logo abaixo do bloco de comentário, acrescentar a linha:

```php
require_once __DIR__ . '/crm.php';
```

No fim do arquivo, acrescentar:

```php
/**
 * Leads que ainda precisam subir para o CRM, mais antigos primeiro.
 *
 * Entram os status pendente, erro e desativado. O desativado entra porque um
 * lead que chegou com o conector desligado precisa subir quando ele for ligado.
 * Sai da fila quem ja foi entregue e quem estourou LEAD_TENTATIVAS_MAX.
 */
function leads_pendentes(int $limite = 20): array
{
    $limite = max(1, min(200, $limite));

    $st = db()->prepare(
        "SELECT * FROM leads
          WHERE crm_status IN ('pendente','erro','desativado')
            AND crm_tentativas < :maximo
          ORDER BY criado_em ASC, id ASC
          LIMIT :limite"
    );
    $st->bindValue(':maximo', LEAD_TENTATIVAS_MAX, PDO::PARAM_INT);
    $st->bindValue(':limite', $limite, PDO::PARAM_INT);
    $st->execute();

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Tenta subir a fila de pendentes.
 *
 * Sai na hora quando o conector esta desligado, para nao gastar tentativa dos
 * leads que so estao esperando o CRM ser configurado.
 *
 * @return array{tentados: int, enviados: int, falhas: int}
 */
function leads_reenviar(): array
{
    $resumo = ['tentados' => 0, 'enviados' => 0, 'falhas' => 0];

    if ((string) config_ler('crm_ativo', '0') !== '1') {
        return $resumo;
    }

    foreach (leads_pendentes(20) as $lead) {
        $resumo['tentados']++;
        $resultado = crm_enviar($lead);
        $tentativas = ((int) ($lead['crm_tentativas'] ?? 0)) + 1;

        if (!empty($resultado['ok'])) {
            lead_marcar((int) $lead['id'], 'enviado', $tentativas, (string) $resultado['resposta']);
            $resumo['enviados']++;
            continue;
        }

        $motivo = trim((string) ($resultado['erro'] ?? '') . ' ' . (string) ($resultado['resposta'] ?? ''));
        lead_marcar((int) $lead['id'], 'erro', $tentativas, $motivo !== '' ? $motivo : null);
        $resumo['falhas']++;
    }

    return $resumo;
}
```

- [ ] **Passo 4: escrever `public_html/reenviar.php`**

```php
<?php
declare(strict_types=1);

// Reenvio dos leads que nao chegaram ao CRM.
//
// Cron da hospedagem, a cada 15 minutos:
//   */15 * * * * /usr/bin/php /home/USUARIO/public_html/reenviar.php >/dev/null 2>&1
//
// Ou por URL, quando a hospedagem so oferece cron por HTTP:
//   https://SEUDOMINIO/reenviar.php?chave=CHAVE
//
// A chave fica em config.reenvio_chave e precisa de pelo menos 16 caracteres.
// Enquanto estiver vazia ou curta, o caminho web fica fechado e so o cron por
// linha de comando roda. Gerar assim:
//   php -r "echo bin2hex(random_bytes(24)), PHP_EOL;"

foreach (['db.php'] as $arquivoLib) {
    $caminhoLib = __DIR__ . '/lib/' . $arquivoLib;
    if (is_file($caminhoLib)) {
        require_once $caminhoLib;
    }
}
require_once __DIR__ . '/lib/leads.php';
require_once __DIR__ . '/lib/crm.php';

if (!function_exists('db') || !function_exists('config_ler')) {
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'reenviar.php: lib/db.php nao encontrada' . PHP_EOL);
        exit(2);
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'erro' => 'servidor']);
    exit;
}

if (PHP_SAPI === 'cli') {
    $resumo = leads_reenviar();
    echo 'tentados=' . $resumo['tentados']
        . ' enviados=' . $resumo['enviados']
        . ' falhas=' . $resumo['falhas'] . PHP_EOL;
    exit($resumo['falhas'] > 0 ? 1 : 0);
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$chave = trim((string) config_ler('reenvio_chave', ''));
$enviada = isset($_GET['chave']) && is_string($_GET['chave']) ? $_GET['chave'] : '';

if (strlen($chave) < 16 || !hash_equals($chave, $enviada)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'erro' => 'chave']);
    exit;
}

$resumo = leads_reenviar();
echo json_encode(['ok' => true] + $resumo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
```

O cabeçalho usa comentário de linha (`//`) de propósito: a linha do cron contém `*/15`, e dentro de um bloco `/** ... */` esse `*/` fecharia o comentário e quebraria o arquivo. Não converter esse cabeçalho em docblock. O `php -l` do Passo 6 pega o erro caso alguém tente.

- [ ] **Passo 5: rodar e ver passar**

```bash
php testes/smoke-f3.php; echo "codigo de saida: $?"
```

Esperado: as seções `leads_pendentes e leads_reenviar` e `reenviar.php` com `ok` e `codigo de saida: 0`.

- [ ] **Passo 6: conferir o modo de linha de comando na mão**

```bash
php -l public_html/reenviar.php
php public_html/reenviar.php; echo "codigo de saida: $?"
```

Esperado do `php -l`: `No syntax errors detected`. O `php public_html/reenviar.php` sem a lib da frente 1 imprime `reenviar.php: lib/db.php nao encontrada` e sai com código 2, que é o comportamento correto até a frente 1 entregar `lib/db.php`.

- [ ] **Passo 7: commitar**

```bash
git add public_html/lib/leads.php public_html/reenviar.php testes/smoke-f3.php
git commit -F - <<'MSG'
feat(leads): fila de pendentes e rotina de reenvio

leads_pendentes junta pendente, erro e desativado, mais antigos primeiro, e
deixa de fora quem ja foi entregue e quem estourou cinco tentativas.
leads_reenviar sai na hora quando o conector esta desligado, para nao gastar
tentativa a toa. reenviar.php roda por cron na linha de comando ou por URL
protegida por config.reenvio_chave, comparada com hash_equals.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
MSG
```

---

### Tarefa 8: `js/formulario.js` — UTM, campos ocultos e envio

Move para um arquivo próprio a lógica que hoje vive no `js/main.js` e completa o que falta: UTM guardada em `sessionStorage`, campos ocultos `pagina`, `referrer`, `ts` e `csrf`, e envio de verdade para o `enviar.php`.

**`js/main.js` não pode ser editado.** O `formulario.js` desliga o envio antigo escutando `submit` no `document` em fase de captura e chamando `stopPropagation()`: o evento nunca chega ao `<form>`, então o `addEventListener('submit', ...)` do `main.js` não roda. O resto do `main.js` (abrir e fechar o modal, foco preso, seleção condicional de modelo) continua funcionando normalmente.

**Files:**
- Create: `public_html/js/formulario.js`
- Create: `testes/formulario.test.js`
- Modify: `testes/smoke-f3.php` (nova seção que chama o Node)

**Interfaces:**
- Consumes: o `enviar.php` da Tarefa 6 e a marcação do modal (`#quoteForm`, `#quoteSubmit`, `#quoteStatus`, `#quoteDone`, `#quoteDoneMsg`, `#quoteWppLink`, `#q-nome`, `#q-whatsapp`, `#q-busca`, `[data-quote-open]`), que já existe no `index.html`.
- Produces: `window.CastelloFormulario` com as funções puras
  - `utmDaUrl(busca: string): object`
  - `utmLer(store): object`
  - `utmGravar(obj, store): boolean`
  - `utmResolver(busca: string, store): object`
  - `mascaraWhatsapp(valor: string): string`
  - `validar({nome, whatsapp, busca}): string[]`
  - `mensagemDeErro(campos: string[]): string`
  - `resumoWhatsapp(dados: object): string`
  - `tokenDaResposta(dados: object): string`
  - `obterToken(buscar: function): Promise<string>`
  - `esquecerToken(): void`
  
  e, em Node, o mesmo objeto por `module.exports`.

**De onde vem o CSRF, conforme a seção 6.1 do contrato:** de `public_html/csrf.php`, um endereço próprio escrito pela **frente 1**, que responde `{"token":"..."}` com `Cache-Control: private, no-store`. Não existe metatag no `<head>`: token em HTML obrigaria `no-store` em toda página do site, e a Castello vai investir em tráfego pago, então página de destino incacheável é custo permanente.

Três consequências para este arquivo:

- A busca acontece **quando o visitante abre o modal**, nunca no carregamento da página. Quem só lê o site não faz requisição nenhuma e não recebe cookie de sessão.
- O `fetch` do token e o `fetch` do envio usam `credentials: 'same-origin'`. Sem isso o cookie de sessão não acompanha e o token não bate na hora do envio.
- Falha na busca mostra erro no formulário **antes** de deixar enviar, em vez de mandar e tomar 419. Modal aberto, fechado e reaberto não busca de novo: o token fica guardado em memória pela sessão de página.

Este é o acoplamento entre as frentes 1 e 3. Sem o `csrf.php`, todo envio volta 419.

- [ ] **Passo 1: escrever os testes que falham**

Criar `testes/formulario.test.js`:

```js
/* Testes das funcoes puras de public_html/js/formulario.js.
   Rodar: node testes/formulario.test.js
   Chamado tambem por testes/smoke-f3.php. */

'use strict';

var assert = require('node:assert/strict');
var caminho = require('node:path').join(__dirname, '..', 'public_html', 'js', 'formulario.js');
var api = require(caminho);

var total = 0;
var falhas = 0;

function teste(nome, corpo) {
  total++;
  try {
    corpo();
    console.log('  ok    ' + nome);
  } catch (erro) {
    falhas++;
    console.log('  FALHA ' + nome + '  ->  ' + erro.message);
  }
}

function guardaFalsa(inicial) {
  var dados = Object.assign({}, inicial || {});
  return {
    getItem: function (chave) { return Object.prototype.hasOwnProperty.call(dados, chave) ? dados[chave] : null; },
    setItem: function (chave, valor) { dados[chave] = String(valor); },
    bruto: function () { return dados; }
  };
}

console.log('== js/formulario.js ==');

teste('utmDaUrl le os cinco parametros', function () {
  var r = api.utmDaUrl('?utm_source=instagram&utm_medium=social&utm_campaign=flex&utm_term=casa&utm_content=reel3');
  assert.equal(r.utm_source, 'instagram');
  assert.equal(r.utm_medium, 'social');
  assert.equal(r.utm_campaign, 'flex');
  assert.equal(r.utm_term, 'casa');
  assert.equal(r.utm_content, 'reel3');
});

teste('utmDaUrl ignora o que nao e utm', function () {
  var r = api.utmDaUrl('?pagina=2&utm_source=google&gclid=xyz');
  assert.deepEqual(Object.keys(r), ['utm_source']);
});

teste('utmDaUrl aceita url sem interrogacao e decodifica', function () {
  var r = api.utmDaUrl('utm_campaign=casa%20de%20madeira&utm_source=meta+ads');
  assert.equal(r.utm_campaign, 'casa de madeira');
  assert.equal(r.utm_source, 'meta ads');
});

teste('utmDaUrl devolve objeto vazio sem parametro', function () {
  assert.deepEqual(api.utmDaUrl(''), {});
  assert.deepEqual(api.utmDaUrl(undefined), {});
});

teste('utmLer devolve o que estava guardado', function () {
  var store = guardaFalsa({ castello_utm: '{"utm_source":"instagram"}' });
  assert.equal(api.utmLer(store).utm_source, 'instagram');
});

teste('utmLer aguenta lixo no sessionStorage', function () {
  assert.deepEqual(api.utmLer(guardaFalsa({ castello_utm: 'nao e json' })), {});
  assert.deepEqual(api.utmLer(guardaFalsa({})), {});
  assert.deepEqual(api.utmLer(null), {});
});

teste('utmResolver guarda o que veio na URL', function () {
  var store = guardaFalsa({});
  var r = api.utmResolver('?utm_source=instagram&utm_campaign=flex', store);
  assert.equal(r.utm_source, 'instagram');
  assert.equal(JSON.parse(store.bruto().castello_utm).utm_campaign, 'flex');
});

teste('utmResolver mantem a utm guardada quando a URL nao traz nenhuma', function () {
  var store = guardaFalsa({ castello_utm: '{"utm_source":"instagram","utm_campaign":"flex"}' });
  var r = api.utmResolver('', store);
  assert.equal(r.utm_source, 'instagram');
  assert.equal(r.utm_campaign, 'flex');
});

teste('parametro na URL sobrescreve o guardado', function () {
  var store = guardaFalsa({ castello_utm: '{"utm_source":"instagram","utm_campaign":"antiga"}' });
  var r = api.utmResolver('?utm_source=google', store);
  assert.equal(r.utm_source, 'google');
  assert.equal(r.utm_campaign, 'antiga');
  assert.equal(JSON.parse(store.bruto().castello_utm).utm_source, 'google');
});

teste('utmGravar nao explode com sessionStorage bloqueado', function () {
  var travada = { getItem: function () { throw new Error('bloqueado'); }, setItem: function () { throw new Error('bloqueado'); } };
  assert.equal(api.utmGravar({ utm_source: 'x' }, travada), false);
  assert.deepEqual(api.utmLer(travada), {});
});

teste('mascaraWhatsapp formata celular de 11 digitos', function () {
  assert.equal(api.mascaraWhatsapp('48998244494'), '(48) 99824-4494');
});

teste('mascaraWhatsapp formata fixo de 10 digitos', function () {
  assert.equal(api.mascaraWhatsapp('4836328743'), '(48) 3632-8743');
});

teste('mascaraWhatsapp e idempotente', function () {
  assert.equal(api.mascaraWhatsapp('(48) 99824-4494'), '(48) 99824-4494');
});

teste('mascaraWhatsapp aguenta digitacao pela metade', function () {
  assert.equal(api.mascaraWhatsapp('4'), '4');
  assert.equal(api.mascaraWhatsapp('48'), '48');
  assert.equal(api.mascaraWhatsapp('489'), '(48) 9');
  assert.equal(api.mascaraWhatsapp('489982'), '(48) 9982');
});

teste('mascaraWhatsapp corta o que passa de 11 digitos', function () {
  assert.equal(api.mascaraWhatsapp('489982444949999'), '(48) 99824-4494');
});

teste('validar aceita lead completo', function () {
  assert.deepEqual(api.validar({ nome: 'Fabiano Hirtz', whatsapp: '(48) 99824-4494', busca: 'Modelo pronto do catalogo' }), []);
});

teste('validar acusa nome vazio', function () {
  assert.deepEqual(api.validar({ nome: '   ', whatsapp: '48998244494', busca: 'Ainda pesquisando' }), ['nome']);
});

teste('validar acusa whatsapp com menos de 10 digitos', function () {
  assert.deepEqual(api.validar({ nome: 'Fabiano', whatsapp: '(48) 9982', busca: 'Ainda pesquisando' }), ['whatsapp']);
});

teste('validar acusa busca vazia', function () {
  assert.deepEqual(api.validar({ nome: 'Fabiano', whatsapp: '48998244494', busca: '' }), ['busca']);
});

teste('validar acusa os tres de uma vez, na ordem do formulario', function () {
  assert.deepEqual(api.validar({ nome: '', whatsapp: '', busca: '' }), ['nome', 'whatsapp', 'busca']);
});

teste('mensagemDeErro fala do primeiro campo ruim', function () {
  assert.match(api.mensagemDeErro(['nome']), /nome/i);
  assert.match(api.mensagemDeErro(['whatsapp']), /WhatsApp/i);
  assert.match(api.mensagemDeErro(['busca']), /busca/i);
  assert.equal(typeof api.mensagemDeErro([]), 'string');
});

teste('resumoWhatsapp monta o texto so com o que foi preenchido', function () {
  var texto = api.resumoWhatsapp({ nome: 'Fabiano', whatsapp: '(48) 99824-4494', busca: 'Modelo pronto', modelo: '', cidade: 'Tubarao', mensagem: '' });
  assert.match(texto, /Nome: Fabiano/);
  assert.match(texto, /Cidade\/regiao: Tubarao/);
  assert.equal(/Modelo de interesse/.test(texto), false);
  assert.equal(/Mensagem/.test(texto), false);
});

teste('tokenDaResposta aceita o que o csrf.php devolve', function () {
  assert.equal(api.tokenDaResposta({ token: 'a1b2c3d4e5f6' }), 'a1b2c3d4e5f6');
});

teste('tokenDaResposta recusa resposta sem token util', function () {
  assert.equal(api.tokenDaResposta({}), '');
  assert.equal(api.tokenDaResposta({ token: 'curto' }), '');
  assert.equal(api.tokenDaResposta({ token: 123456789 }), '');
  assert.equal(api.tokenDaResposta(null), '');
  assert.equal(api.tokenDaResposta('a1b2c3d4e5f6'), '');
});

/* Os tres testes abaixo sao assincronos e mexem no mesmo token guardado em
   memoria, entao rodam EM FILA, um depois do outro, no fim do arquivo. Se
   forem disparados juntos, o token que um guarda vaza para o outro. */
var assincronos = [];

function testeAssincrono(nome, corpo) {
  total++;
  assincronos.push(function () {
    return Promise.resolve()
      .then(corpo)
      .then(function () { console.log('  ok    ' + nome); })
      .catch(function (erro) { falhas++; console.log('  FALHA ' + nome + '  ->  ' + erro.message); });
  });
}

testeAssincrono('obterToken busca uma vez e guarda em memoria', function () {
  api.esquecerToken();
  var chamadas = 0;
  var buscar = function () { chamadas++; return Promise.resolve({ token: 'a1b2c3d4e5f6' }); };
  return api.obterToken(buscar)
    .then(function (t) {
      assert.equal(t, 'a1b2c3d4e5f6');
      return api.obterToken(buscar);
    })
    .then(function (t) {
      assert.equal(t, 'a1b2c3d4e5f6');
      assert.equal(chamadas, 1, 'reabrir o modal nao pode buscar o token de novo');
    });
});

testeAssincrono('obterToken devolve vazio quando o csrf.php esta fora do ar', function () {
  api.esquecerToken();
  var buscar = function () { return Promise.reject(new Error('http 500')); };
  return api.obterToken(buscar).then(function (t) {
    assert.equal(t, '', 'falha no csrf.php nao pode virar token');
  });
});

testeAssincrono('obterToken nao guarda a falha e tenta de novo depois', function () {
  api.esquecerToken();
  var vezes = 0;
  var buscar = function () {
    vezes++;
    return vezes === 1 ? Promise.reject(new Error('caiu')) : Promise.resolve({ token: 'a1b2c3d4e5f6' });
  };
  return api.obterToken(buscar)
    .then(function (t) {
      assert.equal(t, '');
      return api.obterToken(buscar);
    })
    .then(function (t) {
      assert.equal(t, 'a1b2c3d4e5f6');
      assert.equal(vezes, 2);
    });
});

teste('sem emoji e sem travessao na copy do arquivo', function () {
  var fonte = require('node:fs').readFileSync(caminho, 'utf8');
  assert.equal(/[\u2014\u2013]/.test(fonte), false, 'achou travessao');
  assert.equal(/[\u{1F300}-\u{1FAFF}\u{2600}-\u{27BF}]/u.test(fonte), false, 'achou emoji');
});

teste('o token nunca e buscado no carregamento, so na abertura do modal', function () {
  var fonte = require('node:fs').readFileSync(caminho, 'utf8');
  assert.equal(/csrf-token/.test(fonte), false, 'nao pode ler metatag de csrf');
  assert.match(fonte, /credentials: 'same-origin'/);
});

assincronos.reduce(function (fila, passo) {
  return fila.then(passo);
}, Promise.resolve()).then(function () {
  console.log('');
  console.log((total - falhas) + '/' + total + ' passaram');
  process.exit(falhas > 0 ? 1 : 0);
});
```

- [ ] **Passo 2: rodar e ver falhar**

```bash
cd "E:/Clientes/Castello Madeiras/prototipo-site-castello"
node testes/formulario.test.js; echo "codigo de saida: $?"
```

Esperado: `Cannot find module '.../public_html/js/formulario.js'` e código de saída 1.

- [ ] **Passo 3: escrever `public_html/js/formulario.js`**

```js
/* Castello - formulario de orcamento (frente 3).
   Carregado depois de js/main.js, em arquivo proprio.

   Este arquivo NAO edita js/main.js. Ele desliga o envio antigo escutando
   submit no document em fase de captura e chamando stopPropagation: o evento
   nunca chega ao form, entao o listener do main.js nao roda. Abrir e fechar o
   modal, foco preso e selecao condicional de modelo continuam com o main.js.

   As funcoes puras ficam em window.CastelloFormulario (e em module.exports,
   quando rodando no Node) para poderem ser testadas sem navegador. */
(function () {
  'use strict';

  var ENDPOINT = 'enviar.php';
  var CSRF_ENDPOINT = 'csrf.php';
  var CHAVE_UTM = 'castello_utm';
  var CAMPOS_UTM = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
  var TEMPO_MINIMO_MS = 3000;
  var WA_PHONE = '5548998244494';
  var ROTULOS = [
    ['nome', 'Nome'],
    ['whatsapp', 'WhatsApp'],
    ['busca', 'O que busca'],
    ['modelo', 'Modelo de interesse'],
    ['cidade', 'Cidade/regiao'],
    ['mensagem', 'Mensagem']
  ];

  var api = {};

  /* ---------- funcoes puras (testadas por testes/formulario.test.js) ---------- */

  api.utmDaUrl = function (busca) {
    var achados = {};
    String(busca == null ? '' : busca).replace(/^\?/, '').split('&').forEach(function (par) {
      if (!par) return;
      var corte = par.indexOf('=');
      var nome = corte < 0 ? par : par.slice(0, corte);
      var valor = corte < 0 ? '' : par.slice(corte + 1);
      try {
        nome = decodeURIComponent(nome.replace(/\+/g, ' '));
        valor = decodeURIComponent(valor.replace(/\+/g, ' '));
      } catch (e) { return; }
      if (CAMPOS_UTM.indexOf(nome) >= 0 && valor) achados[nome] = valor.slice(0, 200);
    });
    return achados;
  };

  api.utmLer = function (store) {
    try {
      var cru = store && store.getItem(CHAVE_UTM);
      var obj = cru ? JSON.parse(cru) : null;
      if (!obj || typeof obj !== 'object') return {};
      var limpo = {};
      CAMPOS_UTM.forEach(function (c) {
        if (typeof obj[c] === 'string' && obj[c]) limpo[c] = obj[c];
      });
      return limpo;
    } catch (e) {
      return {};
    }
  };

  api.utmGravar = function (obj, store) {
    try {
      store.setItem(CHAVE_UTM, JSON.stringify(obj));
      return true;
    } catch (e) {
      return false;
    }
  };

  /* Parametro na URL sempre sobrescreve o que estava guardado. */
  api.utmResolver = function (busca, store) {
    var guardadas = api.utmLer(store);
    var daUrl = api.utmDaUrl(busca);
    var fim = {};
    CAMPOS_UTM.forEach(function (c) { if (guardadas[c]) fim[c] = guardadas[c]; });
    CAMPOS_UTM.forEach(function (c) { if (daUrl[c]) fim[c] = daUrl[c]; });
    if (Object.keys(daUrl).length) api.utmGravar(fim, store);
    return fim;
  };

  /* Mesma mascara que o js/main.js aplica hoje: (48) 99824-4494. */
  api.mascaraWhatsapp = function (valor) {
    var d = String(valor == null ? '' : valor).replace(/\D/g, '').slice(0, 11);
    if (d.length <= 2) return d;
    if (d.length <= 7) return '(' + d.slice(0, 2) + ') ' + d.slice(2);
    var corte = d.length > 10 ? 7 : 6;
    return '(' + d.slice(0, 2) + ') ' + d.slice(2, corte) + '-' + d.slice(corte);
  };

  /* Mesmas regras do enviar.php, para o erro aparecer antes da viagem. */
  api.validar = function (dados) {
    var ruins = [];
    var nome = String((dados && dados.nome) || '').trim();
    var digitos = String((dados && dados.whatsapp) || '').replace(/\D/g, '');
    var busca = String((dados && dados.busca) || '').trim();
    if (nome.length < 2) ruins.push('nome');
    if (digitos.length < 10 || digitos.length > 13) ruins.push('whatsapp');
    if (!busca) ruins.push('busca');
    return ruins;
  };

  api.mensagemDeErro = function (campos) {
    var lista = campos || [];
    if (lista.indexOf('nome') >= 0) return 'Preencha seu nome.';
    if (lista.indexOf('whatsapp') >= 0) return 'Informe um WhatsApp com DDD.';
    if (lista.indexOf('busca') >= 0) return 'Escolha o que voce busca.';
    return 'Confira os campos e tente de novo.';
  };

  api.resumoWhatsapp = function (dados) {
    return ROTULOS.map(function (par) {
      var valor = String((dados && dados[par[0]]) || '').trim();
      return valor ? par[1] + ': ' + valor : null;
    }).filter(Boolean).join('\n');
  };

  /* ---------- token de CSRF ---------- */

  var tokenGuardado = '';

  api.tokenDaResposta = function (dados) {
    if (!dados || typeof dados !== 'object') return '';
    var t = dados.token;
    return (typeof t === 'string' && t.length >= 8) ? t : '';
  };

  api.esquecerToken = function () {
    tokenGuardado = '';
  };

  /* buscar devolve uma promessa com o objeto que o csrf.php respondeu.
     O token vale pela sessao de pagina inteira: abrir, fechar e reabrir o
     modal nao busca de novo. Falha nao e guardada, para a proxima abertura
     tentar outra vez. */
  api.obterToken = function (buscar) {
    if (tokenGuardado) return Promise.resolve(tokenGuardado);
    return Promise.resolve()
      .then(function () { return buscar(); })
      .then(function (dados) {
        tokenGuardado = api.tokenDaResposta(dados);
        return tokenGuardado;
      })
      .catch(function () { return ''; });
  };

  if (typeof window !== 'undefined') window.CastelloFormulario = api;
  if (typeof module !== 'undefined' && module.exports) module.exports = api;

  /* ---------- ligacao com a pagina ---------- */

  if (typeof document === 'undefined') return;

  var form = document.getElementById('quoteForm');
  if (!form) return;

  var status = document.getElementById('quoteStatus');
  var botao = document.getElementById('quoteSubmit');
  var painelDone = document.getElementById('quoteDone');
  var doneMsg = document.getElementById('quoteDoneMsg');
  var doneTitulo = painelDone ? painelDone.querySelector('h3') : null;
  var wppLink = document.getElementById('quoteWppLink');
  var campoWpp = document.getElementById('q-whatsapp');
  var abertoEm = Date.now();

  /* Guarda a utm logo na chegada, mesmo que o visitante nunca abra o modal:
     ela precisa sobreviver a navegacao entre a home e a pagina Flex. */
  api.utmResolver(window.location.search, window.sessionStorage);

  function dizer(texto) {
    if (status) status.textContent = texto || '';
  }

  function campoOculto(nome, valor) {
    var el = form.querySelector('[name="' + nome + '"]');
    if (!el) {
      el = document.createElement('input');
      el.type = 'hidden';
      el.name = nome;
      form.appendChild(el);
    }
    el.value = valor == null ? '' : String(valor);
    return el;
  }

  function preencherOcultos() {
    var utm = api.utmResolver(window.location.search, window.sessionStorage);
    campoOculto('pagina', window.location.pathname || '/');
    campoOculto('referrer', document.referrer || '');
    CAMPOS_UTM.forEach(function (c) { campoOculto(c, utm[c] || ''); });
    campoOculto('ts', String(abertoEm));
  }

  /* credentials same-origin e obrigatorio: sem o cookie de sessao o token que
     volta nao bate com o que o enviar.php espera. */
  function buscarTokenNoServidor() {
    return fetch(CSRF_ENDPOINT, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
      cache: 'no-store'
    }).then(function (resposta) {
      if (!resposta.ok) throw new Error('http ' + resposta.status);
      return resposta.json();
    });
  }

  /* Chamada na abertura do modal, nunca no carregamento da pagina: quem so le
     o site nao faz requisicao nem recebe cookie de sessao. */
  function garantirToken() {
    return api.obterToken(buscarTokenNoServidor).then(function (token) {
      campoOculto('csrf', token);
      if (!token) {
        dizer('Nao deu pra preparar o envio agora. Tente de novo em alguns segundos ou chame no WhatsApp.');
      }
      return token;
    });
  }

  function limparErros() {
    ['q-nome', 'q-whatsapp', 'q-busca'].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.classList.remove('is-error');
    });
  }

  function marcarErros(campos) {
    var mapa = { nome: 'q-nome', whatsapp: 'q-whatsapp', busca: 'q-busca' };
    (campos || []).forEach(function (campo) {
      var el = document.getElementById(mapa[campo]);
      if (el) el.classList.add('is-error');
    });
    var primeiro = document.getElementById(mapa[(campos || [])[0]]);
    if (primeiro) primeiro.focus({ preventScroll: true });
  }

  function restaurar(rotulo) {
    if (botao) {
      botao.disabled = false;
      botao.textContent = rotulo;
    }
  }

  function mostrarDone(entregue, dados) {
    if (!painelDone) return;
    form.hidden = true;
    painelDone.hidden = false;
    if (doneTitulo) doneTitulo.textContent = entregue ? 'Pedido enviado' : 'Pedido pronto pra enviar';
    if (doneMsg) {
      doneMsg.textContent = entregue
        ? 'Recebemos seu pedido. A Castello responde em ate 1 dia util. Se preferir adiantar, chame no WhatsApp.'
        : 'Nao deu pra enviar pelo site agora. Toque no botao abaixo para mandar seu pedido no WhatsApp da Castello.';
    }
    if (wppLink) {
      wppLink.href = 'https://wa.me/' + WA_PHONE + '?text=' +
        encodeURIComponent('Ola! Quero um orcamento de casa de madeira.\n\n' + api.resumoWhatsapp(dados));
      wppLink.focus({ preventScroll: true });
    }
  }

  function dadosDoForm(fd) {
    var saida = {};
    ROTULOS.forEach(function (par) {
      saida[par[0]] = (fd.get(par[0]) || '').toString();
    });
    return saida;
  }

  function postar(fd, dados) {
    var rotulo = botao ? botao.textContent : '';
    if (botao) {
      botao.disabled = true;
      botao.textContent = 'Enviando...';
    }
    dizer('');

    fetch(ENDPOINT, {
      method: 'POST',
      body: fd,
      headers: { Accept: 'application/json' },
      credentials: 'same-origin'
    })
      .then(function (resposta) {
        return resposta.text().then(function (texto) {
          var json = null;
          try { json = JSON.parse(texto); } catch (e) { json = null; }
          return { http: resposta.status, dados: json };
        });
      })
      .then(function (r) {
        if (r.http === 200 && r.dados && r.dados.ok === true) {
          mostrarDone(true, dados);
          return;
        }
        if (r.http === 422 && r.dados && r.dados.erro === 'campos') {
          restaurar(rotulo);
          limparErros();
          marcarErros(r.dados.campos || []);
          dizer(api.mensagemDeErro(r.dados.campos || []));
          return;
        }
        if (r.http === 419) {
          /* A sessao virou. Joga fora o token guardado, busca outro e pede
             para o visitante mandar de novo. */
          restaurar(rotulo);
          api.esquecerToken();
          garantirToken();
          dizer('A pagina ficou aberta tempo demais. Toque em enviar de novo.');
          return;
        }
        restaurar(rotulo);
        mostrarDone(false, dados);
      })
      .catch(function () {
        restaurar(rotulo);
        mostrarDone(false, dados);
      });
  }

  function enviar() {
    preencherOcultos();

    var fd = new FormData(form);
    /* O contrato chama o honeypot de empresa; a marcacao antiga usa _gotcha.
       Manda os dois, com o mesmo valor, para o servidor barrar de qualquer jeito. */
    var isca = (fd.get('empresa') || fd.get('_gotcha') || '').toString();
    fd.set('empresa', isca);

    var dados = dadosDoForm(fd);
    limparErros();

    var ruins = api.validar(dados);
    if (ruins.length) {
      marcarErros(ruins);
      dizer(api.mensagemDeErro(ruins));
      return;
    }

    /* Sem token o envio voltaria 419. Mostra o erro aqui e tenta buscar de
       novo, para o visitante entender e poder repetir. */
    if (!String(fd.get('csrf') || '')) {
      dizer('Nao deu pra preparar o envio agora. Tente de novo em alguns segundos ou chame no WhatsApp.');
      garantirToken();
      return;
    }

    /* O servidor recusa envio abaixo de 3 segundos. Em vez de perder o lead de
       quem digitou rapido, espera o resto do tempo e manda. */
    var falta = TEMPO_MINIMO_MS - (Date.now() - abertoEm);
    if (falta > 0) {
      if (botao) {
        botao.disabled = true;
        botao.textContent = 'Enviando...';
      }
      setTimeout(function () {
        if (botao) botao.disabled = false;
        postar(fd, dados);
      }, falta + 150);
      return;
    }

    postar(fd, dados);
  }

  /* Captura no document: o evento e interrompido antes de chegar ao form,
     entao o listener de submit do js/main.js nao roda. */
  document.addEventListener('submit', function (ev) {
    if (ev.target !== form) return;
    ev.preventDefault();
    ev.stopPropagation();
    enviar();
  }, true);

  /* Marca a hora de abertura para o time-trap. Sem stopPropagation: o main.js
     continua abrindo o modal normalmente. */
  document.addEventListener('click', function (ev) {
    var abridor = ev.target && ev.target.closest ? ev.target.closest('[data-quote-open]') : null;
    if (!abridor) return;
    abertoEm = Date.now();
    setTimeout(function () {
      preencherOcultos();
      garantirToken();
    }, 0);
  }, true);

  /* Mascara do WhatsApp. O main.js tambem aplica a dele; a transformacao e
     idempotente, entao os dois juntos dao o mesmo resultado. */
  if (campoWpp && !campoWpp.dataset.mascaraF3) {
    campoWpp.dataset.mascaraF3 = '1';
    campoWpp.addEventListener('input', function () {
      campoWpp.value = api.mascaraWhatsapp(campoWpp.value);
      campoWpp.classList.remove('is-error');
    });
  }

  preencherOcultos();
})();
```

- [ ] **Passo 4: rodar os testes do JS e ver passar**

```bash
cd "E:/Clientes/Castello Madeiras/prototipo-site-castello"
node testes/formulario.test.js; echo "codigo de saida: $?"
```

Esperado: `29/29 passaram` e `codigo de saida: 0`.

- [ ] **Passo 5: pendurar o teste do JS no smoke**

Inserir em `testes/smoke-f3.php`, antes de `exit(t_resumo());`:

```php
t_secao('js/formulario.js pelo Node');
$ondeNode = trim((string) shell_exec(PHP_OS_FAMILY === 'Windows' ? 'where node 2>NUL' : 'command -v node 2>/dev/null'));
if ($ondeNode === '') {
    echo '  pulado: node nao encontrado no PATH', PHP_EOL;
} else {
    $saidaNode = [];
    $codigoNode = 0;
    exec('node ' . escapeshellarg(__DIR__ . '/formulario.test.js') . ' 2>&1', $saidaNode, $codigoNode);
    t_ok('node testes/formulario.test.js passa', $codigoNode === 0, implode(' | ', $saidaNode));
}
```

- [ ] **Passo 6: rodar o smoke inteiro**

```bash
php testes/smoke-f3.php; echo "codigo de saida: $?"
```

Esperado: todas as seções com `ok`, incluindo `node testes/formulario.test.js passa`, e `codigo de saida: 0`.

- [ ] **Passo 7: conferir no navegador que o envio antigo ficou desligado**

Como `index.php` ainda é da frente 1, a conferência é feita no `index.html` atual sem editá-lo, injetando o arquivo pela mão:

```bash
php -S localhost:8000 -t .
```

No navegador, abrir `http://localhost:8000/index.html`, abrir o console e rodar:

```js
var s = document.createElement('script');
s.src = '/public_html/js/formulario.js';
document.body.appendChild(s);
```

Depois abrir o modal por qualquer CTA e conferir, ainda no console:

```js
document.querySelector('#quoteForm [name=pagina]').value    // '/index.html'
document.querySelector('#quoteForm [name=ts]').value        // um numero grande
window.CastelloFormulario.mascaraWhatsapp('48998244494')    // '(48) 99824-4494'
```

Como o `csrf.php` é da frente 1 e ainda não existe, esta é a hora de conferir o **caminho de falha**: na aba Rede do navegador deve aparecer uma chamada a `csrf.php` disparada só na abertura do modal (nenhuma antes disso), voltando 404, e o formulário deve mostrar `Nao deu pra preparar o envio agora.` no lugar do status. Tentar enviar assim mesmo não pode disparar requisição para `enviar.php`: a mensagem repete e nada é postado. Conferir também que o valor de `document.querySelector('#quoteForm [name=csrf]').value` fica vazio.

Para ver o caminho feliz antes da frente 1 entregar, criar um `csrf.php` de mentira **fora do repositório** e servir a pasta por cima, ou simular no console:

```js
window.CastelloFormulario.esquecerToken();
window.CastelloFormulario.obterToken(function () { return Promise.resolve({ token: 'a1b2c3d4e5f6' }); })
  .then(function (t) { console.log('token:', t); });
```

Depois abrir `http://localhost:8000/index.html?utm_source=instagram&utm_campaign=flex`, repetir a injeção e conferir:

```js
sessionStorage.getItem('castello_utm')   // {"utm_source":"instagram","utm_campaign":"flex"}
```

Navegar para `http://localhost:8000/index.html` sem parâmetro, injetar de novo, abrir o modal e conferir que `document.querySelector('#quoteForm [name=utm_source]').value` continua `instagram`. O envio em si só fecha o ciclo quando a frente 1 entregar o `csrf.php`, e isso é conferido na Tarefa 9.

- [ ] **Passo 8: commitar**

```bash
git add public_html/js/formulario.js testes/formulario.test.js testes/smoke-f3.php
git commit -F - <<'MSG'
feat(form): js/formulario.js com captura de UTM e envio para o enviar.php

Le as utm da URL, guarda em sessionStorage sob castello_utm com o parametro da
URL sempre sobrescrevendo o guardado, preenche pagina, referrer, ts e csrf, e
posta no enviar.php tratando 200, 422 e 419. Desliga o envio antigo do
js/main.js escutando submit no document em fase de captura, sem editar aquele
arquivo. As funcoes puras ficam em window.CastelloFormulario e sao testadas por
node testes/formulario.test.js.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
MSG
```

---

### Tarefa 9: costura — virar caso do runner e remover o apoio

Só rodar depois que a frente 1 tiver entregue `public_html/lib/db.php`, `public_html/lib/auth.php`, `public_html/csrf.php` e `testes/smoke.php` com `testes/casos/`. Enquanto isso não acontecer, esta tarefa fica parada e as Tarefas 1 a 8 já entregam a frente 3 funcionando sozinha.

**O runner da frente 1 não aceita `require` no topo.** Ele varre `testes/casos/*.php`, ordena por nome e roda **cada caso num processo PHP separado**, com pasta de configuração e de uploads temporárias próprias, e não carrega `lib/` nenhuma: quem carrega é cada arquivo de caso. Um `require` no topo do `smoke.php` não teria efeito nenhum e daria a impressão de estar funcionando. A fusão certa é o `testes/smoke-f3.php` virar `testes/casos/85-crm.php`.

**Files:**
- Create: `testes/casos/85-crm.php` (a partir de `testes/smoke-f3.php`)
- Delete: `testes/apoio-f1.php`, `testes/smoke-f3.php`
- Modify: `public_html/enviar.php`, `public_html/reenviar.php` (requires diretos)
- Não modificar: `testes/smoke.php`, que é da frente 1 e não precisa de mudança nenhuma

**Interfaces:**
- Consumes: `db()`, `e()`, `agora()`, `config_ler()`, `config_gravar()`, `csrf_token()`, `csrf_validar()` reais da frente 1; os helpers do runner: `site()`, `teste(string, callable)`, `igual($esperado, $obtido)`, `verdade(bool, string)`, `contem(string, string)`, `nao_contem(string, string)`, `pular(string)`.
- Produces: `php testes/smoke.php` verde com o caso `85-crm.php` dentro, e nenhum arquivo de apoio sobrando.

- [ ] **Passo 1: confirmar que a frente 1 entregou**

```bash
cd "E:/Clientes/Castello Madeiras/prototipo-site-castello"
ls -1 public_html/lib/db.php public_html/lib/auth.php public_html/csrf.php testes/smoke.php testes/executar-caso.php
ls -1 testes/casos/
php testes/smoke.php; echo "saida f1: $?"
php testes/smoke-f3.php; echo "saida f3: $?"
```

As duas suítes precisam passar antes de fundir. Se alguma falhar, corrigir na frente de origem primeiro. Conferir também qual é o nome exato dos helpers de asserção lendo `testes/casos/00-runner.php`: o Passo 2 depende deles.

- [ ] **Passo 2: converter o smoke da frente 3 em caso do runner**

```bash
git mv testes/smoke-f3.php testes/casos/85-crm.php
```

No `testes/casos/85-crm.php`:

1. Apagar o `require_once __DIR__ . '/apoio-f1.php';` e o bloco inteiro de helpers guardado por `if (!function_exists('t_ok'))`, junto com `teste_banco_apagar()` e a chamada a ele. O runner já traz os helpers e já dá banco limpo por processo.
2. Trocar os `require_once __DIR__ . '/../public_html/...'` pelos caminhos do runner:

```php
<?php
declare(strict_types=1);

require_once site() . '/lib/db.php';
require_once site() . '/lib/auth.php';
require_once site() . '/lib/leads.php';
require_once site() . '/lib/crm.php';
require_once site() . '/lib/email.php';
require_once site() . '/enviar.php';
```

3. Envolver cada seção num `teste(...)`, trocando os helpers da frente 3 pelos do runner: `t_secao('X')` vira o nome do `teste`, `t_igual($a, $b)` vira `igual($a, $b)` e `t_ok($nome, $cond, $detalhe)` vira `verdade($cond, $detalhe !== '' ? $detalhe : $nome)`. Exemplo do que sai e do que entra:

```php
// antes
t_secao('lib/leads.php: gravacao e marcacao');
teste_banco_limpar();
$id = lead_gravar([...]);
t_ok('lead_gravar devolve um id positivo', $id > 0, 'id = ' . var_export($id, true));

// depois
teste('lead_gravar grava o lead com status pendente', function (): void {
    limpar_leads();
    $id = lead_gravar([...]);
    verdade($id > 0, 'lead_gravar devolveu id = ' . var_export($id, true));
});
```

4. Trocar `teste_banco_limpar()` por uma função local no próprio caso, já que o apoio some:

```php
function limpar_leads(): void
{
    db()->exec('DELETE FROM leads');
    db()->exec("DELETE FROM config WHERE chave LIKE 'crm_%'
                   OR chave IN ('email_aviso','email_dominio','reenvio_chave')");
}
```

5. Trocar `csrf_token()` de mentira pelo real: o `post_valido()` já chama `csrf_token()`, e agora ele vem do `lib/auth.php`. Como o caso roda em CLI e `csrf_validar()` compara com o token da sessão, chamar `auth_iniciar()` uma vez no topo do caso, antes do primeiro `post_valido()`.
6. Manter intactos os helpers do CRM falso (`crm_falso_porta`, `crm_falso_url`, `crm_falso_arquivo`, `crm_falso_ultima`, `crm_falso_subir`, `crm_falso_derrubar`, `crm_falso_pedir`), a constante `CRM_FALSO_ULTIMA`, o global `crm_falso_porta` e as funções `post_valido()`, `contar_leads()` e `semear_lead()`. Trocar `__DIR__ . '/crm-falso.php'` por `__DIR__ . '/../crm-falso.php'`, porque o caso desceu um nível.
7. Apagar a linha `exit(t_resumo());` do fim: quem soma e sai é o runner.

- [ ] **Passo 3: apagar o apoio**

```bash
git rm testes/apoio-f1.php
```

- [ ] **Passo 4: trocar os requires guardados por requires diretos**

Em `public_html/enviar.php`, trocar o bloco:

```php
foreach (['db.php', 'auth.php'] as $arquivoLib) {
    $caminhoLib = __DIR__ . '/lib/' . $arquivoLib;
    if (is_file($caminhoLib)) {
        require_once $caminhoLib;
    }
}
```

por:

```php
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/auth.php';
```

E apagar o `foreach (['db', 'agora', 'config_ler', 'csrf_validar'] as $obrigatoria) { ... }` inteiro: com o require direto, a falta de qualquer uma delas já vira erro fatal registrado no log, e a checagem passa a ser ruído.

Em `public_html/reenviar.php`, trocar:

```php
foreach (['db.php'] as $arquivoLib) {
    $caminhoLib = __DIR__ . '/lib/' . $arquivoLib;
    if (is_file($caminhoLib)) {
        require_once $caminhoLib;
    }
}
```

por:

```php
require_once __DIR__ . '/lib/db.php';
```

e apagar o bloco `if (!function_exists('db') || !function_exists('config_ler')) { ... }` inteiro.

- [ ] **Passo 5: rodar a suíte fundida e ver passar**

```bash
php testes/smoke.php; echo "codigo de saida: $?"
php testes/smoke.php 85; echo "so o caso da frente 3: $?"
php -l public_html/enviar.php
php -l public_html/reenviar.php
node testes/formulario.test.js
```

Esperado: `85-crm.php` na listagem, `todos os casos passaram`, `codigo de saida: 0`, `No syntax errors detected` nos dois arquivos e `29/29 passaram` no Node.

- [ ] **Passo 6: fechar o ciclo no navegador, ponta a ponta**

```bash
php -S localhost:8000 -t public_html
```

Com o site rodando, no navegador:

1. Abrir `http://localhost:8000/?utm_source=instagram&utm_campaign=flex-setembro`.
2. Na aba Rede, conferir que **não houve** chamada a `csrf.php` só por carregar a página, e que nenhum cookie de sessão foi entregue.
3. No console, conferir `sessionStorage.getItem('castello_utm')`.
4. Navegar para `http://localhost:8000/flex.php` sem parâmetro nenhum.
5. Abrir o formulário. Agora sim deve aparecer uma chamada a `csrf.php` respondendo 200 com `{"token":"..."}` e `Cache-Control: private, no-store`. Conferir que `document.querySelector('#quoteForm [name=csrf]').value` está preenchido.
6. Fechar e reabrir o modal: **nenhuma** chamada nova a `csrf.php`.
7. Esperar mais de 3 segundos, preencher nome, WhatsApp e o que busca, e enviar. A tela de sucesso deve dizer `Pedido enviado`.
8. No terminal, conferir o lead e a utm que sobreviveram à navegação:

```bash
php -r "define('CASTELLO_CONFIG', __DIR__ . '/config'); require 'public_html/lib/db.php'; var_dump(db()->query('SELECT id, nome, pagina, utm_source, utm_campaign, crm_status FROM leads ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC));"
```

Esperado: `pagina` igual a `/flex.php`, `utm_source` igual a `instagram`, `utm_campaign` igual a `flex-setembro`, `crm_status` igual a `desativado` (porque `crm_ativo` ainda é `0`).

9. Enviar de novo, agora clicando em menos de 3 segundos. A tela de sucesso deve aparecer do mesmo jeito, porque o JS espera o tempo faltante antes de postar, e um lead novo deve ter sido gravado.
10. Renomear `public_html/csrf.php` por um minuto, recarregar, abrir o modal e conferir o caminho de falha: mensagem de erro no formulário, `enviar.php` não chamado. Devolver o nome depois.

- [ ] **Passo 7: conferir as três configurações do conector**

O contrato já traz `crm_timeout`, `reenvio_chave` e `email_dominio`. Confirmar que o `migrar.php` da frente 1 semeou as três:

```bash
php -r "define('CASTELLO_CONFIG', __DIR__ . '/config'); require 'public_html/lib/db.php'; foreach (['crm_timeout','reenvio_chave','email_dominio'] as \$c) { echo \$c, ' = ', var_export(config_ler(\$c), true), PHP_EOL; }"
```

Esperado: `crm_timeout = '10'`, `reenvio_chave = ''` (caminho web fechado, que é o padrão seguro) e `email_dominio = 'castellomadeiras.com.br'`. Nenhuma delas bloqueia: o código funciona com o padrão quando a chave não existe.

- [ ] **Passo 8: validar o e-mail de verdade no servidor**

Na EreHost, com o site publicado, enviar um lead pelo formulário e confirmar que o aviso chegou em `config.email_aviso`. Se não chegar, conferir o log de erro do PHP e trocar o `From` por uma caixa real do domínio, que é o motivo mais comum de recusa em hospedagem compartilhada. Esta é a única verificação da frente 3 que não pode ser feita na máquina local.

- [ ] **Passo 9: commitar**

```bash
git add -A testes public_html/enviar.php public_html/reenviar.php
git commit -F - <<'MSG'
chore(f3): vira caso do runner e remove o apoio temporario

O smoke da frente 3 passa a ser testes/casos/85-crm.php, no formato do runner
da frente 1, que roda cada caso em processo separado com banco proprio. Nada
e acrescentado ao topo de testes/smoke.php, que nao carrega lib nenhuma.
testes/apoio-f1.php sai do repositorio e os requires guardados de enviar.php e
reenviar.php viram requires diretos.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
MSG
```

---

## Como rodar

```bash
cd "E:/Clientes/Castello Madeiras/prototipo-site-castello"

# ciclo de teste da frente 3 (antes da costura)
php testes/smoke-f3.php

# ciclo de teste depois da costura
php testes/smoke.php
php testes/smoke.php 85   # so o caso da frente 3

# so os testes do JS
node testes/formulario.test.js

# subir o CRM de mentira na mao, para experimentar
php -S 127.0.0.1:8765 testes/crm-falso.php
curl -s -X POST "http://127.0.0.1:8765/?modo=ok" -d '{}'

# o site
php -S localhost:8000 -t public_html
```

---

## Cobertura dos casos de teste pedidos

| Caso | Onde |
|---|---|
| CRM responde 200 | Tarefa 4 (`CRM 200: ok true`) e Tarefa 6 (`CRM 200: status enviado`) |
| CRM responde 500 | Tarefa 4 (`CRM 500: erro crm_http`) e Tarefa 6 (`CRM 500: visitante ainda ve ok true`) |
| CRM estoura o tempo | Tarefa 4 (`CRM lento: erro crm_tempo`) e Tarefa 6 (`CRM lento: status erro`) |
| CRM desligado | Tarefa 4 (`CRM desligado: erro crm_desativado`) e Tarefa 6 (`CRM desligado deixa o status desativado`) |
| CSRF inválido | Tarefa 6 (`csrf invalido: HTTP 419`, `csrf ausente: HTTP 419`) |
| Honeypot preenchido | Tarefa 6 (`honeypot: responde sucesso falso`, `honeypot _gotcha`) |
| Envio em menos de 3 segundos | Tarefa 6 (`rapido demais: nao gravou lead`) e Tarefa 8 (espera o tempo faltante) |
| WhatsApp com menos de 10 dígitos | Tarefa 6 (`whatsapp curto: lista whatsapp`) e Tarefa 8 (`validar acusa whatsapp com menos de 10 digitos`) |
| Campos obrigatórios vazios | Tarefa 6 (`campos vazios: lista os tres`, `nome vazio: lista so nome`) |
| Reenvio de pendentes | Tarefa 7 (seção inteira) |
| Extras | resposta do CRM que não é JSON, CRM fora do ar, mapa e cabeçalhos quebrados, relógio adiantado, UTM sobrevivendo à navegação, e-mail em modo de arquivo |

---

## Pontos em que o contrato ou a spec ficaram curtos

Os nove pontos levantados na primeira versão deste plano foram todos respondidos. Oito viraram texto no contrato; um foi resolvido de outro jeito, melhor que o proposto; dois seguem em aberto por escolha.

**Resolvidos no contrato, seção 6.1 e seção 7**

1. **De onde vem o `csrf`.** A proposta original era uma metatag no `<head>`. A frente 1 apontou o problema: token no HTML obriga `Cache-Control: private, no-store` em toda página do site, senão um cache compartilhado (LiteSpeed ou CDN) entrega o token de um visitante para outro. Com tráfego pago, página de destino incacheável é custo permanente. A solução que ficou é um endereço próprio, `public_html/csrf.php`, da frente 1, buscado pelo JS **só na abertura do modal**. As páginas seguem cacheáveis, ninguém recebe cookie de sessão só por ler o site, e o custo é uma requisição pequena para quem realmente abre o formulário. Segue sendo o acoplamento entre as frentes 1 e 3: sem o `csrf.php`, todo envio volta 419.
2. **Honeypot com dois nomes.** O contrato aceita `empresa` e `_gotcha` até a costura unificar. Depois dela, só `empresa`.
3. **Três chaves novas em `config`:** `crm_timeout`, `reenvio_chave` e `email_dominio`, todas com padrão no código.
4. **`reenviar.php`** entrou na divisão de arquivos da frente 3.
5. **Recusa silenciosa** responde `{"ok":true,"id":0}`.
6. **Time-trap com relógio adiantado** passa, e o contrato diz por quê.
7. **O runner de teste é da frente 1** e roda cada caso em processo separado. A fusão é o `smoke-f3.php` virar `testes/casos/85-crm.php`, não um `require` no topo do `smoke.php`. A Tarefa 9 está escrita assim.

**Segue em aberto, por escolha**

8. **O contrato não define o que é resposta inválida do CRM.** O plano trata como inválido todo corpo que não seja JSON válido em resposta 2xx, devolvendo `crm_resposta_invalida` com `ok = false`. Se o CRM escolhido responder texto puro no sucesso, isso vira falso negativo e a regra precisa virar configuração. Só dá para fechar quando a Castello disser qual é o CRM.
9. **Não há limite de envios por IP.** O honeypot com o time-trap dá conta do robô comum, mas envio repetido de propósito enche a tabela `leads` e a caixa de e-mail da Castello. Fica como candidato para depois do lançamento, fora do escopo desta frente.
10. **A spec manda o e-mail sair em linha com o envio.** Com `mail()` lento, o visitante espera. Na EreHost costuma ser instantâneo. Se no servidor passar de um segundo, a saída é passar o e-mail para a rotina do cron, o que muda o passo 5 da seção 6 da spec.
