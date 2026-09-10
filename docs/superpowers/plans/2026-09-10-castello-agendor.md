# Integração com o CRM Agendor — Plano de Implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fazer o formulário do site criar, no Agendor, uma pessoa e um negócio no funil de vendas da Castello, no mesmo formato dos leads que já chegam do Meta.

**Architecture:** O `lib/crm.php` genérico é substituído por um conector do Agendor que encadeia três chamadas: busca de duplicata por telefone, criação da pessoa e criação do negócio ligado a ela. A rede de segurança existente não muda: o lead continua sendo gravado no SQLite antes de qualquer chamada externa, o e-mail sai sempre, e a fila de reenvio cobre falha e tempo esgotado. O token mora em `config/segredos.php`, fora do banco e fora do backup; o resto da configuração fica na tabela `config`, editável no painel.

**Tech Stack:** PHP 8.5 sem framework, SQLite via PDO, cURL, suíte própria em `testes/smoke.php` (um processo por caso, banco temporário), Node para o teste do JS.

**Spec:** `docs/superpowers/specs/2026-09-10-castello-agendor-design.md`

## Global Constraints

- **Idioma do código:** identificadores, comentários e mensagens em português, **sem acentos em comentários e nomes de função**, seguindo `lib/crm.php` e `lib/leads.php`. Texto visível ao usuário (painel, e-mail, formulário) usa acento normal.
- **O lead nunca se perde.** Nenhuma falha de CRM pode impedir a gravação no banco nem o envio do e-mail, nem chegar ao visitante como erro.
- **Teto de tempo do CRM: 10 segundos no total**, para o conjunto das três chamadas. `max_execution_time` do servidor é 60.
- **Base da API:** `https://api.agendor.com.br/v3`. Autenticação: cabeçalho `Authorization: Token <uuid>`. Limite de 4 requisições por segundo.
- **Ids reais da conta da Castello:** funil `904296`, etapa "Contato" sequência `1`, origem "Site" `2656389`, categoria "Cliente em potencial" `4187395`.
- **Identificadores dos campos customizados de pessoa:** `cidade_da_obra`, `anuncio_de_origem`, `pretende_iniciar_a_obra_em`. Existem na conta e são usados pela equipe.
- **Convenção do título do negócio:** `[SITE] - <prazo> - <Nome>`, espelhando o `[META] - <prazo> - <Nome>` que já existe no funil.
- **Os quatro valores de prazo, literais:** `Imediato`, `Até 3 meses`, `Até 6 meses`, `Só pesquisando`. Qualquer variação quebra a comparação entre os canais no funil.
- **Telefone em dois formatos:** `contact.whatsapp` com DDI (`5548998244494`), `contact.mobile` sem DDI, como digitado. A busca de duplicata usa os dígitos **sem** DDI. Medido: com `55` a busca volta vazia.
- **Rodar os testes:** `php testes/smoke.php` para tudo, `php testes/smoke.php 85` para um caso.

---

### Task 1: Colunas `prazo` e `crm_pessoa_id` na tabela `leads`

O `schema.sql` roda a cada conexão, mas `CREATE TABLE IF NOT EXISTS` não acrescenta coluna a tabela existente, e o banco do servidor já tem leads. A migração precisa morar na `db()`, e **não** no `migrar.php`, que é apagado do servidor depois da instalação.

**Files:**
- Modify: `public_html/lib/schema.sql` (bloco `CREATE TABLE ... leads`)
- Modify: `public_html/lib/db.php` (função `db()`, mais a função nova)
- Test: `testes/casos/10-db.php`

**Interfaces:**
- Consumes: nada.
- Produces: `db_garantir_colunas(PDO $pdo): array` — devolve a lista de nomes de coluna acrescentados nesta chamada, vazia quando não havia o que fazer. Colunas novas em `leads`: `prazo TEXT`, `crm_pessoa_id INTEGER`.

- [ ] **Step 1: Write the failing test**

Acrescente ao fim de `testes/casos/10-db.php`:

```php
teste('db_garantir_colunas acrescenta o que falta e e idempotente', function (): void {
    /* Banco em memoria com o leads ANTIGO, sem prazo nem crm_pessoa_id. */
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('CREATE TABLE leads (id INTEGER PRIMARY KEY, nome TEXT, crm_status TEXT)');

    $acrescentadas = db_garantir_colunas($pdo);
    sort($acrescentadas);
    igual(['crm_pessoa_id', 'prazo'], $acrescentadas, 'acrescenta as duas colunas que faltavam');

    $colunas = array_column($pdo->query('PRAGMA table_info(leads)')->fetchAll(PDO::FETCH_ASSOC), 'name');
    verdade(in_array('prazo', $colunas, true), 'prazo existe agora');
    verdade(in_array('crm_pessoa_id', $colunas, true), 'crm_pessoa_id existe agora');

    /* Segunda passada nao pode falhar nem repetir: roda em toda requisicao. */
    igual([], db_garantir_colunas($pdo), 'segunda chamada nao acrescenta nada');

    /* O dado que ja estava la sobrevive. */
    $pdo->exec("INSERT INTO leads (nome, crm_status) VALUES ('Antigo', 'pendente')");
    $linha = $pdo->query('SELECT * FROM leads')->fetch(PDO::FETCH_ASSOC);
    igual('Antigo', $linha['nome']);
    verdade($linha['prazo'] === null, 'coluna nova nasce nula no registro antigo');
});

teste('o banco real do runner ja tem as colunas novas', function (): void {
    $colunas = array_column(db()->query('PRAGMA table_info(leads)')->fetchAll(PDO::FETCH_ASSOC), 'name');
    verdade(in_array('prazo', $colunas, true), 'prazo no banco do runner');
    verdade(in_array('crm_pessoa_id', $colunas, true), 'crm_pessoa_id no banco do runner');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php testes/smoke.php 10-db`
Expected: FAIL com `Call to undefined function db_garantir_colunas()`.

- [ ] **Step 3: Acrescentar as colunas ao schema**

Em `public_html/lib/schema.sql`, dentro do `CREATE TABLE IF NOT EXISTS leads`, logo depois da linha `mensagem TEXT,`:

```sql
  prazo                TEXT,
```

E logo depois de `crm_resposta TEXT`, trocando por:

```sql
  crm_resposta         TEXT,
  crm_pessoa_id        INTEGER
```

- [ ] **Step 4: Escrever `db_garantir_colunas` em `lib/db.php`**

Acrescente antes da função `db()`:

```php
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
            $pdo->exec('ALTER TABLE ' . $tabela . ' ADD COLUMN ' . $coluna . ' ' . $tipo);
            $acrescentadas[] = $coluna;
        }
    }

    return $acrescentadas;
}
```

- [ ] **Step 5: Chamar a migração dentro de `db()`**

Em `lib/db.php`, na função `db()`, logo depois de `$pdo->exec($schema);`:

```php
    db_garantir_colunas($pdo);
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php testes/smoke.php 10-db`
Expected: PASS.

- [ ] **Step 7: Rodar a suíte inteira**

Run: `php testes/smoke.php`
Expected: todos os casos passam. Nada mais depende dessas colunas ainda.

- [ ] **Step 8: Commit**

```bash
git add public_html/lib/schema.sql public_html/lib/db.php testes/casos/10-db.php
git commit -m "feat(leads): colunas prazo e crm_pessoa_id, com migracao na db()

O migrar.php e apagado do servidor depois da instalacao, entao uma migracao
ali nunca rodaria em producao. db_garantir_colunas roda a cada conexao,
le PRAGMA table_info e acrescenta so o que falta."
```

---

### Task 2: Configuração do Agendor no lugar da configuração genérica

