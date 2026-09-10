# Castello Fase 2 — Contrato compartilhado

> **Este documento é a fonte da verdade das três frentes.** Nenhuma frente inventa nome de tabela, coluna, função ou campo de formulário. Se algo aqui estiver errado ou faltando, pare e avise em vez de improvisar: mudar o contrato no meio quebra as outras frentes.

**Spec:** [2026-09-09-castello-fase2-design.md](../specs/2026-09-09-castello-fase2-design.md)

---

## 1. Ambiente e convenções

- **PHP 8.3** (verificado local: 8.3.32). Sem framework, sem Composer, sem dependência externa.
- Extensões usadas: `pdo_sqlite`, `fileinfo`, `gd`, `curl`, `mbstring`, `session`.
- **`ZipArchive` não existe no ambiente local** e `phar.readonly` está ligado. O backup por zip só pode ser validado no servidor. Todo código que usa `ZipArchive` deve checar `class_exists('ZipArchive')` e devolver um erro claro quando ausente, nunca um fatal.
- Todo arquivo PHP começa com `<?php` e **não** tem `?>` no final.
- `declare(strict_types=1);` em toda a `lib/`.
- Codificação UTF-8 sem BOM. Fuso `America/Sao_Paulo`.
- Datas gravadas no formato `Y-m-d H:i:s`.
- Nomes de função, tabela e coluna em português, sem acento, `snake_case`.
- Toda saída de dado do banco no HTML passa por `e()` (seção 4.1). Sem exceção.
- Copy do site segue o tom da Freela: sem travessões, sem emojis, números concretos.

---

## 2. Schema SQL

Conteúdo exato de `public_html/lib/schema.sql`. Copie sem alterar.

```sql
PRAGMA journal_mode = WAL;
PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS usuarios (
  id            INTEGER PRIMARY KEY,
  login         TEXT NOT NULL UNIQUE,
  senha_hash    TEXT NOT NULL,
  nome          TEXT,
  criado_em     TEXT NOT NULL,
  ultimo_acesso TEXT
);

CREATE TABLE IF NOT EXISTS login_tentativas (
  ip            TEXT PRIMARY KEY,
  tentativas    INTEGER NOT NULL DEFAULT 0,
  bloqueado_ate TEXT
);

CREATE TABLE IF NOT EXISTS modelos (
  id          INTEGER PRIMARY KEY,
  modalidade  TEXT NOT NULL CHECK (modalidade IN ('pronta','flex')),
  nome        TEXT NOT NULL,
  area        TEXT,
  parede      TEXT,
  preco       TEXT,
  prazo       TEXT,
  descricao   TEXT,
  foto        TEXT,
  foto_alt    TEXT,
  destaque    INTEGER NOT NULL DEFAULT 0,
  ativo       INTEGER NOT NULL DEFAULT 1,
  ordem       INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS portfolio (
  id        INTEGER PRIMARY KEY,
  titulo    TEXT NOT NULL,
  categoria TEXT,
  foto      TEXT,
  foto_alt  TEXT,
  ativo     INTEGER NOT NULL DEFAULT 1,
  ordem     INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS avaliacoes (
  id       INTEGER PRIMARY KEY,
  nome     TEXT NOT NULL,
  texto    TEXT NOT NULL,
  estrelas INTEGER NOT NULL DEFAULT 5,
  ativo    INTEGER NOT NULL DEFAULT 1,
  ordem    INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS videos (
  id      INTEGER PRIMARY KEY,
  arquivo TEXT NOT NULL,
  poster  TEXT,
  legenda TEXT,
  ativo   INTEGER NOT NULL DEFAULT 1,
  ordem   INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS faq (
  id       INTEGER PRIMARY KEY,
  pergunta TEXT NOT NULL,
  resposta TEXT NOT NULL,
  icone    TEXT NOT NULL DEFAULT 'relogio',
  contexto TEXT NOT NULL DEFAULT 'geral' CHECK (contexto IN ('geral','flex')),
  ativo    INTEGER NOT NULL DEFAULT 1,
  ordem    INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS passos (
  id       INTEGER PRIMARY KEY,
  contexto TEXT NOT NULL CHECK (contexto IN ('pronta','flex')),
  titulo   TEXT NOT NULL,
  texto    TEXT,
  imagem   TEXT,
  ativo    INTEGER NOT NULL DEFAULT 1,
  ordem    INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS blocos (
  chave  TEXT PRIMARY KEY,
  rotulo TEXT NOT NULL,
  valor  TEXT,
  tipo   TEXT NOT NULL DEFAULT 'texto' CHECK (tipo IN ('texto','texto_longo'))
);

CREATE TABLE IF NOT EXISTS config (
  chave TEXT PRIMARY KEY,
  valor TEXT
);

CREATE TABLE IF NOT EXISTS leads (
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
);

CREATE INDEX IF NOT EXISTS idx_modelos_lista   ON modelos (modalidade, ativo, ordem);
CREATE INDEX IF NOT EXISTS idx_faq_lista       ON faq (contexto, ativo, ordem);
CREATE INDEX IF NOT EXISTS idx_passos_lista    ON passos (contexto, ativo, ordem);
CREATE INDEX IF NOT EXISTS idx_leads_pendentes ON leads (crm_status, criado_em);
```

