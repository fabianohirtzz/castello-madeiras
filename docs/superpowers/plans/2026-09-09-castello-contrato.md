# Castello Fase 2 — Contrato compartilhado

> **Este documento é a fonte da verdade das três frentes.** Nenhuma frente inventa nome de tabela, coluna, função ou campo de formulário. Se algo aqui estiver errado ou faltando, pare e avise em vez de improvisar: mudar o contrato no meio quebra as outras frentes.

**Spec:** [2026-09-09-castello-fase2-design.md](../specs/2026-09-09-castello-fase2-design.md)

---

## 1. Ambiente e convenções

Dois ambientes, verificados por sondagem em 2026-09-09.

| | Local (desenvolvimento) | Servidor de teste |
|---|---|---|
| PHP | 8.3.32, CLI | **8.5.9**, LiteSpeed |
| SQLite | do PHP local | **3.26.0** |
| `ZipArchive` | **ausente** | presente |
| `mail()` | não entrega | presente |
| `upload_max_filesize` | padrão | 64M |
| `post_max_size` | padrão | 64M |
| `memory_limit` | padrão | 1G |
| `max_execution_time` | 0 | **60s** |
| ffmpeg | não | **não** |

- **Piso de compatibilidade: PHP 8.1.** Não use recurso exclusivo de 8.2 ou mais novo. O código roda em 8.3 local e 8.5 no servidor de teste, mas a hospedagem final do cliente é desconhecida e pode ser mais velha. Escrever para 8.1 é o que garante que a migração seja copiar a pasta.
- Sem framework, sem Composer, sem dependência externa.
- Extensões usadas: `pdo_sqlite`, `fileinfo`, `gd`, `curl`, `mbstring`, `session`.

### 1.1 SQLite 3.26 é velho e limita a sintaxe

O servidor tem SQLite **3.26.0**, de 2018. Estes recursos **não existem** lá e não podem ser usados:

- `INSERT ... RETURNING` (precisa de 3.35). Use `PDO::lastInsertId()`.
- `ALTER TABLE ... DROP COLUMN` (precisa de 3.35). Migração de schema é criar tabela nova e copiar.
- Tabelas `STRICT` (precisa de 3.37).
- `ALTER TABLE ... RENAME COLUMN` (precisa de 3.25, esse funciona).

`UPSERT` com `ON CONFLICT DO UPDATE` funciona (3.24+) e é o jeito de gravar em `config` e `blocos`.

Escrever contra 3.26 é a escolha segura: roda também em qualquer SQLite mais novo.

### 1.2 Consequências práticas

- **`ZipArchive` não existe no ambiente local** e `phar.readonly` está ligado, mas **existe no servidor**. Todo código que usa `ZipArchive` checa `class_exists('ZipArchive')` e devolve erro claro quando ausente, nunca um fatal. O teste local se marca como pulado; a validação do backup é feita no servidor.
- **`max_execution_time` é 60s.** O tempo limite do curl ao CRM é de **10 segundos**, nunca mais. Um CRM lento não pode segurar a resposta ao visitante.
- **Não há ffmpeg.** O painel não converte vídeo: recebe `.mp4` já otimizado e avisa o tamanho recomendado no upload.
- **`upload_max_filesize` é 64M**, então o limite de 30M para vídeo cabe com folga.
- **`mail()` não entrega na máquina local.** O modo de teste grava o e-mail em arquivo; a validação real é no servidor.
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
  id         INTEGER PRIMARY KEY,
  contexto   TEXT NOT NULL CHECK (contexto IN ('pronta','flex')),
  titulo     TEXT NOT NULL,
  texto      TEXT,
  imagem     TEXT,
  imagem_alt TEXT,
  ativo      INTEGER NOT NULL DEFAULT 1,
  ordem      INTEGER NOT NULL DEFAULT 0
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
| `crm_timeout` | `10` | Segundos de espera pelo CRM. Nunca acima de 10, porque `max_execution_time` é 60 |
| `reenvio_chave` | gerada na instalação | Chave que autoriza o reenvio de pendentes por URL |
| `email_dominio` | `castellomadeiras.com.br` | Domínio usado no remetente do aviso |

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
| `flexpg_hero_titulo` | Título do topo da página Flex | `texto` |
| `flexpg_hero_texto` | Texto do topo da página Flex | `texto_longo` |
| `flexpg_oque_titulo` | Título de "o que é a Castelo Flex" | `texto` |
| `flexpg_oque_texto` | Texto de "o que é a Castelo Flex" | `texto_longo` |
| `flexpg_depois_titulo` | Título de "o que fica por sua conta" | `texto` |
| `flexpg_depois_texto` | Texto de "o que fica por sua conta" | `texto_longo` |
| `flexpg_catalogo_nota` | Nota abaixo do catálogo Flex | `texto` |
| `flexpg_cta_titulo` | Título da faixa de orçamento da Flex | `texto` |
| `flexpg_cta_texto` | Texto da faixa de orçamento da Flex | `texto_longo` |