Quatro chaves saem (`crm_endpoint`, `crm_metodo`, `crm_cabecalhos`, `crm_mapa_campos`) e seis entram, com os ids reais da conta como padrão, para a integração funcionar sem ninguém configurar nada. Entra também `crm_base`, que **não aparece no painel**: existe para os testes apontarem o conector ao servidor falso, e como válvula caso o Agendor mude o endereço da API.

**Files:**
- Modify: `public_html/lib/db.php` (`CASTELLO_CONFIG_PADRAO`)
- Modify: `public_html/painel/tabelas.php` (`painel_config_campos()` e `painel_config_validar()`)
- Modify: `config/segredos.php.exemplo`
- Test: `testes/casos/10-db.php`, `testes/casos/80-painel.php`

**Interfaces:**
- Consumes: nada.
- Produces: chaves de config `crm_ativo`, `crm_base`, `crm_funil`, `crm_etapa`, `crm_origem`, `crm_categoria`, `crm_marcador`, `crm_responsavel`, `crm_timeout`. A constante `CASTELLO_AGENDOR_TOKEN` passa a ser documentada em `config/segredos.php.exemplo`.

- [ ] **Step 1: Write the failing test**

Acrescente a `testes/casos/10-db.php`:

```php
teste('a config nasce com os valores reais da conta do Agendor', function (): void {
    igual('0', config_ler('crm_ativo'), 'CRM nasce desligado');
    igual('https://api.agendor.com.br/v3', config_ler('crm_base'));
    igual('904296', config_ler('crm_funil'), 'Funil de Vendas');
    igual('1', config_ler('crm_etapa'), 'sequencia da etapa Contato, nao o id 3845540');
    igual('2656389', config_ler('crm_origem'), 'origem Site');
    igual('4187395', config_ler('crm_categoria'), 'Cliente em potencial');
    igual('[SITE]', config_ler('crm_marcador'), 'marcador com colchetes no proprio valor');
    igual('', config_ler('crm_responsavel'), 'vazio: cai no dono do token');
    igual('10', config_ler('crm_timeout'));

    foreach (['crm_endpoint', 'crm_metodo', 'crm_cabecalhos', 'crm_mapa_campos'] as $morta) {
        verdade(config_ler($morta) === null, 'chave generica ' . $morta . ' nao existe mais');
    }
});
```

Acrescente a `testes/casos/80-painel.php`:

```php
teste('a tela de config oferece os campos do Agendor e nao o token', function (): void {
    $campos = painel_config_campos();
    foreach (['crm_ativo', 'crm_funil', 'crm_etapa', 'crm_origem', 'crm_categoria', 'crm_marcador', 'crm_responsavel'] as $chave) {
        verdade(isset($campos[$chave]), 'painel oferece ' . $chave);
    }
    foreach (['crm_endpoint', 'crm_metodo', 'crm_cabecalhos', 'crm_mapa_campos'] as $morta) {
        falso(isset($campos[$morta]), 'painel nao oferece mais ' . $morta);
    }
    falso(isset($campos['crm_token']), 'o token nunca entra no banco, logo nunca no painel');
    falso(isset($campos['crm_base']), 'crm_base e valvula tecnica, nao vai para o cliente');
});

teste('a validacao da config aceita id numerico e recusa lixo', function (): void {
    $r = painel_config_validar([
        'videos_na_home' => '8',
        'email_aviso'    => 'contato@castellomadeiras.com.br',
        'email_dominio'  => 'castellomadeiras.com.br',
        'crm_ativo'      => '1',
        'crm_funil'      => '904296',
        'crm_etapa'      => '1',
        'crm_origem'     => '2656389',
        'crm_categoria'  => '4187395',
        'crm_marcador'   => '[SITE]',
        'crm_responsavel' => '',
        'crm_timeout'    => '10',
        'reenvio_chave'  => str_repeat('a', 32),
    ]);
    igual([], $r['erros'], 'config boa passa: ' . json_encode($r['erros']));
    igual('904296', $r['valores']['crm_funil']);
    igual('', $r['valores']['crm_responsavel'], 'responsavel vazio e valido');

    $ruim = painel_config_validar([
        'videos_na_home' => '8',
        'email_aviso'    => 'contato@castellomadeiras.com.br',
        'email_dominio'  => 'castellomadeiras.com.br',
        'crm_ativo'      => '1',
        'crm_funil'      => 'abc',
        'crm_etapa'      => '0',
        'crm_origem'     => '2656389',
        'crm_categoria'  => '4187395',
        'crm_marcador'   => '',
        'crm_responsavel' => '',
        'crm_timeout'    => '10',
        'reenvio_chave'  => str_repeat('a', 32),
    ]);
    verdade(isset($ruim['erros']['crm_funil']), 'funil nao numerico e erro');
    verdade(isset($ruim['erros']['crm_etapa']), 'etapa zero e erro: sequencia comeca em 1');
    verdade(isset($ruim['erros']['crm_marcador']), 'marcador vazio e erro');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php testes/smoke.php 10-db` e `php testes/smoke.php 80-painel`
Expected: FAIL, `config_ler('crm_base')` devolve null e `painel_config_campos()` ainda traz `crm_endpoint`.

- [ ] **Step 3: Trocar os padrões em `lib/db.php`**

Substitua o bloco das chaves `crm_*` em `CASTELLO_CONFIG_PADRAO` por:

```php
    'crm_ativo'       => '0',
    'crm_base'        => 'https://api.agendor.com.br/v3',
    'crm_funil'       => '904296',
    'crm_etapa'       => '1',
    'crm_origem'      => '2656389',
    'crm_categoria'   => '4187395',
    'crm_marcador'    => '[SITE]',
    'crm_responsavel' => '',
    'crm_timeout'     => '10',
```

Comentário acima do bloco, para o próximo leitor:

```php
    /* Os ids sao os da conta real da Castello, lidos em 2026-09-10. crm_etapa
       e a SEQUENCIA da etapa Contato, nao o id 3845540: a API pede sequencia.
       crm_base nao aparece no painel; existe para o teste apontar o conector
       ao servidor falso. */
```

- [ ] **Step 4: Trocar os campos do painel em `painel/tabelas.php`**

Em `painel_config_campos()`, remova as entradas `crm_endpoint`, `crm_metodo`, `crm_cabecalhos` e `crm_mapa_campos`, e ponha no lugar:

```php
        'crm_funil'      => ['rotulo' => 'Funil do Agendor', 'tipo' => 'numero',
                             'min' => 1, 'max' => 99999999,
                             'ajuda' => 'O número do funil que recebe os pedidos. O funil de vendas da Castello é o 904296'],
        'crm_etapa'      => ['rotulo' => 'Etapa onde o pedido entra', 'tipo' => 'numero',
                             'min' => 1, 'max' => 99,
                             'ajuda' => 'A posição da etapa no funil, contando da esquerda. Contato é a 1'],
        'crm_origem'     => ['rotulo' => 'Origem do contato', 'tipo' => 'numero',
                             'min' => 1, 'max' => 99999999,
                             'ajuda' => 'O número da origem no Agendor. Site é 2656389'],
        'crm_categoria'  => ['rotulo' => 'Categoria do contato', 'tipo' => 'numero',
                             'min' => 1, 'max' => 99999999,
                             'ajuda' => 'O número da categoria. Cliente em potencial é 4187395'],
        'crm_marcador'   => ['rotulo' => 'Marcador no título do negócio', 'tipo' => 'texto',
                             'ajuda' => 'Aparece na frente do título, para separar o que veio do site do que veio das redes. Escreva com os colchetes, como [SITE]'],
        'crm_responsavel' => ['rotulo' => 'Responsável pelos pedidos do site', 'tipo' => 'texto',
                              'ajuda' => 'O número ou o e-mail do vendedor no Agendor. Deixe vazio para cair na conta principal'],
```

- [ ] **Step 5: Validar as chaves novas em `painel_config_validar()`**

Antes do bloco do `reenvio_chave`, acrescente:

```php
        if (in_array($chave, ['crm_funil', 'crm_etapa', 'crm_origem', 'crm_categoria'], true)) {
            if ($bruto === '' || !ctype_digit($bruto) || (int) $bruto < 1) {
                $erros[$chave] = 'Escreva só o número, maior que zero. Ele vem do Agendor.';
                continue;
            }
            $valores[$chave] = (string) (int) $bruto;
            continue;
        }

        if ($chave === 'crm_marcador') {
            if ($bruto === '') {
                $erros[$chave] = 'O marcador não pode ficar vazio. O padrão é [SITE].';
                continue;
            }
            $valores[$chave] = mb_substr($bruto, 0, 30);
            continue;
        }

        if ($chave === 'crm_responsavel') {
            $valores[$chave] = mb_substr($bruto, 0, 120);
            continue;
        }
```

- [ ] **Step 6: Documentar o token em `config/segredos.php.exemplo`**

Substitua o comentário final do arquivo por:

```php
// Token da conta do Agendor, de Menu > Integracoes dentro do CRM.
//
// Mora aqui, e nao na tabela config, porque o backup do painel empacota o
// banco: um token no banco viajaria dentro de todo zip que o cliente baixa.
// Vazio com o CRM ligado devolve o erro crm_sem_token.
define('CASTELLO_AGENDOR_TOKEN', '');
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `php testes/smoke.php 10-db` e `php testes/smoke.php 80-painel`
Expected: PASS nos dois.

- [ ] **Step 8: Commit**

```bash
git add public_html/lib/db.php public_html/painel/tabelas.php config/segredos.php.exemplo testes/casos/10-db.php testes/casos/80-painel.php
git commit -m "feat(crm): configuracao do agendor no lugar da generica

Sai endpoint, metodo, cabecalhos e mapa de campos, que so faziam sentido
para um CRM desconhecido. Entram funil, etapa, origem, categoria, marcador
e responsavel, com os ids reais da conta como padrao. O token fica em
segredos.php porque o backup do painel empacota o banco."
```

---

### Task 3: O CRM falso vira um Agendor falso

O conector só pode ser testado contra um servidor que fale as três rotas. Esta tarefa vem antes do conector porque é a bancada onde ele será construído.

**Files:**
- Modify: `testes/crm-falso.php`
- Test: `testes/casos/85-crm.php` (bloco "testes/crm-falso.php")

**Interfaces:**
- Consumes: nada.
- Produces: servidor que responde, sob a URL base `http://127.0.0.1:<porta>`:
  - `GET /people?phone=<digitos>` → `{"data":[...]}`. Devolve a pessoa quando os dígitos batem com uma cadastrada **e não começam com `55`**, imitando o comportamento medido na conta real. Caso contrário, `{"data":[]}`.
  - `POST /people` → 201 `{"data":{"id":<novo id>}}`.
  - `POST /people/<id>/deals` → 201 `{"data":{"id":<id>,"_webUrl":"https://web.agendor.com.br/negocio/<id>"}}`.
  - Modos de falha por cabeçalho `X-Falso-Modo`: `erro500`, `invalido`, `demora:<seg>`, `auth401`, `limite429`.
  - Continua registrando a última requisição em `sys_get_temp_dir()/crm-falso-ultima.json`, agora com a lista de **todas** as requisições da rodada em `crm-falso-todas.json`.

- [ ] **Step 1: Write the failing test**

Substitua, em `testes/casos/85-crm.php`, o teste `'o CRM falso responde em cada modo e registra a requisicao'` por:

```php
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
```

Troque também o ajudante `crm_falso_pedir` por uma versão que aceita método, e acrescente `crm_falso_todas`:

```php
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

function crm_falso_todas(): array
{
    $arquivo = sys_get_temp_dir() . '/crm-falso-todas.json';
    $dados = json_decode(is_file($arquivo) ? (string) file_get_contents($arquivo) : '', true);
    return is_array($dados) ? $dados : [];
}
```

E em `crm_falso_subir()`, acrescente `@unlink(sys_get_temp_dir() . '/crm-falso-todas.json');` ao lado do `@unlink(crm_falso_arquivo());`.

- [ ] **Step 2: Run test to verify it fails**

Run: `php testes/smoke.php 85`
Expected: FAIL. O falso atual responde `{"status":"ok"}` em qualquer rota e ignora o caminho.

- [ ] **Step 3: Reescrever `testes/crm-falso.php`**

```php
<?php
declare(strict_types=1);

/**
 * Agendor de mentira, para testar lib/crm.php sem tocar na conta do cliente.
 *
 * Subir:  php -S 127.0.0.1:8765 testes/crm-falso.php
 *
 * Rotas imitadas:
 *   GET  /people?phone=<digitos>   busca de duplicata
 *   POST /people                   cria pessoa,  201 {"data":{"id":N}}
 *   POST /people/<id>/deals        cria negocio, 201 {"data":{"id":M,"_webUrl":...}}
 *
 * A busca imita o comportamento MEDIDO na conta real em 2026-09-10: encontra
 * pelos digitos sem DDI e devolve vazio quando a busca chega com 55 na frente.
 * E justamente isso que o conector precisa acertar.
 *
 * Falhas pelo cabecalho X-Falso-Modo: erro500, auth401, limite429, invalido,
 * demora:<segundos>.
 *
 * Estado das pessoas em sys_get_temp_dir()/crm-falso-pessoas.json, para a
 * pessoa criada numa requisicao ser encontrada na seguinte.
 */

const FALSO_PESSOAS = '/crm-falso-pessoas.json';
const FALSO_ULTIMA  = '/crm-falso-ultima.json';
const FALSO_TODAS   = '/crm-falso-todas.json';

function falso_arquivo(string $nome): string
{
    return sys_get_temp_dir() . $nome;
}

function falso_ler(string $nome): array
{
    $bruto = is_file(falso_arquivo($nome)) ? (string) file_get_contents(falso_arquivo($nome)) : '';
    $dados = json_decode($bruto, true);
    return is_array($dados) ? $dados : [];
}

function falso_gravar(string $nome, array $dados): void
{
    file_put_contents(
        falso_arquivo($nome),
        (string) json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
    );
}

function falso_responder(int $http, array $corpo): void
{
    http_response_code($http);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($corpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/* ---------- registro da requisicao ---------- */

$corpo = (string) file_get_contents('php://input');

$cabecalhos = [];
foreach ($_SERVER as $chave => $valor) {
    if (strpos($chave, 'HTTP_') === 0) {
        $cabecalhos[strtolower(str_replace('_', '-', substr($chave, 5)))] = (string) $valor;
    }
}
if (isset($_SERVER['CONTENT_TYPE'])) {
    $cabecalhos['content-type'] = (string) $_SERVER['CONTENT_TYPE'];
}

$metodo  = (string) ($_SERVER['REQUEST_METHOD'] ?? '');
$uri     = (string) ($_SERVER['REQUEST_URI'] ?? '');
$caminho = (string) parse_url($uri, PHP_URL_PATH);

$registro = [
    'metodo'     => $metodo,
    'uri'        => $uri,
    'caminho'    => $caminho,
    'cabecalhos' => $cabecalhos,
    'corpo'      => $corpo,
    'quando'     => date('Y-m-d H:i:s'),
];
falso_gravar(FALSO_ULTIMA, $registro);
$todas = falso_ler(FALSO_TODAS);
$todas[] = $registro;
falso_gravar(FALSO_TODAS, $todas);

/* ---------- modos de falha ---------- */

$modo = $cabecalhos['x-falso-modo'] ?? '';

if ($modo === 'erro500') {
    falso_responder(500, ['errors' => ['erro interno']]);
    return;
}
if ($modo === 'auth401') {
    falso_responder(401, ['errors' => ['Token could not be authenticated']]);
    return;
}
if ($modo === 'limite429') {
    falso_responder(429, ['errors' => ['Too many requests']]);
    return;
}
if ($modo === 'invalido') {
    http_response_code(200);
    header('Content-Type: text/html; charset=utf-8');
    echo '<html><body>Manutencao programada. Volte mais tarde.</body></html>';
    return;
}
if (str_starts_with($modo, 'demora:')) {
    sleep(max(1, min(30, (int) substr($modo, 7))));
}

/* ---------- rotas ---------- */

$pessoas = falso_ler(FALSO_PESSOAS);

if ($metodo === 'GET' && $caminho === '/people') {
    $procurado = preg_replace('/\D+/', '', (string) ($_GET['phone'] ?? '')) ?? '';

    /* Comportamento medido na conta real: com 55 na frente nao acha nada. */
    $achadas = [];
    if ($procurado !== '' && !str_starts_with($procurado, '55')) {
        foreach ($pessoas as $pessoa) {
            if (in_array($procurado, $pessoa['telefones'] ?? [], true)) {
                $achadas[] = ['id' => $pessoa['id'], 'name' => $pessoa['name']];
            }
        }
    }
    falso_responder(200, ['data' => $achadas]);
    return;
}

if ($metodo === 'POST' && $caminho === '/people') {
    $entrada = json_decode($corpo, true);
    $entrada = is_array($entrada) ? $entrada : [];

    /* Guarda todo telefone que veio, em digitos, para a busca seguinte. */
    $telefones = [];
    foreach (['whatsapp', 'mobile', 'work'] as $campo) {
        $valor = (string) ($entrada['contact'][$campo] ?? '');
        $digitos = preg_replace('/\D+/', '', $valor) ?? '';
        if ($digitos !== '') {
            $telefones[] = $digitos;
        }
    }

    $id = 70000000 + count($pessoas) + 1;
    $pessoas[] = ['id' => $id, 'name' => (string) ($entrada['name'] ?? ''), 'telefones' => $telefones];
    falso_gravar(FALSO_PESSOAS, $pessoas);

    falso_responder(201, ['data' => ['id' => $id, 'name' => (string) ($entrada['name'] ?? '')]]);
    return;
}

if ($metodo === 'POST' && preg_match('#^/people/(\d+)/deals$#', $caminho, $partes) === 1) {
    $entrada = json_decode($corpo, true);
    $id = 90000000 + count(falso_ler(FALSO_TODAS));
    falso_responder(201, ['data' => [
        'id'      => $id,
        'title'   => (string) (is_array($entrada) ? ($entrada['title'] ?? '') : ''),
        '_webUrl' => 'https://web.agendor.com.br/negocio/' . $id,
    ]]);
    return;
}

falso_responder(404, ['errors' => ['rota nao encontrada: ' . $metodo . ' ' . $caminho]]);
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php testes/smoke.php 85`
Expected: o teste do Agendor falso PASSA. Os testes do conector e do `enviar.php` **continuam falhando**, porque `lib/crm.php` ainda é o genérico. Isso é esperado; a Task 4 os conserta.