### 2.1 Chaves de `config` e seus valores iniciais

| Chave | Valor inicial | Para que serve |
|---|---|---|
| `videos_na_home` | `8` | Quantos vídeos a home mostra |
| `email_aviso` | `contato@castellomadeiras.com.br` | Destino do aviso de lead novo |
| `crm_ativo` | `0` | Liga o envio ao CRM |
| `crm_endpoint` | vazio | URL do CRM |
| `crm_metodo` | `POST` | Método HTTP |
| `crm_cabecalhos` | `{}` | JSON de cabeçalhos, ex: `{"Authorization":"Bearer xxx"}` |
| `crm_mapa_campos` | ver abaixo | JSON que liga campo do site a campo do CRM |

Valor inicial de `crm_mapa_campos`:

```json
{"nome":"nome","whatsapp":"telefone","busca":"interesse","modelo":"modelo","cidade":"cidade","mensagem":"observacao","utm_source":"origem","utm_campaign":"campanha","pagina":"pagina"}
```

### 2.2 Chaves de `blocos`

| Chave | Rótulo no painel | Tipo |
|---|---|---|
| `hero_titulo` | Título do topo | `texto` |
| `hero_subtitulo` | Subtítulo do topo | `texto` |
| `modalidades_titulo` | Título do bloco de modalidades | `texto` |
| `modalidades_texto` | Texto do bloco de modalidades | `texto_longo` |
| `pronta_titulo` | Título da seção Casa Pronta | `texto` |
| `pronta_texto` | Texto da seção Casa Pronta | `texto_longo` |
| `pronta_prazo` | Prazo da Casa Pronta | `texto` |
| `flex_titulo` | Título da seção Castelo Flex | `texto` |
| `flex_texto` | Texto da seção Castelo Flex | `texto_longo` |
| `flex_prazo` | Prazo da Castelo Flex | `texto` |
| `flex_video` | Vídeo explicativo da Flex | `texto` |
| `flex_video_poster` | Capa do vídeo da Flex | `texto` |

Valores iniciais: `pronta_prazo` = `90 a 120 dias`, `flex_prazo` = `45 dias`. Os demais saem do texto que já está no `index.html` atual, exceto os da Flex, que a frente 2 escreve.

### 2.3 Ícones válidos do FAQ

Conjunto fechado. O campo `faq.icone` só aceita estas chaves:

`relogio`, `chave`, `planta`, `clima`, `escudo`, `fundacao`, `garantia`

O SVG de cada uma sai do `index.html` atual, na seção `#faq`, na ordem em que as perguntas aparecem.

---

## 3. Caminhos de arquivo

```
config/castello.db              banco (fora do public_html)
config/segredos.php             segredos (fora do public_html)
public_html/uploads/modelos/    fotos de modelo
public_html/uploads/portfolio/  fotos de portfólio
public_html/uploads/videos/     vídeos e posters
public_html/uploads/passos/     imagens do passo a passo
```

O valor gravado nas colunas `foto`, `arquivo`, `poster` e `imagem` é **sempre relativo ao `public_html`**, começando por `uploads/`. Exemplo: `uploads/modelos/familia-a1b2c3.jpg`. Nunca caminho absoluto, nunca com barra no início.

### 3.1 Reorganização do repositório