As nove chaves `flexpg_*` cobrem a página Flex inteira. Sem elas, metade da página ficaria fixa no PHP e o cliente não conseguiria editá-la pelo painel, o que contraria o motivo de o painel existir.

Valores iniciais: `pronta_prazo` = `90 a 120 dias`, `flex_prazo` = `45 dias`. Os demais saem do texto que já está no `index.html` atual, exceto os da Flex, que a frente 2 escreve.

### 2.3 Ícones válidos do FAQ

Conjunto fechado. O campo `faq.icone` só aceita estas chaves:

`relogio`, `chave`, `planta`, `clima`, `escudo`, `parede`, `fundacao`, `garantia`

O SVG de cada uma sai do `index.html` atual, na seção `#faq`, na ordem em que as perguntas aparecem.
O `parede` é o único desenhado depois, para a pergunta sobre madeira vertical e horizontal
que entrou em 2026-09-11.

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

### 3.2 Onde fica o `config/` em cada ambiente

Os dois ambientes têm profundidades diferentes, então o caminho não pode ser fixo. `lib/db.php` resolve `CASTELLO_CONFIG` nesta ordem, parando no primeiro que existir:

1. A variável de ambiente `CASTELLO_CONFIG`.
2. O arquivo `lib/caminho-config.php`, que devolve um caminho absoluto. Este arquivo é **ignorado pelo git** e existe só no servidor.
3. O padrão `dirname(__DIR__, 2) . '/config'`.

```php
// lib/caminho-config.php no servidor de teste
<?php
return '/home/freelain/domains/tohospedando.com.br/castello-config';
```

Local, o padrão já acerta: `<repo>/config`.

No servidor de teste, o caminho **precisa** ser esse absoluto. A raiz do subdomínio é `/home/freelain/domains/tohospedando.com.br/public_html/castello`, e a pasta acima dela, `public_html`, é a raiz pública do próprio `tohospedando.com.br`. Deixar o `config/` ali publicaria o banco em `https://tohospedando.com.br/config/castello.db`.

O usuário de FTP está preso ao `public_html` e **não consegue criar** a pasta `castello-config`, que fica um nível acima. Quem cria é o PHP: `db()` faz `mkdir` do diretório de configuração quando ele não existe, com permissão `0700`. A sondagem confirmou que o PHP tem permissão de escrita nesse nível.

Depois de criar, confirme que o banco não é alcançável pelo navegador. É a Task de verificação de segurança da frente 4.

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
| `csrf` | campo oculto, preenchido pelo JS a partir da metatag | sim |

**De onde o JS tira o token de CSRF.** De um endereço próprio, `public_html/csrf.php`, escrito pela **frente 1**:

```php
<?php
declare(strict_types=1);
require __DIR__ . '/lib/auth.php';
auth_iniciar();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
echo json_encode(['token' => csrf_token()]);
```

O `js/formulario.js` busca o token **quando o visitante abre o modal**, não no carregamento da página, e preenche o campo oculto. Se a busca falhar, o formulário mostra erro antes de deixar enviar, em vez de mandar e tomar 419.

**Por que não uma metatag no `<head>`.** Seria mais simples, mas o token é por sessão: uma página que carrega token no HTML não pode ser guardada em cache compartilhado, sob pena de o LiteSpeed ou um CDN entregar o token de um visitante para outro. Isso obrigaria `Cache-Control: private, no-store` em toda página do site. A Castello vai investir em tráfego pago, então cada página de destino ser incacheável é um custo permanente e direto no que eles estão pagando para trazer gente. Com o endereço à parte, as páginas continuam cacheáveis por inteiro, nenhum visitante recebe cookie de sessão só por ler o site, e o custo é uma requisição pequena, só para quem realmente abre o formulário.