- [ ] **Step 5: Commit**

```bash
git add testes/crm-falso.php testes/casos/85-crm.php
git commit -m "test(crm): o CRM falso vira um agendor falso de tres rotas

Imita GET /people, POST /people e POST /people/{id}/deals, guardando as
pessoas entre requisicoes para a deduplicacao ser testavel de ponta a ponta.
A busca por telefone reproduz o comportamento medido na conta real: acha
pelos digitos sem DDI e volta vazia quando a busca leva 55 na frente."
```

---

### Task 4: O conector do Agendor

Substitui `lib/crm.php` inteiro. As funções de montagem são puras e testadas isoladamente, porque é nelas que mora a regra de negócio; a orquestração fica numa função só, `crm_enviar`, que preserva a assinatura antiga para não tocar em `enviar.php` nem em `leads.php`.

**Files:**
- Rewrite: `public_html/lib/crm.php`
- Test: `testes/casos/85-crm.php`

**Interfaces:**
- Consumes: config da Task 2, `db_garantir_colunas` da Task 1 (indiretamente, pela coluna `crm_pessoa_id`).
- Produces:
  - `crm_token(): string`
  - `crm_whatsapp_ddi(string $bruto): string` — dígitos com `55` na frente, ou `''` se não der para normalizar
  - `crm_whatsapp_busca(string $bruto): string` — dígitos sem `55`, ou `''`
  - `crm_payload_pessoa(array $lead): array`
  - `crm_titulo_negocio(array $lead): string`
  - `crm_descricao_negocio(array $lead): string`
  - `crm_payload_negocio(array $lead): array`
  - `crm_enviar(array $lead): array{ok: bool, http: int, resposta: string, erro: ?string, pessoa_id: ?int, negocio_url: ?string}` — as quatro primeiras chaves são as mesmas de antes, para `enviar.php` e `leads.php` continuarem funcionando sem alteração.

- [ ] **Step 1: Write the failing test — funções puras**

Substitua, em `testes/casos/85-crm.php`, os dois testes do conector antigo (`'crm_enviar recusa sem fazer requisicao...'` e `'crm_enviar conversa com o CRM...'`) e a constante `MAPA_PADRAO` por:

```php
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
```

Acrescente os dois ajudantes, perto de `crm_teste_limpar()`:

```php
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
```