O repositório passa a espelhar a hospedagem: `public_html/` é a raiz do site e `config/` fica ao lado dela, fora do alcance do navegador. A frente 1 faz essa mudança com `git mv`, preservando o histórico:

```
prototipo-site-castello/
  config/            castello.db, segredos.php   (ignorados pelo git)
  public_html/       index.php, css/, js/, images/, fotos-casas/, ...
  front/             marcação da frente 2, descartada na costura
  testes/
  docs/
```

`lib/db.php` define `CASTELLO_CONFIG` como `dirname(__DIR__, 2) . '/config'`, o que dá o mesmo caminho local e na EreHost.

**Consequência:** o site sai do GitHub Pages. Depois desta mudança, o `main` deixa de servir o site em `fabianohirtzz.github.io`, porque a raiz muda e o conteúdo vira PHP, que o Pages não executa. O ambiente de demonstração passa a ser o subdomínio de teste na EreHost. O `.gitignore` recebe `config/castello.db`, `config/segredos.php` e `public_html/uploads/`.

---

## 4. Assinaturas de `lib/`

Toda frente pode **chamar** estas funções. Só a frente dona pode **escrevê-las**.

### 4.1 `lib/db.php` (frente 1)

```php
function db(): PDO;                    // conexão única, cria o banco e aplica o schema se faltar
function e(?string $texto): string;    // htmlspecialchars com ENT_QUOTES | ENT_SUBSTITUTE, UTF-8
function agora(): string;              // data atual em 'Y-m-d H:i:s'
function config_ler(string $chave, ?string $padrao = null): ?string;
function config_gravar(string $chave, string $valor): void;
```

### 4.2 `lib/conteudo.php` (frente 1)

Toda função devolve `array` de linhas associativas, já filtradas por `ativo = 1` e ordenadas por `ordem ASC, id ASC`.

```php
function modelos(string $modalidade): array;   // 'pronta' ou 'flex'
function portfolio(): array;
function avaliacoes(): array;
function videos(?int $limite = null): array;   // null usa config.videos_na_home
function faq(string $contexto = 'geral'): array;
function passos(string $contexto): array;      // 'pronta' ou 'flex'
function bloco(string $chave, string $padrao = ''): string;
function icone_faq(string $chave): string;     // devolve o SVG do ícone; string vazia se a chave não existir
```

### 4.3 `lib/leads.php` (frente 3)

```php
function lead_gravar(array $dados): int;              // devolve o id do lead
function lead_marcar(int $id, string $status, int $tentativas, ?string $resposta): void;
function leads_pendentes(int $limite = 20): array;
function leads_reenviar(): array;                     // ['tentados'=>int,'enviados'=>int,'falhas'=>int]
```

### 4.4 `lib/crm.php` (frente 3)

```php
/**
 * @return array{ok: bool, http: int, resposta: string, erro: ?string}
 */
function crm_enviar(array $lead): array;
```

Quando `config.crm_ativo` é `0`, devolve `['ok'=>false,'http'=>0,'resposta'=>'','erro'=>'crm_desativado']` sem fazer requisição.

### 4.5 `lib/email.php` (frente 3)

```php
function email_lead_novo(array $lead, array $resultado_crm): bool;
```

### 4.6 `lib/upload.php` (frente 1)

```php
/**
 * @return array{ok: bool, caminho: ?string, erro: ?string}
 */
function upload_receber(array $arquivo, string $pasta, string $tipo): array;
```

`$pasta` é uma de `modelos`, `portfolio`, `videos`, `passos`. `$tipo` é `imagem` ou `video`.
Imagem aceita `image/jpeg`, `image/png`, `image/webp`, no máximo 5 MB.
Vídeo aceita `video/mp4`, no máximo 30 MB.
O tipo é conferido com `finfo_file`, nunca pela extensão. O arquivo é renomeado para `slug-do-nome-original` mais 6 caracteres aleatórios.

### 4.7 `lib/auth.php` (frente 1)

```php
function auth_iniciar(): void;                       // session_start com cookie seguro
function auth_logado(): bool;
function auth_exigir(): void;                        // redireciona para /painel/ se não logado
function auth_entrar(string $login, string $senha): bool;
function auth_sair(): void;
function auth_bloqueado(string $ip): bool;
function auth_registrar_falha(string $ip): void;
function csrf_token(): string;
function csrf_validar(?string $token): bool;
```