Este é o acoplamento entre as frentes 1 e 3: sem o `csrf.php`, todo envio volta 419.

**Honeypot, nome de transição.** O contrato define `empresa`, mas o `index.html` de hoje usa `_gotcha`. Até a costura unificar, o `enviar.php` aceita os dois e recusa se qualquer um vier preenchido, e o JS envia os dois vazios. Depois da costura, só `empresa` permanece.

**Recusa silenciosa.** Quando o honeypot vem preenchido ou o time-trap dispara, a resposta é `{"ok":true,"id":0}`. Parece sucesso para o robô, e o `id` zero distingue do lead real para quem lê o log. Nada é gravado.

**Time-trap e o relógio do visitante.** O `ts` vem do navegador, então um relógio adiantado produz diferença negativa. Nesse caso o envio **passa**. Perder um lead real por causa do relógio de quem está comprando uma casa é pior do que aceitar um robô que já passou pelo honeypot.

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
| 1 | `lib/db.php` `lib/conteudo.php` `lib/auth.php` `lib/upload.php` `lib/schema.sql` `partials/` `painel/` `index.php` `flex.php` `csrf.php` `migrar.php` `testes/smoke.php` |
| 2 | `css/style.css` `js/main.js` `front/home.html` `front/flex.html` |
| 3 | `enviar.php` `reenviar.php` `lib/leads.php` `lib/crm.php` `lib/email.php` `testes/crm-falso.php` `js/formulario.js` |

A frente 2 **não** toca em `index.php` nem em `flex.php`. Entrega marcação estática em `front/`, que a costura converte.
A frente 3 **não** toca em `js/main.js`. O JS do formulário vive em `js/formulario.js`, carregado à parte.

### 7.0 Dono do runner de teste e formato dos casos

O `testes/smoke.php` é da **frente 1**, e roda cada caso num processo PHP separado, com pasta de configuração e de uploads temporárias próprias. Isso existe para que um teste não contamine o outro pelo banco.

Consequência para a frente 3: **não acrescente `require` no topo do `smoke.php`**. O runner não carrega `lib/` nenhuma; quem carrega é cada caso. A fusão correta é o `testes/smoke-f3.php` virar um arquivo de caso, `testes/casos/85-crm.php`, no formato que o runner da frente 1 espera. Está escrito assim na frente 4 para a costura não errar.

O `migrar.php` fica em `public_html/` e é da frente 1.

### 7.1 O formulário já existe no `js/main.js`, e isso é um cruzamento

O `js/main.js` de hoje já traz, entre as linhas 683 e 860, a abertura do modal, a máscara de WhatsApp, o honeypot, o time-trap e a constante `FORM_ENDPOINT`. A frente 3 escreve a versão nova em `js/formulario.js`, mas **nenhuma frente está autorizada a apagar o bloco antigo**: o arquivo é da frente 2, e a frente 2 não mexe em lógica de formulário.

Resolução: a **costura** faz a mudança, numa tarefa própria. Ela move o que continua valendo (abertura do modal, máscara, foco) e apaga o que a frente 3 substituiu (envio, honeypot, time-trap). Até a costura acontecer, as duas implementações coexistem no repositório sem se encontrar, porque `js/formulario.js` só é carregado nas páginas que a costura monta.

O honeypot muda de nome: era `_gotcha` no `main.js`, passa a ser `empresa`. `_gotcha` é nome conhecido de biblioteca e bot moderno reconhece. A checagem no `main.js` antigo vira letra morta assim que o campo muda, o que é mais um motivo para a costura apagar aquele bloco. A defesa que vale é a do servidor, no `enviar.php`.

### 7.2 O site tem um formulário só, em modal

Não existe formulário embutido em página. Todos os CTAs, na home e na página Flex, abrem o mesmo `#quoteModal` por `data-quote-open`. Onde a spec fala em "formulário" numa seção, leia "faixa de chamada que abre o modal". Um segundo `<form>` inline duplicaria `id`, laço de foco e contrato de envio.

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