E troque a constante `LEAD_EXEMPLO` para incluir o prazo:

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php testes/smoke.php 85`
Expected: FAIL com `Call to undefined function crm_whatsapp_ddi()`.

- [ ] **Step 3: Escrever `public_html/lib/crm.php`**

```php
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
        'name'       => crm_campo($lead, 'nome'),
        'leadOrigin' => (int) config_ler('crm_origem', '2656389'),
        'category'   => (int) config_ler('crm_categoria', '4187395'),
    ];

    $contato = [];
    $comDdi = crm_whatsapp_ddi(crm_campo($lead, 'whatsapp'));
    if ($comDdi !== '') {
        $contato['whatsapp'] = $comDdi;
    }
    $semDdi = crm_campo($lead, 'whatsapp');
    if (crm_whatsapp_busca($semDdi) !== '') {
        $contato['mobile'] = $semDdi;
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

    return $prefixo . mb_substr($nome, 0, max(1, $sobra));
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
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php testes/smoke.php 85`
Expected: os quatro testes de função pura PASSAM. Os testes de `crm_enviar` e de `enviar.php` ainda falham, porque `crm_enviar` ainda não existe neste arquivo. Task 5 fecha.

- [ ] **Step 5: Commit**

```bash
git add public_html/lib/crm.php testes/casos/85-crm.php
git commit -m "feat(crm): montagem do payload do agendor

Funcoes puras: os dois formatos de telefone, o payload da pessoa com os tres
campos customizados que a conta ja usa, e o titulo do negocio na convencao
[SITE] - prazo - nome, espelhando o [META] que ja existe no funil."
```

---

### Task 5: `crm_enviar` encadeando as três chamadas

**Files:**
- Modify: `public_html/lib/crm.php` (acrescenta a orquestração)
- Test: `testes/casos/85-crm.php`

**Interfaces:**
- Consumes: tudo da Task 4.
- Produces: `crm_enviar(array $lead): array` com as chaves `ok`, `http`, `resposta`, `erro`, `pessoa_id`, `negocio_url`. Quando o lead traz `crm_pessoa_id` preenchido, a busca e a criação da pessoa são puladas.

- [ ] **Step 1: Write the failing test**

```php
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
        crm_teste_limpar();
        crm_teste_config();

        putenv('CASTELLO_AGENDOR_TESTE_MODO=auth401');
        $r = crm_enviar(LEAD_EXEMPLO);
        falso($r['ok'], '401: ok false');
        igual('crm_auth', $r['erro']);
        igual(401, $r['http']);

        putenv('CASTELLO_AGENDOR_TESTE_MODO=limite429');
        igual('crm_limite', crm_enviar(LEAD_EXEMPLO)['erro']);

        putenv('CASTELLO_AGENDOR_TESTE_MODO=erro500');
        igual('crm_http', crm_enviar(LEAD_EXEMPLO)['erro']);

        putenv('CASTELLO_AGENDOR_TESTE_MODO=invalido');
        igual('crm_resposta_invalida', crm_enviar(LEAD_EXEMPLO)['erro']);

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
        config_gravar('crm_timeout', '3');
        putenv('CASTELLO_AGENDOR_TESTE_MODO=demora:8');

        $inicio = microtime(true);
        $r = crm_enviar(LEAD_EXEMPLO);
        $gasto = microtime(true) - $inicio;

        falso($r['ok'], 'CRM lento: ok false');
        igual('crm_tempo', $r['erro']);
        verdade($gasto < 8, 'desistiu dentro do orcamento, gastou ' . round($gasto, 2) . 's');
    } finally {
        putenv('CASTELLO_AGENDOR_TESTE_MODO');
        config_gravar('crm_timeout', '10');
        crm_falso_derrubar($servidor);
    }
});
```

O `CASTELLO_AGENDOR_TESTE_MODO` precisa chegar ao servidor falso como cabeçalho. Acrescente ao `crm_requisitar` do Step 3 o repasse desse cabeçalho, que só existe quando a variável está definida — em produção ela nunca está.

- [ ] **Step 2: Run test to verify it fails**

Run: `php testes/smoke.php 85`
Expected: FAIL com `Call to undefined function crm_enviar()`.

- [ ] **Step 3: Acrescentar a orquestração ao fim de `lib/crm.php`**

```php
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

    $ch = curl_init($base . $caminho);
    $opcoes = [
        CURLOPT_CUSTOMREQUEST  => $metodo,
        CURLOPT_HTTPHEADER     => $cabecalhos,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => (int) ceil($restante),
        CURLOPT_CONNECTTIMEOUT => (int) min(5, ceil($restante)),
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
    $http = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($erroNum !== 0) {
        $erro = $erroNum === CURLE_OPERATION_TIMEDOUT ? 'crm_tempo' : 'crm_conexao';
        return ['ok' => false, 'http' => $http, 'bruto' => 'curl ' . $erroNum, 'dados' => null, 'erro' => $erro];
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
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php testes/smoke.php 85`
Expected: todos os testes do conector PASSAM. Os de `enviar.php` ainda podem falhar por causa do `crm_pessoa_id`, que é a Task 6.

- [ ] **Step 5: Commit**

```bash
git add public_html/lib/crm.php testes/casos/85-crm.php
git commit -m "feat(crm): crm_enviar encadeia busca, pessoa e negocio

Orcamento de tempo compartilhado pelas tres chamadas, com o teto de 10s do
contrato valendo para o conjunto. Lead com crm_pessoa_id pula direto ao
negocio, para o reenvio nao duplicar pessoa. A busca e o unico passo
tolerante a falha: duplicar no CRM e menos grave que perder o lead."
```

---

### Task 6: Guardar o `crm_pessoa_id` do lead

Sem isto, uma falha entre a criação da pessoa e a do negócio faz o reenvio criar pessoa nova a cada tentativa.

**Files:**
- Modify: `public_html/lib/leads.php`
- Modify: `public_html/enviar.php`
- Test: `testes/casos/85-crm.php`

**Interfaces:**
- Consumes: `crm_enviar()` da Task 5, coluna `crm_pessoa_id` da Task 1.
- Produces: `lead_marcar_pessoa(int $id, int $pessoaId): void`.

- [ ] **Step 1: Write the failing test**

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php testes/smoke.php 85`
Expected: FAIL com `Call to undefined function lead_marcar_pessoa()`.

- [ ] **Step 3: Acrescentar `lead_marcar_pessoa` a `lib/leads.php`**

Logo depois de `lead_marcar()`:

```php
/**
 * Guarda o id da pessoa no CRM.
 *
 * Existe para o reenvio nao criar pessoa duplicada quando a primeira tentativa
 * conseguiu criar a pessoa e morreu antes de criar o negocio. Nao mexe em
 * status nem em tentativas: e so a memoria de meio caminho.
 */
function lead_marcar_pessoa(int $id, int $pessoaId): void
{
    if ($pessoaId <= 0) {
        return;
    }
    $st = db()->prepare('UPDATE leads SET crm_pessoa_id = :pessoa WHERE id = :id');
    $st->execute([':pessoa' => $pessoaId, ':id' => $id]);
}
```

- [ ] **Step 4: Gravar o id em `enviar.php` e em `leads_reenviar()`**

Em `public_html/enviar.php`, logo depois de `$resultado = crm_enviar($lead);`:

```php
    if (!empty($resultado['pessoa_id'])) {
        lead_marcar_pessoa($id, (int) $resultado['pessoa_id']);
    }
```

Em `lib/leads.php`, dentro de `leads_reenviar()`, logo depois de `$resultado = crm_enviar($lead);`:

```php
        if (!empty($resultado['pessoa_id'])) {
            lead_marcar_pessoa((int) $lead['id'], (int) $resultado['pessoa_id']);
        }
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php testes/smoke.php 85`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add public_html/lib/leads.php public_html/enviar.php testes/casos/85-crm.php
git commit -m "feat(leads): guarda o id da pessoa no crm entre tentativas

Pessoa criada e negocio falhando era o caso novo que as tres chamadas
introduziram. Sem isso o reenvio criaria uma pessoa por tentativa."
```

---

### Task 7: O campo de prazo no formulário

O prazo é o critério de qualificação da Castello, está no título de todo negócio do funil e alimenta um campo customizado que a equipe preenche na mão. **Esta tarefa invalida as cinco bases de comparação, e regenerá-las faz parte dela.**

**Files:**
- Modify: `public_html/partials/formulario.php`
- Modify: `public_html/enviar.php` (`ENVIAR_LIMITES` e validação)
- Modify: `public_html/lib/leads.php` (`LEAD_CAMPOS`)
- Modify: `public_html/lib/email.php` (`EMAIL_ROTULOS`)
- Create: `testes/gerar-bases.php`
- Regenerate: `testes/base/pagina-home.html`, `pagina-casa-pronta.html`, `pagina-flex.html`, `pagina-portfolio.html`, `pagina-contato.html`
- Test: `testes/casos/85-crm.php`, `testes/casos/50-paginas.php`

**Interfaces:**
- Consumes: coluna `prazo` da Task 1, `crm_payload_pessoa` da Task 4.
- Produces: campo `prazo` no POST e na tabela `leads`. Constante `ENVIAR_PRAZOS` com os quatro valores literais.

- [ ] **Step 1: Write the failing test**

Em `testes/casos/85-crm.php`:

```php
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
```

Em `testes/casos/50-paginas.php`:

```php
teste('o campo de prazo aparece nas cinco paginas com os valores do CRM', function (): void {
    foreach (PAGINAS_SITE as $arquivo) {
        $html = render(site() . '/' . $arquivo);
        contem('name="prazo"', $html, $arquivo . ' tem o campo de prazo');
        foreach (['Imediato', 'Até 3 meses', 'Até 6 meses', 'Só pesquisando'] as $opcao) {
            contem('<option value="' . $opcao . '">' . $opcao . '</option>', $html, $arquivo . ' oferece ' . $opcao);
        }
    }
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php testes/smoke.php 85` e `php testes/smoke.php 50`
Expected: FAIL. `ENVIAR_PRAZOS` não existe, e as páginas não têm o campo.

- [ ] **Step 3: Acrescentar o select em `partials/formulario.php`**

Logo **depois** do grupo `q-busca` e **antes** do grupo `qGroupModelo`:

```php
        <div class="qform__group">
          <label class="qform__label" for="q-prazo">Quando pretende iniciar a obra? <span class="opt">(opcional)</span></label>
          <div class="qform__select">
            <select id="q-prazo" name="prazo">
              <option value="">Prefiro não dizer agora</option>
              <option value="Imediato">Imediato</option>
              <option value="Até 3 meses">Até 3 meses</option>
              <option value="Até 6 meses">Até 6 meses</option>
              <option value="Só pesquisando">Só pesquisando</option>
            </select>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
          </div>
        </div>
```

Os quatro textos são idênticos aos que a Castello já usa no CRM. Não os reescreva: qualquer variação quebra a comparação entre o lead do site e o lead do Meta no mesmo funil.

- [ ] **Step 4: Aceitar e validar o prazo em `enviar.php`**

Acrescente `'prazo' => 60,` a `ENVIAR_LIMITES`, logo depois de `'cidade' => 120,`.

Acrescente a constante, logo depois de `ENVIAR_LIMITES`:

```php
/**
 * Os quatro prazos, exatamente como estao no CRM da Castello. Valor fora da
 * lista vira vazio em vez de 422: o campo e opcional e um POST adulterado nao
 * pode custar o lead.
 */
const ENVIAR_PRAZOS = ['Imediato', 'Até 3 meses', 'Até 6 meses', 'Só pesquisando'];
```

E, logo depois do laço que preenche os campos de UTM:

```php
    if (!in_array($campos['prazo'], ENVIAR_PRAZOS, true)) {
        $campos['prazo'] = '';
    }
```

- [ ] **Step 5: Gravar e exibir o prazo**

Em `lib/leads.php`, em `LEAD_CAMPOS`, acrescente `'prazo'` logo depois de `'cidade'`:

```php
const LEAD_CAMPOS = [
    'nome', 'whatsapp', 'busca', 'modelo', 'cidade', 'prazo', 'mensagem',
    'pagina', 'referrer',
    'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
];
```

Em `lib/email.php`, em `EMAIL_ROTULOS`, logo depois de `'cidade'`:

```php
    'prazo'        => 'Quer iniciar a obra',
```

- [ ] **Step 6: Rodar os testes de comportamento**

Run: `php testes/smoke.php 85`
Expected: PASS.

- [ ] **Step 7: Conferir as cinco páginas no navegador antes de regenerar as bases**

Suba o site local e abra as cinco páginas:

```bash
php -S localhost:8000 -t public_html
```

Confira em `http://localhost:8000/` (home), `/casa-pronta.php`, `/flex.php`, `/portfolio.php` e `/contato.php`:

1. O campo novo aparece entre "O que você busca?" e "Modelo de interesse".
2. Ele usa a mesma caixa de select dos outros, com a seta no lugar.
3. Em largura de telefone (400px), o campo não estoura nem quebra o modal.
4. O modal continua abrindo, fechando e enviando.

O cabeçalho do `50-paginas.php` é explícito: base nova só depois da conferência no navegador. Não pule este passo, senão a base deixa de ser referência e vira eco da própria saída.

- [ ] **Step 8: Criar a ferramenta que gera as bases**

Não gere as bases pelo servidor local nem por `curl`. O `50-paginas.php` renderiza as páginas contra um **banco temporário semeado pelo `migrar()`**, e não contra o banco de desenvolvimento; uma base tirada do servidor local carregaria as diferenças do seu banco e o teste passaria a comparar duas coisas diferentes.

A base tem que nascer do mesmo ambiente do teste. Crie `testes/gerar-bases.php`, que repete a montagem do `executar-caso.php`:

```php
<?php
declare(strict_types=1);

/**
 * Regenera testes/base/pagina-*.html, as referencias do 50-paginas.php.
 *
 * Monta o MESMO ambiente do runner: pasta de config temporaria e banco semeado
 * pelo migrar(). Gerar pelo servidor local em vez daqui traria as diferencas do
 * banco de desenvolvimento para dentro da referencia, e o teste passaria a
 * comparar duas coisas diferentes.
 *
 * So rode depois de conferir as paginas no navegador: esta ferramenta grava o
 * que a pagina produz, entao ela promove a bug a referencia com a mesma
 * facilidade com que registra uma melhoria.
 *
 * Uso: php testes/gerar-bases.php
 */

$temp = rtrim(sys_get_temp_dir(), "/\\") . '/castello-bases-' . getmypid();
mkdir($temp . '/config', 0775, true);
mkdir($temp . '/uploads', 0775, true);

define('CASTELLO_CONFIG', $temp . '/config');
define('CASTELLO_UPLOADS', $temp . '/uploads');

register_shutdown_function(static function () use ($temp): void {
    if (!is_dir($temp)) {
        return;
    }
    $itens = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($temp, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($itens as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($temp);
});

require __DIR__ . '/assertivas.php';

banco_com_conteudo();

/* As mesmas cinco chaves de PAGINAS_SITE, em testes/casos/50-paginas.php. */
$paginas = [
    'home'        => 'index.php',
    'casa-pronta' => 'casa-pronta.php',
    'flex'        => 'flex.php',
    'portfolio'   => 'portfolio.php',
    'contato'     => 'contato.php',
];

foreach ($paginas as $chave => $arquivo) {
    $destino = raiz() . '/testes/base/pagina-' . $chave . '.html';
    file_put_contents($destino, render(site() . '/' . $arquivo));
    echo 'regenerada: pagina-', $chave, '.html (', number_format(filesize($destino)), " bytes)\n";
}

echo "\nConfira o diff antes de commitar: git diff testes/base/\n";
```

- [ ] **Step 9: Regenerar as cinco bases**

Run: `php testes/gerar-bases.php`
Expected: cinco linhas `regenerada: ...`, uma por página.

- [ ] **Step 10: Conferir que o diff das bases só tem o campo novo**

```bash
git diff --stat testes/base/
git diff testes/base/pagina-home.html
```

Expected: cada base ganha apenas o bloco do `q-prazo`. Qualquer outra diferença é sinal de que algo mais mudou junto e precisa ser entendido antes de seguir. Se aparecer diferença que você não sabe explicar, **pare** e investigue: uma base errada envenena todos os testes de página daqui para a frente.

- [ ] **Step 11: Rodar a suíte inteira**

Run: `php testes/smoke.php`
Expected: todos os casos passam.

- [ ] **Step 12: Commit**

```bash
git add public_html/partials/formulario.php public_html/enviar.php public_html/lib/leads.php public_html/lib/email.php testes/gerar-bases.php testes/base/ testes/casos/50-paginas.php testes/casos/85-crm.php
git commit -m "feat(form): campo de prazo da obra, com os valores do CRM

E o criterio de qualificacao da Castello: esta no titulo de todo negocio do
funil e num campo customizado que a equipe preenche na mao. Sem ele o lead do
site nasceria mais pobre que o do Meta no mesmo funil.

Os quatro textos sao identicos aos do CRM de proposito: variacao quebraria a
comparacao entre os dois canais. As cinco bases foram regeneradas depois da
conferencia no navegador."
```

---

### Task 8: O link do negócio no e-mail de aviso

O painel não tem tela de leads: o e-mail é o único lugar onde a equipe lê o lead fora do CRM. Com o `_webUrl` no corpo, quem recebe pula do e-mail direto para o negócio.

**Files:**
- Modify: `public_html/lib/email.php` (`email_corpo_lead`, `email_linha_crm`)
- Test: `testes/casos/85-crm.php`

**Interfaces:**
- Consumes: `negocio_url` do resultado de `crm_enviar()` (Task 5).
- Produces: nada novo; muda o corpo do e-mail.

- [ ] **Step 1: Write the failing test**

```php
teste('o e-mail traz o prazo e o link do negocio no CRM', function (): void {
    $comPrazo = LEAD_EMAIL + ['prazo' => 'Até 3 meses'];

    $corpo = email_corpo_lead($comPrazo, [
        'ok' => true, 'http' => 201, 'resposta' => '{"data":{"id":90000001}}', 'erro' => null,
        'pessoa_id' => 71397195, 'negocio_url' => 'https://web.agendor.com.br/negocio/90000001',
    ]);
    contem('Quer iniciar a obra: Até 3 meses', $corpo);
    contem('https://web.agendor.com.br/negocio/90000001', $corpo, 'link direto para o negocio');
    contem('CRM: entregue', $corpo);

    /* Sem link, o corpo nao pode ficar com rotulo orfao. */
    $semLink = email_corpo_lead($comPrazo, [
        'ok' => false, 'http' => 500, 'resposta' => 'interno', 'erro' => 'crm_http',
        'pessoa_id' => null, 'negocio_url' => null,
    ]);
    nao_contem('web.agendor.com.br', $semLink);
    contem('CRM: falhou', $semLink);
    contem('crm_http', $semLink);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php testes/smoke.php 85`
Expected: FAIL, o corpo não contém o link.

- [ ] **Step 3: Acrescentar o link em `email_corpo_lead`**

Em `lib/email.php`, dentro de `email_corpo_lead`, logo depois da linha `$linhas[] = email_linha_crm($resultado_crm);`:

```php
    $link = $resultado_crm['negocio_url'] ?? null;
    if (is_string($link) && $link !== '') {
        $linhas[] = 'Abrir no CRM: ' . $link;
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php testes/smoke.php 85`
Expected: PASS.

- [ ] **Step 5: Rodar a suíte inteira e commitar**

```bash
php testes/smoke.php
git add public_html/lib/email.php testes/casos/85-crm.php
git commit -m "feat(email): prazo e link do negocio no aviso de lead novo

O painel nao tem tela de leads, entao o e-mail e o unico lugar onde a equipe
le o lead fora do CRM. Com o _webUrl no corpo, o vendedor pula do e-mail
direto para o negocio."
```

---

### Task 9: Validar `dealStage` contra a conta real

Único ponto que servidor falso nenhum resolve. O swagger descreve `dealStage` como *"Deal stage sequence number"*, mas id e sequência são ambos inteiros: mandar o valor errado não gera erro de validação, só põe o negócio no lugar errado, em silêncio.

**Esta tarefa escreve na conta do cliente.** Anuncie antes de rodar, e confirme a limpeza depois.

**Files:**
- Create: `ferramentas/agendor-provar-etapa.php`
- Modify: `docs/superpowers/specs/2026-09-10-castello-agendor-design.md` (seção 11.2, registrando o resultado)

**Interfaces:**
- Consumes: `.credenciais-agendor` (já existe, ignorado pelo git), `ferramentas/agendor-ler.php` para as funções de leitura.
- Produces: resposta definitiva sobre o valor de `config.crm_etapa`.

- [ ] **Step 1: Escrever a ferramenta**

Crie `ferramentas/agendor-provar-etapa.php`. Ela cria uma pessoa e um negócio de teste, lê em que etapa o negócio caiu, imprime o veredito e **apaga a pessoa**, o que leva o negócio junto. Nada fica na conta.

```php
<?php
declare(strict_types=1);

/**
 * Prova se dealStage quer a SEQUENCIA da etapa ou o ID dela.
 *
 * O swagger diz "Deal stage sequence number", mas os dois sao inteiros: o
 * valor errado nao da erro, so poe o negocio na etapa errada em silencio.
 *
 * ESTA FERRAMENTA ESCREVE NA CONTA DO CLIENTE. Cria uma pessoa de teste com um
 * negocio, confere a etapa e apaga a pessoa, o que leva o negocio junto.
 *
 * Uso: php ferramentas/agendor-provar-etapa.php --confirmo
 */

require_once __DIR__ . '/agendor-ler.php';
```

**Primeiro, extraia o que os dois arquivos compartilham.** O `agendor-ler.php` roda a leitura ao ser incluído, então incluí-lo aqui dispararia o relatório inteiro antes da prova. Crie `ferramentas/agendor-comum.php` com, exatamente:

- as constantes `AGENDOR_BASE`, `AGENDOR_PAUSA_US` e `AGENDOR_TIMEOUT`
- as funções `agendor_normalizar()`, `agendor_token()` e `agendor_get()`

Deixe em `agendor-ler.php` só o que é do relatório: `AGENDOR_ROTAS`, `agendor_texto()`, `agendor_colunas()`, `agendor_preencher()`, `agendor_tabela()`, `agendor_campos_custom()` e o bloco de execução. Ponha `require_once __DIR__ . '/agendor-comum.php';` no topo dos dois.

Confira que nada quebrou antes de seguir: `php ferramentas/agendor-ler.php` tem que continuar imprimindo o relatório inteiro, com as doze rotas.

Acrescente ao comum também:

```php
/** Requisicao com corpo. Separada do agendor_get para deixar obvio, ao ler o
    arquivo, onde estao as chamadas que ESCREVEM na conta. */
function agendor_escrever(string $token, string $metodo, string $caminho, ?array $corpo): array
{
    $ch = curl_init(AGENDOR_BASE . $caminho);
    $opcoes = [
        CURLOPT_CUSTOMREQUEST  => $metodo,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Token ' . $token,
            'Accept: application/json',
            'Content-Type: application/json; charset=utf-8',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => AGENDOR_TIMEOUT,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT      => 'castello-site/agendor-provar',
    ];
    if ($corpo !== null) {
        $opcoes[CURLOPT_POSTFIELDS] = (string) json_encode($corpo, JSON_UNESCAPED_UNICODE);
    }
    curl_setopt_array($ch, $opcoes);
    $bruto = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $erroNum = curl_errno($ch);
    curl_close($ch);

    $dados = json_decode(is_string($bruto) ? $bruto : '', true);
    return [
        'ok'    => $erroNum === 0 && $http >= 200 && $http < 300,
        'http'  => $http,
        'dados' => is_array($dados) ? $dados : null,
        'bruto' => is_string($bruto) ? $bruto : '',
    ];
}
```

O corpo da prova:

```php
if (!in_array('--confirmo', $argv, true)) {
    fwrite(STDERR, "Esta ferramenta CRIA e APAGA um registro na conta real do cliente.\n");
    fwrite(STDERR, "Rode com --confirmo se e isso mesmo que voce quer.\n");
    exit(1);
}

$token = agendor_token($argv);
if ($token === '') {
    fwrite(STDERR, "Token nao encontrado. Veja ferramentas/agendor-ler.php.\n");
    exit(1);
}

$marca = 'TESTE TECNICO SITE ' . date('Ymd-His');
$pessoaId = null;

try {
    $pessoa = agendor_escrever($token, 'POST', '/people', [
        'name'    => $marca,
        'contact' => ['mobile' => '(48) 90000-0000'],
    ]);
    if (!$pessoa['ok']) {
        throw new RuntimeException('nao criou a pessoa: HTTP ' . $pessoa['http'] . ' ' . $pessoa['bruto']);
    }
    $pessoaId = (int) $pessoa['dados']['data']['id'];
    echo "pessoa de teste criada: id {$pessoaId}\n";

    $negocio = agendor_escrever($token, 'POST', '/people/' . $pessoaId . '/deals', [
        'title'     => $marca,
        'funnel'    => 904296,
        'dealStage' => 1,
    ]);
    if (!$negocio['ok']) {
        throw new RuntimeException('nao criou o negocio: HTTP ' . $negocio['http'] . ' ' . $negocio['bruto']);
    }

    $etapa = $negocio['dados']['data']['dealStage'] ?? [];
    $nome = $etapa['name'] ?? '(sem nome)';
    $id   = $etapa['id'] ?? '(sem id)';
    $seq  = $etapa['sequence'] ?? '(sem sequencia)';

    echo "\nMandei dealStage: 1\n";
    echo "O negocio caiu em: {$nome}  (id {$id}, sequencia {$seq})\n\n";
    echo $nome === 'Contato'
        ? "VEREDITO: dealStage quer a SEQUENCIA. config.crm_etapa = 1 esta certo.\n"
        : "VEREDITO: dealStage NAO quer a sequencia. Trocar config.crm_etapa para o id 3845540 e rodar de novo.\n";
} finally {
    if ($pessoaId !== null) {
        $apagou = agendor_escrever($token, 'DELETE', '/people/' . $pessoaId, null);
        echo "\nlimpeza: pessoa {$pessoaId} apagada: ", $apagou['ok'] ? 'sim' : 'NAO, APAGUE NA MAO', "\n";
    }
}
```

- [ ] **Step 2: Avisar antes de rodar**

Diga ao seu parceiro humano, em texto, que a próxima chamada cria e apaga um registro na conta real do cliente, e espere a confirmação. Não rode sem isso.

- [ ] **Step 3: Rodar a prova**

Run: `php ferramentas/agendor-provar-etapa.php --confirmo`
Expected: a saída diz em que etapa o negócio caiu e imprime o veredito, terminando com a confirmação da limpeza.

- [ ] **Step 4: Conferir a limpeza na conta**

Run: `php ferramentas/agendor-ler.php | head -30`

E confirme, no Agendor pelo navegador, que não sobrou nenhuma pessoa nem negócio chamado `TESTE TECNICO SITE`. Se o DELETE falhou, apague na mão antes de seguir.

- [ ] **Step 5: Registrar o resultado na spec e ajustar a config se for o caso**

Reescreva a seção 11.2 da spec com o resultado medido, no mesmo formato da 11.1, trocando o título de "ABERTO, exige escrita" para "MEDIDO em <data>".

Se o veredito disser que `dealStage` quer o **id**, troque o padrão de `crm_etapa` em `lib/db.php` para `3845540`, ajuste a ajuda do campo no painel (que hoje diz "a posição da etapa no funil") e corrija as seções 5.2 e 6.2 da spec, mais os testes da Task 5 que afirmam `igual(1, $corpoNegocio['dealStage'])`.

- [ ] **Step 6: Commit**

```bash
git add ferramentas/agendor-comum.php ferramentas/agendor-ler.php ferramentas/agendor-provar-etapa.php docs/superpowers/specs/2026-09-10-castello-agendor-design.md
git commit -m "test(crm): prova qual valor o dealStage do agendor espera

Cria um negocio de teste na conta real, le em que etapa ele caiu e apaga o
que criou. Era o ultimo ponto que servidor falso nenhum resolvia: id e
sequencia sao ambos inteiros, entao o valor errado nao da erro, so poe o
negocio na etapa errada em silencio."
```

---

### Task 10: Ligar em produção

**Files:**
- Modify: `config/segredos.php` no servidor (não vai para o git)
- Modify: `CLAUDE.md`
- Test: verificação manual no servidor

**Interfaces:**
- Consumes: tudo das tarefas anteriores.
- Produces: integração ligada, com um lead real chegando ao funil.

- [ ] **Step 1: Rodar a suíte inteira antes de subir**

Run: `php testes/smoke.php`
Expected: todos os casos passam. Não suba nada com a suíte vermelha.

- [ ] **Step 2: Conferir o que o deploy vai enviar**

Run: `bash ferramentas/deploy.sh --listar`
Expected: a lista traz `lib/crm.php`, `lib/db.php`, `lib/leads.php`, `lib/schema.sql`, `enviar.php`, `partials/formulario.php`, `lib/email.php` e `painel/tabelas.php`. **Não pode trazer `migrar.php`** nem qualquer arquivo de credencial.

- [ ] **Step 3: Enviar**

Run: `bash ferramentas/deploy.sh`

- [ ] **Step 4: Gravar o token no servidor**

O `config/segredos.php` do servidor fica em `/home/freelain/domains/tohospedando.com.br/castello-config/segredos.php`, fora do `public_html`. Acrescente a linha:

```php
define('CASTELLO_AGENDOR_TOKEN', 'o-token-da-conta');
```

Confirme que o arquivo continua fora do alcance da web.

- [ ] **Step 5: Conferir que a migração das colunas rodou**

Abra qualquer página do site (o que dispara a `db()`), depois confirme pelo painel que as configurações do CRM aparecem com os valores certos. Lembre do DNS: toda requisição ao servidor de teste precisa de `--resolve castello.tohospedando.com.br:443:200.11.120.114` e `-k`.

```bash
curl -sk --resolve castello.tohospedando.com.br:443:200.11.120.114 \
  https://castello.tohospedando.com.br/ -o /dev/null -w "home: %{http_code}\n"
```

- [ ] **Step 6: Ligar o CRM e mandar um lead de verdade**

No painel, em Configurações, ligue "Enviar os pedidos para o CRM" e salve. Depois preencha o formulário do site com dados seus e envie.

Confira, nesta ordem:

1. O site respondeu sucesso ao visitante.
2. O e-mail de aviso chegou, com a linha do prazo e o link do negócio.
3. No Agendor, a pessoa nasceu com whatsapp, mobile, cidade da obra, anúncio de origem e prazo preenchidos.
4. O negócio está **na etapa Contato**, com o título `[SITE] - <prazo> - <seu nome>`.
5. Envie **uma segunda vez com o mesmo telefone** e confirme que nasceu um negócio novo **na mesma pessoa**, sem pessoa duplicada. Este é o teste que prova a deduplicação em produção.

- [ ] **Step 7: Limpar os leads de teste da conta**

Apague no Agendor a pessoa de teste e os dois negócios. Combine antes com o cliente que esses registros vão aparecer, para ninguém do comercial trabalhar um lead falso.

- [ ] **Step 8: Atualizar o `CLAUDE.md`**

O arquivo está desatualizado em três pontos, e um deles vai atrapalhar quem pegar o projeto depois:

1. Diz que o site tem duas páginas (`index.php` e `flex.php`). São cinco: `index.php`, `casa-pronta.php`, `flex.php`, `portfolio.php` e `contato.php`.
2. Diz que o conteúdo da Flex é provisório, com "3 modelos sem preço, Sob consulta". São cinco modelos com preços reais, conferidos contra a tabela de setembro de 2026 do cliente.
3. Não menciona o Agendor. Acrescente: o CRM é o Agendor, o token mora em `config/segredos.php`, a configuração fica no painel, e `ferramentas/agendor-ler.php` lê a estrutura da conta.

Acrescente também, em Pendências, o item do catálogo da Casa Pronta descrito na seção 13.1 da spec.

- [ ] **Step 9: Commit e push**

```bash
git add CLAUDE.md
git commit -m "docs: CLAUDE.md alinhado ao site multipagina e ao agendor

Tres correcoes: o site tem cinco paginas e nao duas, a Flex ja esta com os
cinco modelos e os precos reais de setembro, e o CRM agora tem nome."
git push origin main
```

---

## Notas de execução

**A ordem importa.** As Tasks 1 a 6 são uma corrente: cada uma deixa a suíte verde no que já foi feito, mas os testes do `enviar.php` só voltam ao verde por completo na Task 6. Se você precisar parar no meio, pare **entre** tarefas, nunca dentro de uma.

**A Task 7 é a que mais assusta e a menos perigosa.** Ela deixa vermelhos os cinco testes de página até o passo 8. Isso é esperado e está no roteiro. O que não pode acontecer é regenerar as bases sem abrir o navegador: a base existe justamente para pegar o que o teste automático não vê.

**A Task 9 é a única que toca a conta do cliente.** Peça confirmação antes, e confira a limpeza depois.

**Se `crm_base` parecer um cheiro de teste vazando para produção:** é uma escolha consciente. A alternativa seria uma constante, e ela não funciona aqui, porque o servidor falso muda de porta a cada bloco de teste e constante não se redefine no mesmo processo. Fica documentado no código e escondido do painel.