Trava de força bruta: 5 tentativas erradas bloqueiam o IP por 15 minutos.
Sessão expira com 2 horas de inatividade.

---

## 5. Assinatura dos partials (frente 1)

Cada partial é um arquivo PHP que **imprime HTML** e não devolve nada. As variáveis chegam por escopo, definidas logo antes do `include`.

```php
// partials/modelos.php     espera: string $modalidade
// partials/portfolio.php   espera: nada
// partials/avaliacoes.php  espera: nada
// partials/videos.php      espera: nada
// partials/faq.php         espera: string $contexto
// partials/passos.php      espera: string $contexto
```

Uso na página:

```php
<?php $modalidade = 'pronta'; include __DIR__ . '/partials/modelos.php'; ?>
```

O HTML impresso por cada partial tem que ser **idêntico** ao que está hoje no `index.html`, trocando só os valores. A frente 2 pode mudar essa marcação; quando mudar, a costura atualiza o partial.

---

## 6. Contrato do formulário

### 6.1 Campos enviados

| Campo | Origem | Obrigatório |
|---|---|---|
| `nome` | usuário | sim |
| `whatsapp` | usuário, com máscara | sim |
| `busca` | usuário, select | sim |
| `modelo` | usuário, select condicional | não |
| `cidade` | usuário | não |
| `mensagem` | usuário, textarea | não |
| `pagina` | JS, `location.pathname` | sim |
| `referrer` | JS, `document.referrer` | não |
| `utm_source` `utm_medium` `utm_campaign` `utm_term` `utm_content` | JS, da URL ou do `sessionStorage` | não |
| `empresa` | honeypot, tem que chegar vazio | — |
| `ts` | JS, `Date.now()` na abertura do formulário | sim |
| `csrf` | campo oculto impresso pelo PHP | sim |

### 6.2 Regras de recusa

- `empresa` preenchido: recusa em silêncio, respondendo sucesso falso para não ensinar o bot.
- Menos de 3 segundos entre `ts` e o envio: mesma recusa em silêncio.
- `csrf` inválido: HTTP 419 com `{"ok":false,"erro":"csrf"}`.
- `nome`, `whatsapp` ou `busca` vazios: HTTP 422 com `{"ok":false,"erro":"campos","campos":["nome"]}`.
- `whatsapp` com menos de 10 dígitos depois de tirar tudo que não é número: entra em `campos`.

### 6.3 Resposta do `enviar.php`

Sempre JSON, sempre com `Content-Type: application/json; charset=utf-8`.

```json
{"ok": true, "id": 42}
```

```json
{"ok": false, "erro": "campos", "campos": ["nome", "whatsapp"]}
```

O `ok: true` significa **lead gravado**, não lead entregue ao CRM. A entrega ao CRM nunca faz o visitante ver erro: se o CRM falhar, o lead está salvo e o e-mail foi disparado.

### 6.4 UTM no navegador

Na primeira visita, o JS lê os parâmetros `utm_*` da URL e grava em `sessionStorage` sob a chave `castello_utm`, como JSON. Toda abertura de formulário lê de lá. Parâmetro na URL sempre sobrescreve o que estava guardado.

---

## 7. Divisão de arquivos

Nenhuma frente escreve arquivo de outra.

| Frente | Escreve |
|---|---|
| 1 | `lib/db.php` `lib/conteudo.php` `lib/auth.php` `lib/upload.php` `lib/schema.sql` `partials/` `painel/` `index.php` `flex.php` `migrar.php` |
| 2 | `css/style.css` `js/main.js` `front/home.html` `front/flex.html` |
| 3 | `enviar.php` `lib/leads.php` `lib/crm.php` `lib/email.php` `testes/crm-falso.php` `js/formulario.js` |

A frente 2 **não** toca em `index.php` nem em `flex.php`. Entrega marcação estática em `front/`, que a costura converte.
A frente 3 **não** toca em `js/main.js`. O JS do formulário vive em `js/formulario.js`, carregado à parte.

---

## 8. Como rodar local

```bash
cd "E:/Clientes/Castello Madeiras/prototipo-site-castello"
php -S localhost:8000 -t public_html
```

Testes:

```bash
php testes/smoke.php
```
