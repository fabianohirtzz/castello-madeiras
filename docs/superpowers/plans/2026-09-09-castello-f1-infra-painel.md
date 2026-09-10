# Castello Fase 2 — Frente 1: Infra e Painel — Plano de Implementação

> **Para agentes executores:** SUB-SKILL OBRIGATÓRIA: use `superpowers:subagent-driven-development` (recomendado) ou `superpowers:executing-plans` para implementar este plano tarefa a tarefa. Os passos usam caixinhas (`- [ ]`) para acompanhamento.

**Objetivo:** transformar o protótipo estático da Castello em um site PHP + SQLite servido de `public_html/`, com todo o conteúdo real já migrado para o banco e um painel em `/painel` onde o cliente cadastra, edita, desativa e reordena esse conteúdo sozinho.

**Arquitetura:** PHP sem framework e sem Composer, renderizando no servidor. O banco é um único arquivo SQLite fora do `public_html`. As seções dinâmicas da home viram parciais que imprimem exatamente o mesmo HTML de hoje, lendo do banco. O painel não tem seis telas copiadas: tem uma tela de lista e uma tela de formulário genéricas, dirigidas por uma descrição declarativa das tabelas em `painel/tabelas.php`.

**Stack:** PHP escrito para o piso 8.1, `pdo_sqlite`, `fileinfo`, `gd`, `curl`, `mbstring`, `session`. SQLite em modo WAL, com sintaxe compatível com a 3.26. HTML, CSS e JS existentes preservados sem alteração. Testes por um runner próprio em `testes/smoke.php`, sem PHPUnit.

**Spec:** [`docs/superpowers/specs/2026-09-09-castello-fase2-design.md`](../specs/2026-09-09-castello-fase2-design.md)

**Contrato (fonte da verdade):** [`docs/superpowers/plans/2026-09-09-castello-contrato.md`](2026-09-09-castello-contrato.md)

---

## Restrições globais

Todo requisito abaixo vale para todas as tarefas. Valores copiados literalmente do contrato.

### Os dois ambientes

| | Local (desenvolvimento) | Servidor de teste |
|---|---|---|
| PHP | 8.3.32, CLI | 8.5.9, LiteSpeed |
| SQLite | do PHP local | **3.26.0** |
| `ZipArchive` | **ausente** | presente |
| `mail()` | não entrega | presente |
| `upload_max_filesize` | padrão | 64M |
| `post_max_size` | padrão | 64M |
| `memory_limit` | padrão | 1G |
| `max_execution_time` | 0 | **60s** |
| ffmpeg | não | não |

- **Piso de compatibilidade: PHP 8.1.** Nada exclusivo de 8.2 ou mais novo. Nada de `json_validate()`, constante de classe tipada, classe `readonly`, tipo DNF ou `#[\Override]`. `match`, `str_contains`, `str_starts_with`, `str_ends_with`, `static fn` e desestruturação em `foreach` são permitidos, todos existem desde a 8.0.
- **SQLite 3.26 no servidor.** Proibido: `INSERT ... RETURNING` (use `PDO::lastInsertId()`), `ALTER TABLE ... DROP COLUMN`, tabelas `STRICT`. Permitido e usado: `UPSERT` com `ON CONFLICT DO UPDATE`, `INSERT OR IGNORE`, `COALESCE`.
- `ZipArchive` não existe localmente e `phar.readonly` está ligado, mas existe no servidor. Todo código que usa `ZipArchive` checa `class_exists('ZipArchive')` e devolve erro claro, nunca fatal. O teste local se marca como **pulado**, dizendo o motivo; a validação do backup é no servidor.
- Não há ffmpeg. O painel não converte vídeo: recebe `.mp4` já otimizado e avisa o tamanho recomendado.
- Sem framework, sem Composer, sem dependência externa.
- Todo arquivo PHP começa com a tag de abertura do PHP e **não** termina com tag de fechamento. Arquivos que alternam PHP e HTML (parciais, telas do painel, `index.php`, `flex.php`) fecham a tag para sair do modo PHP, mas o arquivo nunca termina com a tag de fechamento.
- `declare(strict_types=1);` em todo arquivo de `lib/`, em `migrar.php`, nos parciais e nos arquivos do painel.
- Codificação UTF-8 sem BOM. Fuso `America/Sao_Paulo`. Datas gravadas em `Y-m-d H:i:s`.
- Nomes de função, tabela e coluna em português, sem acento, `snake_case`.
- Toda saída de dado do banco no HTML passa por `e()`. Sem exceção. A única saída de HTML cru permitida é `icone_faq()`, que devolve SVG de uma lista fechada escrita no código.
- Copy do site segue o tom da Freela: sem travessões, sem emojis, números concretos.
- Não existe PHPUnit. O ciclo de teste é `php testes/smoke.php`.
- Caminhos gravados em `foto`, `arquivo`, `poster` e `imagem` são **sempre relativos ao `public_html`**, começando por `uploads/`. Nunca absolutos, nunca com barra no início.
- Ícones válidos de FAQ, conjunto fechado de sete: `relogio`, `chave`, `planta`, `clima`, `escudo`, `fundacao`, `garantia`.
- Trava de força bruta: 5 tentativas erradas bloqueiam o IP por 15 minutos. Sessão expira com 2 horas de inatividade.
- Upload: imagem `image/jpeg`, `image/png`, `image/webp`, máximo 5 MB. Vídeo `video/mp4`, máximo 30 MB. Tipo conferido com `finfo_file`, nunca pela extensão. Arquivo renomeado para `slug-do-nome-original` mais 6 caracteres aleatórios.
- **O `csrf.php` é obrigatório.** O token do formulário sai de um endereço próprio, `public_html/csrf.php`, que a Frente 3 busca quando o visitante abre o modal. Sem ele, todo envio volta 419 e o site não capta um lead. É a Tarefa 9. **Nenhuma página imprime o token no HTML**: token no `<head>` obrigaria `Cache-Control: private, no-store` no site inteiro, e a Castello vai investir em tráfego pago, então página de destino incacheável é custo permanente em cima do que eles pagam para trazer gente. `index.php` e `flex.php` continuam cacheáveis por inteiro e o visitante não recebe cookie de sessão só por ler o site.
- Toda mensagem de commit é em português e termina com a linha:
  `Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>`

### Divisão de arquivos

A Frente 1 escreve: `public_html/lib/db.php`, `lib/conteudo.php`, `lib/auth.php`, `lib/upload.php`, `lib/schema.sql`, `lib/caminho-config.php` (só no servidor, fora do git), `partials/`, `painel/`, `index.php`, `flex.php`, `migrar.php`, os `.htaccess`, e `testes/` menos `testes/crm-falso.php`.

**Fora de escopo desta frente, não escrever:** `enviar.php`, `reenviar.php`, `lib/leads.php`, `lib/crm.php`, `lib/email.php`, `js/formulario.js`, `testes/crm-falso.php`, `css/style.css`, `js/main.js`, `front/home.html`, `front/flex.html`.

A Frente 2 vai renomear a seção `#modelos` da home para `#casa-pronta` e criar `#modalidades` e `#flex`. Os parciais desta frente não mudam por causa disso: quem transplanta a marcação nova é a costura.

---

## Mapa de arquivos

Depois da Tarefa 1 o repositório fica assim. A coluna da direita diz em que tarefa cada arquivo nasce.

```
prototipo-site-castello/
  config/
    segredos.php.exemplo         T1   modelo versionado dos segredos
    segredos.php                 -    gerado na instalacao, ignorado pelo git
    castello.db                  -    gerado por migrar.php, ignorado pelo git
  public_html/
    index.php                    T6   home (conversao do index.html)
    flex.php                     T6   esqueleto da pagina Castelo Flex
    csrf.php                     T9   entrega o token do formulario em JSON
    migrar.php                   T4   carrega o conteudo real no banco
    .htaccess                    T15  HTTPS, DirectoryIndex, bloqueios
    lib/
      schema.sql                 T2   schema do contrato, copia literal
      db.php                     T2   db(), e(), agora(), config_ler(), config_gravar()
      caminho-config.php         T16  so no servidor, fora do git
      conteudo.php               T3   leitura das secoes
      auth.php                   T7   sessao, login, forca bruta, CSRF
      upload.php                 T8   validacao e gravacao de midia
    partials/
      modelos.php                T5   espera $modalidade
      portfolio.php              T5
      avaliacoes.php             T5
      videos.php                 T5
      faq.php                    T5   espera $contexto
      passos.php                 T5   espera $contexto
      nav.php                    T6   nav e drawer, compartilhados home/flex
      rodape.php                 T6   rodape, compartilhado home/flex
      modal.php                  T6   modal de orcamento, compartilhado
    uploads/
      modelos/ portfolio/ videos/ passos/    T4  populadas por migrar.php
      .htaccess                  T15  proibe execucao de script
    painel/
      index.php                  T10  login
      painel.php                 T10  shell, menu e roteamento das telas
      sair.php                   T10  encerra a sessao
      tabelas.php                T10  descricao declarativa + logica do CRUD
      telas/
        lista.php                T10  lista generica de qualquer tabela
        form.php                 T11  formulario generico de qualquer tabela
        textos.php               T13  tabela blocos
        config.php               T13  tabela config
        backup.php               T14  tela de backup
        senha.php                T14  trocar senha
      acoes/
        salvar.php               T11
        estado.php               T12  desativar e reativar
        ordem.php                T12  reordenar arrastando
        textos.php               T13
        config.php               T13
        backup.php               T14
        senha.php                T14
      assets/
        painel.css               T10
        painel.js                T12  arrastar para reordenar
      .htaccess                  T15  HTTP Basic opcional, pronto para ativar
    css/ js/ images/ fotos-casas/ videos-instagram/ passos/ video-hero/   T1 (git mv)
  testes/
    smoke.php                    T1   runner, roda cada caso em processo separado
    executar-caso.php            T1   ambiente isolado de um caso
    assertivas.php               T1   teste(), igual(), verdade(), contem(), pular()
    base/                        T5, T6  fragmentos e home de referencia
    casos/
      00-runner.php              T1
      10-db.php                  T2
      20-migracao.php            T4
      30-conteudo.php            T3
      40-partials.php            T5
      50-paginas.php             T6, T9
      60-auth.php                T7
      70-upload.php              T8
      80-painel.php              T10, T11, T12, T13
      90-backup.php              T14
      95-htaccess.php            T15
  docs/
  README.md  hdr-top.png  preview-mountain-vista.html   (ficam na raiz, fora do site)
```

**Por que os parciais `nav.php`, `rodape.php` e `modal.php` existem.** A spec, na seção 7.2, exige que a página Flex compartilhe nav e rodapé com a home. O contrato lista só os seis parciais de conteúdo. Como `partials/` é pasta da Frente 1 e nenhuma outra frente chama esses três arquivos, extrair nav, rodapé e modal para lá não quebra o contrato e evita duplicar cem linhas em `flex.php`. O HTML impresso continua idêntico ao de hoje.

---

## Como o runner de testes funciona

`php testes/smoke.php` procura `testes/casos/*.php`, ordena por nome e roda **cada caso em um processo PHP separado**. Cada processo recebe um diretório temporário próprio para `config/` e para `uploads/`, então nenhum caso enxerga o banco do outro e nenhum caso suja o banco real de desenvolvimento. O runner imprime a saída de cada caso, soma passou/falhou/pulou e sai com código diferente de zero se houver qualquer falha.

Dentro de um caso, `banco_com_conteudo()` roda o `migrar()` de verdade no banco temporário, então os casos que precisam do conteúdo real o têm sem depender da ordem de execução.

**Como a Frente 3 entra nesta suíte.** O plano da Frente 3 prevê, na Tarefa 9 dela, fundir o `testes/smoke-f3.php` dentro de `testes/smoke.php`, acrescentando `require` no topo do arquivo. Isso **não** funciona com este runner: `smoke.php` aqui não carrega `lib/` nenhuma, ele despacha processos. A fusão correta é outra, e é mais simples: o conteúdo do `smoke-f3.php` vira `testes/casos/85-crm.php`, que o runner encontra sozinho e roda em processo isolado, com banco e uploads próprios, sem precisar de `testes/apoio-f1.php`. Quem faz essa fusão é a costura; este parágrafo existe para que ela não seja feita da forma que quebra os dois lados.

---

## Tarefa 1: Reorganizar o repositório e criar o runner de smoke tests

**Arquivos:**
- Mover (`git mv`): `css/`, `images/`, `js/`, `fotos-casas/`, `passos/`, `video-hero/`, `videos-instagram/`, `index.html` para dentro de `public_html/`
- Modificar: `.gitignore`
- Criar: `config/segredos.php.exemplo`
- Criar: `testes/assertivas.php`, `testes/executar-caso.php`, `testes/smoke.php`
- Teste: `testes/casos/00-runner.php`

**Interfaces:**
- Consome: nada, é a primeira tarefa.
- Produz: `teste(string $nome, callable $corpo): void`, `igual($esperado, $obtido, string $msg = ''): void`, `verdade($valor, string $msg = ''): void`, `falso($valor, string $msg = ''): void`, `contem(string $agulha, string $palheiro, string $msg = ''): void`, `nao_contem(string $agulha, string $palheiro, string $msg = ''): void`, `pular(string $motivo): void`, `smoke_encerrar(): void`, `raiz(): string`, `site(): string`, `norm(string $html): string`, `norm_sem_alt(string $html): string`, `render(string $arquivo, array $vars = []): string`, `banco_com_conteudo(): array`. As constantes `CASTELLO_CONFIG` e `CASTELLO_UPLOADS` passam a existir já definidas dentro de qualquer caso de teste.

- [ ] **Passo 1: Mover os arquivos do site para `public_html/`**

```bash
cd "E:/Clientes/Castello Madeiras/prototipo-site-castello"
mkdir -p public_html config testes/casos testes/base
git mv css public_html/css
git mv images public_html/images
git mv js public_html/js
git mv fotos-casas public_html/fotos-casas
git mv passos public_html/passos
git mv video-hero public_html/video-hero
git mv videos-instagram public_html/videos-instagram
git mv index.html public_html/index.html
git status --short
```

`README.md`, `hdr-top.png`, `preview-mountain-vista.html` e `docs/` ficam na raiz de propósito: não fazem parte do site publicado.

- [ ] **Passo 2: Ajustar o `.gitignore`**

Substitua o conteúdo de `.gitignore` por:

```gitignore
# OS / editor
Thumbs.db
.DS_Store
desktop.ini

# QA artifacts
.playwright-mcp/
qa-*.png
qa-*.jpeg

# Brainstorm companion (efêmero)
.superpowers/

# Notas internas (não publicar)
CLAUDE.md
avaliacoes/

# Backup do video original do hero (pre all-intra)
public_html/video-hero/video-hero.original.mp4

# Backups de vídeos brutos (pre otimização web)
public_html/videos-instagram/web/*.original.mp4

# Fase 2: banco, segredos e mídia enviada pelo painel
config/castello.db
config/castello.db-wal
config/castello.db-shm
config/segredos.php
public_html/uploads/

# Caminho absoluto do config no servidor. Existe só lá, nunca no git.
public_html/lib/caminho-config.php
```

- [ ] **Passo 3: Criar o modelo de segredos**

Crie `config/segredos.php.exemplo`:

```php
<?php
// Copie este arquivo para config/segredos.php e preencha os valores.
// config/segredos.php nunca vai para o git.

// Chave que libera migrar.php pela web, para hospedagem sem acesso a linha
// de comando. Vazia significa acesso web bloqueado, que e o padrao seguro.
define('CASTELLO_MIGRAR_CHAVE', '');

// Credenciais do CRM e do envio de e-mail entram aqui quando a Frente 3
// precisar. Ate la, este arquivo so carrega a chave acima.
```

- [ ] **Passo 4: Escrever o caso de teste do próprio runner**

Crie `testes/casos/00-runner.php`:

```php
<?php
declare(strict_types=1);

teste('igual aceita valores identicos', function (): void {
    igual(3, 1 + 2);
    igual('abc', 'a' . 'bc');
});

teste('igual recusa tipos diferentes', function (): void {
    $pegou = false;
    try {
        igual(3, '3');
    } catch (Throwable $t) {
        $pegou = true;
    }
    verdade($pegou, 'igual deveria recusar o inteiro 3 contra a string "3"');
});

teste('contem encontra pedaco de string', function (): void {
    contem('bola', 'a bola rolou');
});

teste('nao_contem recusa quando o pedaco existe', function (): void {
    $pegou = false;
    try {
        nao_contem('bola', 'a bola rolou');
    } catch (Throwable $t) {
        $pegou = true;
    }
    verdade($pegou, 'nao_contem deveria falhar quando a agulha existe');
});

teste('norm colapsa espaco entre tags', function (): void {
    igual('<a><b>x</b></a>', norm("<a>\n   <b>x</b>\n</a>"));
});

teste('norm reescreve caminho de uploads para o caminho original', function (): void {
    igual('<img src="fotos-casas/casa1.png">', norm('<img src="uploads/modelos/casa1.png">'));
    igual('<img src="fotos-casas/casa3.png">', norm('<img src="uploads/portfolio/casa3.png">'));
    igual('<source src="videos-instagram/web/insta-01.mp4">', norm('<source src="uploads/videos/insta-01.mp4">'));
    igual('<img src="passos/passo-1.jpg">', norm('<img src="uploads/passos/passo-1.jpg">'));
});

teste('norm_sem_alt remove o atributo alt', function (): void {
    igual('<img src="a.png">', norm_sem_alt('<img src="a.png" alt="qualquer coisa">'));
});

teste('pular marca o caso como pulado sem quebrar a suite', function (): void {
    pular('exemplo proposital, so para provar o mecanismo de pular');
});

teste('o caso roda em pastas temporarias isoladas', function (): void {
    verdade(defined('CASTELLO_CONFIG'), 'CASTELLO_CONFIG precisa estar definida');
    verdade(defined('CASTELLO_UPLOADS'), 'CASTELLO_UPLOADS precisa estar definida');
    verdade(is_dir(CASTELLO_CONFIG), 'a pasta de config temporaria precisa existir');
    verdade(is_dir(CASTELLO_UPLOADS), 'a pasta de uploads temporaria precisa existir');
    nao_contem('prototipo-site-castello', CASTELLO_CONFIG, 'o caso nao pode escrever no config real do projeto');
});

teste('raiz e site apontam para as pastas certas', function (): void {
    verdade(is_file(raiz() . '/testes/smoke.php'), 'raiz() deve conter testes/smoke.php');
    verdade(is_dir(site() . '/css'), 'site() deve conter a pasta css');
});
```

- [ ] **Passo 5: Rodar e ver falhar**

Rode: `php testes/smoke.php`
Esperado: falha, porque `testes/smoke.php` ainda não existe. O PHP responde `Could not open input file: testes/smoke.php`.

- [ ] **Passo 6: Escrever as assertivas**

Crie `testes/assertivas.php`:

```php
<?php
declare(strict_types=1);

/**
 * Assertivas do smoke test da Castello. Sem framework: um caso e um arquivo
 * PHP que chama teste() varias vezes. O processo do caso sai com codigo 1
 * assim que qualquer teste falhar.
 */

$GLOBALS['smoke'] = ['passou' => 0, 'falhou' => 0, 'pulou' => 0];

final class SmokeFalha extends RuntimeException {}
final class SmokePulado extends RuntimeException {}

function teste(string $nome, callable $corpo): void
{
    try {
        $corpo();
        $GLOBALS['smoke']['passou']++;
        echo "  ok    $nome\n";
    } catch (SmokePulado $p) {
        $GLOBALS['smoke']['pulou']++;
        echo "  pula  $nome  (" . $p->getMessage() . ")\n";
    } catch (Throwable $t) {
        $GLOBALS['smoke']['falhou']++;
        echo "  FALHA $nome\n";
        echo '        ' . $t->getMessage() . "\n";
        echo '        ' . $t->getFile() . ':' . $t->getLine() . "\n";
    }
}

function igual($esperado, $obtido, string $msg = ''): void
{
    if ($esperado !== $obtido) {
        throw new SmokeFalha(($msg !== '' ? $msg . ' | ' : '')
            . 'esperado ' . smoke_mostrar($esperado) . ', obtido ' . smoke_mostrar($obtido));
    }
}

function verdade($valor, string $msg = 'esperava verdadeiro'): void
{
    if ($valor !== true) {
        throw new SmokeFalha($msg . ' | obtido ' . smoke_mostrar($valor));
    }
}

function falso($valor, string $msg = 'esperava falso'): void
{
    if ($valor !== false) {
        throw new SmokeFalha($msg . ' | obtido ' . smoke_mostrar($valor));
    }
}

function contem(string $agulha, string $palheiro, string $msg = ''): void
{
    if (!str_contains($palheiro, $agulha)) {
        throw new SmokeFalha(($msg !== '' ? $msg . ' | ' : '')
            . 'nao encontrou ' . smoke_mostrar($agulha)
            . ' dentro de ' . smoke_mostrar(mb_substr($palheiro, 0, 400)));
    }
}

function nao_contem(string $agulha, string $palheiro, string $msg = ''): void
{
    if (str_contains($palheiro, $agulha)) {
        throw new SmokeFalha(($msg !== '' ? $msg . ' | ' : '')
            . 'encontrou ' . smoke_mostrar($agulha) . ' e nao deveria');
    }
}

function pular(string $motivo): void
{
    throw new SmokePulado($motivo);
}

function smoke_mostrar($valor): string
{
    if (is_string($valor)) return '"' . $valor . '"';
    if (is_bool($valor)) return $valor ? 'true' : 'false';
    if ($valor === null) return 'null';
    if (is_array($valor)) return 'array(' . count($valor) . ')';
    if (is_object($valor)) return get_class($valor);
    return (string) $valor;
}

function smoke_encerrar(): void
{
    $s = $GLOBALS['smoke'];
    echo sprintf("  -- %d ok, %d falha, %d pulado\n", $s['passou'], $s['falhou'], $s['pulou']);
    exit($s['falhou'] > 0 ? 1 : 0);
}

/** Raiz do repositorio. */
function raiz(): string
{
    return dirname(__DIR__);
}

/** Raiz publica do site. */
function site(): string
{
    return raiz() . '/public_html';
}

/**
 * Roda a migracao de conteudo real no banco temporario do caso.
 * Devolve o mapa de contagens que migrar() produz.
 */
function banco_com_conteudo(): array
{
    require_once site() . '/migrar.php';
    return migrar();
}

/**
 * Normaliza HTML para comparacao: colapsa espaco em branco e traz os
 * caminhos de uploads de volta para os caminhos originais do prototipo,
 * que e o unico ponto em que a saida legitimamente difere do index.html.
 */
function norm(string $html): string
{
    $html = strtr($html, [
        'uploads/modelos/'   => 'fotos-casas/',
        'uploads/portfolio/' => 'fotos-casas/',
        'uploads/videos/'    => 'videos-instagram/web/',
        'uploads/passos/'    => 'passos/',
    ]);
    $html = (string) preg_replace('/>\s+</u', '><', $html);
    $html = (string) preg_replace('/\s+/u', ' ', $html);
    return trim($html);
}

/** Igual a norm(), mas tambem apaga os atributos alt. */
function norm_sem_alt(string $html): string
{
    return (string) preg_replace('/ alt="[^"]*"/u', '', norm($html));
}

/** Inclui um arquivo PHP com variaveis em escopo e devolve o que ele imprimiu. */
function render(string $arquivo, array $vars = []): string
{
    extract($vars, EXTR_SKIP);
    ob_start();
    include $arquivo;
    return (string) ob_get_clean();
}
```

- [ ] **Passo 7: Escrever o executor de um caso**

Crie `testes/executar-caso.php`:

```php
<?php
declare(strict_types=1);

/**
 * Roda um unico arquivo de caso em um ambiente isolado: pasta de config e
 * pasta de uploads proprias, apagadas ao final. Chamado por smoke.php.
 */

$arquivo = $argv[1] ?? '';
if ($arquivo === '' || !is_file($arquivo)) {
    fwrite(STDERR, "uso: php testes/executar-caso.php <arquivo-do-caso>\n");
    exit(2);
}

$temp = rtrim(sys_get_temp_dir(), "/\\") . '/castello-smoke-' . getmypid() . '-' . bin2hex(random_bytes(4));
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
require $arquivo;
smoke_encerrar();
```

- [ ] **Passo 8: Escrever o runner**

Crie `testes/smoke.php`:

```php
<?php
declare(strict_types=1);

/**
 * Runner do smoke test da Castello.
 *
 *   php testes/smoke.php            roda todos os casos
 *   php testes/smoke.php 40         roda so os casos cujo nome contem "40"
 *
 * Cada caso roda em um processo PHP separado, com banco e uploads proprios.
 * Sai com codigo 1 se qualquer caso falhar.
 */

$filtro = $argv[1] ?? '';
$casos  = glob(__DIR__ . '/casos/*.php') ?: [];
sort($casos);

if ($filtro !== '') {
    $casos = array_values(array_filter(
        $casos,
        static fn (string $c): bool => str_contains(basename($c), $filtro)
    ));
}

if ($casos === []) {
    fwrite(STDERR, "nenhum caso encontrado em testes/casos\n");
    exit(1);
}

$falharam = [];

foreach ($casos as $caso) {
    echo basename($caso) . "\n";

    $linha = escapeshellarg(PHP_BINARY)
        . ' ' . escapeshellarg(__DIR__ . '/executar-caso.php')
        . ' ' . escapeshellarg($caso);

    $proc = proc_open($linha, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $canos);
    if (!is_resource($proc)) {
        echo "  FALHA nao consegui abrir o processo do caso\n";
        $falharam[] = basename($caso);
        continue;
    }

    $saida = (string) stream_get_contents($canos[1]);
    $erro  = (string) stream_get_contents($canos[2]);
    fclose($canos[1]);
    fclose($canos[2]);
    $codigo = proc_close($proc);

    echo $saida;
    if (trim($erro) !== '') {
        echo '  stderr: ' . trim($erro) . "\n";
    }
    if ($codigo !== 0) {
        $falharam[] = basename($caso);
    }
}

echo "\n";
if ($falharam !== []) {
    echo 'FALHOU: ' . implode(', ', $falharam) . "\n";
    exit(1);
}
echo "todos os casos passaram\n";
exit(0);
```

- [ ] **Passo 9: Rodar e ver passar**

Rode: `php testes/smoke.php`
Esperado: `00-runner.php` com 9 ok, 0 falha, 1 pulado, e a linha final `todos os casos passaram`. Confirme também que o código de saída é zero.

- [ ] **Passo 10: Commit**

```bash
git add -A
git commit -m "chore: reorganiza o repositorio em public_html e cria o runner de smoke tests

O repositorio passa a espelhar a hospedagem: public_html/ vira a raiz do
site e config/ fica ao lado, fora do alcance do navegador. Consequencia
esperada e ja acordada: o site sai do GitHub Pages, e o ambiente de
demonstracao passa a ser o subdominio de teste da EreHost.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Tarefa 2: `lib/schema.sql` e `lib/db.php`

**Arquivos:**
- Criar: `public_html/lib/schema.sql`
- Criar: `public_html/lib/db.php`
- Teste: `testes/casos/10-db.php`

**Interfaces:**
- Consome: `teste()`, `igual()`, `verdade()`, `falso()`, `contem()` da Tarefa 1; a constante `CASTELLO_CONFIG` já definida pelo executor de casos.
- Produz: `db(): PDO`, `e(?string $texto): string`, `agora(): string`, `config_ler(string $chave, ?string $padrao = null): ?string`, `config_gravar(string $chave, string $valor): void`, `castello_caminho_config(): string`, e a constante `CASTELLO_CONFIG`.

**Onde fica o `config/`.** Os dois ambientes têm profundidades diferentes, então o caminho não pode ser fixo. `castello_caminho_config()` resolve nesta ordem, parando no primeiro que existir:

1. A constante `CASTELLO_CONFIG`, quando alguém já a definiu (é o que o executor de casos faz).
2. A variável de ambiente `CASTELLO_CONFIG`.
3. O arquivo `lib/caminho-config.php`, que devolve um caminho absoluto. É ignorado pelo git e existe só no servidor.
4. O padrão `dirname(__DIR__, 2) . '/config'`, que já acerta no ambiente local.

No servidor de teste a raiz do subdomínio é `/home/freelain/domains/tohospedando.com.br/public_html/castello`, e a pasta acima é a raiz pública do próprio `tohospedando.com.br`. Por isso o config vai para `/home/freelain/domains/tohospedando.com.br/castello-config`. O usuário de FTP está preso ao `public_html` e não consegue criar essa pasta: quem cria é o PHP, dentro de `db()`, com `mkdir` e permissão **0700**.

- [ ] **Passo 1: Escrever o teste que falha**

Crie `testes/casos/10-db.php`:

```php
<?php
declare(strict_types=1);

require_once site() . '/lib/db.php';

teste('db() cria o banco e aplica o schema inteiro', function (): void {
    $tabelas = db()->query(
        "SELECT name FROM sqlite_master WHERE type = 'table' ORDER BY name"
    )->fetchAll(PDO::FETCH_COLUMN);

    foreach (['avaliacoes', 'blocos', 'config', 'faq', 'leads', 'login_tentativas',
              'modelos', 'passos', 'portfolio', 'usuarios', 'videos'] as $tabela) {
        verdade(in_array($tabela, $tabelas, true), "faltou a tabela $tabela");
    }
    verdade(is_file(CASTELLO_CONFIG . '/castello.db'), 'o arquivo do banco precisa existir');
});

teste('o banco abre em modo WAL', function (): void {
    igual('wal', strtolower((string) db()->query('PRAGMA journal_mode')->fetchColumn()));
});

teste('db() devolve sempre a mesma conexao', function (): void {
    verdade(db() === db(), 'db() nao pode abrir uma conexao nova a cada chamada');
});

teste('os indices do contrato existem', function (): void {
    $indices = db()->query(
        "SELECT name FROM sqlite_master WHERE type = 'index' ORDER BY name"
    )->fetchAll(PDO::FETCH_COLUMN);

    foreach (['idx_modelos_lista', 'idx_faq_lista', 'idx_passos_lista', 'idx_leads_pendentes'] as $ix) {
        verdade(in_array($ix, $indices, true), "faltou o indice $ix");
    }
});

teste('o CHECK de modalidade recusa valor fora da lista', function (): void {
    $pegou = false;
    try {
        db()->prepare('INSERT INTO modelos (modalidade, nome) VALUES (?, ?)')
            ->execute(['inventada', 'Teste']);
    } catch (PDOException $ex) {
        $pegou = true;
    }
    verdade($pegou, 'modalidade fora de pronta/flex tinha que ser recusada');
});

teste('e() escapa aspas, sinal de maior e e comercial', function (): void {
    igual('&lt;b&gt;', e('<b>'));
    igual('&quot;a&quot;', e('"a"'));
    igual('&#039;a&#039;', e("'a'"));
    igual('Casa &amp; Cia', e('Casa & Cia'));
    igual('', e(null));
    igual('45 m²', e('45 m²'), 'acentos e simbolos UTF-8 passam intactos');
});

teste('agora() devolve data no formato do contrato', function (): void {
    verdade((bool) preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', agora()));
    igual('America/Sao_Paulo', date_default_timezone_get());
});

teste('as dez chaves iniciais de config nascem com os valores do contrato', function (): void {
    igual('8', config_ler('videos_na_home'));
    igual('contato@castellomadeiras.com.br', config_ler('email_aviso'));
    igual('0', config_ler('crm_ativo'));
    igual('', config_ler('crm_endpoint'));
    igual('POST', config_ler('crm_metodo'));
    igual('{}', config_ler('crm_cabecalhos'));
    igual('10', config_ler('crm_timeout'));
    igual('castellomadeiras.com.br', config_ler('email_dominio'));

    $mapa = json_decode((string) config_ler('crm_mapa_campos'), true);
    igual('telefone', $mapa['whatsapp']);
    igual('observacao', $mapa['mensagem']);
    igual('origem', $mapa['utm_source']);

    igual(10, (int) db()->query('SELECT COUNT(*) FROM config')->fetchColumn());
});

teste('reenvio_chave e gerada na instalacao e nao muda depois', function (): void {
    $chave = (string) config_ler('reenvio_chave');
    verdade(strlen($chave) >= 32, 'a chave de reenvio precisa ser longa, obtida: ' . $chave);
    verdade((bool) preg_match('/^[0-9a-f]+$/', $chave), 'a chave e hexadecimal');
    igual($chave, (string) config_ler('reenvio_chave'), 'a chave nao pode ser regerada a cada leitura');
});

teste('o UPSERT do SQLite 3.26 funciona, que e como config e blocos gravam', function (): void {
    db()->prepare(
        'INSERT INTO config (chave, valor) VALUES (?, ?)
         ON CONFLICT(chave) DO UPDATE SET valor = excluded.valor'
    )->execute(['teste_upsert', 'um']);

    db()->prepare(
        'INSERT INTO config (chave, valor) VALUES (?, ?)
         ON CONFLICT(chave) DO UPDATE SET valor = excluded.valor'
    )->execute(['teste_upsert', 'dois']);

    igual('dois', config_ler('teste_upsert'));
    db()->exec("DELETE FROM config WHERE chave = 'teste_upsert'");
});

teste('lastInsertId funciona, porque INSERT RETURNING nao existe no SQLite 3.26', function (): void {
    db()->prepare("INSERT INTO avaliacoes (nome, texto) VALUES (?, ?)")->execute(['Teste', 'Texto']);
    $id = (int) db()->lastInsertId();

    verdade($id > 0);
    igual('Teste', (string) db()->query('SELECT nome FROM avaliacoes WHERE id = ' . $id)->fetchColumn());
    db()->exec('DELETE FROM avaliacoes WHERE id = ' . $id);
});

teste('castello_caminho_config respeita a constante ja definida pelo teste', function (): void {
    igual(CASTELLO_CONFIG, castello_caminho_config());
    nao_contem('prototipo-site-castello', castello_caminho_config());
});

teste('config_ler devolve o padrao quando a chave nao existe', function (): void {
    igual(null, config_ler('chave_que_nao_existe'));
    igual('reserva', config_ler('chave_que_nao_existe', 'reserva'));
});

teste('config_gravar cria e depois atualiza a chave', function (): void {
    config_gravar('teste_chave', 'primeiro');
    igual('primeiro', config_ler('teste_chave'));
    config_gravar('teste_chave', 'segundo');
    igual('segundo', config_ler('teste_chave'));
});
```

- [ ] **Passo 2: Rodar e ver falhar**

Rode: `php testes/smoke.php 10-db`
Esperado: erro fatal `Failed opening required .../public_html/lib/db.php`, e o runner encerra com `FALHOU: 10-db.php`.

- [ ] **Passo 3: Criar o `lib/schema.sql`**

Crie `public_html/lib/schema.sql` com **exatamente** o conteúdo do bloco SQL da seção 2 do contrato, sem alterar nada:

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

- [ ] **Passo 4: Escrever o `lib/db.php`**

Crie `public_html/lib/db.php`:

```php
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
```

- [ ] **Passo 5: Rodar e ver passar**

Rode: `php testes/smoke.php 10-db`
Esperado: `10-db.php` com 14 ok, 0 falha, 0 pulado.

Depois rode a suíte inteira: `php testes/smoke.php`
Esperado: `todos os casos passaram`.

- [ ] **Passo 6: Commit**

```bash
git add public_html/lib/schema.sql public_html/lib/db.php testes/casos/10-db.php
git commit -m "feat: banco SQLite com o schema do contrato e os ajudantes de acesso

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Tarefa 3: `lib/conteudo.php`

**Arquivos:**
- Criar: `public_html/lib/conteudo.php`
- Teste: `testes/casos/30-conteudo.php`

**Interfaces:**
- Consome: `db()`, `e()`, `config_ler()`, `config_gravar()` da Tarefa 2.
- Produz: `modelos(string $modalidade): array`, `portfolio(): array`, `avaliacoes(): array`, `videos(?int $limite = null): array`, `faq(string $contexto = 'geral'): array`, `passos(string $contexto): array`, `bloco(string $chave, string $padrao = ''): string`, `icone_faq(string $chave): string`, e a constante `CASTELLO_ICONES_FAQ` (mapa chave para SVG, as sete chaves fechadas do contrato).

Toda função de listagem devolve `array` de linhas associativas, já filtradas por `ativo = 1` e ordenadas por `ordem ASC, id ASC`.

- [ ] **Passo 1: Escrever o teste que falha**

Crie `testes/casos/30-conteudo.php`:

```php
<?php
declare(strict_types=1);

require_once site() . '/lib/conteudo.php';

/** Fixture propria: este caso nao depende da migracao. */
function semear(): void
{
    db()->exec("INSERT INTO modelos (modalidade, nome, area, parede, preco, foto, foto_alt, destaque, ativo, ordem)
                VALUES ('pronta','Beta','42,75 m²','Parede dupla','79.988','uploads/modelos/b.png','Foto B',0,1,2),
                       ('pronta','Alfa','39,00 m²','Parede vertical','69.900','uploads/modelos/a.png','Foto A',1,1,1),
                       ('pronta','Sumida','10,00 m²','Parede vertical','1','uploads/modelos/s.png','Foto S',0,0,3),
                       ('flex','Flexinha','20,00 m²','Parede vertical','2','uploads/modelos/f.png','Foto F',0,1,1)");

    db()->exec("INSERT INTO portfolio (titulo, categoria, foto, foto_alt, ativo, ordem)
                VALUES ('Casa dois','Categoria dois','uploads/portfolio/2.png','Alt dois',1,2),
                       ('Casa um','Categoria um','uploads/portfolio/1.png','Alt um',1,1),
                       ('Casa off','Categoria off','uploads/portfolio/3.png','Alt off',0,3)");

    db()->exec("INSERT INTO avaliacoes (nome, texto, estrelas, ativo, ordem)
                VALUES ('Beatriz','Texto da Beatriz',5,1,2),
                       ('Andre','Texto do Andre',5,1,1),
                       ('Oculto','Texto oculto',5,0,3)");

    for ($i = 1; $i <= 11; $i++) {
        db()->prepare('INSERT INTO videos (arquivo, poster, legenda, ativo, ordem) VALUES (?, ?, ?, 1, ?)')
            ->execute([
                sprintf('uploads/videos/insta-%02d.mp4', $i),
                sprintf('uploads/videos/insta-%02d.jpg', $i),
                '',
                $i,
            ]);
    }

    db()->exec("INSERT INTO faq (pergunta, resposta, icone, contexto, ativo, ordem)
                VALUES ('Geral dois?','Resposta dois','chave','geral',1,2),
                       ('Geral um?','Resposta um','relogio','geral',1,1),
                       ('Flex um?','Resposta flex','planta','flex',1,1),
                       ('Geral off?','Resposta off','clima','geral',0,3)");

    db()->exec("INSERT INTO passos (contexto, titulo, texto, imagem, imagem_alt, ativo, ordem)
                VALUES ('pronta','Passo dois','Texto dois','uploads/passos/2.jpg','Alt dois',1,2),
                       ('pronta','Passo um','Texto um','uploads/passos/1.jpg','Alt um',1,1),
                       ('flex','Flex passo','Texto flex','uploads/passos/f.jpg','Alt flex',1,1)");

    db()->exec("INSERT INTO blocos (chave, rotulo, valor, tipo)
                VALUES ('hero_titulo','Titulo do topo','A casa dos seus sonhos','texto'),
                       ('flex_texto','Texto da Flex',NULL,'texto_longo')");
}

semear();

teste('modelos filtra por modalidade, esconde inativo e respeita a ordem', function (): void {
    $lista = modelos('pronta');
    igual(2, count($lista));
    igual('Alfa', $lista[0]['nome']);
    igual('Beta', $lista[1]['nome']);
    igual(1, (int) $lista[0]['destaque']);
    igual('uploads/modelos/a.png', $lista[0]['foto']);

    igual(1, count(modelos('flex')));
    igual('Flexinha', modelos('flex')[0]['nome']);
});

teste('portfolio esconde inativo e respeita a ordem', function (): void {
    $lista = portfolio();
    igual(2, count($lista));
    igual('Casa um', $lista[0]['titulo']);
    igual('Casa dois', $lista[1]['titulo']);
});

teste('avaliacoes esconde inativo e respeita a ordem', function (): void {
    $lista = avaliacoes();
    igual(2, count($lista));
    igual('Andre', $lista[0]['nome']);
    igual(5, (int) $lista[0]['estrelas']);
});

teste('videos sem argumento usa o limite da config', function (): void {
    igual('8', config_ler('videos_na_home'));
    igual(8, count(videos()));
    igual('uploads/videos/insta-01.mp4', videos()[0]['arquivo']);
    igual('uploads/videos/insta-08.mp4', videos()[7]['arquivo']);
});

teste('videos aceita limite explicito e limite zero devolve todos', function (): void {
    igual(3, count(videos(3)));
    igual(11, count(videos(0)));
    igual(11, count(videos(99)));
});

teste('videos acompanha a mudanca do limite na config', function (): void {
    config_gravar('videos_na_home', '4');
    igual(4, count(videos()));
    config_gravar('videos_na_home', '8');
});

teste('faq separa geral de flex', function (): void {
    $geral = faq('geral');
    igual(2, count($geral));
    igual('Geral um?', $geral[0]['pergunta']);
    igual('relogio', $geral[0]['icone']);

    igual(2, count(faq()), 'o padrao de faq() e o contexto geral');
    igual(1, count(faq('flex')));
    igual('Flex um?', faq('flex')[0]['pergunta']);
});

teste('passos separa pronta de flex', function (): void {
    $lista = passos('pronta');
    igual(2, count($lista));
    igual('Passo um', $lista[0]['titulo']);
    igual('uploads/passos/1.jpg', $lista[0]['imagem']);
    igual('Alt um', $lista[0]['imagem_alt']);
    igual(1, count(passos('flex')));
});

teste('bloco devolve o valor e cai no padrao quando vazio', function (): void {
    igual('A casa dos seus sonhos', bloco('hero_titulo'));
    igual('', bloco('flex_texto'), 'valor NULL vira string vazia');
    igual('reserva', bloco('flex_texto', 'reserva'), 'valor NULL cai no padrao');
    igual('', bloco('chave_inexistente'));
    igual('reserva', bloco('chave_inexistente', 'reserva'));
});

teste('icone_faq devolve o SVG das sete chaves fechadas', function (): void {
    foreach (['relogio', 'chave', 'planta', 'clima', 'escudo', 'fundacao', 'garantia'] as $chave) {
        $svg = icone_faq($chave);
        contem('<svg viewBox="0 0 24 24" aria-hidden="true">', $svg, "icone $chave");
        contem('</svg>', $svg, "icone $chave");
    }
    igual(7, count(CASTELLO_ICONES_FAQ));
});

teste('icone_faq devolve string vazia para chave desconhecida', function (): void {
    igual('', icone_faq('inventado'));
    igual('', icone_faq(''));
});
```

- [ ] **Passo 2: Rodar e ver falhar**

Rode: `php testes/smoke.php 30-conteudo`
Esperado: erro fatal `Failed opening required .../public_html/lib/conteudo.php`.

- [ ] **Passo 3: Escrever o `lib/conteudo.php`**

Crie `public_html/lib/conteudo.php`. Os sete SVGs são cópia literal dos ícones do bloco `#faq` do `index.html` original, na ordem em que as perguntas aparecem.

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Leitura do conteudo por secao. Toda funcao de listagem devolve linhas
 * associativas ja filtradas por ativo = 1 e ordenadas por ordem, id.
 */

/** Conjunto fechado de icones do FAQ. Chaves fixadas pelo contrato. */
const CASTELLO_ICONES_FAQ = [
    'relogio'  => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v4l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>',
    'chave'    => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="15" cy="9" r="4"/><path d="m12 12-8 8M8 16l2 2M6 18l1.6 1.6"/></svg>',
    'planta'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h7M15 6h5M4 12h2M10 12h10M4 18h9M17 18h3"/><circle cx="13" cy="6" r="2" fill="currentColor" stroke="none"/><circle cx="8" cy="12" r="2" fill="currentColor" stroke="none"/><circle cx="15" cy="18" r="2" fill="currentColor" stroke="none"/></svg>',
    'clima'    => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v2M12 19v2M5 12H3M21 12h-2M6.3 6.3 4.9 4.9M19.1 19.1l-1.4-1.4M17.7 6.3l1.4-1.4M4.9 19.1l1.4-1.4M16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z"/></svg>',
    'escudo'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 8 4v6c0 4.4-3.4 7.4-8 8-4.6-.6-8-3.6-8-8V7l8-4Z"/><path d="m9 12 2 2 4-4"/></svg>',
    'fundacao' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 9 5-9 5-9-5 9-5ZM3 12l9 5 9-5M3 16l9 5 9-5"/></svg>',
    'garantia' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l2.1 1.5 2.6-.2.9 2.4 2.3 1.3-.6 2.5.6 2.5-2.3 1.3-.9 2.4-2.6-.2L12 21l-2.1-1.5-2.6.2-.9-2.4-2.3-1.3.6-2.5-.6-2.5 2.3-1.3.9-2.4 2.6.2L12 3Z"/><path d="m9 12 2 2 4-4"/></svg>',
];

/** @param string $modalidade 'pronta' ou 'flex' */
function modelos(string $modalidade): array
{
    $st = db()->prepare(
        'SELECT * FROM modelos WHERE modalidade = ? AND ativo = 1 ORDER BY ordem ASC, id ASC'
    );
    $st->execute([$modalidade]);

    return $st->fetchAll();
}

function portfolio(): array
{
    return db()->query('SELECT * FROM portfolio WHERE ativo = 1 ORDER BY ordem ASC, id ASC')->fetchAll();
}

function avaliacoes(): array
{
    return db()->query('SELECT * FROM avaliacoes WHERE ativo = 1 ORDER BY ordem ASC, id ASC')->fetchAll();
}

/**
 * @param int|null $limite null usa config.videos_na_home.
 *                         Zero ou negativo devolve todos os ativos.
 */
function videos(?int $limite = null): array
{
    if ($limite === null) {
        $limite = (int) config_ler('videos_na_home', '8');
    }

    $st = db()->prepare('SELECT * FROM videos WHERE ativo = 1 ORDER BY ordem ASC, id ASC LIMIT ?');
    $st->bindValue(1, $limite > 0 ? $limite : -1, PDO::PARAM_INT);
    $st->execute();

    return $st->fetchAll();
}

/** @param string $contexto 'geral' ou 'flex' */
function faq(string $contexto = 'geral'): array
{
    $st = db()->prepare(
        'SELECT * FROM faq WHERE contexto = ? AND ativo = 1 ORDER BY ordem ASC, id ASC'
    );
    $st->execute([$contexto]);

    return $st->fetchAll();
}

/** @param string $contexto 'pronta' ou 'flex' */
function passos(string $contexto): array
{
    $st = db()->prepare(
        'SELECT * FROM passos WHERE contexto = ? AND ativo = 1 ORDER BY ordem ASC, id ASC'
    );
    $st->execute([$contexto]);

    return $st->fetchAll();
}

/** Texto avulso editavel. Valor vazio ou ausente cai no padrao. */
function bloco(string $chave, string $padrao = ''): string
{
    $st = db()->prepare('SELECT valor FROM blocos WHERE chave = ?');
    $st->execute([$chave]);
    $valor = $st->fetchColumn();

    if ($valor === false || $valor === null || $valor === '') {
        return $padrao;
    }

    return (string) $valor;
}

/** SVG do icone do FAQ. String vazia quando a chave nao existe. */
function icone_faq(string $chave): string
{
    return CASTELLO_ICONES_FAQ[$chave] ?? '';
}
```

- [ ] **Passo 4: Rodar e ver passar**

Rode: `php testes/smoke.php 30-conteudo`
Esperado: 11 ok, 0 falha, 0 pulado.

Rode a suíte inteira: `php testes/smoke.php`
Esperado: `todos os casos passaram`.

- [ ] **Passo 5: Commit**

```bash
git add public_html/lib/conteudo.php testes/casos/30-conteudo.php
git commit -m "feat: leitura do conteudo por secao com os icones fechados do FAQ

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Tarefa 4: `migrar.php` — o conteúdo real do site vai para o banco

**Arquivos:**
- Criar: `public_html/migrar.php`
- Teste: `testes/casos/20-migracao.php`

**Interfaces:**
- Consome: `db()`, `agora()`, `config_ler()` da Tarefa 2; `modelos()`, `portfolio()`, `avaliacoes()`, `videos()`, `faq()`, `passos()`, `bloco()` da Tarefa 3.
- Produz: `migrar(): array` (mapa `tabela => quantidade inserida`), `migrar_usuario(string $login, string $senha, string $nome): bool` (devolve `true` quando criou, `false` quando o login já existia), `migrar_copiar(string $origem, string $pasta): string` (copia um arquivo do site para `uploads/` e devolve o caminho relativo gravado no banco), `migrar_vazia(string $tabela): bool`, `migrar_segredos(): string` (cria `config/segredos.php` na instalação e devolve a chave gerada, ou string vazia quando o arquivo já existia), `migrar_entrada(): void`, e a constante `CASTELLO_UPLOADS` quando ninguém a definiu antes.

**Como a instalação se destrava sozinha.** O usuário de FTP da hospedagem está preso ao `public_html` e não alcança a pasta de configuração, então ninguém consegue subir `segredos.php` a mão. Por isso `migrar.php` pela web é liberado em dois casos: banco ainda vazio, que é a instalação, ou chave certa em `?chave=`. Assim que existe usuário cadastrado, a porta fecha sozinha e passa a exigir a chave, que o próprio script acabou de gravar em `config/segredos.php`. O passo de deploy manda apagar `migrar.php` do servidor logo depois.

**Contagens esperadas:** 4 modelos, 6 itens de portfólio, 14 avaliações, 11 vídeos, 7 perguntas de FAQ, 5 passos, 21 blocos.

> **Portfólio: são 6, e isso já foi confirmado.** A spec antiga falava em 5 casas entregues; o `index.html` tem 6 botões `.accordion__item`, a spec foi corrigida e os 6 migram.

- [ ] **Passo 1: Escrever o teste que falha**

Crie `testes/casos/20-migracao.php`:

```php
<?php
declare(strict_types=1);

require_once site() . '/lib/conteudo.php';

$contagens = banco_com_conteudo();

teste('a migracao insere a quantidade exata de cada tabela', function () use ($contagens): void {
    igual(4,  $contagens['modelos']);
    igual(6,  $contagens['portfolio']);
    igual(14, $contagens['avaliacoes']);
    igual(11, $contagens['videos']);
    igual(7,  $contagens['faq']);
    igual(5,  $contagens['passos']);
    igual(21, $contagens['blocos']);
});

teste('os quatro modelos Casa Pronta chegaram com preco, area e parede', function (): void {
    $lista = modelos('pronta');
    igual(4, count($lista));

    igual('Compacta', $lista[0]['nome']);
    igual('39,00 m²', $lista[0]['area']);
    igual('Parede vertical', $lista[0]['parede']);
    igual('69.900', $lista[0]['preco']);
    igual('90 a 120 dias', $lista[0]['prazo']);

    igual('Conforto', $lista[1]['nome']);
    igual('79.988', $lista[1]['preco']);

    igual('Família', $lista[2]['nome']);
    igual('87.997', $lista[2]['preco']);
    igual(1, (int) $lista[2]['destaque'], 'Familia e a mais escolhida');

    igual('Ampla', $lista[3]['nome']);
    igual('97.776', $lista[3]['preco']);

    igual(0, count(modelos('flex')), 'material da Flex ainda nao chegou do cliente');
});

teste('as fotos dos modelos foram copiadas para uploads e o caminho e relativo', function (): void {
    foreach (modelos('pronta') as $m) {
        contem('uploads/modelos/', $m['foto']);
        falso(str_starts_with($m['foto'], '/'), 'caminho nunca comeca com barra');
        verdade(is_file(CASTELLO_UPLOADS . '/modelos/' . basename($m['foto'])), 'arquivo copiado: ' . $m['foto']);
        verdade($m['foto_alt'] !== '' && $m['foto_alt'] !== null, 'todo modelo tem alt escrito');
    }
    igual('uploads/modelos/casa5.png', modelos('pronta')[2]['foto']);
});

teste('o portfolio trouxe as seis casas entregues, com categoria e alt', function (): void {
    $lista = portfolio();
    igual(6, count($lista));
    igual('Sobrado à beira da água', $lista[0]['titulo']);
    igual('Beira da água', $lista[0]['categoria']);
    igual('Sobrado de madeira à beira da água com vista para a ponte', $lista[0]['foto_alt']);
    igual('Chalé com varanda', $lista[5]['titulo']);
    igual('uploads/portfolio/casa-8.png', $lista[3]['foto']);
});

teste('as 14 avaliacoes reais do Google chegaram inteiras', function (): void {
    $lista = avaliacoes();
    igual(14, count($lista));
    igual('Joana Lazzaris', $lista[0]['nome']);
    igual('Lares do Sul', $lista[13]['nome']);

    foreach ($lista as $a) {
        igual(5, (int) $a['estrelas'], 'toda avaliacao migrada e 5 estrelas');
        verdade(mb_strlen($a['texto']) > 80, 'texto completo, nao cortado: ' . $a['nome']);
    }

    contem('Tivemos uma excelente experiência com a Castello', $lista[0]['texto']);
    contem('A arquiteta Talita foi super atenciosa', $lista[3]['texto']);
});

teste('os 11 videos migraram com poster e sem o arquivo original de 25 MB', function (): void {
    $lista = videos(0);
    igual(11, count($lista));
    igual('uploads/videos/insta-01.mp4', $lista[0]['arquivo']);
    igual('uploads/videos/insta-01.jpg', $lista[0]['poster']);
    igual('uploads/videos/insta-11.mp4', $lista[10]['arquivo']);

    foreach ($lista as $v) {
        nao_contem('original', $v['arquivo'], 'insta-06.original.mp4 nao pode migrar');
        verdade(is_file(CASTELLO_UPLOADS . '/videos/' . basename($v['arquivo'])), 'video copiado: ' . $v['arquivo']);
        verdade(is_file(CASTELLO_UPLOADS . '/videos/' . basename($v['poster'])), 'poster copiado: ' . $v['poster']);
    }
    falso(is_file(CASTELLO_UPLOADS . '/videos/insta-06.original.mp4'));
});

teste('a home mostra 8 videos por causa do limite acordado na reuniao', function (): void {
    igual('8', config_ler('videos_na_home'));
    igual(8, count(videos()));
    igual(11, count(videos(0)), 'os 11 continuam cadastrados e ativos');
});

teste('as 7 perguntas do FAQ chegaram com o icone certo em cada uma', function (): void {
    $lista = faq('geral');
    igual(7, count($lista));

    $esperado = [
        ['Quanto tempo leva pra minha casa ficar pronta?', 'relogio'],
        ['O que está incluso no chave na mão?',            'chave'],
        ['Posso personalizar a planta e os acabamentos?',  'planta'],
        ['Casa de madeira é confortável o ano todo?',      'clima'],
        ['A casa é resistente e dura com o tempo?',        'escudo'],
        ['Vocês cuidam da fundação e do terreno?',         'fundacao'],
        ['Que garantias eu tenho com a Castello?',         'garantia'],
    ];

    foreach ($esperado as $i => [$pergunta, $icone]) {
        igual($pergunta, $lista[$i]['pergunta']);
        igual($icone, $lista[$i]['icone']);
        verdade(icone_faq($lista[$i]['icone']) !== '', 'o icone precisa existir no conjunto fechado');
        verdade(mb_strlen($lista[$i]['resposta']) > 60, 'resposta completa');
    }

    igual(0, count(faq('flex')), 'FAQ da Flex ainda nao existe');
});

teste('os 5 passos do Como funciona chegaram com imagem', function (): void {
    $lista = passos('pronta');
    igual(5, count($lista));
    igual('Conversa e projeto', $lista[0]['titulo']);
    igual('Fundação', $lista[1]['titulo']);
    igual('Estrutura e montagem', $lista[2]['titulo']);
    igual('Acabamento', $lista[3]['titulo']);
    igual('Chave na mão', $lista[4]['titulo']);
    igual('uploads/passos/passo-1.jpg', $lista[0]['imagem']);
    igual('uploads/passos/passo-5.png', $lista[4]['imagem']);
    contem('90 a 120 dias', $lista[4]['texto']);
    igual(0, count(passos('flex')), 'passo a passo da Flex ainda nao existe');
});

teste('os alt descritivos dos passos vieram do index.html, um a um', function (): void {
    $lista = passos('pronta');

    igual('Maquete do projeto da casa de madeira sobre a planta', $lista[0]['imagem_alt']);
    igual('Início da estrutura de madeira sobre a fundação', $lista[1]['imagem_alt']);
    igual('Estrutura e montagem da casa de madeira', $lista[2]['imagem_alt']);
    igual('Equipe no acabamento do telhado e fachada da casa', $lista[3]['imagem_alt']);
    igual('Chaves da casa de madeira pronta, chave na mão', $lista[4]['imagem_alt']);

    foreach ($lista as $p) {
        verdade($p['imagem_alt'] !== $p['titulo'], 'o alt e descritivo, nao repete o titulo: ' . $p['titulo']);
    }
});

teste('os 21 blocos de texto do contrato existem com rotulo e tipo', function (): void {
    $linhas = db()->query('SELECT chave, rotulo, tipo FROM blocos ORDER BY chave')->fetchAll();
    igual(21, count($linhas));

    $chaves = array_column($linhas, 'chave');
    foreach (['hero_titulo', 'hero_subtitulo', 'modalidades_titulo', 'modalidades_texto',
              'pronta_titulo', 'pronta_texto', 'pronta_prazo', 'flex_titulo', 'flex_texto',
              'flex_prazo', 'flex_video', 'flex_video_poster',
              'flexpg_hero_titulo', 'flexpg_hero_texto', 'flexpg_oque_titulo', 'flexpg_oque_texto',
              'flexpg_depois_titulo', 'flexpg_depois_texto', 'flexpg_catalogo_nota',
              'flexpg_cta_titulo', 'flexpg_cta_texto'] as $chave) {
        verdade(in_array($chave, $chaves, true), "faltou o bloco $chave");
    }

    foreach ($linhas as $linha) {
        verdade(in_array($linha['tipo'], ['texto', 'texto_longo'], true), 'tipo valido em ' . $linha['chave']);
        verdade($linha['rotulo'] !== '', 'rotulo escrito em ' . $linha['chave']);
    }

    igual('90 a 120 dias', bloco('pronta_prazo'));
    igual('45 dias', bloco('flex_prazo'));
    igual('A casa dos seus sonhos', bloco('hero_titulo'));
    igual('', bloco('flex_titulo'), 'texto da Flex e escrito depois, pela Frente 2');
});

teste('rodar a migracao de novo nao duplica conteudo', function (): void {
    $segunda = migrar();
    igual(0, $segunda['modelos']);
    igual(0, $segunda['avaliacoes']);
    igual(4, count(modelos('pronta')));
    igual(14, count(avaliacoes()));
});

teste('migrar_segredos cria o segredos.php uma vez so, com chave longa', function (): void {
    $arquivo = CASTELLO_CONFIG . '/segredos.php';
    falso(is_file($arquivo), 'o caso comeca sem segredos.php');

    $chave = migrar_segredos();
    verdade(strlen($chave) >= 32, 'a chave precisa ser longa, obtida: ' . $chave);
    verdade(is_file($arquivo));
    contem("define('CASTELLO_MIGRAR_CHAVE'", (string) file_get_contents($arquivo));
    contem($chave, (string) file_get_contents($arquivo));

    igual('', migrar_segredos(), 'arquivo ja existente nao e sobrescrito');
});

teste('migrar_usuario cria o acesso do painel uma vez so', function (): void {
    verdade(migrar_usuario('castello', 'senha-de-teste-123', 'Castello Casas de Madeira'));
    falso(migrar_usuario('castello', 'outra-senha', 'Castello Casas de Madeira'));

    $u = db()->query("SELECT * FROM usuarios WHERE login = 'castello'")->fetch();
    igual(1, (int) db()->query('SELECT COUNT(*) FROM usuarios')->fetchColumn());
    verdade(password_verify('senha-de-teste-123', $u['senha_hash']), 'a senha e verificavel por bcrypt');
    nao_contem('senha-de-teste-123', $u['senha_hash'], 'senha nunca em texto puro');
    verdade((bool) preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $u['criado_em']));
});
```

- [ ] **Passo 2: Rodar e ver falhar**

Rode: `php testes/smoke.php 20-migracao`
Esperado: erro fatal `Failed opening required .../public_html/migrar.php`.

- [ ] **Passo 3: Escrever o `migrar.php`**

Crie `public_html/migrar.php`. Todo o conteúdo abaixo é cópia literal do que está no `index.html` de hoje.

```php
<?php
declare(strict_types=1);

/**
 * Carrega no banco o conteudo real que hoje esta escrito no index.html.
 *
 * Linha de comando:  php public_html/migrar.php
 * Pela web:          /migrar.php enquanto o banco estiver vazio, que e a
 *                    instalacao. Depois disso, /migrar.php?chave=SUA_CHAVE,
 *                    com a chave que este script gravou em segredos.php.
 *
 * Cada tabela so e preenchida quando esta vazia, entao rodar de novo nunca
 * apaga o que o cliente ja editou pelo painel.
 */

require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/conteudo.php';

if (!defined('CASTELLO_UPLOADS')) {
    define('CASTELLO_UPLOADS', __DIR__ . '/uploads');
}

/** modalidade, nome, area, parede, preco, foto de origem, alt, destaque */
const MIGRAR_MODELOS = [
    ['pronta', 'Compacta', '39,00 m²', 'Parede vertical', '69.900', 'fotos-casas/casa4.png', 'Casa de madeira compacta de dois pavimentos da Castello', 0],
    ['pronta', 'Conforto', '42,75 m²', 'Parede dupla', '79.988', 'fotos-casas/casa2.png', 'Casa de madeira Castello térrea com telhado de telhas e varanda', 0],
    ['pronta', 'Família', '51,00 m²', 'Parede dupla', '87.997', 'fotos-casas/casa5.png', 'Casa de madeira Castello com varanda ampla em volta e jardim', 1],
    ['pronta', 'Ampla', '59,75 m²', 'Parede dupla', '97.776', 'fotos-casas/casa3.png', 'Sobrado de madeira Castello à beira da água com vista para a ponte', 0],
];

/** titulo, categoria, foto de origem, alt */
const MIGRAR_PORTFOLIO = [
    ['Sobrado à beira da água', 'Beira da água', 'fotos-casas/casa3.png', 'Sobrado de madeira à beira da água com vista para a ponte'],
    ['Sobrado com sacada', 'Dois pavimentos', 'fotos-casas/casa7.png', 'Sobrado de madeira com sacada e fachada de réguas'],
    ['Varanda ampla e garagem', 'Térrea', 'fotos-casas/casa6.png', 'Casa de madeira térrea com varanda ampla e garagem coberta'],
    ['Casa de campo com varanda', 'No campo', 'fotos-casas/casa-8.png', 'Casa de madeira de campo com varanda e cerca branca'],
    ['Varanda com pergolado', 'Área externa', 'fotos-casas/casa1.png', 'Casa de madeira com pergolado e varanda ao sol'],
    ['Chalé com varanda', 'Na natureza', 'fotos-casas/casa5.png', 'Casa de madeira com varanda em volta e mata ao fundo'],
];

/** nome, texto. Avaliacoes reais do Google Meu Negocio, 5,0 estrelas. */
const MIGRAR_AVALIACOES = [
    ['Joana Lazzaris', 'Tivemos uma excelente experiência com a Castello, desde a negociação da compra, modelo da casa, até a entrega dentro do prazo. Tivemos zero dor de cabeça de obra, mesmo sendo um pouco distante da cidade sede da empresa. São muito flexíveis e nos auxiliaram em todas as etapas.'],
    ['Franciely Silva', 'Estou muito satisfeita com a experiência que tive com a Castello. Desde o início, o atendimento foi excelente, sempre prestativo e transparente. A qualidade da construção superou minhas expectativas, com acabamentos bem feitos e um ótimo padrão. Recomendo para quem busca confiança e compromisso em cada detalhe.'],
    ['Luiz Flores', 'A Castello foi uma grata surpresa na nossa vida. Uma entrega com excelência, com negociação online e acompanhamento de obra em todas as etapas. Nunca imaginei ter uma obra de construção sem qualquer problema com o prestador do serviço. Parabéns ao Carlos e ao time da Castello.'],
    ['Ana Paula Elias de Oliveira', 'Entrei em contato com algumas empresas da região que constroem casas de madeira e fechei negócio com a Castello. A arquiteta Talita foi super atenciosa desde o primeiro momento, com paciência para responder às minhas infinitas perguntas e adaptar o projeto até chegarmos ao ideal. A velocidade e a qualidade do trabalho são impressionantes, e a equipe toda muito cordial. Meu pai, que resistia à casa de madeira, está super feliz. Recomendo!'],
    ['Nany Festa', 'Conhecer a Castello foi a realização de um sonho. Nossa construção ocorreu tudo certo, dentro do prazo, e entregaram a nossa até antes. Sempre com uma comunicação clara e dispostos a esclarecer as nossas dúvidas. Com certeza recomendo o trabalho deles. Além da construção ser excelente, a equipe toda é muito profissional.'],
    ['Diana Chris de Souza', 'A Castello realizou meu sonho e ficou tudo perfeito, com um atendimento muito especial de todos, em especial o Carlos. Já fazem quatro anos que nossa casa foi entregue e sempre recomendo eles.'],
    ['Antonio Marcos Decker', 'Atendimento de excelência em todas as etapas do contrato. Obra concluída dentro do prazo e sem intercorrências. Ótima experiência com a Castello Casas de Madeira. Para quem quer construir sem dores de cabeça, recomendo!'],
    ['Jussara Varela', 'Só temos que agradecer pelo profissionalismo e dedicação em todo o processo da obra. Foi um imenso prazer trabalhar com essa equipe sensacional, com comprometimento e responsabilidade incrível. O prazo de entrega foi devidamente cumprido conforme o combinado. Recomendamos a todos o trabalho dessa equipe de excelência.'],
    ['Roberto Gavioli', 'Em todas as etapas, desde o projeto até a entrega, a experiência foi ótima. A equipe da Castello trabalhou conosco para conseguirmos a casa que queríamos. Estamos satisfeitos e recomendamos, sem qualquer ressalva, a construtora.'],
    ['Vanderson Luiz', 'Desde o primeiro contato até a entrega das chaves, não tenho o que reclamar. O pessoal sempre pronto para atender e tirar as dúvidas. Super indico a construtora, parabéns pelo profissionalismo.'],
    ['Renato Goulart', 'Estava fazendo orçamento e fechei com a Castello pelo melhor preço, condições de pagamento e excelente qualidade da obra. Tudo que foi redigido no contrato foi cumprido. Eu indico para quem pensar em fazer uma casa.'],
    ['Frederico Guimarães', 'Minha primeira construção com a Castello e estou muito satisfeito. A empresa conta com profissionais competentes e dedicados, sempre à disposição de seus clientes e parceiros. Uma equipe enérgica, que trabalha muito para alcançar seus objetivos. O resultado são os charmosos chalés que vão surgindo e dando um toque especial em toda a região.'],
    ['Leslie Souza', 'Super indico a Castello. Fizeram a casa exatamente como pedimos, estavam sempre presentes na obra, atenciosos e dispostos a resolver tudo. Estamos muito contentes com a nossa casa nova. Muito obrigado!'],
    ['Lares do Sul', 'Tivemos uma ótima experiência com a Castello, entregaram dentro do prazo, serviço de qualidade. O proprietário também é uma pessoa de fácil negociação e respondia rapidamente sempre que solicitado. Recomendo.'],
];

/** pergunta, resposta, icone */
const MIGRAR_FAQ = [
    ['Quanto tempo leva pra minha casa ficar pronta?', 'Entre 90 e 120 dias, do projeto à chave na mão. Enquanto a obra convencional se arrasta por anos, sua casa de madeira é montada de forma rápida e organizada, com o prazo combinado em contrato.', 'relogio'],
    ['O que está incluso no chave na mão?', 'Sua casa sai pronta pra morar: laje aérea, elétrica, hidráulica, cerâmica, fossa, sumidouro, vidros e aberturas. Você cuida da mudança, a Castello cuida de projeto, materiais, prazos e acabamento.', 'chave'],
    ['Posso personalizar a planta e os acabamentos?', 'Sim, 100% personalizável. Planta, acabamentos, revestimentos, janelas, portas e piso são escolhidos do seu jeito. Cada projeto Castello é exclusivo e desenhado pra sua rotina e o seu gosto.', 'planta'],
    ['Casa de madeira é confortável o ano todo?', 'É um dos maiores diferenciais. A madeira mantém o ambiente fresco no calor e aconchegante no frio, com um conforto térmico bem acima da alvenaria comum em todas as estações.', 'clima'],
    ['A casa é resistente e dura com o tempo?', 'Construímos com madeira de qualidade e prego galvanizado em toda a estrutura, com equipe experiente na obra todo dia. Bem cuidada, a casa atravessa gerações e ainda valoriza como patrimônio.', 'escudo'],
    ['Vocês cuidam da fundação e do terreno?', 'A fundação faz parte do processo. A gente avalia o seu terreno e prepara a base certa pra receber a estrutura, com técnica e segurança em cada etapa, do primeiro passo até a chave na mão.', 'fundacao'],
    ['Que garantias eu tenho com a Castello?', 'Você tem a garantia da construção, o compromisso com a excelência da obra e o cumprimento do prazo combinado. Do primeiro contato ao pós-venda, é tudo com uma empresa só.', 'garantia'],
];

/** titulo, texto, imagem de origem, alt da imagem */
const MIGRAR_PASSOS = [
    ['Conversa e projeto', 'Entendemos seu sonho, seu terreno e seu orçamento, e desenhamos a planta ideal pra você.', 'passos/passo-1.jpg', 'Maquete do projeto da casa de madeira sobre a planta'],
    ['Fundação', 'Preparamos a base da casa com técnica e segurança, prontos para receber a estrutura.', 'passos/passo-2.jpg', 'Início da estrutura de madeira sobre a fundação'],
    ['Estrutura e montagem', 'Montamos a casa com madeira de qualidade e prego galvanizado, no padrão Castello.', 'passos/passo-3.jpg', 'Estrutura e montagem da casa de madeira'],
    ['Acabamento', 'Elétrica, hidráulica, revestimentos, vidros e os detalhes finos que fazem do seu jeito.', 'passos/passo-4.jpg', 'Equipe no acabamento do telhado e fachada da casa'],
    ['Chave na mão', 'Você recebe a casa pronta pra morar, completa, em 90 a 120 dias.', 'passos/passo-5.png', 'Chaves da casa de madeira pronta, chave na mão'],
];

/**
 * chave, rotulo no painel, valor inicial, tipo.
 * Os valores da Flex e do bloco de modalidades ficam vazios de proposito:
 * essas secoes ainda nao existem no site e a copy e escrita pela Frente 2.
 * As nove chaves flexpg_ cobrem a pagina Flex inteira, para que nenhuma parte
 * dela fique fixa no PHP fora do alcance do painel.
 */
const MIGRAR_BLOCOS = [
    ['hero_titulo', 'Título do topo', 'A casa dos seus sonhos', 'texto'],
    ['hero_subtitulo', 'Subtítulo do topo', 'pronta em até 120 dias', 'texto'],
    ['modalidades_titulo', 'Título do bloco de modalidades', '', 'texto'],
    ['modalidades_texto', 'Texto do bloco de modalidades', '', 'texto_longo'],
    ['pronta_titulo', 'Título da seção Casa Pronta', 'Escolha o tamanho. A gente entrega completa.', 'texto'],
    ['pronta_texto', 'Texto da seção Casa Pronta', 'Todos os modelos saem prontos pra morar: laje aérea, elétrica, hidráulica, cerâmica, fossa, sumidouro, vidros e aberturas.', 'texto_longo'],
    ['pronta_prazo', 'Prazo da Casa Pronta', '90 a 120 dias', 'texto'],
    ['flex_titulo', 'Título da seção Castelo Flex', '', 'texto'],
    ['flex_texto', 'Texto da seção Castelo Flex', '', 'texto_longo'],
    ['flex_prazo', 'Prazo da Castelo Flex', '45 dias', 'texto'],
    ['flex_video', 'Vídeo explicativo da Flex', '', 'texto'],
    ['flex_video_poster', 'Capa do vídeo da Flex', '', 'texto'],
    ['flexpg_hero_titulo', 'Título do topo da página Flex', '', 'texto'],
    ['flexpg_hero_texto', 'Texto do topo da página Flex', '', 'texto_longo'],
    ['flexpg_oque_titulo', 'Título de o que é a Castelo Flex', '', 'texto'],
    ['flexpg_oque_texto', 'Texto de o que é a Castelo Flex', '', 'texto_longo'],
    ['flexpg_depois_titulo', 'Título de o que fica por sua conta', '', 'texto'],
    ['flexpg_depois_texto', 'Texto de o que fica por sua conta', '', 'texto_longo'],
    ['flexpg_catalogo_nota', 'Nota abaixo do catálogo Flex', '', 'texto'],
    ['flexpg_cta_titulo', 'Título da faixa de orçamento da Flex', '', 'texto'],
    ['flexpg_cta_texto', 'Texto da faixa de orçamento da Flex', '', 'texto_longo'],
];

/**
 * Copia um arquivo que ja esta no site para dentro de uploads/ e devolve o
 * caminho relativo ao public_html que vai ser gravado no banco.
 */
function migrar_copiar(string $origem, string $pasta): string
{
    $de = __DIR__ . '/' . $origem;
    if (!is_file($de)) {
        throw new RuntimeException('arquivo de origem nao encontrado: ' . $origem);
    }

    $destino = CASTELLO_UPLOADS . '/' . $pasta;
    if (!is_dir($destino) && !mkdir($destino, 0775, true) && !is_dir($destino)) {
        throw new RuntimeException('nao consegui criar a pasta ' . $destino);
    }

    $nome = basename($origem);
    $para = $destino . '/' . $nome;
    if (!is_file($para) && !copy($de, $para)) {
        throw new RuntimeException('nao consegui copiar ' . $origem . ' para ' . $para);
    }

    return 'uploads/' . $pasta . '/' . $nome;
}

/** True quando a tabela ainda nao tem nenhuma linha. */
function migrar_vazia(string $tabela): bool
{
    return (int) db()->query('SELECT COUNT(*) FROM ' . $tabela)->fetchColumn() === 0;
}

/**
 * Carrega o conteudo real. Cada tabela so e preenchida quando esta vazia.
 *
 * @return array<string,int> tabela para quantidade inserida
 */
function migrar(): array
{
    $conta = ['modelos' => 0, 'portfolio' => 0, 'avaliacoes' => 0, 'videos' => 0, 'faq' => 0, 'passos' => 0, 'blocos' => 0];

    if (migrar_vazia('modelos')) {
        $st = db()->prepare(
            'INSERT INTO modelos (modalidade, nome, area, parede, preco, prazo, descricao, foto, foto_alt, destaque, ativo, ordem)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)'
        );
        foreach (MIGRAR_MODELOS as $i => [$modalidade, $nome, $area, $parede, $preco, $foto, $alt, $destaque]) {
            $st->execute([$modalidade, $nome, $area, $parede, $preco, '90 a 120 dias', '', migrar_copiar($foto, 'modelos'), $alt, $destaque, $i + 1]);
            $conta['modelos']++;
        }
    }

    if (migrar_vazia('portfolio')) {
        $st = db()->prepare(
            'INSERT INTO portfolio (titulo, categoria, foto, foto_alt, ativo, ordem) VALUES (?, ?, ?, ?, 1, ?)'
        );
        foreach (MIGRAR_PORTFOLIO as $i => [$titulo, $categoria, $foto, $alt]) {
            $st->execute([$titulo, $categoria, migrar_copiar($foto, 'portfolio'), $alt, $i + 1]);
            $conta['portfolio']++;
        }
    }

    if (migrar_vazia('avaliacoes')) {
        $st = db()->prepare(
            'INSERT INTO avaliacoes (nome, texto, estrelas, ativo, ordem) VALUES (?, ?, 5, 1, ?)'
        );
        foreach (MIGRAR_AVALIACOES as $i => [$nome, $texto]) {
            $st->execute([$nome, $texto, $i + 1]);
            $conta['avaliacoes']++;
        }
    }

    if (migrar_vazia('videos')) {
        $st = db()->prepare(
            'INSERT INTO videos (arquivo, poster, legenda, ativo, ordem) VALUES (?, ?, ?, 1, ?)'
        );
        for ($i = 1; $i <= 11; $i++) {
            $base = sprintf('videos-instagram/web/insta-%02d', $i);
            $st->execute([
                migrar_copiar($base . '.mp4', 'videos'),
                migrar_copiar($base . '.jpg', 'videos'),
                '',
                $i,
            ]);
            $conta['videos']++;
        }
    }

    if (migrar_vazia('faq')) {
        $st = db()->prepare(
            "INSERT INTO faq (pergunta, resposta, icone, contexto, ativo, ordem) VALUES (?, ?, ?, 'geral', 1, ?)"
        );
        foreach (MIGRAR_FAQ as $i => [$pergunta, $resposta, $icone]) {
            $st->execute([$pergunta, $resposta, $icone, $i + 1]);
            $conta['faq']++;
        }
    }

    if (migrar_vazia('passos')) {
        $st = db()->prepare(
            "INSERT INTO passos (contexto, titulo, texto, imagem, imagem_alt, ativo, ordem)
             VALUES ('pronta', ?, ?, ?, ?, 1, ?)"
        );
        foreach (MIGRAR_PASSOS as $i => [$titulo, $texto, $imagem, $alt]) {
            $st->execute([$titulo, $texto, migrar_copiar($imagem, 'passos'), $alt, $i + 1]);
            $conta['passos']++;
        }
    }

    if (migrar_vazia('blocos')) {
        $st = db()->prepare('INSERT INTO blocos (chave, rotulo, valor, tipo) VALUES (?, ?, ?, ?)');
        foreach (MIGRAR_BLOCOS as [$chave, $rotulo, $valor, $tipo]) {
            $st->execute([$chave, $rotulo, $valor, $tipo]);
            $conta['blocos']++;
        }
    }

    return $conta;
}

/** Cria o unico usuario do painel. Devolve false se o login ja existia. */
function migrar_usuario(string $login, string $senha, string $nome = 'Castello Casas de Madeira'): bool
{
    $st = db()->prepare('SELECT COUNT(*) FROM usuarios WHERE login = ?');
    $st->execute([$login]);
    if ((int) $st->fetchColumn() > 0) {
        return false;
    }

    db()->prepare('INSERT INTO usuarios (login, senha_hash, nome, criado_em) VALUES (?, ?, ?, ?)')
        ->execute([$login, password_hash($senha, PASSWORD_BCRYPT), $nome, agora()]);

    return true;
}

/**
 * Garante que config/segredos.php exista e devolve a chave de migracao.
 *
 * O usuario de FTP da hospedagem esta preso ao public_html e nao alcanca a
 * pasta de configuracao, entao ninguem consegue subir esse arquivo a mao.
 * Quem o cria e o PHP, na primeira instalacao.
 */
function migrar_segredos(): string
{
    $arquivo = CASTELLO_CONFIG . '/segredos.php';

    if (is_file($arquivo)) {
        return defined('CASTELLO_MIGRAR_CHAVE') ? (string) CASTELLO_MIGRAR_CHAVE : '';
    }

    $chave = bin2hex(random_bytes(16));
    $conteudo = "<?php\n"
        . "// Gerado por migrar.php na instalacao. Nao vai para o git.\n"
        . "define('CASTELLO_MIGRAR_CHAVE', '" . $chave . "');\n";

    if (file_put_contents($arquivo, $conteudo) === false) {
        return '';
    }
    @chmod($arquivo, 0600);

    return $chave;
}

/**
 * Ponto de entrada. Roda so quando migrar.php e o script chamado.
 *
 * Pela web o acesso e liberado em dois casos: banco ainda vazio, que e a
 * instalacao, ou chave certa em ?chave=. Depois da primeira instalacao existe
 * usuario cadastrado, entao a porta fecha sozinha. O passo de deploy manda
 * apagar este arquivo do servidor assim que a migracao terminar.
 */
function migrar_entrada(): void
{
    $instalando = migrar_vazia('usuarios') && migrar_vazia('modelos');

    if (PHP_SAPI !== 'cli') {
        $esperada = defined('CASTELLO_MIGRAR_CHAVE') ? (string) CASTELLO_MIGRAR_CHAVE : '';
        $recebida = (string) ($_GET['chave'] ?? '');
        $liberado = $instalando || ($esperada !== '' && hash_equals($esperada, $recebida));

        if (!$liberado) {
            http_response_code(404);
            echo 'nao encontrado';
            return;
        }
        header('Content-Type: text/plain; charset=utf-8');
    }

    $chave = migrar_segredos();

    $conta = migrar();
    foreach ($conta as $tabela => $quantidade) {
        echo str_pad($tabela, 12) . ($quantidade > 0 ? $quantidade . ' inseridos' : 'ja tinha conteudo, nao mexi') . "\n";
    }

    $senha = bin2hex(random_bytes(6));
    if (migrar_usuario('castello', $senha)) {
        echo "\nacesso ao painel criado\n";
        echo "  login: castello\n";
        echo "  senha: $senha\n";
        echo "anote agora, esta senha nao aparece de novo. Troque em /painel na tela Trocar senha.\n";
    } else {
        echo "\nacesso ao painel ja existia, senha mantida\n";
    }

    if ($chave !== '') {
        echo "\nchave de migracao gravada em config/segredos.php\n";
        echo "  chave: $chave\n";
    }

    echo "\napague migrar.php do servidor agora que a instalacao terminou.\n";
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    migrar_entrada();
}
```

- [ ] **Passo 4: Rodar e ver passar**

Rode: `php testes/smoke.php 20-migracao`
Esperado: 14 ok, 0 falha, 0 pulado.

- [ ] **Passo 5: Rodar a migração de verdade, no banco local**

```bash
php public_html/migrar.php
```

Esperado: as sete linhas de contagem (`modelos 4`, `portfolio 6`, `avaliacoes 14`, `videos 11`, `faq 7`, `passos 5`, `blocos 21`) e o bloco com login `castello` e a senha gerada. **Anote a senha**, ela é usada nas tarefas do painel.

Confira que os arquivos foram copiados:

```bash
ls public_html/uploads/modelos public_html/uploads/portfolio public_html/uploads/passos
ls public_html/uploads/videos | wc -l
```

Esperado: 4 fotos em `modelos`, 6 em `portfolio`, 5 em `passos`, 22 arquivos em `videos` (11 mp4 mais 11 jpg), e nenhum `insta-06.original.mp4`.

- [ ] **Passo 6: Rodar a suíte inteira e commitar**

Rode: `php testes/smoke.php`
Esperado: `todos os casos passaram`.

```bash
git add public_html/migrar.php testes/casos/20-migracao.php
git commit -m "feat: migra para o banco o conteudo real do index.html

4 modelos, 6 itens de portfolio, 14 avaliacoes do Google, 11 videos, 7
perguntas de FAQ com seus icones e 5 passos. O insta-06.original.mp4 de
25 MB fica de fora, como manda a spec. A spec fala em 5 itens de
portfolio, mas o index.html tem 6: migrados os 6 que existem.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Tarefa 5: os seis parciais, com HTML idêntico ao do site atual

**Arquivos:**
- Criar: `public_html/partials/modelos.php`, `portfolio.php`, `avaliacoes.php`, `videos.php`, `faq.php`, `passos.php`
- Criar: `testes/base/frag-modelos.html`, `frag-portfolio.html`, `frag-avaliacoes.html`, `frag-videos.html`, `frag-faq.html`, `frag-passos.html`
- Teste: `testes/casos/40-partials.php`

**Interfaces:**
- Consome: `modelos()`, `portfolio()`, `avaliacoes()`, `videos()`, `faq()`, `passos()`, `icone_faq()`, `e()`; `banco_com_conteudo()`, `render()` e `norm()` dos testes.
- Produz: seis arquivos que **imprimem** HTML e não devolvem nada. Variáveis chegam por escopo, definidas logo antes do `include`:
  - `partials/modelos.php` espera `string $modalidade`
  - `partials/portfolio.php` espera nada
  - `partials/avaliacoes.php` espera nada
  - `partials/videos.php` espera nada
  - `partials/faq.php` espera `string $contexto`
  - `partials/passos.php` espera `string $contexto`

**Uma diferença conhecida e aceita em relação ao `index.html`:** os caminhos de mídia passam a apontar para `uploads/`. O `norm()` dos testes desfaz essa troca antes de comparar. Fora isso a saída é byte a byte a mesma, inclusive os `alt`, porque a tabela `passos` ganhou a coluna `imagem_alt` no contrato e os cinco textos descritivos migram como estão.

- [ ] **Passo 1: Extrair os fragmentos de referência do `index.html`**

O `index.html` ainda não foi tocado, então as linhas abaixo valem. Rode na raiz do repositório:

```bash
mkdir -p testes/base
sed -n '230,287p' public_html/index.html > testes/base/frag-modelos.html
sed -n '302,351p' public_html/index.html > testes/base/frag-portfolio.html
sed -n '445,529p' public_html/index.html > testes/base/frag-avaliacoes.html
sed -n '555,567p' public_html/index.html > testes/base/frag-videos.html
sed -n '582,637p' public_html/index.html > testes/base/frag-faq.html
sed -n '368,385p' public_html/index.html > testes/base/frag-passos.html
```

Confira cada fragmento antes de seguir:

```bash
head -1 testes/base/frag-modelos.html     # <div class="grid grid--models">
tail -1 testes/base/frag-modelos.html     # </div>
head -1 testes/base/frag-portfolio.html   # <div class="accordion reveal" id="accordion">
head -1 testes/base/frag-avaliacoes.html  # <div class="reviews__track" id="reviewsTrack" tabindex="0">
head -1 testes/base/frag-videos.html      # <div class="insta__rail reveal" id="instaRail" ...>
head -1 testes/base/frag-faq.html         # <div class="faq__board reveal" id="faqTabs">
head -1 testes/base/frag-passos.html      # <!-- véu (mobile): ... -->
tail -1 testes/base/frag-passos.html      # </ol>
grep -c 'class="review"' testes/base/frag-avaliacoes.html   # 14
grep -c 'class="ivid"' testes/base/frag-videos.html         # 11
grep -c 'faq__tab' testes/base/frag-faq.html                # 7 (mais 0 no tablist)
```

Se algum `head`/`tail` não bater, ajuste o intervalo até bater. Os fragmentos são a referência do teste, então precisam estar exatos.

- [ ] **Passo 2: Escrever o teste que falha**

Crie `testes/casos/40-partials.php`:

```php
<?php
declare(strict_types=1);

require_once site() . '/lib/conteudo.php';

banco_com_conteudo();

function base_frag(string $nome): string
{
    $caminho = raiz() . '/testes/base/frag-' . $nome . '.html';
    $html = file_get_contents($caminho);
    if ($html === false) {
        throw new RuntimeException('nao consegui ler ' . $caminho);
    }
    return $html;
}

function parcial(string $nome, array $vars = []): string
{
    return render(site() . '/partials/' . $nome . '.php', $vars);
}

teste('partials/modelos.php imprime a grade identica ao site atual', function (): void {
    igual(norm(base_frag('modelos')), norm(parcial('modelos', ['modalidade' => 'pronta'])));
});

teste('partials/modelos.php respeita a modalidade recebida', function (): void {
    igual('<div class="grid grid--models"></div>', norm(parcial('modelos', ['modalidade' => 'flex'])));
});

teste('o rotulo do botao de orcamento sai no formato do site atual', function (): void {
    $html = parcial('modelos', ['modalidade' => 'pronta']);
    contem('data-modelo="Compacta · 39 m² · R$ 69.900"', $html);
    contem('data-modelo="Conforto · 42,75 m² · R$ 79.988"', $html);
    contem('data-modelo="Família · 51 m² · R$ 87.997"', $html);
    contem('data-modelo="Ampla · 59,75 m² · R$ 97.776"', $html);
});

teste('partials/portfolio.php imprime o acordeao identico ao site atual', function (): void {
    igual(norm(base_frag('portfolio')), norm(parcial('portfolio')));
});

teste('partials/avaliacoes.php imprime as 14 avaliacoes identicas ao site atual', function (): void {
    igual(norm(base_frag('avaliacoes')), norm(parcial('avaliacoes')));
});

teste('partials/videos.php com os 11 imprime a trilha identica ao site atual', function (): void {
    config_gravar('videos_na_home', '11');
    $saida = parcial('videos');
    config_gravar('videos_na_home', '8');
    igual(norm(base_frag('videos')), norm($saida));
});

teste('partials/videos.php respeita o limite da home', function (): void {
    igual(8, substr_count(parcial('videos'), 'class="ivid"'));
});

teste('partials/faq.php imprime as abas e paineis identicos ao site atual', function (): void {
    igual(norm(base_frag('faq')), norm(parcial('faq', ['contexto' => 'geral'])));
});

teste('partials/passos.php imprime o scrollytelling identico ao site atual', function (): void {
    igual(norm(base_frag('passos')), norm(parcial('passos', ['contexto' => 'pronta'])));
});

teste('toda imagem impressa pelos parciais tem alt preenchido', function (): void {
    $html = parcial('modelos', ['modalidade' => 'pronta'])
          . parcial('portfolio')
          . parcial('passos', ['contexto' => 'pronta']);

    preg_match_all('/<img\b[^>]*>/u', $html, $tags);
    verdade(count($tags[0]) > 0, 'deveria haver imagens');
    foreach ($tags[0] as $tag) {
        verdade((bool) preg_match('/ alt="[^"]+"/u', $tag), 'img sem alt: ' . $tag);
    }
});

teste('nenhum parcial imprime caminho absoluto de mídia', function (): void {
    $html = parcial('modelos', ['modalidade' => 'pronta'])
          . parcial('portfolio')
          . parcial('videos')
          . parcial('passos', ['contexto' => 'pronta']);

    nao_contem('src="/uploads', $html);
    nao_contem('poster="/uploads', $html);
    nao_contem(CASTELLO_UPLOADS, $html, 'caminho de disco nunca vai para o HTML');
});

teste('o texto do banco sai escapado', function (): void {
    db()->prepare('INSERT INTO avaliacoes (nome, texto, estrelas, ativo, ordem) VALUES (?, ?, 5, 1, 99)')
        ->execute(['Teste <script>', 'Aspas "duplas" & sinal <b>']);

    $html = parcial('avaliacoes');
    contem('Teste &lt;script&gt;', $html);
    contem('Aspas &quot;duplas&quot; &amp; sinal &lt;b&gt;', $html);
    nao_contem('<script>', $html);
});
```

- [ ] **Passo 3: Rodar e ver falhar**

Rode: `php testes/smoke.php 40-partials`
Esperado: falha em todos os testes de parcial, com `Failed opening required .../partials/modelos.php`.

- [ ] **Passo 4: Escrever `partials/modelos.php`**

```php
<?php
/**
 * Grade de modelos. Espera: string $modalidade ('pronta' ou 'flex').
 * Imprime a mesma marcacao que hoje esta no index.html, trocando so os valores.
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/conteudo.php';

$lista_modelos = modelos($modalidade ?? 'pronta');
?>
<div class="grid grid--models">
<?php foreach ($lista_modelos as $m):
    $destaque = (int) $m['destaque'] === 1;
    // O rotulo do botao usa a area sem os centavos zerados, como no site atual:
    // "39,00 m²" vira "39 m²", mas "42,75 m²" fica como esta.
    $rotulo = $m['nome'] . ' · ' . str_replace(',00 ', ' ', (string) $m['area']) . ' · R$ ' . $m['preco'];
?>
        <article class="model<?= $destaque ? ' model--featured' : '' ?> reveal">
<?php if ($destaque): ?>
          <span class="model__flag">Mais escolhida</span>
<?php endif; ?>
          <div class="model__media">
            <img src="<?= e($m['foto']) ?>" alt="<?= e($m['foto_alt']) ?>" loading="lazy" />
            <span class="model__badge">Chave na mão</span>
          </div>
          <div class="model__body">
            <span class="eyebrow"><?= e($m['parede']) ?></span>
            <h3 class="model__name"><?= e($m['nome']) ?></h3>
            <p class="model__area"><?= e($m['area']) ?> de área construída</p>
            <div class="price"><span class="price__label">A partir de</span><span class="price__val"><span class="price__cur">R$</span> <?= e($m['preco']) ?></span></div>
            <button type="button" class="btn btn--primary btn--block" data-quote-open data-modelo="<?= e($rotulo) ?>">Pedir orçamento</button>
          </div>
        </article>
<?php endforeach; ?>
      </div>
```

- [ ] **Passo 5: Escrever `partials/portfolio.php`**

```php
<?php
/**
 * Galeria acordeao do portfolio. Espera: nada.
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/conteudo.php';

$lista_portfolio = portfolio();
?>
<div class="accordion reveal" id="accordion">
<?php foreach ($lista_portfolio as $i => $p): $primeiro = $i === 0; ?>
        <button class="accordion__item<?= $primeiro ? ' is-active' : '' ?>" type="button" aria-pressed="<?= $primeiro ? 'true' : 'false' ?>">
          <img src="<?= e($p['foto']) ?>" alt="<?= e($p['foto_alt']) ?>" loading="lazy" />
          <span class="accordion__veil" aria-hidden="true"></span>
          <span class="accordion__cap">
            <span class="accordion__eyebrow"><?= e($p['categoria']) ?></span>
            <span class="accordion__title"><?= e($p['titulo']) ?></span>
          </span>
        </button>
<?php endforeach; ?>
      </div>
```

- [ ] **Passo 6: Escrever `partials/avaliacoes.php`**

```php
<?php
/**
 * Trilha do carrossel de avaliacoes do Google. Espera: nada.
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/conteudo.php';

$lista_avaliacoes = avaliacoes();
?>
<div class="reviews__track" id="reviewsTrack" tabindex="0">
<?php foreach ($lista_avaliacoes as $a):
    $estrelas = max(1, min(5, (int) $a['estrelas']));
    $inicial  = mb_strtoupper(mb_substr((string) $a['nome'], 0, 1));
?>
        <article class="review">
          <div class="review__top"><div class="review__stars"><?= str_repeat('★', $estrelas) ?></div><svg class="g-logo" aria-label="Avaliação no Google"><use href="#ico-google" /></svg></div>
          <p><?= e($a['texto']) ?></p>
          <footer><span class="review__avatar"><?= e($inicial) ?></span><span class="review__who"><strong><?= e($a['nome']) ?></strong><span>Avaliação no Google</span></span></footer>
        </article>
<?php endforeach; ?>
      </div>
```

- [ ] **Passo 7: Escrever `partials/videos.php`**

```php
<?php
/**
 * Trilha de videos do Instagram. Espera: nada.
 * A quantidade exibida vem de config.videos_na_home.
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/conteudo.php';

$lista_videos = videos();
?>
<div class="insta__rail reveal" id="instaRail" aria-label="Vídeos do Instagram da Castello">
<?php foreach ($lista_videos as $v): ?>
      <article class="ivid"><div class="ivid__frame"><video class="ivid__video" playsinline loop preload="none" poster="<?= e($v['poster']) ?>"><source src="<?= e($v['arquivo']) ?>" type="video/mp4" /></video></div></article>
<?php endforeach; ?>
    </div>
```

- [ ] **Passo 8: Escrever `partials/faq.php`**

```php
<?php
/**
 * Abas verticais do FAQ. Espera: string $contexto ('geral' ou 'flex').
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/conteudo.php';

$lista_faq = faq($contexto ?? 'geral');
?>
<div class="faq__board reveal" id="faqTabs">
        <div class="faq__tablist" role="tablist" aria-orientation="vertical" aria-label="Perguntas frequentes">
<?php foreach ($lista_faq as $i => $f): $n = $i + 1; $primeiro = $i === 0; ?>
          <button class="faq__tab<?= $primeiro ? ' is-active' : '' ?>" type="button" role="tab" id="faq-tab<?= $n ?>" aria-selected="<?= $primeiro ? 'true' : 'false' ?>" aria-controls="faq-panel<?= $n ?>"<?= $primeiro ? '' : ' tabindex="-1"' ?> aria-label="<?= e($f['pergunta']) ?>" title="<?= e($f['pergunta']) ?>">
            <span class="faq__ico"><?= icone_faq((string) $f['icone']) ?></span>
          </button>
<?php endforeach; ?>
        </div>

        <div class="faq__panels">
<?php foreach ($lista_faq as $i => $f): $n = $i + 1; $primeiro = $i === 0; ?>
          <div class="faq__content<?= $primeiro ? ' is-active' : '' ?>" role="tabpanel" id="faq-panel<?= $n ?>" aria-labelledby="faq-tab<?= $n ?>" tabindex="0"<?= $primeiro ? '' : ' hidden' ?>>
            <h3 class="faq__content-title"><?= e($f['pergunta']) ?></h3>
            <p class="faq__content-body"><?= e($f['resposta']) ?></p>
          </div>
<?php endforeach; ?>
        </div>
      </div>
```

- [ ] **Passo 9: Escrever `partials/passos.php`**

```php
<?php
/**
 * Passo a passo com imagem sticky. Espera: string $contexto ('pronta' ou 'flex').
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/conteudo.php';

$lista_passos = passos($contexto ?? 'pronta');
$total_passos = count($lista_passos);
?>
<!-- véu (mobile): apaga o texto do passo antes dele encostar na imagem sticky -->
        <div class="process__veil" aria-hidden="true"></div>
        <div class="process__media" id="processMedia">
<?php foreach ($lista_passos as $i => $p): ?>
          <div class="process__media-fig<?= $i === 0 ? ' is-active' : '' ?>" data-step="<?= $i ?>"><img src="<?= e($p['imagem']) ?>" alt="<?= e($p['imagem_alt']) ?>" loading="lazy" /></div>
<?php endforeach; ?>
          <div class="process__counter" id="processCounter"><span>01</span> / <?= sprintf('%02d', $total_passos) ?></div>
        </div>

        <ol class="process__steps" id="processSteps">
<?php foreach ($lista_passos as $i => $p): ?>
          <li class="process__step<?= $i === 0 ? ' is-active' : '' ?>" data-step="<?= $i ?>"><span class="process__step-num">PASSO <?= sprintf('%02d', $i + 1) ?></span><h3><?= e($p['titulo']) ?></h3><p><?= e($p['texto']) ?></p></li>
<?php endforeach; ?>
        </ol>
```

- [ ] **Passo 10: Rodar e ver passar**

Rode: `php testes/smoke.php 40-partials`
Esperado: 12 ok, 0 falha, 0 pulado.

Se algum `igual()` de fragmento falhar, a mensagem mostra os dois lados normalizados. Compare caractere a caractere: a diferença costuma ser um atributo fora de ordem ou um espaço dentro de um atributo, que o `norm()` não colapsa de propósito.

Rode a suíte inteira: `php testes/smoke.php`
Esperado: `todos os casos passaram`.

- [ ] **Passo 11: Commit**

```bash
git add public_html/partials testes/base testes/casos/40-partials.php
git commit -m "feat: os seis parciais imprimem o mesmo HTML do site, lendo do banco

Os fragmentos em testes/base sao recortes literais do index.html e viram a
referencia do teste: cada parcial e comparado com o HTML original,
normalizando so os caminhos que passaram para uploads.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Tarefa 6: `index.php` e `flex.php`

**Arquivos:**
- Renomear (`git mv`): `public_html/index.html` para `public_html/index.php`
- Criar: `public_html/partials/nav.php`, `public_html/partials/rodape.php`, `public_html/partials/modal.php`
- Criar: `public_html/flex.php`
- Criar: `testes/base/home-original.html` (cópia do `index.html` antes da conversão)
- Teste: `testes/casos/50-paginas.php`

**Interfaces:**
- Consome: todos os parciais da Tarefa 5; `modelos()`, `videos()`, `bloco()`, `e()`.
- Produz: `partials/nav.php` (sprite do Google, nav e drawer, não espera nada), `partials/rodape.php` (rodapé e contato, não espera nada), `partials/modal.php` (modal de orçamento, lightbox dos reels e botão flutuante, não espera nada), `index.php` e `flex.php`.

- [ ] **Passo 1: Guardar a home original como referência e escrever o teste que falha**

```bash
cp public_html/index.html testes/base/home-original.html
```

Crie `testes/casos/50-paginas.php`:

```php
<?php
declare(strict_types=1);

require_once site() . '/lib/conteudo.php';

banco_com_conteudo();

teste('index.php renderiza a home identica ao index.html original', function (): void {
    config_gravar('videos_na_home', '11');
    $novo = render(site() . '/index.php');
    config_gravar('videos_na_home', '8');

    $velho = file_get_contents(raiz() . '/testes/base/home-original.html');
    verdade($velho !== false, 'testes/base/home-original.html precisa existir');

    // Sem excecao de alt: a unica diferenca legitima e o caminho da midia, que
    // norm() desfaz. Todo o resto sai byte a byte igual ao prototipo.
    igual(norm((string) $velho), norm($novo));
});

teste('index.php nao vaza codigo PHP nem aviso do PHP', function (): void {
    $html = render(site() . '/index.php');
    nao_contem('<?php', $html);
    nao_contem('<?=', $html);
    nao_contem('Warning:', $html);
    nao_contem('Notice:', $html);
    nao_contem('Deprecated:', $html);
    nao_contem('Fatal error', $html);
});

teste('a home respeita o limite de videos da config', function (): void {
    $html = render(site() . '/index.php');
    igual(8, substr_count($html, 'class="ivid"'));
    contem('<span id="reelboxCount">1 / 8</span>', $html);
});

teste('o select de modelo do formulario sai do banco', function (): void {
    $html = render(site() . '/index.php');
    contem('<option value="Compacta · 39 m² · R$ 69.900">Compacta · 39 m² · R$ 69.900</option>', $html);
    contem('<option value="Ampla · 59,75 m² · R$ 97.776">Ampla · 59,75 m² · R$ 97.776</option>', $html);
    contem('<option value="">Ainda não sei</option>', $html);
});

teste('a home traz nav, rodape e modal pelos parciais compartilhados', function (): void {
    $html = render(site() . '/index.php');
    contem('<header class="nav" id="nav">', $html);
    contem('id="ico-google"', $html);
    contem('<footer class="footer" id="contato">', $html);
    contem('id="quoteModal"', $html);
    contem('id="reelbox"', $html);
    contem('id="wppFloat"', $html);
    contem('<script src="js/main.js?v=12"></script>', $html);
});

teste('flex.php renderiza sem erro mesmo com o conteudo da Flex ainda vazio', function (): void {
    $html = render(site() . '/flex.php');
    contem('<!DOCTYPE html>', $html);
    contem('<header class="nav" id="nav">', $html);
    contem('<footer class="footer" id="contato">', $html);
    contem('id="quoteModal"', $html);
    contem('45 dias', $html, 'o prazo da Flex vem do bloco flex_prazo');
    nao_contem('Warning:', $html);
    nao_contem('Fatal error', $html);
    nao_contem('<?php', $html);
    nao_contem('class="grid grid--models"', $html, 'sem modelo Flex cadastrado, a grade nao aparece');
    nao_contem('id="faqTabs"', $html, 'sem FAQ Flex cadastrado, o bloco nao aparece');
    nao_contem('id="flex-depois"', $html, 'sem texto escrito, a secao nao aparece');
});

teste('flex.php tem um formulario so, o modal compartilhado', function (): void {
    $html = render(site() . '/flex.php');
    igual(1, substr_count($html, '<form'), 'nenhum formulario inline alem do modal');
    contem('data-quote-open', $html);
});

teste('flex.php monta as oito secoes assim que o conteudo Flex existir', function (): void {
    db()->exec("INSERT INTO modelos (modalidade, nome, area, parede, preco, foto, foto_alt, ativo, ordem)
                VALUES ('flex','Flex 30','30,00 m²','Parede simples','39.900','uploads/modelos/casa1.png','Casa Flex',1,1)");
    db()->exec("INSERT INTO faq (pergunta, resposta, icone, contexto, ativo, ordem)
                VALUES ('O que a Castello entrega na Flex?','A estrutura montada e fechada.','chave','flex',1,1)");
    db()->exec("INSERT INTO passos (contexto, titulo, texto, imagem, ativo, ordem)
                VALUES ('flex','Entrega da estrutura','A Castello entrega e monta.','uploads/passos/passo-1.jpg',1,1)");

    $textos = [
        'flexpg_hero_titulo'   => 'Sua casa começa montada',
        'flexpg_hero_texto'    => 'A estrutura pronta em 45 dias, o acabamento no seu tempo.',
        'flexpg_oque_titulo'   => 'O que é a Castelo Flex',
        'flexpg_oque_texto'    => 'A Castello entrega a casa fechada e montada no seu terreno.',
        'flexpg_depois_titulo' => 'O que fica por sua conta',
        'flexpg_depois_texto'  => 'Acabamento interno, elétrica, hidráulica e revestimentos.',
        'flexpg_catalogo_nota' => 'Valores de referência para a estrutura montada.',
        'flexpg_cta_titulo'    => 'Quer um orçamento da Castelo Flex?',
        'flexpg_cta_texto'     => 'Conte o tamanho que você imagina e a gente volta com uma proposta.',
        'flex_video'           => 'uploads/videos/insta-01.mp4',
        'flex_video_poster'    => 'uploads/videos/insta-01.jpg',
    ];
    $st = db()->prepare('UPDATE blocos SET valor = ? WHERE chave = ?');
    foreach ($textos as $chave => $valor) {
        $st->execute([$valor, $chave]);
    }

    $html = render(site() . '/flex.php');

    foreach ($textos as $chave => $valor) {
        if (str_starts_with($chave, 'flexpg_')) {
            contem(e($valor), $html, "o bloco $chave precisa aparecer na pagina");
        }
    }

    contem('id="flex-oque"', $html);
    contem('id="flex-passos"', $html);
    contem('id="flex-depois"', $html);
    contem('id="flex-catalogo"', $html);
    contem('id="flex-prazo"', $html);
    contem('id="flex-faq"', $html);
    contem('class="cta-band"', $html);

    contem('class="grid grid--models"', $html);
    contem('data-modelo="Flex 30 · 30 m² · R$ 39.900"', $html);
    contem('id="faqTabs"', $html);
    contem('id="processSteps"', $html);
    contem('<source src="uploads/videos/insta-01.mp4" type="video/mp4" />', $html);
});
```

- [ ] **Passo 2: Rodar e ver falhar**

Rode: `php testes/smoke.php 50-paginas`
Esperado: falha, `Failed opening required .../public_html/index.php` (o arquivo ainda se chama `index.html`).

- [ ] **Passo 3: Extrair nav, rodapé e modal para parciais**

As linhas abaixo valem para o `index.html` ainda intocado.

```bash
{ echo '<?php'; echo 'declare(strict_types=1);'; echo '?>'; sed -n '33,81p' public_html/index.html; } > public_html/partials/nav.php
{ echo '<?php'; echo 'declare(strict_types=1);'; echo '?>'; sed -n '659,722p' public_html/index.html; } > public_html/partials/rodape.php
sed -n '724,842p' public_html/index.html > /tmp/castello-modal.html
```

Confira: `head -4 public_html/partials/nav.php` mostra as três linhas de PHP e o comentário do sprite; `tail -1 public_html/partials/nav.php` mostra o `</div>` que fecha o drawer; `head -1 public_html/partials/rodape.php` (depois das linhas de PHP) mostra o comentário `FOOTER / CONTATO` e `tail -1` mostra `</footer>`.

O `modal.php` não é cópia pura: duas partes dele passam a sair do banco. Crie `public_html/partials/modal.php` colando o conteúdo de `/tmp/castello-modal.html` com o cabeçalho abaixo e as duas trocas descritas:

```php
<?php
/**
 * Modal de orcamento, lightbox dos reels e botao flutuante.
 * Compartilhado pela home e pela pagina Flex. Espera: nada.
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/conteudo.php';

$modal_modelos = modelos('pronta');
$modal_videos  = count(videos());
?>
```

**Troca 1**, dentro de `<select id="q-modelo" name="modelo">`: apague as quatro `<option>` fixas e deixe

```php
            <select id="q-modelo" name="modelo">
              <option value="">Ainda não sei</option>
<?php foreach ($modal_modelos as $m):
    $rotulo = $m['nome'] . ' · ' . str_replace(',00 ', ' ', (string) $m['area']) . ' · R$ ' . $m['preco'];
?>
              <option value="<?= e($rotulo) ?>"><?= e($rotulo) ?></option>
<?php endforeach; ?>
            </select>
```

**Troca 2**, no contador do lightbox: onde está `<span id="reelboxCount">1 / 11</span>`, deixe

```php
    <div class="reelbox__count"><span id="reelboxCount">1 / <?= $modal_videos ?></span></div>
```

Depois apague o arquivo temporário: `rm /tmp/castello-modal.html`.

- [ ] **Passo 4: Converter o `index.html` em `index.php`**

```bash
git mv public_html/index.html public_html/index.php
```

Agora edite `public_html/index.php`:

1. **No topo do arquivo**, antes do `<!DOCTYPE html>`, insira exatamente:

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/conteudo.php';
?>
```

2. **Linhas 33 a 81** (sprite, nav e drawer): troque o bloco inteiro por

```php
<?php include __DIR__ . '/partials/nav.php'; ?>
```

3. **Dentro da seção `#modelos`**, troque o `<div class="grid grid--models"> ... </div>` inteiro por

```php
<?php $modalidade = 'pronta'; include __DIR__ . '/partials/modelos.php'; ?>
```

4. **Dentro da seção `#portfolio`**, troque o `<div class="accordion reveal" id="accordion"> ... </div>` inteiro por

```php
<?php include __DIR__ . '/partials/portfolio.php'; ?>
```

5. **Dentro de `<div class="process__inner">`**, troque tudo entre a abertura e o `</div>` de fechamento (o comentário do véu, o `.process__media` e o `<ol class="process__steps">`) por

```php
<?php $contexto = 'pronta'; include __DIR__ . '/partials/passos.php'; ?>
```

6. **Dentro da seção `#depoimentos`**, troque o `<div class="reviews__track" id="reviewsTrack" tabindex="0"> ... </div>` inteiro por

```php
<?php include __DIR__ . '/partials/avaliacoes.php'; ?>
```

7. **Dentro da seção `#instagram`**, troque o `<div class="insta__rail reveal" id="instaRail" ...> ... </div>` inteiro por

```php
<?php include __DIR__ . '/partials/videos.php'; ?>
```

8. **Dentro da seção `#faq`**, troque o `<div class="faq__board reveal" id="faqTabs"> ... </div>` inteiro por

```php
<?php $contexto = 'geral'; include __DIR__ . '/partials/faq.php'; ?>
```

9. **Do comentário `FOOTER / CONTATO` até `</footer>`**: troque por

```php
<?php include __DIR__ . '/partials/rodape.php'; ?>
```

10. **Do comentário `MODAL DE ORÇAMENTO` até o `</button>` que fecha o `#wppFloat`**: troque por

```php
<?php include __DIR__ . '/partials/modal.php'; ?>
```

Nada mais muda: `<head>`, hero, prova social, vantagens, cabeçalhos de seção, faixa de CTA e a tag do `js/main.js` ficam exatamente como estão, inclusive o `noindex`, que só sai na ida para produção.

- [ ] **Passo 5: Criar o `flex.php`**

Crie `public_html/flex.php`:

A página tem oito blocos e **todos** os textos saem de `blocos`, usando as nove chaves `flexpg_*` do contrato mais as quatro `flex_*`. Nenhum texto fica preso no PHP: se ficasse, o cliente não conseguiria editá-lo pelo painel, que é o motivo de o painel existir. Um bloco cujo texto ainda não foi escrito simplesmente não é impresso.

```php
<?php
declare(strict_types=1);

/**
 * Pagina Castelo Flex. Esqueleto: nav, rodape e modal ja compartilhados com a
 * home, e as oito secoes aparecendo conforme o cliente preenche os textos e
 * cadastra modelo, passo a passo e FAQ da modalidade flex.
 *
 * Nao existe formulario embutido aqui: o site tem um formulario so, no modal,
 * aberto por data-quote-open. A costura da Fase 2 troca este miolo pela
 * marcacao de front/flex.html.
 */

require_once __DIR__ . '/lib/conteudo.php';

$flex_modelos = modelos('flex');
$flex_passos  = passos('flex');
$flex_faq     = faq('flex');
$flex_video   = bloco('flex_video');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <!-- PROTÓTIPO: noindex enquanto não for produção -->
  <meta name="robots" content="noindex, nofollow" />

  <title>Castelo Flex | Castello Casas de Madeira</title>
  <meta name="description" content="Castelo Flex: casas de madeira semiprontas da Castello, com estrutura montada em 45 dias." />

  <link rel="icon" type="image/png" href="images/icone-colorido.png" />

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet" />

  <link rel="stylesheet" href="css/style.css?v=12" />
</head>
<body>

<?php include __DIR__ . '/partials/nav.php'; ?>

  <main id="topo">

    <!-- 1. Topo da pagina Flex -->
    <section class="section section--sand">
      <div class="container">
        <div class="section__head">
          <span class="eyebrow reveal">Castelo Flex</span>
          <h1 class="section__title reveal"><?= e(bloco('flexpg_hero_titulo', 'Castelo Flex')) ?></h1>
<?php if (bloco('flexpg_hero_texto') !== ''): ?>
          <p class="section__lead reveal"><?= e(bloco('flexpg_hero_texto')) ?></p>
<?php endif; ?>
        </div>
      </div>
    </section>

    <!-- 2. O que e a Castelo Flex, com o video explicativo -->
    <section class="section" id="flex-oque">
      <div class="container">
<?php if (bloco('flexpg_oque_titulo') !== ''): ?>
        <div class="section__head">
          <h2 class="section__title reveal"><?= e(bloco('flexpg_oque_titulo')) ?></h2>
<?php if (bloco('flexpg_oque_texto') !== ''): ?>
          <p class="section__lead reveal"><?= e(bloco('flexpg_oque_texto')) ?></p>
<?php endif; ?>
        </div>
<?php endif; ?>

<?php if ($flex_video !== ''): ?>
        <video class="flex__video reveal" controls playsinline preload="none" poster="<?= e(bloco('flex_video_poster')) ?>">
          <source src="<?= e($flex_video) ?>" type="video/mp4" />
        </video>
<?php endif; ?>
      </div>
    </section>

    <!-- 3. Passo a passo: ate onde vai a entrega da Castello -->
<?php if ($flex_passos !== []): ?>
    <section class="section process" id="flex-passos">
      <div class="container">
        <div class="process__inner">
<?php $contexto = 'flex'; include __DIR__ . '/partials/passos.php'; ?>
        </div>
      </div>
    </section>
<?php endif; ?>

    <!-- 4. O que fica por conta do cliente -->
<?php if (bloco('flexpg_depois_titulo') !== '' || bloco('flexpg_depois_texto') !== ''): ?>
    <section class="section section--sand" id="flex-depois">
      <div class="container">
        <div class="section__head">
          <h2 class="section__title reveal"><?= e(bloco('flexpg_depois_titulo')) ?></h2>
<?php if (bloco('flexpg_depois_texto') !== ''): ?>
          <p class="section__lead reveal"><?= e(bloco('flexpg_depois_texto')) ?></p>
<?php endif; ?>
        </div>
      </div>
    </section>
<?php endif; ?>

    <!-- 5. Catalogo das casas Flex -->
<?php if ($flex_modelos !== []): ?>
    <section class="section modelos" id="flex-catalogo">
      <div class="container">
<?php $modalidade = 'flex'; include __DIR__ . '/partials/modelos.php'; ?>
<?php if (bloco('flexpg_catalogo_nota') !== ''): ?>
        <p class="modelos__note reveal"><?= e(bloco('flexpg_catalogo_nota')) ?></p>
<?php endif; ?>
      </div>
    </section>
<?php endif; ?>

    <!-- 6. Prazo da modalidade -->
    <section class="section" id="flex-prazo">
      <div class="container">
        <p class="section__lead reveal">Estrutura montada em <?= e(bloco('flex_prazo', '45 dias')) ?>.</p>
      </div>
    </section>

    <!-- 7. FAQ da Flex -->
<?php if ($flex_faq !== []): ?>
    <section class="section faq" id="flex-faq" aria-label="Perguntas frequentes da Castelo Flex">
      <div class="container">
<?php $contexto = 'flex'; include __DIR__ . '/partials/faq.php'; ?>
      </div>
    </section>
<?php endif; ?>

    <!-- 8. Faixa de orcamento. O formulario e o modal compartilhado. -->
    <section class="cta-band">
      <div class="container cta-band__inner">
        <div class="reveal">
          <h2><?= e(bloco('flexpg_cta_titulo', 'Quer um orçamento da Castelo Flex?')) ?></h2>
<?php if (bloco('flexpg_cta_texto') !== ''): ?>
          <p><?= e(bloco('flexpg_cta_texto')) ?></p>
<?php endif; ?>
        </div>
        <button type="button" class="btn btn--light btn--lg reveal" data-quote-open>Pedir orçamento</button>
      </div>
    </section>

  </main>

<?php include __DIR__ . '/partials/rodape.php'; ?>
<?php include __DIR__ . '/partials/modal.php'; ?>

  <script src="js/main.js?v=12"></script>
</body>
</html>
```

- [ ] **Passo 6: Rodar e ver passar**

Rode: `php testes/smoke.php 50-paginas`
Esperado: 8 ok, 0 falha, 0 pulado.

Se o primeiro teste falhar, a mensagem traz os dois HTML normalizados. A diferença quase sempre é uma linha em branco a mais dentro de um atributo ou uma tag que ficou duplicada na hora do recorte.

Rode a suíte inteira: `php testes/smoke.php`
Esperado: `todos os casos passaram`.

- [ ] **Passo 7: Conferir no navegador**

```bash
php -S localhost:8000 -t public_html
```

Abra `http://localhost:8000/` e `http://localhost:8000/flex.php`. Na home, confira com os próprios olhos: hero com vídeo, quatro modelos com preço, acordeão do portfólio abrindo, cinco passos com imagem sticky, carrossel de avaliações arrastando, oito vídeos do Instagram abrindo em tela cheia, sete abas de FAQ trocando, modal de orçamento abrindo pelos CTAs. Nenhum erro no console.

- [ ] **Passo 8: Commit**

```bash
git add -A
git commit -m "feat: index.php montado por PHP com o mesmo HTML de saida do prototipo

Nav, rodape e modal viram parciais compartilhados com a nova flex.php. O
teste 50-paginas compara a saida do index.php com a copia do index.html
guardada em testes/base, provando que a troca de origem do conteudo nao
mudou o resultado.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Tarefa 7: `lib/auth.php`

**Arquivos:**
- Criar: `public_html/lib/auth.php`
- Teste: `testes/casos/60-auth.php`

**Interfaces:**
- Consome: `db()`, `agora()` da Tarefa 2; `migrar_usuario()` da Tarefa 4 (só no teste).
- Produz: `auth_iniciar(): void`, `auth_logado(): bool`, `auth_exigir(): void`, `auth_entrar(string $login, string $senha): bool`, `auth_sair(): void`, `auth_bloqueado(string $ip): bool`, `auth_registrar_falha(string $ip): void`, `csrf_token(): string`, `csrf_validar(?string $token): bool`, e as constantes `AUTH_MAX_TENTATIVAS` (5), `AUTH_BLOQUEIO_MINUTOS` (15), `AUTH_INATIVIDADE` (7200), `AUTH_LOGIN_URL` (`index.php`, relativo à pasta do painel).

**Por que o destino do login é relativo.** O contrato descreve `auth_exigir()` como "redireciona para `/painel/`". No servidor de teste o site mora em uma subpasta do domínio, então um caminho começando com barra apontaria para fora dele. O destino é o mesmo lugar, escrito de forma relativa: `index.php` a partir de `painel/`, e `../index.php` a partir de `painel/acoes/`.

**Detalhe do ambiente que muda o código.** Na linha de comando o PHP não consegue abrir sessão de verdade depois que já houve saída, e o runner imprime antes de rodar o caso. Por isso `auth_iniciar()` só chama `session_start()` quando `headers_sent()` é falso; caso contrário trabalha com `$_SESSION` como array em memória. No navegador, que é o que importa, a sessão real sempre abre, porque `auth_iniciar()` roda antes de qualquer saída.

- [ ] **Passo 1: Escrever o teste que falha**

Crie `testes/casos/60-auth.php`:

```php
<?php
declare(strict_types=1);

require_once site() . '/lib/auth.php';
require_once site() . '/migrar.php';

migrar_usuario('castello', 'senha-de-teste-forte');

teste('a senha e guardada em bcrypt e nunca em texto puro', function (): void {
    $hash = (string) db()->query("SELECT senha_hash FROM usuarios WHERE login = 'castello'")->fetchColumn();
    verdade(str_starts_with($hash, '$2y$'), 'o hash precisa ser bcrypt');
    verdade(password_verify('senha-de-teste-forte', $hash));
    nao_contem('senha-de-teste-forte', $hash);
});

teste('login com a senha certa entra e marca o ultimo acesso', function (): void {
    verdade(auth_entrar('castello', 'senha-de-teste-forte'));
    verdade(auth_logado());

    $u = db()->query("SELECT ultimo_acesso FROM usuarios WHERE login = 'castello'")->fetch();
    verdade((bool) preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $u['ultimo_acesso']));

    auth_sair();
    falso(auth_logado());
});

teste('login com senha errada e com login inexistente recusa', function (): void {
    falso(auth_entrar('castello', 'errada'));
    falso(auth_logado());
    falso(auth_entrar('naoexiste', 'seja-la-o-que-for'));
    db()->exec('DELETE FROM login_tentativas');
});

teste('cinco tentativas erradas bloqueiam o IP por 15 minutos', function (): void {
    db()->exec('DELETE FROM login_tentativas');
    $ip = '203.0.113.7';

    for ($i = 1; $i <= 4; $i++) {
        auth_registrar_falha($ip);
        falso(auth_bloqueado($ip), "com $i tentativas o IP ainda nao pode estar bloqueado");
    }

    auth_registrar_falha($ip);
    verdade(auth_bloqueado($ip), 'na quinta tentativa o IP bloqueia');

    $linha = db()->query("SELECT * FROM login_tentativas WHERE ip = '203.0.113.7'")->fetch();
    igual(5, (int) $linha['tentativas']);

    $faltam = strtotime((string) $linha['bloqueado_ate']) - time();
    verdade($faltam > 13 * 60 && $faltam <= 15 * 60, 'o bloqueio dura cerca de 15 minutos, faltam ' . $faltam . 's');
});

teste('o bloqueio vence sozinho e limpa o contador', function (): void {
    db()->exec("INSERT OR REPLACE INTO login_tentativas (ip, tentativas, bloqueado_ate)
                VALUES ('198.51.100.9', 5, '" . date('Y-m-d H:i:s', time() - 60) . "')");

    falso(auth_bloqueado('198.51.100.9'), 'bloqueio vencido nao bloqueia mais');
    igual(0, (int) db()->query("SELECT COUNT(*) FROM login_tentativas WHERE ip = '198.51.100.9'")->fetchColumn());
});

teste('IP bloqueado nao entra nem com a senha certa', function (): void {
    db()->exec('DELETE FROM login_tentativas');
    $_SERVER['REMOTE_ADDR'] = '203.0.113.20';

    for ($i = 1; $i <= 5; $i++) {
        falso(auth_entrar('castello', 'errada'));
    }
    verdade(auth_bloqueado('203.0.113.20'));
    falso(auth_entrar('castello', 'senha-de-teste-forte'), 'IP bloqueado nao entra nem acertando');

    db()->exec('DELETE FROM login_tentativas');
    verdade(auth_entrar('castello', 'senha-de-teste-forte'), 'sem bloqueio, entra normalmente');
    igual(0, (int) db()->query("SELECT COUNT(*) FROM login_tentativas WHERE ip = '203.0.113.20'")->fetchColumn(),
        'entrar limpa as tentativas do IP');
    unset($_SERVER['REMOTE_ADDR']);
});

teste('a sessao expira com duas horas de inatividade', function (): void {
    igual(7200, AUTH_INATIVIDADE);
    verdade(auth_entrar('castello', 'senha-de-teste-forte'));
    verdade(auth_logado());

    $_SESSION['visto_em'] = time() - (AUTH_INATIVIDADE + 60);
    falso(auth_logado(), 'passou o tempo de inatividade, tem que deslogar');

    verdade(auth_entrar('castello', 'senha-de-teste-forte'));
    $_SESSION['visto_em'] = time() - (AUTH_INATIVIDADE - 60);
    verdade(auth_logado(), 'dentro do tempo, continua logado');
});

teste('csrf_token e estavel na sessao e csrf_validar so aceita o token certo', function (): void {
    $token = csrf_token();
    verdade(strlen($token) >= 32, 'token curto demais');
    igual($token, csrf_token(), 'o token nao pode mudar a cada chamada');

    verdade(csrf_validar($token));
    falso(csrf_validar('outro'));
    falso(csrf_validar(''));
    falso(csrf_validar(null));
});

teste('sair apaga a sessao e o token', function (): void {
    verdade(auth_entrar('castello', 'senha-de-teste-forte'));
    csrf_token();
    auth_sair();

    falso(auth_logado());
    igual([], array_diff_key($_SESSION, ['visto_em' => 1]), 'a sessao fica so com o carimbo de tempo');
});

teste('auth_exigir passa direto quando o usuario esta logado', function (): void {
    verdade(auth_entrar('castello', 'senha-de-teste-forte'));
    auth_exigir();
    verdade(true, 'auth_exigir nao pode interromper quem esta logado');
});

teste('as constantes da trava sao as do contrato', function (): void {
    igual(5, AUTH_MAX_TENTATIVAS);
    igual(15, AUTH_BLOQUEIO_MINUTOS);
    igual(7200, AUTH_INATIVIDADE);
});

teste('o destino do login e relativo, para funcionar em subpasta', function (): void {
    igual('index.php', AUTH_LOGIN_URL);
    falso(str_starts_with(AUTH_LOGIN_URL, '/'), 'caminho absoluto quebraria o painel em subpasta');
});
```

- [ ] **Passo 2: Rodar e ver falhar**

Rode: `php testes/smoke.php 60-auth`
Esperado: erro fatal `Failed opening required .../public_html/lib/auth.php`.

- [ ] **Passo 3: Escrever o `lib/auth.php`**

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Sessao, login, trava de forca bruta e CSRF do painel.
 *
 * Um usuario so, sem recuperacao automatica de senha, como decidiu a spec.
 * A Freela redefine manualmente quando precisar.
 */

const AUTH_MAX_TENTATIVAS   = 5;
const AUTH_BLOQUEIO_MINUTOS = 15;
const AUTH_INATIVIDADE      = 7200;

/**
 * Destino do redirecionamento de quem nao esta logado, relativo a pasta do
 * painel. E relativo de proposito: no servidor de teste o site fica em uma
 * subpasta, e um caminho comecando com barra apontaria para fora dele.
 */
const AUTH_LOGIN_URL = 'index.php';

/**
 * Abre a sessao e aplica a expiracao por inatividade.
 *
 * Na linha de comando, quando ja houve saida, session_start() nao funciona.
 * Nesse caso $_SESSION vira um array simples em memoria, o que mantem toda a
 * logica testavel sem afetar o comportamento no navegador.
 */
function auth_iniciar(): void
{
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
              || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_name('castello_painel');
        session_start();
    }

    if (!isset($_SESSION) || !is_array($_SESSION)) {
        $_SESSION = [];
    }

    if (isset($_SESSION['visto_em']) && (time() - (int) $_SESSION['visto_em']) > AUTH_INATIVIDADE) {
        $_SESSION = [];
    }

    $_SESSION['visto_em'] = time();
}

function auth_logado(): bool
{
    auth_iniciar();

    return isset($_SESSION['usuario_id']) && (int) $_SESSION['usuario_id'] > 0;
}

/**
 * Manda para o login quem nao esta logado.
 *
 * Os arquivos de painel/acoes/ estao um nivel abaixo, entao o destino ganha
 * o ../ na frente. Tudo relativo, para o painel funcionar tambem quando o
 * site mora em uma subpasta do dominio.
 */
function auth_exigir(): void
{
    if (auth_logado()) {
        return;
    }

    $script  = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $destino = str_contains($script, '/acoes/') ? '../' . AUTH_LOGIN_URL : AUTH_LOGIN_URL;

    header('Location: ' . $destino);
    exit;
}

function auth_entrar(string $login, string $senha): bool
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli');

    if (auth_bloqueado($ip)) {
        return false;
    }

    $st = db()->prepare('SELECT id, nome, senha_hash FROM usuarios WHERE login = ?');
    $st->execute([$login]);
    $usuario = $st->fetch();

    if ($usuario === false || !password_verify($senha, (string) $usuario['senha_hash'])) {
        auth_registrar_falha($ip);
        return false;
    }

    auth_iniciar();
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    $_SESSION['usuario_id']   = (int) $usuario['id'];
    $_SESSION['usuario_nome'] = (string) ($usuario['nome'] ?? '');
    $_SESSION['visto_em']     = time();

    db()->prepare('UPDATE usuarios SET ultimo_acesso = ? WHERE id = ?')->execute([agora(), $usuario['id']]);
    db()->prepare('DELETE FROM login_tentativas WHERE ip = ?')->execute([$ip]);

    return true;
}

function auth_sair(): void
{
    auth_iniciar();
    $_SESSION = [];

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_unset();
        session_destroy();
    }

    $_SESSION = ['visto_em' => time()];
}

function auth_bloqueado(string $ip): bool
{
    $st = db()->prepare('SELECT bloqueado_ate FROM login_tentativas WHERE ip = ?');
    $st->execute([$ip]);
    $ate = $st->fetchColumn();

    if ($ate === false || $ate === null || $ate === '') {
        return false;
    }

    if (strtotime((string) $ate) > time()) {
        return true;
    }

    db()->prepare('DELETE FROM login_tentativas WHERE ip = ?')->execute([$ip]);

    return false;
}

function auth_registrar_falha(string $ip): void
{
    $st = db()->prepare('SELECT tentativas FROM login_tentativas WHERE ip = ?');
    $st->execute([$ip]);
    $tentativas = (int) $st->fetchColumn() + 1;

    $bloqueado = $tentativas >= AUTH_MAX_TENTATIVAS
        ? date('Y-m-d H:i:s', time() + AUTH_BLOQUEIO_MINUTOS * 60)
        : null;

    db()->prepare(
        'INSERT INTO login_tentativas (ip, tentativas, bloqueado_ate) VALUES (?, ?, ?)
         ON CONFLICT(ip) DO UPDATE SET tentativas = excluded.tentativas, bloqueado_ate = excluded.bloqueado_ate'
    )->execute([$ip, $tentativas, $bloqueado]);
}

function csrf_token(): string
{
    auth_iniciar();

    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['csrf'];
}

function csrf_validar(?string $token): bool
{
    auth_iniciar();

    if ($token === null || $token === '' || empty($_SESSION['csrf'])) {
        return false;
    }

    return hash_equals((string) $_SESSION['csrf'], $token);
}
```

- [ ] **Passo 4: Rodar e ver passar**

Rode: `php testes/smoke.php 60-auth`
Esperado: 12 ok, 0 falha, 0 pulado.

Rode a suíte inteira: `php testes/smoke.php`
Esperado: `todos os casos passaram`.

- [ ] **Passo 5: Commit**

```bash
git add public_html/lib/auth.php testes/casos/60-auth.php
git commit -m "feat: login com bcrypt, trava de forca bruta, CSRF e sessao de 2 horas

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Tarefa 8: `lib/upload.php`

**Arquivos:**
- Criar: `public_html/lib/upload.php`
- Teste: `testes/casos/70-upload.php`

**Interfaces:**
- Consome: `db()` da Tarefa 2 (só para carregar as constantes de caminho).
- Produz: `upload_receber(array $arquivo, string $pasta, string $tipo): array` devolvendo `array{ok: bool, caminho: ?string, erro: ?string}`; `upload_slug(string $nome): string`; as constantes `UPLOAD_PASTAS` (`modelos`, `portfolio`, `videos`, `passos`) e `UPLOAD_TIPOS` (limites e MIME aceitos por tipo); e `CASTELLO_UPLOADS` quando ninguém a definiu antes.

Códigos de erro devolvidos, todos em `snake_case`: `pasta_invalida`, `tipo_invalido`, `sem_arquivo`, `erro_upload`, `tamanho`, `tipo`, `finfo_indisponivel`, `gravacao`.

- [ ] **Passo 1: Escrever o teste que falha**

Crie `testes/casos/70-upload.php`:

```php
<?php
declare(strict_types=1);

require_once site() . '/lib/upload.php';

/** Cria um arquivo temporario e devolve o array no formato de $_FILES. */
function arquivo_falso(string $nome, string $conteudo): array
{
    $tmp = tempnam(sys_get_temp_dir(), 'castello-up');
    file_put_contents($tmp, $conteudo);

    return ['name' => $nome, 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => strlen($conteudo)];
}

function imagem_falsa(string $formato): string
{
    $img = imagecreatetruecolor(60, 40);
    imagefilledrectangle($img, 0, 0, 59, 39, imagecolorallocate($img, 200, 30, 40));

    ob_start();
    match ($formato) {
        'jpg'  => imagejpeg($img, null, 90),
        'png'  => imagepng($img),
        'webp' => imagewebp($img),
        'gif'  => imagegif($img),
    };
    $bytes = (string) ob_get_clean();
    imagedestroy($img);

    return $bytes;
}

teste('imagem jpeg valida e aceita, renomeada e gravada em uploads', function (): void {
    $r = upload_receber(arquivo_falso('Foto da Casa Bonita.jpg', imagem_falsa('jpg')), 'modelos', 'imagem');

    verdade($r['ok'], 'erro: ' . var_export($r['erro'], true));
    igual(null, $r['erro']);
    verdade(str_starts_with((string) $r['caminho'], 'uploads/modelos/'));
    verdade((bool) preg_match('#^uploads/modelos/foto-da-casa-bonita-[0-9a-f]{6}\.jpg$#', (string) $r['caminho']),
        'nome esperado: slug mais 6 caracteres aleatorios, obtido ' . $r['caminho']);
    verdade(is_file(CASTELLO_UPLOADS . '/modelos/' . basename((string) $r['caminho'])));
});

teste('a extensao gravada vem do tipo real, nao do nome enviado', function (): void {
    $r = upload_receber(arquivo_falso('mentira.PNG', imagem_falsa('jpg')), 'portfolio', 'imagem');
    verdade($r['ok']);
    verdade(str_ends_with((string) $r['caminho'], '.jpg'), 'conteudo jpeg tem que virar .jpg');
});

teste('png e webp sao aceitos, gif nao', function (): void {
    verdade(upload_receber(arquivo_falso('a.png', imagem_falsa('png')), 'modelos', 'imagem')['ok']);

    if ((gd_info()['WebP Support'] ?? false) === true) {
        verdade(upload_receber(arquivo_falso('a.webp', imagem_falsa('webp')), 'modelos', 'imagem')['ok']);
    }

    $gif = upload_receber(arquivo_falso('a.gif', imagem_falsa('gif')), 'modelos', 'imagem');
    falso($gif['ok']);
    igual('tipo', $gif['erro']);
});

teste('arquivo PHP renomeado para .jpg e recusado', function (): void {
    $r = upload_receber(arquivo_falso('shell.jpg', "<?php system(\$_GET['c']); "), 'modelos', 'imagem');

    falso($r['ok']);
    igual('tipo', $r['erro']);
    igual(null, $r['caminho']);
    igual([], glob(CASTELLO_UPLOADS . '/modelos/shell*') ?: [], 'nada pode ter sido gravado');
});

teste('imagem acima de 5 MB e recusada', function (): void {
    $grande = imagem_falsa('jpg') . str_repeat("\0", 6 * 1024 * 1024);
    $r = upload_receber(arquivo_falso('pesada.jpg', $grande), 'modelos', 'imagem');

    falso($r['ok']);
    igual('tamanho', $r['erro']);
});

teste('video mp4 real e aceito na pasta de videos', function (): void {
    $bytes = file_get_contents(site() . '/videos-instagram/web/insta-01.mp4');
    verdade($bytes !== false, 'o mp4 de referencia precisa existir no repositorio');

    $r = upload_receber(arquivo_falso('Reel do Instagram.mp4', (string) $bytes), 'videos', 'video');
    verdade($r['ok'], 'erro: ' . var_export($r['erro'], true));
    verdade((bool) preg_match('#^uploads/videos/reel-do-instagram-[0-9a-f]{6}\.mp4$#', (string) $r['caminho']));
});

teste('imagem enviada como video e video enviado como imagem sao recusados', function (): void {
    $comoVideo = upload_receber(arquivo_falso('a.jpg', imagem_falsa('jpg')), 'videos', 'video');
    falso($comoVideo['ok']);
    igual('tipo', $comoVideo['erro']);

    $bytes = (string) file_get_contents(site() . '/videos-instagram/web/insta-01.mp4');
    $comoImagem = upload_receber(arquivo_falso('a.mp4', $bytes), 'modelos', 'imagem');
    falso($comoImagem['ok']);
    igual('tipo', $comoImagem['erro']);
});

teste('pasta fora da lista e tipo fora da lista sao recusados', function (): void {
    $r = upload_receber(arquivo_falso('a.jpg', imagem_falsa('jpg')), 'lib', 'imagem');
    falso($r['ok']);
    igual('pasta_invalida', $r['erro']);

    $r = upload_receber(arquivo_falso('a.jpg', imagem_falsa('jpg')), '../lib', 'imagem');
    falso($r['ok']);
    igual('pasta_invalida', $r['erro']);

    $r = upload_receber(arquivo_falso('a.jpg', imagem_falsa('jpg')), 'modelos', 'documento');
    falso($r['ok']);
    igual('tipo_invalido', $r['erro']);
});

teste('campo vazio e erro do PHP viram erro claro', function (): void {
    $r = upload_receber(['name' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0], 'modelos', 'imagem');
    falso($r['ok']);
    igual('sem_arquivo', $r['erro']);

    $r = upload_receber(['name' => 'a.jpg', 'tmp_name' => '', 'error' => UPLOAD_ERR_INI_SIZE, 'size' => 0], 'modelos', 'imagem');
    igual('tamanho', $r['erro']);

    $r = upload_receber(['name' => 'a.jpg', 'tmp_name' => '', 'error' => UPLOAD_ERR_PARTIAL, 'size' => 0], 'modelos', 'imagem');
    igual('erro_upload', $r['erro']);

    $r = upload_receber([], 'modelos', 'imagem');
    igual('sem_arquivo', $r['erro']);
});

teste('dois envios com o mesmo nome geram arquivos diferentes', function (): void {
    $a = upload_receber(arquivo_falso('igual.jpg', imagem_falsa('jpg')), 'passos', 'imagem');
    $b = upload_receber(arquivo_falso('igual.jpg', imagem_falsa('jpg')), 'passos', 'imagem');

    verdade($a['ok']);
    verdade($b['ok']);
    verdade($a['caminho'] !== $b['caminho'], 'o sufixo aleatorio evita sobrescrever');
});

teste('upload_slug limpa acento, espaco e caractere de caminho', function (): void {
    igual('casa-de-madeira', upload_slug('Casa de Madeira.jpg'));
    igual('sao-jose-acucar', upload_slug('São José & Açúcar.png'));
    igual('arquivo', upload_slug('...jpg'));
    igual('passwd', upload_slug('../../etc/passwd'), 'pathinfo ja corta o diretorio');
    verdade(mb_strlen(upload_slug(str_repeat('a', 200) . '.jpg')) <= 40);
    nao_contem('/', upload_slug('../../etc/passwd'));
    nao_contem('.', upload_slug('a.b.c.jpg'));
});

teste('os limites sao os do contrato', function (): void {
    igual(5 * 1024 * 1024, UPLOAD_TIPOS['imagem']['limite']);
    igual(30 * 1024 * 1024, UPLOAD_TIPOS['video']['limite']);
    igual(['image/jpeg', 'image/png', 'image/webp'], array_keys(UPLOAD_TIPOS['imagem']['mime']));
    igual(['video/mp4'], array_keys(UPLOAD_TIPOS['video']['mime']));
    igual(['modelos', 'portfolio', 'videos', 'passos'], UPLOAD_PASTAS);
});
```

- [ ] **Passo 2: Rodar e ver falhar**

Rode: `php testes/smoke.php 70-upload`
Esperado: erro fatal `Failed opening required .../public_html/lib/upload.php`.

- [ ] **Passo 3: Escrever o `lib/upload.php`**

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Validacao e gravacao de imagem e video enviados pelo painel.
 *
 * O tipo e conferido com finfo_file, nunca pela extensao. O arquivo e
 * renomeado para slug do nome original mais 6 caracteres aleatorios, e a
 * extensao vem do tipo real detectado.
 */

if (!defined('CASTELLO_UPLOADS')) {
    define('CASTELLO_UPLOADS', dirname(__DIR__) . '/uploads');
}

const UPLOAD_PASTAS = ['modelos', 'portfolio', 'videos', 'passos'];

const UPLOAD_TIPOS = [
    'imagem' => [
        'limite' => 5242880,
        'mime'   => ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
    ],
    'video' => [
        'limite' => 31457280,
        'mime'   => ['video/mp4' => 'mp4'],
    ],
];

/**
 * @param array  $arquivo uma entrada de $_FILES
 * @param string $pasta   modelos, portfolio, videos ou passos
 * @param string $tipo    imagem ou video
 *
 * @return array{ok: bool, caminho: ?string, erro: ?string}
 */
function upload_receber(array $arquivo, string $pasta, string $tipo): array
{
    $falha = static fn (string $erro): array => ['ok' => false, 'caminho' => null, 'erro' => $erro];

    if (!in_array($pasta, UPLOAD_PASTAS, true)) {
        return $falha('pasta_invalida');
    }
    if (!isset(UPLOAD_TIPOS[$tipo])) {
        return $falha('tipo_invalido');
    }

    $codigo = (int) ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($codigo === UPLOAD_ERR_NO_FILE) {
        return $falha('sem_arquivo');
    }
    if ($codigo === UPLOAD_ERR_INI_SIZE || $codigo === UPLOAD_ERR_FORM_SIZE) {
        return $falha('tamanho');
    }
    if ($codigo !== UPLOAD_ERR_OK) {
        return $falha('erro_upload');
    }

    $temporario = (string) ($arquivo['tmp_name'] ?? '');
    if ($temporario === '' || !is_file($temporario)) {
        return $falha('sem_arquivo');
    }

    $regra = UPLOAD_TIPOS[$tipo];
    if (filesize($temporario) > $regra['limite']) {
        return $falha('tamanho');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo === false) {
        return $falha('finfo_indisponivel');
    }
    $mime = (string) finfo_file($finfo, $temporario);
    finfo_close($finfo);

    if (!isset($regra['mime'][$mime])) {
        return $falha('tipo');
    }

    $destino = CASTELLO_UPLOADS . '/' . $pasta;
    if (!is_dir($destino) && !mkdir($destino, 0775, true) && !is_dir($destino)) {
        return $falha('gravacao');
    }

    $nome = upload_slug((string) ($arquivo['name'] ?? 'arquivo'))
          . '-' . bin2hex(random_bytes(3))
          . '.' . $regra['mime'][$mime];

    $emDisco = $destino . '/' . $nome;

    // Na linha de comando is_uploaded_file e sempre falso, entao o teste usa
    // rename. No navegador o caminho e move_uploaded_file, que so aceita
    // arquivo que veio mesmo de um POST.
    $movido = PHP_SAPI === 'cli'
        ? @rename($temporario, $emDisco)
        : @move_uploaded_file($temporario, $emDisco);

    if (!$movido) {
        return $falha('gravacao');
    }

    @chmod($emDisco, 0644);

    return ['ok' => true, 'caminho' => 'uploads/' . $pasta . '/' . $nome, 'erro' => null];
}

/** Nome de arquivo seguro: sem acento, sem espaco, sem caractere de caminho. */
function upload_slug(string $nome): string
{
    $base = pathinfo($nome, PATHINFO_FILENAME);

    $base = strtr($base, [
        'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
        'é' => 'e', 'ê' => 'e', 'è' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i',
        'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ò' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c', 'ñ' => 'n',
        'Á' => 'a', 'À' => 'a', 'Ã' => 'a', 'Â' => 'a', 'Ä' => 'a',
        'É' => 'e', 'Ê' => 'e', 'È' => 'e',
        'Í' => 'i', 'Ì' => 'i',
        'Ó' => 'o', 'Ô' => 'o', 'Õ' => 'o', 'Ò' => 'o',
        'Ú' => 'u', 'Ù' => 'u', 'Ü' => 'u',
        'Ç' => 'c', 'Ñ' => 'n',
    ]);

    $base = mb_strtolower($base);
    $base = (string) preg_replace('/[^a-z0-9]+/u', '-', $base);
    $base = trim($base, '-');

    if ($base === '') {
        $base = 'arquivo';
    }

    return mb_substr($base, 0, 40);
}
```

- [ ] **Passo 4: Rodar e ver passar**

Rode: `php testes/smoke.php 70-upload`
Esperado: 12 ok, 0 falha, 0 pulado.

Rode a suíte inteira: `php testes/smoke.php`
Esperado: `todos os casos passaram`.

- [ ] **Passo 5: Commit**

```bash
git add public_html/lib/upload.php testes/casos/70-upload.php
git commit -m "feat: upload validado por finfo, com limite de tamanho e nome reescrito

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Tarefa 9: `csrf.php`, o endereço que entrega o token do formulário

**Arquivos:**
- Criar: `public_html/csrf.php`
- Modificar: `public_html/partials/modal.php` (campo oculto `csrf`, vazio)
- Teste: acrescentar ao final de `testes/casos/50-paginas.php`

**Interfaces:**
- Consome: `auth_iniciar()`, `csrf_token()` da Tarefa 7.
- Produz: o endereço `public_html/csrf.php`, que responde `{"token":"..."}` com `Content-Type: application/json; charset=utf-8` e `Cache-Control: private, no-store`; e o campo oculto `csrf` dentro do `#quoteForm`, vazio, preenchido pelo JS da Frente 3.

**Por que isto é bloqueante.** A Frente 3 escreve `enviar.php` e `js/formulario.js`, e não pode escrever em `index.php` nem em `flex.php`. O `csrf.php` é o único ponto por onde o JS dela conhece o token. Sem ele, todo envio volta HTTP 419 e o site não capta um lead. É o único acoplamento duro entre as duas frentes.

**Por que um endereço à parte e não uma metatag no `<head>`.** A metatag seria mais simples, mas o token é por sessão: uma página que carrega token no HTML não pode ser guardada em cache compartilhado, sob pena de o LiteSpeed ou um CDN entregar o token de um visitante para outro. Isso obrigaria `Cache-Control: private, no-store` em **toda** página do site. A Castello vai investir em tráfego pago, então cada página de destino incacheável é custo permanente e direto em cima do que eles pagam para trazer gente. Com o endereço à parte, `index.php` e `flex.php` continuam cacheáveis por inteiro, o visitante não recebe cookie de sessão só por ler o site, e o custo vira uma requisição pequena, só para quem realmente abre o formulário.

Por isso `index.php` e `flex.php` **não mudam** nesta tarefa: nada de metatag, nada de `auth_iniciar()`, nada de `Cache-Control`.

- [ ] **Passo 1: Escrever os testes que falham**

Acrescente ao topo de `testes/casos/50-paginas.php`, logo depois do `require_once` que já está lá:

```php
require_once site() . '/lib/auth.php';
```

E acrescente ao final do mesmo arquivo:

```php
teste('csrf.php responde JSON com um token utilizavel', function (): void {
    $saida = render(site() . '/csrf.php');

    $dados = json_decode($saida, true);
    verdade(is_array($dados), 'a resposta precisa ser JSON, obtida: ' . mb_substr($saida, 0, 120));
    verdade(isset($dados['token']), 'o JSON precisa ter a chave token');
    verdade((bool) preg_match('/^[0-9a-f]{64}$/', (string) $dados['token']),
        'token esperado: 64 caracteres hexadecimais, obtido ' . var_export($dados['token'], true));

    verdade(csrf_validar((string) $dados['token']), 'o token entregue precisa passar em csrf_validar');
    igual(['token'], array_keys($dados), 'a resposta nao devolve mais nada alem do token');
});

teste('o token e o mesmo dentro da sessao e muda quando a sessao muda', function (): void {
    $primeiro = json_decode(render(site() . '/csrf.php'), true)['token'];
    $segundo  = json_decode(render(site() . '/csrf.php'), true)['token'];
    igual($primeiro, $segundo, 'na mesma sessao o token nao pode mudar a cada chamada');

    // Simula outro visitante: a sessao e zerada e o token e sorteado de novo.
    $_SESSION = [];
    $outro = json_decode(render(site() . '/csrf.php'), true)['token'];

    verdade($outro !== $primeiro, 'visitantes diferentes precisam receber tokens diferentes');
    verdade(csrf_validar($outro));
    falso(csrf_validar($primeiro), 'o token da sessao antiga deixa de valer');
});

teste('csrf.php manda os cabecalhos certos', function (): void {
    $fonte = (string) file_get_contents(site() . '/csrf.php');

    contem("Content-Type: application/json; charset=utf-8", $fonte);
    contem("Cache-Control: private, no-store", $fonte);
    contem('auth_iniciar()', $fonte);
    contem('csrf_token()', $fonte);
});

teste('nenhuma pagina do site imprime o token no HTML', function (): void {
    foreach ([site() . '/index.php', site() . '/flex.php'] as $pagina) {
        $html = render($pagina);

        nao_contem('csrf-token', $html, 'metatag de token em ' . basename($pagina));
        nao_contem('Cache-Control', (string) file_get_contents($pagina),
            basename($pagina) . ' precisa continuar cacheavel por inteiro');
    }
});

teste('o formulario do modal tem o campo oculto de csrf, vazio para o JS preencher', function (): void {
    foreach ([site() . '/index.php', site() . '/flex.php'] as $pagina) {
        $html = render($pagina);
        contem('<input type="hidden" name="csrf" value="" />', $html, basename($pagina));
        igual(1, substr_count($html, 'name="csrf"'), 'um campo csrf so, em ' . basename($pagina));
    }
});
```

E troque o primeiro teste do arquivo, o de identidade da home, por esta versão, que tira o campo oculto novo antes de comparar. Ele é a única adição ao HTML do protótipo, e tem teste próprio logo acima:

```php
teste('index.php renderiza a home identica ao index.html original', function (): void {
    config_gravar('videos_na_home', '11');
    $novo = render(site() . '/index.php');
    config_gravar('videos_na_home', '8');

    $velho = file_get_contents(raiz() . '/testes/base/home-original.html');
    verdade($velho !== false, 'testes/base/home-original.html precisa existir');

    $novo = (string) preg_replace('#\s*<input type="hidden" name="csrf" value="" />#u', '', $novo);

    igual(norm((string) $velho), norm($novo));
});
```

- [ ] **Passo 2: Rodar e ver falhar**

Rode: `php testes/smoke.php 50-paginas`
Esperado: os testes do `csrf.php` falham com `Failed opening required .../public_html/csrf.php`, e o do campo oculto falha porque o campo ainda não existe. O teste de identidade continua passando, já que o `preg_replace` não encontra nada para tirar.

- [ ] **Passo 3: Escrever o `csrf.php`**

Crie `public_html/csrf.php` com exatamente o conteúdo definido no contrato:

```php
<?php
declare(strict_types=1);

require __DIR__ . '/lib/auth.php';

auth_iniciar();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');

echo json_encode(['token' => csrf_token()]);
```

- [ ] **Passo 4: Acrescentar o campo oculto ao formulário do modal**

Em `public_html/partials/modal.php`, dentro do `<form class="qform" id="quoteForm" novalidate>`, logo antes da linha do honeypot, acrescente:

```html
        <input type="hidden" name="csrf" value="" />
```

O valor fica vazio de propósito: quem preenche é o `js/formulario.js` da Frente 3, que busca o token em `csrf.php` **quando o visitante abre o modal**, não no carregamento da página. Assim existe um lugar só de onde o token sai, e a página continua cacheável.

O honeypot continua sendo `_gotcha` neste arquivo. O contrato define `empresa` como nome final, mas quem troca é a costura, junto com a limpeza do bloco antigo de formulário no `js/main.js`. Até lá o `enviar.php` aceita os dois nomes.

- [ ] **Passo 5: Rodar e ver passar**

Rode: `php testes/smoke.php 50-paginas`
Esperado: 13 ok, 0 falha, 0 pulado.

Rode a suíte inteira: `php testes/smoke.php`
Esperado: `todos os casos passaram`.

- [ ] **Passo 6: Conferir no navegador**

Com `php -S localhost:8000 -t public_html` no ar:

1. Abra `http://localhost:8000/csrf.php`. A resposta é `{"token":"..."}` com um valor longo.
2. Recarregue com F5: o token continua o mesmo, porque a sessão é a mesma.
3. Abra em uma janela anônima: o token é outro.
4. Nas ferramentas de rede, a resposta do `csrf.php` traz `Content-Type: application/json; charset=utf-8` e `Cache-Control: private, no-store`.
5. Abra `http://localhost:8000/` e veja o código-fonte: **não** existe nenhuma metatag `csrf-token`. Nas ferramentas de rede, a resposta da home **não** traz `Cache-Control: private` nem `Set-Cookie`.
6. No console da home, `fetch('csrf.php').then(r=>r.json()).then(console.log)` devolve o token. É exatamente o que o JS da Frente 3 vai fazer ao abrir o modal.
7. Confira que o `#quoteForm` tem `<input type="hidden" name="csrf" value="">`, vazio.
8. Repita os passos 5 e 7 em `http://localhost:8000/flex.php`.

- [ ] **Passo 7: Commit**

```bash
git add public_html/csrf.php public_html/partials/modal.php testes/casos/50-paginas.php
git commit -m "feat: csrf.php entrega o token do formulario sem sujar as paginas

A Frente 3 nao pode escrever em index.php nem em flex.php, entao o token do
formulario sai de um endereco proprio. Token no HTML obrigaria Cache-Control
private em todo o site, e a Castello vai investir em trafego pago: pagina de
destino incacheavel seria custo permanente. Assim as paginas continuam
cacheaveis por inteiro e ninguem recebe cookie de sessao so por ler o site.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Tarefa 10: Painel — login, shell e a descrição declarativa das tabelas

**Arquivos:**
- Criar: `public_html/painel/tabelas.php`
- Criar: `public_html/painel/index.php` (login), `public_html/painel/painel.php` (shell), `public_html/painel/sair.php`
- Criar: `public_html/painel/telas/lista.php`
- Criar: `public_html/painel/assets/painel.css`
- Teste: `testes/casos/80-painel.php`

**Interfaces:**
- Consome: `db()`, `e()`, `config_ler()`; `auth_iniciar()`, `auth_logado()`, `auth_exigir()`, `auth_entrar()`, `auth_sair()`, `auth_bloqueado()`, `csrf_token()`, `csrf_validar()`; `videos()`; `icone_faq()`, `CASTELLO_ICONES_FAQ`.
- Produz: `painel_tabelas(): array` (descrição declarativa das seis telas de conteúdo), `painel_tabela(string $chave): ?array`, `painel_abas(): array` (mapa `chave => rótulo` do menu), `painel_listar(string $chave, ?string $filtro = null): array` (inclui inativos), `painel_linha(string $chave, int $id): ?array`.

**A ideia central:** uma entrada em `painel_tabelas()` gera lista, formulário, salvar, desativar, reativar e reordenar. Nenhuma tela é copiada. Os nomes de tabela e de coluna usados no SQL vêm sempre dessa descrição, nunca da requisição, e é isso que torna a interpolação de nome de tabela no SQL segura.

- [ ] **Passo 1: Escrever o teste que falha**

Crie `testes/casos/80-painel.php`:

```php
<?php
declare(strict_types=1);

require_once site() . '/painel/tabelas.php';

banco_com_conteudo();

teste('as seis telas de conteudo estao descritas', function (): void {
    igual(['modelos', 'portfolio', 'avaliacoes', 'videos', 'faq', 'passos'], array_keys(painel_tabelas()));
});

teste('toda tabela descrita existe no banco e todo campo e uma coluna real', function (): void {
    foreach (painel_tabelas() as $chave => $def) {
        $colunas = array_column(db()->query('PRAGMA table_info(' . $chave . ')')->fetchAll(), 'name');
        verdade($colunas !== [], "a tabela $chave precisa existir no banco");

        foreach (array_keys($def['campos']) as $coluna) {
            verdade(in_array($coluna, $colunas, true), "$chave.$coluna nao existe no banco");
        }
        foreach (array_keys($def['resumo']) as $coluna) {
            verdade(in_array($coluna, $colunas, true), "resumo de $chave aponta para coluna inexistente: $coluna");
        }
        verdade(in_array('ativo', $colunas, true), "$chave precisa de ativo");
        verdade(in_array('ordem', $colunas, true), "$chave precisa de ordem");
    }
});

teste('toda descricao tem rotulo, singular, resumo e campos com tipo conhecido', function (): void {
    $tipos = ['texto', 'texto_longo', 'numero', 'selecao', 'sim_nao', 'imagem', 'video', 'icone_faq'];

    foreach (painel_tabelas() as $chave => $def) {
        verdade(($def['rotulo'] ?? '') !== '', "$chave sem rotulo");
        verdade(($def['singular'] ?? '') !== '', "$chave sem singular");
        verdade(($def['resumo'] ?? []) !== [], "$chave sem resumo");

        foreach ($def['campos'] as $coluna => $campo) {
            verdade(($campo['rotulo'] ?? '') !== '', "$chave.$coluna sem rotulo");
            verdade(in_array($campo['tipo'] ?? '', $tipos, true), "$chave.$coluna com tipo desconhecido");

            if ($campo['tipo'] === 'selecao') {
                verdade(($campo['opcoes'] ?? []) !== [], "$chave.$coluna precisa de opcoes");
            }
            if (in_array($campo['tipo'], ['imagem', 'video'], true)) {
                verdade(in_array($campo['pasta'] ?? '', ['modelos', 'portfolio', 'videos', 'passos'], true),
                    "$chave.$coluna precisa apontar para uma pasta valida de upload");
            }
        }
    }
});

teste('modelos e passos filtram por modalidade e contexto', function (): void {
    igual('modalidade', painel_tabela('modelos')['filtro']['coluna']);
    igual(['pronta', 'flex'], array_keys(painel_tabela('modelos')['filtro']['opcoes']));
    igual('pronta', painel_tabela('modelos')['filtro']['padrao']);

    igual('contexto', painel_tabela('passos')['filtro']['coluna']);
    igual('contexto', painel_tabela('faq')['filtro']['coluna']);
    igual(['geral', 'flex'], array_keys(painel_tabela('faq')['filtro']['opcoes']));

    igual(null, painel_tabela('portfolio')['filtro'] ?? null, 'portfolio nao tem filtro');
});

teste('o FAQ usa o seletor visual de icone com as sete chaves fechadas', function (): void {
    igual('icone_faq', painel_tabela('faq')['campos']['icone']['tipo']);
    verdade((bool) (painel_tabela('faq')['campos']['icone']['obrigatorio'] ?? false), 'icone e obrigatorio');
    igual(7, count(CASTELLO_ICONES_FAQ));
});

teste('painel_tabela devolve null para chave inventada', function (): void {
    igual(null, painel_tabela('usuarios'));
    igual(null, painel_tabela('leads'));
    igual(null, painel_tabela('config'));
    igual(null, painel_tabela(''));
});

teste('painel_abas monta o menu com as telas de conteudo', function (): void {
    $abas = painel_abas();
    igual('Modelos', $abas['modelos']);
    igual('Portfólio', $abas['portfolio']);
    igual('Avaliações', $abas['avaliacoes']);
    igual('Vídeos', $abas['videos']);
    igual('FAQ', $abas['faq']);
    igual('Passo a passo', $abas['passos']);
});

teste('painel_listar mostra tambem o que esta desativado', function (): void {
    db()->exec("UPDATE portfolio SET ativo = 0 WHERE titulo = 'Chalé com varanda'");

    igual(5, count(portfolio()), 'o site so mostra os ativos');
    igual(6, count(painel_listar('portfolio')), 'o painel mostra todos');

    db()->exec("UPDATE portfolio SET ativo = 1 WHERE titulo = 'Chalé com varanda'");
});

teste('painel_listar aplica o filtro quando a tabela tem um', function (): void {
    igual(4, count(painel_listar('modelos', 'pronta')));
    igual(0, count(painel_listar('modelos', 'flex')));
    igual(4, count(painel_listar('modelos')), 'sem filtro, lista tudo');
    igual(4, count(painel_listar('modelos', 'inventada')), 'filtro invalido e ignorado');
});

teste('painel_listar devolve vazio para tabela fora da descricao', function (): void {
    igual([], painel_listar('usuarios'));
    igual([], painel_listar('leads'));
});

teste('painel_linha traz uma linha pelo id e null quando nao existe', function (): void {
    $id = (int) db()->query("SELECT id FROM modelos WHERE nome = 'Família'")->fetchColumn();

    igual('Família', painel_linha('modelos', $id)['nome']);
    igual(null, painel_linha('modelos', 999999));
    igual(null, painel_linha('usuarios', 1));
});
```

- [ ] **Passo 2: Rodar e ver falhar**

Rode: `php testes/smoke.php 80-painel`
Esperado: erro fatal `Failed opening required .../public_html/painel/tabelas.php`.

- [ ] **Passo 3: Escrever `painel/tabelas.php`**

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/conteudo.php';
require_once __DIR__ . '/../lib/upload.php';

/**
 * Descricao declarativa das telas de conteudo do painel.
 *
 * Cada entrada gera sozinha a lista, o formulario, o salvar, o desativar,
 * o reativar e o reordenar. Os nomes de tabela e de coluna usados no SQL saem
 * daqui e nunca da requisicao, e e isso que torna seguro interpolar o nome da
 * tabela na consulta.
 *
 * Tipos de campo: texto, texto_longo, numero, selecao, sim_nao, imagem,
 * video, icone_faq.
 */
function painel_tabelas(): array
{
    return [
        'modelos' => [
            'rotulo'    => 'Modelos',
            'singular'  => 'modelo',
            'miniatura' => 'foto',
            'resumo'    => ['nome' => 'Nome', 'area' => 'Área', 'preco' => 'Preço'],
            'filtro'    => [
                'coluna' => 'modalidade',
                'rotulo' => 'Modalidade',
                'opcoes' => ['pronta' => 'Casa Pronta', 'flex' => 'Castelo Flex'],
                'padrao' => 'pronta',
            ],
            'campos' => [
                'modalidade' => ['rotulo' => 'Modalidade', 'tipo' => 'selecao', 'obrigatorio' => true,
                                 'opcoes' => ['pronta' => 'Casa Pronta', 'flex' => 'Castelo Flex']],
                'nome'       => ['rotulo' => 'Nome do modelo', 'tipo' => 'texto', 'obrigatorio' => true],
                'area'       => ['rotulo' => 'Área', 'tipo' => 'texto', 'ajuda' => 'Escreva com a unidade, como 39,00 m²'],
                'parede'     => ['rotulo' => 'Parede', 'tipo' => 'texto', 'ajuda' => 'Parede vertical ou Parede dupla'],
                'preco'      => ['rotulo' => 'Preço', 'tipo' => 'texto', 'ajuda' => 'Só o número, como 69.900. O site coloca o R$ sozinho'],
                'prazo'      => ['rotulo' => 'Prazo', 'tipo' => 'texto', 'ajuda' => 'Como 90 a 120 dias'],
                'descricao'  => ['rotulo' => 'Descrição', 'tipo' => 'texto_longo'],
                'foto'       => ['rotulo' => 'Foto', 'tipo' => 'imagem', 'pasta' => 'modelos'],
                'foto_alt'   => ['rotulo' => 'Descrição da foto', 'tipo' => 'texto',
                                 'ajuda' => 'Conte o que aparece na foto. Serve para quem usa leitor de tela e para o Google'],
                'destaque'   => ['rotulo' => 'Marcar como Mais escolhida', 'tipo' => 'sim_nao'],
            ],
        ],

        'portfolio' => [
            'rotulo'    => 'Portfólio',
            'singular'  => 'casa entregue',
            'miniatura' => 'foto',
            'resumo'    => ['titulo' => 'Título', 'categoria' => 'Categoria'],
            'campos'    => [
                'titulo'    => ['rotulo' => 'Título', 'tipo' => 'texto', 'obrigatorio' => true],
                'categoria' => ['rotulo' => 'Categoria', 'tipo' => 'texto', 'ajuda' => 'A linha pequena acima do título, como Beira da água'],
                'foto'      => ['rotulo' => 'Foto', 'tipo' => 'imagem', 'pasta' => 'portfolio'],
                'foto_alt'  => ['rotulo' => 'Descrição da foto', 'tipo' => 'texto'],
            ],
        ],

        'avaliacoes' => [
            'rotulo'   => 'Avaliações',
            'singular' => 'avaliação',
            'resumo'   => ['nome' => 'Cliente', 'estrelas' => 'Estrelas'],
            'campos'   => [
                'nome'     => ['rotulo' => 'Nome do cliente', 'tipo' => 'texto', 'obrigatorio' => true],
                'texto'    => ['rotulo' => 'Texto da avaliação', 'tipo' => 'texto_longo', 'obrigatorio' => true],
                'estrelas' => ['rotulo' => 'Estrelas', 'tipo' => 'numero', 'ajuda' => 'De 1 a 5'],
            ],
        ],

        'videos' => [
            'rotulo'    => 'Vídeos',
            'singular'  => 'vídeo',
            'miniatura' => 'poster',
            'resumo'    => ['arquivo' => 'Arquivo', 'legenda' => 'Legenda'],
            'campos'    => [
                'arquivo' => ['rotulo' => 'Vídeo em MP4', 'tipo' => 'video', 'pasta' => 'videos', 'obrigatorio' => true],
                'poster'  => ['rotulo' => 'Capa do vídeo', 'tipo' => 'imagem', 'pasta' => 'videos',
                              'ajuda' => 'A imagem que aparece antes de tocar'],
                'legenda' => ['rotulo' => 'Legenda', 'tipo' => 'texto'],
            ],
        ],

        'faq' => [
            'rotulo'   => 'FAQ',
            'singular' => 'pergunta',
            'resumo'   => ['pergunta' => 'Pergunta'],
            'filtro'   => [
                'coluna' => 'contexto',
                'rotulo' => 'Onde aparece',
                'opcoes' => ['geral' => 'Home', 'flex' => 'Página Castelo Flex'],
                'padrao' => 'geral',
            ],
            'campos' => [
                'contexto' => ['rotulo' => 'Onde aparece', 'tipo' => 'selecao', 'obrigatorio' => true,
                               'opcoes' => ['geral' => 'Home', 'flex' => 'Página Castelo Flex']],
                'pergunta' => ['rotulo' => 'Pergunta', 'tipo' => 'texto', 'obrigatorio' => true],
                'resposta' => ['rotulo' => 'Resposta', 'tipo' => 'texto_longo', 'obrigatorio' => true],
                'icone'    => ['rotulo' => 'Ícone', 'tipo' => 'icone_faq', 'obrigatorio' => true,
                               'ajuda' => 'Toque no desenho que combina com a pergunta'],
            ],
        ],

        'passos' => [
            'rotulo'    => 'Passo a passo',
            'singular'  => 'passo',
            'miniatura' => 'imagem',
            'resumo'    => ['titulo' => 'Título'],
            'filtro'    => [
                'coluna' => 'contexto',
                'rotulo' => 'Onde aparece',
                'opcoes' => ['pronta' => 'Home, Casa Pronta', 'flex' => 'Página Castelo Flex'],
                'padrao' => 'pronta',
            ],
            'campos' => [
                'contexto' => ['rotulo' => 'Onde aparece', 'tipo' => 'selecao', 'obrigatorio' => true,
                               'opcoes' => ['pronta' => 'Home, Casa Pronta', 'flex' => 'Página Castelo Flex']],
                'titulo'   => ['rotulo' => 'Título do passo', 'tipo' => 'texto', 'obrigatorio' => true],
                'texto'    => ['rotulo' => 'Texto do passo', 'tipo' => 'texto_longo'],
                'imagem'   => ['rotulo' => 'Imagem', 'tipo' => 'imagem', 'pasta' => 'passos'],
                'imagem_alt' => ['rotulo' => 'Descrição da imagem', 'tipo' => 'texto',
                                 'ajuda' => 'Conte o que aparece na imagem. Serve para quem usa leitor de tela e para o Google'],
            ],
        ],
    ];
}

/** Descricao de uma tela de conteudo, ou null se a chave nao for uma delas. */
function painel_tabela(string $chave): ?array
{
    return painel_tabelas()[$chave] ?? null;
}

/** Menu do painel: chave da tela para o rotulo mostrado. */
function painel_abas(): array
{
    $abas = [];
    foreach (painel_tabelas() as $chave => $def) {
        $abas[$chave] = $def['rotulo'];
    }

    return $abas;
}

/** Linhas de uma tela, inativas incluidas. O filtro so vale se a tela tiver um. */
function painel_listar(string $chave, ?string $filtro = null): array
{
    $def = painel_tabela($chave);
    if ($def === null) {
        return [];
    }

    if (isset($def['filtro']) && $filtro !== null && isset($def['filtro']['opcoes'][$filtro])) {
        $st = db()->prepare(
            'SELECT * FROM ' . $chave . ' WHERE ' . $def['filtro']['coluna'] . ' = ? ORDER BY ordem ASC, id ASC'
        );
        $st->execute([$filtro]);

        return $st->fetchAll();
    }

    return db()->query('SELECT * FROM ' . $chave . ' ORDER BY ordem ASC, id ASC')->fetchAll();
}

/** Uma linha pelo id, ou null. */
function painel_linha(string $chave, int $id): ?array
{
    if (painel_tabela($chave) === null) {
        return null;
    }

    $st = db()->prepare('SELECT * FROM ' . $chave . ' WHERE id = ?');
    $st->execute([$id]);
    $linha = $st->fetch();

    return $linha === false ? null : $linha;
}
```

- [ ] **Passo 4: Rodar e ver passar a parte de lógica**

Rode: `php testes/smoke.php 80-painel`
Esperado: 11 ok, 0 falha, 0 pulado.

- [ ] **Passo 5: Escrever o CSS do painel**

Crie `public_html/painel/assets/painel.css`:

```css
/* Painel da Castello. Independente do css/style.css, que e da Frente 2. */
:root {
  --p-vermelho: #ED2128;
  --p-vermelho-fundo: #B3141B;
  --p-preto: #141414;
  --p-papel: #FAF7F2;
  --p-borda: #E4DED4;
  --p-texto: #2A2724;
  --p-suave: #6E675E;
  --p-ok: #1F7A44;
}

* { box-sizing: border-box; }

body {
  margin: 0;
  background: var(--p-papel);
  color: var(--p-texto);
  font: 16px/1.5 -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
}

a { color: var(--p-vermelho-fundo); }

.p-topo {
  display: flex; align-items: center; justify-content: space-between; gap: 12px;
  padding: 12px 16px; background: var(--p-preto); color: #fff;
}
.p-topo strong { font-size: 15px; letter-spacing: .02em; }
.p-topo a { color: #fff; text-decoration: none; font-size: 14px; }

.p-menu {
  display: flex; gap: 4px; overflow-x: auto; padding: 8px 12px;
  background: #fff; border-bottom: 1px solid var(--p-borda);
}
.p-menu a {
  white-space: nowrap; padding: 8px 12px; border-radius: 999px;
  text-decoration: none; color: var(--p-suave); font-size: 14px;
}
.p-menu a.is-ativo { background: var(--p-vermelho); color: #fff; }

.p-corpo { max-width: 900px; margin: 0 auto; padding: 16px; }
.p-corpo h1 { font-size: 22px; margin: 0 0 4px; }
.p-corpo .p-sub { color: var(--p-suave); font-size: 14px; margin: 0 0 16px; }

.p-aviso {
  padding: 10px 14px; border-radius: 8px; margin-bottom: 16px;
  background: #E8F5EC; color: var(--p-ok); border: 1px solid #BEE3CB;
}
.p-aviso--erro { background: #FDEBEC; color: var(--p-vermelho-fundo); border-color: #F5C3C6; }

.p-barra { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 14px; }

.p-btn {
  display: inline-flex; align-items: center; gap: 6px; cursor: pointer;
  padding: 9px 14px; border-radius: 8px; border: 1px solid var(--p-borda);
  background: #fff; color: var(--p-texto); font-size: 14px; text-decoration: none;
}
.p-btn--forte { background: var(--p-vermelho); border-color: var(--p-vermelho); color: #fff; }
.p-btn--fraco { color: var(--p-suave); }

.p-lista { list-style: none; margin: 0; padding: 0; }
.p-item {
  display: flex; align-items: center; gap: 12px; padding: 10px;
  background: #fff; border: 1px solid var(--p-borda); border-radius: 10px; margin-bottom: 8px;
}
.p-item.is-inativo { opacity: .55; }
.p-item.is-arrastando { outline: 2px dashed var(--p-vermelho); }
.p-pega { cursor: grab; touch-action: none; color: var(--p-suave); padding: 6px; user-select: none; }
.p-mini { width: 54px; height: 54px; object-fit: cover; border-radius: 6px; background: var(--p-borda); flex: none; }
.p-dados { flex: 1 1 auto; min-width: 0; }
.p-dados strong { display: block; font-size: 15px; }
.p-dados span { display: block; color: var(--p-suave); font-size: 13px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.p-acoes { display: flex; gap: 6px; flex: none; }
.p-tag { font-size: 11px; padding: 2px 7px; border-radius: 999px; background: var(--p-borda); color: var(--p-suave); }

.p-form { background: #fff; border: 1px solid var(--p-borda); border-radius: 10px; padding: 16px; }
.p-campo { margin-bottom: 14px; }
.p-campo > label,
.p-campo > strong { display: block; font-size: 14px; font-weight: 600; margin-bottom: 4px; }
.p-campo input[type="checkbox"] { width: auto; }
.p-campo .p-ajuda { display: block; font-size: 12px; color: var(--p-suave); margin-top: 4px; }
.p-campo input[type="text"], .p-campo input[type="number"], .p-campo input[type="password"],
.p-campo textarea, .p-campo select, .p-campo input[type="file"] {
  width: 100%; padding: 10px; border: 1px solid var(--p-borda); border-radius: 8px;
  font: inherit; background: #fff; color: inherit;
}
.p-campo textarea { min-height: 110px; resize: vertical; }
.p-campo--erro input, .p-campo--erro textarea, .p-campo--erro select { border-color: var(--p-vermelho); }
.p-erro { color: var(--p-vermelho-fundo); font-size: 13px; margin-top: 4px; }

.p-icones { display: flex; flex-wrap: wrap; gap: 8px; }
.p-icone {
  width: 56px; height: 56px; display: grid; place-items: center; cursor: pointer;
  border: 2px solid var(--p-borda); border-radius: 10px; background: #fff;
}
.p-icone svg { width: 26px; height: 26px; fill: none; stroke: var(--p-preto); stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }
.p-icone input { position: absolute; opacity: 0; pointer-events: none; }
.p-icone:has(input:checked) { border-color: var(--p-vermelho); background: #FDEBEC; }

.p-atual { display: flex; align-items: center; gap: 10px; margin-bottom: 6px; font-size: 13px; color: var(--p-suave); }
.p-atual img, .p-atual video { width: 70px; height: 70px; object-fit: cover; border-radius: 6px; }

.p-login { max-width: 360px; margin: 12vh auto; padding: 0 16px; }
.p-login img { display: block; height: 54px; margin: 0 auto 20px; }

@media (max-width: 560px) {
  .p-item { flex-wrap: wrap; }
  .p-acoes { width: 100%; justify-content: flex-end; }
}
```

- [ ] **Passo 6: Escrever a tela de login**

Crie `public_html/painel/index.php`:

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';

auth_iniciar();

if (auth_logado()) {
    header('Location: painel.php');
    exit;
}

$ip   = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$erro = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_validar($_POST['csrf'] ?? null)) {
        $erro = 'A sessão expirou nesta página. Tente entrar de novo.';
    } elseif (auth_bloqueado($ip)) {
        $erro = 'Muitas tentativas erradas. Espere 15 minutos e tente de novo.';
    } elseif (auth_entrar(trim((string) ($_POST['login'] ?? '')), (string) ($_POST['senha'] ?? ''))) {
        header('Location: painel.php');
        exit;
    } elseif (auth_bloqueado($ip)) {
        $erro = 'Muitas tentativas erradas. Espere 15 minutos e tente de novo.';
    } else {
        $erro = 'Login ou senha incorretos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex, nofollow" />
  <title>Painel Castello</title>
  <link rel="icon" type="image/png" href="../images/icone-colorido.png" />
  <link rel="stylesheet" href="assets/painel.css?v=1" />
</head>
<body>
  <main class="p-login">
    <img src="../images/logo-vertical-branco.png" alt="Castello Casas de Madeira" style="filter: invert(1)" />

<?php if ($erro !== ''): ?>
    <p class="p-aviso p-aviso--erro"><?= e($erro) ?></p>
<?php endif; ?>

    <form class="p-form" method="post" action="index.php">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>" />

      <div class="p-campo">
        <label for="login">Login</label>
        <input type="text" id="login" name="login" autocomplete="username" autocapitalize="none" required />
      </div>

      <div class="p-campo">
        <label for="senha">Senha</label>
        <input type="password" id="senha" name="senha" autocomplete="current-password" required />
      </div>

      <button class="p-btn p-btn--forte" type="submit">Entrar</button>
    </form>
  </main>
</body>
</html>
```

- [ ] **Passo 7: Escrever o shell e o `sair.php`**

Crie `public_html/painel/painel.php`:

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/tabelas.php';

auth_exigir();

$abas = painel_abas();

$tela = (string) ($_GET['tela'] ?? 'modelos');
if (!isset($abas[$tela])) {
    $tela = 'modelos';
}

$def = painel_tabela($tela);

// Telas de conteudo: lista por padrao, formulario quando ha novo ou editar.
// Telas fixas (textos, config, backup, senha) tem arquivo proprio em telas/.
if ($def !== null) {
    $arquivo = (isset($_GET['novo']) || isset($_GET['editar']))
        ? __DIR__ . '/telas/form.php'
        : __DIR__ . '/telas/lista.php';
} else {
    $arquivo = __DIR__ . '/telas/' . $tela . '.php';
}

$recado = (string) ($_GET['ok'] ?? '');
$alerta = (string) ($_GET['erro'] ?? '');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex, nofollow" />
  <title><?= e($abas[$tela] ?? 'Painel') ?> | Painel Castello</title>
  <link rel="icon" type="image/png" href="../images/icone-colorido.png" />
  <link rel="stylesheet" href="assets/painel.css?v=1" />
</head>
<body>
  <header class="p-topo">
    <strong>Painel Castello</strong>
    <a href="sair.php">Sair</a>
  </header>

  <nav class="p-menu" aria-label="Seções do painel">
<?php foreach ($abas as $chave => $rotulo): ?>
    <a href="painel.php?tela=<?= e($chave) ?>"<?= $chave === $tela ? ' class="is-ativo"' : '' ?>><?= e($rotulo) ?></a>
<?php endforeach; ?>
  </nav>

  <main class="p-corpo">
<?php if ($recado !== ''): ?>
    <p class="p-aviso" role="status"><?= e($recado) ?></p>
<?php endif; ?>
<?php if ($alerta !== ''): ?>
    <p class="p-aviso p-aviso--erro" role="alert"><?= e($alerta) ?></p>
<?php endif; ?>

<?php include $arquivo; ?>
  </main>
</body>
</html>
```

Crie `public_html/painel/sair.php`:

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';

auth_sair();

header('Location: index.php');
exit;
```

- [ ] **Passo 8: Escrever a tela de lista genérica**

Crie `public_html/painel/telas/lista.php`. Espera, por escopo: `string $tela` e `array $def`.

```php
<?php
/**
 * Lista generica de uma tela de conteudo. Espera: string $tela, array $def.
 * Serve as seis telas descritas em painel/tabelas.php.
 */
declare(strict_types=1);

$filtro = null;
if (isset($def['filtro'])) {
    $pedido = (string) ($_GET['filtro'] ?? $def['filtro']['padrao']);
    $filtro = isset($def['filtro']['opcoes'][$pedido]) ? $pedido : $def['filtro']['padrao'];
}

$linhas = painel_listar($tela, $filtro);
$ativos = 0;
foreach ($linhas as $linha) {
    $ativos += (int) $linha['ativo'] === 1 ? 1 : 0;
}
?>
<h1><?= e($def['rotulo']) ?></h1>
<p class="p-sub">
  <?= count($linhas) ?> <?= count($linhas) === 1 ? 'item' : 'itens' ?>, <?= $ativos ?> aparecendo no site.
<?php if ($tela === 'videos'): ?>
  A home mostra os <?= e((string) config_ler('videos_na_home', '8')) ?> primeiros da ordem abaixo.
  Para trocar quais aparecem, arraste os que você quer para o topo.
<?php endif; ?>
</p>

<div class="p-barra">
  <a class="p-btn p-btn--forte" href="painel.php?tela=<?= e($tela) ?>&amp;novo=1<?= $filtro !== null ? '&amp;filtro=' . e($filtro) : '' ?>">
    Adicionar <?= e($def['singular']) ?>
  </a>

<?php if (isset($def['filtro'])): ?>
<?php foreach ($def['filtro']['opcoes'] as $valor => $rotulo): ?>
  <a class="p-btn<?= $valor === $filtro ? ' p-btn--forte' : ' p-btn--fraco' ?>"
     href="painel.php?tela=<?= e($tela) ?>&amp;filtro=<?= e((string) $valor) ?>"><?= e($rotulo) ?></a>
<?php endforeach; ?>
<?php endif; ?>
</div>

<?php if ($linhas === []): ?>
<p class="p-sub">Nada cadastrado aqui ainda. Toque em Adicionar <?= e($def['singular']) ?> para começar.</p>
<?php else: ?>
<ul class="p-lista" id="pLista" data-tabela="<?= e($tela) ?>">
<?php foreach ($linhas as $linha):
    $inativo = (int) $linha['ativo'] !== 1;
    $mini    = isset($def['miniatura']) ? (string) ($linha[$def['miniatura']] ?? '') : '';
?>
  <li class="p-item<?= $inativo ? ' is-inativo' : '' ?>" data-id="<?= (int) $linha['id'] ?>">
    <span class="p-pega" title="Arraste para mudar a ordem" aria-hidden="true">≡</span>

<?php if ($mini !== ''): ?>
    <img class="p-mini" src="../<?= e($mini) ?>" alt="" loading="lazy" />
<?php elseif (isset($def['miniatura'])): ?>
    <span class="p-mini" aria-hidden="true"></span>
<?php endif; ?>

    <span class="p-dados">
<?php $primeiro = true; foreach ($def['resumo'] as $coluna => $rotulo): ?>
<?php if ($primeiro): $primeiro = false; ?>
      <strong><?= e((string) ($linha[$coluna] ?? '')) ?><?= $inativo ? ' <span class="p-tag">desativado</span>' : '' ?></strong>
<?php else: ?>
      <span><?= e($rotulo) ?>: <?= e((string) ($linha[$coluna] ?? '')) ?></span>
<?php endif; ?>
<?php endforeach; ?>
    </span>

    <span class="p-acoes">
      <a class="p-btn" href="painel.php?tela=<?= e($tela) ?>&amp;editar=<?= (int) $linha['id'] ?>">Editar</a>
    </span>
  </li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
```

O `<strong>` acima imprime uma tag HTML depois do `e()`. Isso é proposital e seguro: o dado passou por `e()` e o `<span class="p-tag">` é markup fixo escrito aqui, não vem do banco.

- [ ] **Passo 9: Conferir no navegador**

```bash
php -S localhost:8000 -t public_html
```

Abra `http://localhost:8000/painel/`. Faça, nesta ordem:

1. Entre com login e senha errados. Confira a mensagem "Login ou senha incorretos.".
2. Erre cinco vezes seguidas. Na quinta, a mensagem passa a ser a de bloqueio por 15 minutos.
3. Libere o bloqueio para continuar: `php -r "require 'public_html/lib/db.php'; db()->exec('DELETE FROM login_tentativas');"`
4. Entre com `castello` e a senha que o `migrar.php` imprimiu. Confira que cai no painel.
5. Percorra as seis abas. Modelos mostra 4 itens com miniatura, Portfólio 6, Avaliações 14, Vídeos 11 com a frase do limite, FAQ 7, Passo a passo 5.
6. Em Modelos, troque o filtro entre Casa Pronta e Castelo Flex. Flex mostra a mensagem de lista vazia.
7. Abra em um celular ou na visão responsiva com 390 px de largura. O menu rola na horizontal e a lista continua legível.
8. Abra `http://localhost:8000/painel/painel.php` em uma janela anônima. Tem que redirecionar para o login.

- [ ] **Passo 10: Rodar a suíte e commitar**

Rode: `php testes/smoke.php`
Esperado: `todos os casos passaram`.

```bash
git add public_html/painel testes/casos/80-painel.php
git commit -m "feat: painel com login, shell e lista generica dirigida por descricao

painel/tabelas.php descreve as seis telas de conteudo. Lista, filtro,
miniatura e resumo saem dessa descricao, sem tela copiada.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Tarefa 11: Painel — formulário genérico, salvar e upload

**Arquivos:**
- Modificar: `public_html/painel/tabelas.php` (acrescentar as funções de escrita ao final)
- Criar: `public_html/painel/telas/form.php`
- Criar: `public_html/painel/acoes/salvar.php`
- Teste: acrescentar ao final de `testes/casos/80-painel.php`

**Interfaces:**
- Consome: `painel_tabela()`, `painel_linha()`, `painel_listar()` da Tarefa 10; `upload_receber()`, `UPLOAD_TIPOS` da Tarefa 8; `csrf_token()`, `csrf_validar()`, `auth_exigir()`, `auth_iniciar()` da Tarefa 7; `CASTELLO_ICONES_FAQ` da Tarefa 3.
- Produz: `painel_valores(array $def, array $entrada): array`, `painel_arquivos(array $def, array $arquivos): array` devolvendo `array{valores: array<string,string>, erros: array<string,string>}`, `painel_erro_upload(string $erro, string $tipo): string`, `painel_erros(array $def, array $valores): array` (mapa `coluna => mensagem`), `painel_salvar(string $chave, ?int $id, array $valores): int` (devolve o id gravado).

- [ ] **Passo 1: Escrever os testes que falham**

Acrescente ao final de `testes/casos/80-painel.php`:

```php
teste('painel_valores so deixa passar as colunas descritas', function (): void {
    $def = painel_tabela('portfolio');

    $v = painel_valores($def, [
        'titulo'    => '  Casa nova  ',
        'categoria' => 'Térrea',
        'foto_alt'  => 'Alt da foto',
        'id'        => '7',
        'ativo'     => '0',
        'ordem'     => '99',
        'inventada' => 'x',
    ]);

    igual(['titulo', 'categoria', 'foto_alt'], array_keys($v), 'campos de arquivo e colunas de fora nao entram');
    igual('Casa nova', $v['titulo'], 'texto e aparado');
});

teste('painel_valores converte cada tipo do jeito certo', function (): void {
    $modelos = painel_tabela('modelos');

    igual(1, painel_valores($modelos, ['destaque' => '1'])['destaque']);
    igual(0, painel_valores($modelos, ['destaque' => '0'])['destaque']);
    igual(0, painel_valores($modelos, [])['destaque'], 'checkbox ausente vira 0');

    igual('pronta', painel_valores($modelos, ['modalidade' => 'pronta'])['modalidade']);
    igual('flex', painel_valores($modelos, ['modalidade' => 'flex'])['modalidade']);
    igual('pronta', painel_valores($modelos, ['modalidade' => 'invasao'])['modalidade'], 'selecao invalida cai na primeira opcao');

    igual(5, painel_valores(painel_tabela('avaliacoes'), ['estrelas' => '5'])['estrelas']);
    igual(0, painel_valores(painel_tabela('avaliacoes'), ['estrelas' => 'texto'])['estrelas']);

    igual('chave', painel_valores(painel_tabela('faq'), ['icone' => 'chave'])['icone']);
    igual('relogio', painel_valores(painel_tabela('faq'), ['icone' => 'inventado'])['icone'], 'icone fora do conjunto cai em relogio');
});

teste('painel_erros aponta so os campos obrigatorios vazios', function (): void {
    $def = painel_tabela('avaliacoes');

    igual([], painel_erros($def, ['nome' => 'Ana', 'texto' => 'Muito bom', 'estrelas' => 5]));

    $erros = painel_erros($def, ['nome' => '', 'texto' => '', 'estrelas' => 5]);
    igual(['nome', 'texto'], array_keys($erros));
    contem('Nome do cliente', $erros['nome']);
});

teste('painel_salvar insere ativo, no fim da ordem, e devolve o id', function (): void {
    $antes = count(painel_listar('portfolio'));

    $id = painel_salvar('portfolio', null, [
        'titulo'    => 'Casa de teste',
        'categoria' => 'Teste',
        'foto'      => 'uploads/portfolio/casa1.png',
        'foto_alt'  => 'Alt de teste',
    ]);

    verdade($id > 0);
    igual($antes + 1, count(painel_listar('portfolio')));

    $linha = painel_linha('portfolio', $id);
    igual('Casa de teste', $linha['titulo']);
    igual(1, (int) $linha['ativo'], 'item novo nasce ativo');

    $maior = (int) db()->query('SELECT MAX(ordem) FROM portfolio')->fetchColumn();
    igual($maior, (int) $linha['ordem'], 'item novo entra no fim da ordem');
});

teste('painel_salvar com id atualiza e nao mexe em ativo nem em ordem', function (): void {
    $id = painel_salvar('portfolio', null, ['titulo' => 'Antes', 'categoria' => 'A', 'foto' => '', 'foto_alt' => '']);
    db()->prepare('UPDATE portfolio SET ativo = 0, ordem = 42 WHERE id = ?')->execute([$id]);

    $mesmo = painel_salvar('portfolio', $id, ['titulo' => 'Depois', 'categoria' => 'B', 'foto' => '', 'foto_alt' => '']);
    igual($id, $mesmo, 'atualizar devolve o mesmo id');

    $linha = painel_linha('portfolio', $id);
    igual('Depois', $linha['titulo']);
    igual(0, (int) $linha['ativo'], 'salvar nao reativa sozinho');
    igual(42, (int) $linha['ordem'], 'salvar nao muda a ordem');
});

teste('painel_salvar recusa tabela que nao esta na descricao', function (): void {
    foreach (['usuarios', 'leads', 'config', 'portfolio; DROP TABLE portfolio'] as $chave) {
        $pegou = false;
        try {
            painel_salvar($chave, null, ['x' => 'y']);
        } catch (InvalidArgumentException $ex) {
            $pegou = true;
        }
        verdade($pegou, "painel_salvar tinha que recusar a chave $chave");
    }

    igual(1, (int) db()->query("SELECT COUNT(*) FROM sqlite_master WHERE name = 'portfolio'")->fetchColumn(),
        'a tabela portfolio continua de pe');
    igual(0, (int) db()->query('SELECT COUNT(*) FROM usuarios')->fetchColumn(), 'nada foi escrito em usuarios');
});

teste('painel_salvar ignora coluna que nao esta na descricao', function (): void {
    $id = painel_salvar('avaliacoes', null, [
        'nome'     => 'Cliente teste',
        'texto'    => 'Texto de teste',
        'estrelas' => 5,
        'ativo'    => 0,
        'ordem'    => 1,
    ]);

    igual(1, (int) painel_linha('avaliacoes', $id)['ativo'], 'ativo nao pode vir do formulario');
});

teste('painel_arquivos grava o upload valido e devolve o caminho relativo', function (): void {
    $img = imagecreatetruecolor(40, 40);
    ob_start();
    imagejpeg($img);
    $bytes = (string) ob_get_clean();
    imagedestroy($img);

    $tmp = tempnam(sys_get_temp_dir(), 'castello-pan');
    file_put_contents($tmp, $bytes);

    $r = painel_arquivos(painel_tabela('portfolio'), [
        'foto' => ['name' => 'Casa Nova.jpg', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => strlen($bytes)],
    ]);

    igual([], $r['erros']);
    verdade(str_starts_with($r['valores']['foto'], 'uploads/portfolio/'));
});

teste('painel_arquivos devolve mensagem em portugues quando o tipo esta errado', function (): void {
    $tmp = tempnam(sys_get_temp_dir(), 'castello-pan');
    file_put_contents($tmp, "<?php echo 1; ");

    $r = painel_arquivos(painel_tabela('portfolio'), [
        'foto' => ['name' => 'shell.jpg', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => 14],
    ]);

    igual([], $r['valores']);
    contem('JPG, PNG ou WEBP', $r['erros']['foto']);
});

teste('painel_arquivos ignora campo de arquivo que veio vazio', function (): void {
    $r = painel_arquivos(painel_tabela('portfolio'), [
        'foto' => ['name' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0],
    ]);

    igual([], $r['valores'], 'campo vazio nao apaga a foto que ja estava la');
    igual([], $r['erros']);
});

teste('painel_erro_upload fala a lingua do cliente', function (): void {
    contem('30 MB', painel_erro_upload('tamanho', 'video'));
    contem('5 MB', painel_erro_upload('tamanho', 'imagem'));
    contem('MP4', painel_erro_upload('tipo', 'video'));
    nao_contem('finfo', painel_erro_upload('tipo', 'imagem'));
});
```

- [ ] **Passo 2: Rodar e ver falhar**

Rode: `php testes/smoke.php 80-painel`
Esperado: os 11 testes da Tarefa 10 continuam passando e os 11 novos falham com `Call to undefined function painel_valores()`.

- [ ] **Passo 3: Acrescentar as funções de escrita ao `painel/tabelas.php`**

Cole ao final de `public_html/painel/tabelas.php`:

```php
/**
 * Extrai da entrada apenas as colunas descritas, ja convertidas para o tipo
 * de cada campo. Campos de arquivo ficam de fora: eles entram por
 * painel_arquivos(). Colunas de controle (id, ativo, ordem) nunca entram.
 */
function painel_valores(array $def, array $entrada): array
{
    $valores = [];

    foreach ($def['campos'] as $coluna => $campo) {
        if (in_array($campo['tipo'], ['imagem', 'video'], true)) {
            continue;
        }

        $bruto = $entrada[$coluna] ?? null;

        $valores[$coluna] = match ($campo['tipo']) {
            'sim_nao'   => ((string) $bruto === '1') ? 1 : 0,
            'numero'    => (int) $bruto,
            'selecao'   => isset($campo['opcoes'][(string) $bruto])
                             ? (string) $bruto
                             : (string) array_key_first($campo['opcoes']),
            'icone_faq' => icone_faq((string) $bruto) !== '' ? (string) $bruto : 'relogio',
            default     => trim((string) $bruto),
        };
    }

    return $valores;
}

/**
 * Processa os campos de arquivo do formulario.
 *
 * @return array{valores: array<string,string>, erros: array<string,string>}
 */
function painel_arquivos(array $def, array $arquivos): array
{
    $valores = [];
    $erros   = [];

    foreach ($def['campos'] as $coluna => $campo) {
        if (!in_array($campo['tipo'], ['imagem', 'video'], true)) {
            continue;
        }
        if (!isset($arquivos[$coluna])) {
            continue;
        }
        if ((int) ($arquivos[$coluna]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $r = upload_receber($arquivos[$coluna], (string) $campo['pasta'], $campo['tipo']);

        if ($r['ok']) {
            $valores[$coluna] = (string) $r['caminho'];
        } else {
            $erros[$coluna] = painel_erro_upload((string) $r['erro'], $campo['tipo']);
        }
    }

    return ['valores' => $valores, 'erros' => $erros];
}

/** Traduz o codigo de erro do upload para uma frase que o cliente entende. */
function painel_erro_upload(string $erro, string $tipo): string
{
    return match ($erro) {
        'tamanho' => $tipo === 'video'
            ? 'O vídeo passa de 30 MB. Reduza o arquivo e envie de novo.'
            : 'A imagem passa de 5 MB. Reduza o arquivo e envie de novo.',
        'tipo' => $tipo === 'video'
            ? 'Este arquivo não é um vídeo MP4. Envie o vídeo em MP4.'
            : 'Este arquivo não é uma imagem. Envie em JPG, PNG ou WEBP.',
        'gravacao'    => 'Não consegui gravar o arquivo no servidor. Tente de novo.',
        'sem_arquivo' => 'Nenhum arquivo chegou.',
        default       => 'O envio do arquivo não deu certo. Tente de novo.',
    };
}

/**
 * Campos obrigatorios que chegaram vazios.
 *
 * @return array<string,string> coluna para mensagem
 */
function painel_erros(array $def, array $valores): array
{
    $erros = [];

    foreach ($def['campos'] as $coluna => $campo) {
        if (empty($campo['obrigatorio'])) {
            continue;
        }

        $valor = $valores[$coluna] ?? '';
        if ($valor === '' || $valor === null) {
            $erros[$coluna] = $campo['rotulo'] . ' precisa ser preenchido.';
        }
    }

    return $erros;
}

/**
 * Insere ou atualiza uma linha. Devolve o id gravado.
 *
 * Item novo nasce ativo e no fim da ordem. Atualizar nunca mexe em ativo nem
 * em ordem: para isso existem painel_estado() e painel_reordenar().
 */
function painel_salvar(string $chave, ?int $id, array $valores): int
{
    $def = painel_tabela($chave);
    if ($def === null) {
        throw new InvalidArgumentException('tela de conteudo desconhecida: ' . $chave);
    }

    $colunas = array_values(array_intersect(array_keys($def['campos']), array_keys($valores)));
    if ($colunas === []) {
        throw new InvalidArgumentException('nada para gravar em ' . $chave);
    }

    $parametros = array_map(static fn (string $coluna) => $valores[$coluna], $colunas);

    if ($id === null || $id <= 0) {
        $ordem = (int) db()->query('SELECT COALESCE(MAX(ordem), 0) + 1 FROM ' . $chave)->fetchColumn();

        $sql = 'INSERT INTO ' . $chave . ' (' . implode(', ', $colunas) . ', ativo, ordem) VALUES ('
             . implode(', ', array_fill(0, count($colunas), '?')) . ', 1, ?)';

        $parametros[] = $ordem;
        db()->prepare($sql)->execute($parametros);

        return (int) db()->lastInsertId();
    }

    $sql = 'UPDATE ' . $chave . ' SET '
         . implode(', ', array_map(static fn (string $coluna): string => $coluna . ' = ?', $colunas))
         . ' WHERE id = ?';

    $parametros[] = $id;
    db()->prepare($sql)->execute($parametros);

    return $id;
}
```

- [ ] **Passo 4: Escrever a tela de formulário**

Crie `public_html/painel/telas/form.php`:

```php
<?php
/**
 * Formulario generico de adicionar e editar. Espera: string $tela, array $def.
 * Os campos saem da descricao em painel/tabelas.php.
 */
declare(strict_types=1);

$id    = (int) ($_GET['editar'] ?? 0);
$linha = $id > 0 ? painel_linha($tela, $id) : null;

if ($id > 0 && $linha === null) {
    echo '<p class="p-aviso p-aviso--erro">Este item não existe mais.</p>';
    echo '<p><a class="p-btn" href="painel.php?tela=' . e($tela) . '">Voltar para a lista</a></p>';
    return;
}

auth_iniciar();
$rascunho = $_SESSION['painel_form'] ?? null;
unset($_SESSION['painel_form']);

$valores = $linha ?? [];
$erros   = [];

if (is_array($rascunho) && ($rascunho['tela'] ?? '') === $tela && (int) ($rascunho['id'] ?? -1) === $id) {
    $valores = array_merge($valores, $rascunho['valores']);
    $erros   = $rascunho['erros'];
}

$filtro = (string) ($_GET['filtro'] ?? '');
$voltar = 'painel.php?tela=' . rawurlencode($tela) . ($filtro !== '' ? '&filtro=' . rawurlencode($filtro) : '');
?>
<h1><?= $id > 0 ? 'Editar' : 'Adicionar' ?> <?= e($def['singular']) ?></h1>
<p class="p-sub"><a href="<?= e($voltar) ?>">Voltar para <?= e($def['rotulo']) ?></a></p>

<?php if ($erros !== []): ?>
<p class="p-aviso p-aviso--erro" role="alert">Faltou alguma coisa. Veja os campos marcados abaixo.</p>
<?php endif; ?>

<form class="p-form" method="post" action="acoes/salvar.php" enctype="multipart/form-data">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>" />
  <input type="hidden" name="tela" value="<?= e($tela) ?>" />
  <input type="hidden" name="id" value="<?= $id ?>" />
  <input type="hidden" name="filtro" value="<?= e($filtro) ?>" />

<?php foreach ($def['campos'] as $coluna => $campo):
    $valor = (string) ($valores[$coluna] ?? '');
    $erro  = (string) ($erros[$coluna] ?? '');
    $idc   = 'c-' . $coluna;
    $arquivo = in_array($campo['tipo'], ['imagem', 'video'], true);
?>
  <div class="p-campo<?= $erro !== '' ? ' p-campo--erro' : '' ?>">
<?php if ($campo['tipo'] === 'icone_faq'): ?>
    <strong><?= e($campo['rotulo']) ?><?= !empty($campo['obrigatorio']) ? ' *' : '' ?></strong>
<?php else: ?>
    <label for="<?= e($idc) ?>"><?= e($campo['rotulo']) ?><?= !empty($campo['obrigatorio']) ? ' *' : '' ?></label>
<?php endif; ?>

<?php if ($campo['tipo'] === 'texto'): ?>
    <input type="text" id="<?= e($idc) ?>" name="<?= e($coluna) ?>" value="<?= e($valor) ?>" />

<?php elseif ($campo['tipo'] === 'texto_longo'): ?>
    <textarea id="<?= e($idc) ?>" name="<?= e($coluna) ?>" rows="5"><?= e($valor) ?></textarea>

<?php elseif ($campo['tipo'] === 'numero'): ?>
    <input type="number" id="<?= e($idc) ?>" name="<?= e($coluna) ?>" value="<?= e($valor) ?>" min="0" step="1" />

<?php elseif ($campo['tipo'] === 'selecao'): ?>
    <select id="<?= e($idc) ?>" name="<?= e($coluna) ?>">
<?php foreach ($campo['opcoes'] as $opcao => $rotulo): ?>
      <option value="<?= e((string) $opcao) ?>"<?= (string) $opcao === $valor ? ' selected' : '' ?>><?= e($rotulo) ?></option>
<?php endforeach; ?>
    </select>

<?php elseif ($campo['tipo'] === 'sim_nao'): ?>
    <input type="hidden" name="<?= e($coluna) ?>" value="0" />
    <input type="checkbox" id="<?= e($idc) ?>" name="<?= e($coluna) ?>" value="1"<?= $valor === '1' ? ' checked' : '' ?> />

<?php elseif ($campo['tipo'] === 'icone_faq'): $marcado = $valor !== '' ? $valor : 'relogio'; ?>
    <div class="p-icones">
<?php foreach (CASTELLO_ICONES_FAQ as $chaveIcone => $svg): ?>
      <label class="p-icone" title="<?= e($chaveIcone) ?>">
        <input type="radio" name="<?= e($coluna) ?>" value="<?= e($chaveIcone) ?>"<?= $marcado === $chaveIcone ? ' checked' : '' ?> />
        <?= $svg ?>
        <span class="p-icone-nome" hidden><?= e($chaveIcone) ?></span>
      </label>
<?php endforeach; ?>
    </div>

<?php elseif ($arquivo): ?>
<?php if ($valor !== ''): ?>
    <span class="p-atual">
<?php if ($campo['tipo'] === 'video'): ?>
      <video src="../<?= e($valor) ?>" muted playsinline preload="metadata"></video>
<?php else: ?>
      <img src="../<?= e($valor) ?>" alt="" />
<?php endif; ?>
      Já enviado. Escolha outro arquivo só se quiser trocar.
    </span>
<?php endif; ?>
    <input type="file" id="<?= e($idc) ?>" name="<?= e($coluna) ?>"
           accept="<?= $campo['tipo'] === 'video' ? 'video/mp4' : 'image/jpeg,image/png,image/webp' ?>" />
<?php endif; ?>

<?php if (!empty($campo['ajuda'])): ?>
    <span class="p-ajuda"><?= e($campo['ajuda']) ?></span>
<?php endif; ?>
<?php if ($erro !== ''): ?>
    <p class="p-erro"><?= e($erro) ?></p>
<?php endif; ?>
  </div>
<?php endforeach; ?>

  <button class="p-btn p-btn--forte" type="submit">Salvar</button>
  <a class="p-btn p-btn--fraco" href="<?= e($voltar) ?>">Cancelar</a>
</form>
```

- [ ] **Passo 5: Escrever a ação de salvar**

Crie `public_html/painel/acoes/salvar.php`:

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../tabelas.php';

auth_exigir();

$tela   = (string) ($_POST['tela'] ?? '');
$id     = (int) ($_POST['id'] ?? 0);
$filtro = (string) ($_POST['filtro'] ?? '');
$def    = painel_tabela($tela);

if ($def === null) {
    header('Location: ../painel.php?erro=' . rawurlencode('Seção desconhecida.'));
    exit;
}

$lista = '../painel.php?tela=' . rawurlencode($tela) . ($filtro !== '' ? '&filtro=' . rawurlencode($filtro) : '');
$form  = '../painel.php?tela=' . rawurlencode($tela) . ($id > 0 ? '&editar=' . $id : '&novo=1');

if (!csrf_validar($_POST['csrf'] ?? null)) {
    header('Location: ' . $lista . '&erro=' . rawurlencode('A sessão expirou. Entre de novo e repita o envio.'));
    exit;
}

$arquivos = painel_arquivos($def, $_FILES);
$valores  = array_merge(painel_valores($def, $_POST), $arquivos['valores']);

// Campo de arquivo que veio vazio mantem o que ja estava gravado.
$atual = $id > 0 ? painel_linha($tela, $id) : null;
foreach ($def['campos'] as $coluna => $campo) {
    if (in_array($campo['tipo'], ['imagem', 'video'], true) && !isset($valores[$coluna])) {
        $valores[$coluna] = (string) ($atual[$coluna] ?? '');
    }
}

$erros = array_merge(painel_erros($def, $valores), $arquivos['erros']);

if ($erros !== []) {
    auth_iniciar();
    $_SESSION['painel_form'] = ['tela' => $tela, 'id' => $id, 'valores' => $valores, 'erros' => $erros];
    header('Location: ' . $form);
    exit;
}

painel_salvar($tela, $id > 0 ? $id : null, $valores);

$recado = $id > 0
    ? 'Alterações salvas.'
    : mb_strtoupper(mb_substr($def['singular'], 0, 1)) . mb_substr($def['singular'], 1) . ' adicionado.';

header('Location: ' . $lista . '&ok=' . rawurlencode($recado));
exit;
```

- [ ] **Passo 6: Rodar e ver passar**

Rode: `php testes/smoke.php 80-painel`
Esperado: 22 ok, 0 falha, 0 pulado.

- [ ] **Passo 7: Conferir no navegador**

Com `php -S localhost:8000 -t public_html` no ar e logado no painel:

1. **Modelos, adicionar:** clique em Adicionar modelo, deixe o nome vazio e salve. Volta ao formulário com o campo marcado em vermelho, a mensagem do campo e o resto do que você digitou preservado.
2. Preencha nome, área `30,00 m²`, parede `Parede simples`, preço `49.900`, escolha modalidade Castelo Flex, envie uma foto e salve. Volta para a lista com a faixa verde `Modelo adicionado.` e o item aparece no filtro Castelo Flex.
3. Abra `http://localhost:8000/flex.php`. O modelo novo aparece lá.
4. **Upload recusado:** renomeie um `.php` qualquer para `.jpg` e tente enviar como foto. O formulário volta com a mensagem `Este arquivo não é uma imagem. Envie em JPG, PNG ou WEBP.` e nada é gravado.
5. **Editar sem trocar a foto:** edite um modelo, mude só o preço, salve. A foto continua a mesma.
6. **FAQ:** adicione uma pergunta. Confira que os sete ícones aparecem desenhados e que clicar em um marca o quadro em vermelho. Salve e veja o ícone escolhido na home.
7. **Vídeos:** adicione um vídeo MP4 e uma capa. Confira que aparece na lista com miniatura.
8. Abra o painel no celular e repita o passo 2. O formulário e o seletor de ícone precisam ser usáveis com o polegar.

- [ ] **Passo 8: Rodar a suíte e commitar**

Rode: `php testes/smoke.php`
Esperado: `todos os casos passaram`.

```bash
git add public_html/painel testes/casos/80-painel.php
git commit -m "feat: formulario generico do painel com salvar, upload e seletor de icone

Um formulario so atende as seis telas, montado a partir da descricao das
tabelas. Coluna que nao esta na descricao nunca chega ao SQL.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Tarefa 12: Painel — desativar, reativar e reordenar arrastando

**Arquivos:**
- Modificar: `public_html/painel/tabelas.php` (acrescentar `painel_estado` e `painel_reordenar`)
- Modificar: `public_html/painel/telas/lista.php` (botão de estado, aviso da ordem e o script)
- Criar: `public_html/painel/acoes/estado.php`, `public_html/painel/acoes/ordem.php`
- Criar: `public_html/painel/assets/painel.js`
- Teste: acrescentar ao final de `testes/casos/80-painel.php`

**Interfaces:**
- Consome: `painel_tabela()`, `painel_linha()`, `painel_listar()`, `csrf_token()`, `csrf_validar()`, `auth_exigir()`, `auth_logado()`, `videos()`.
- Produz: `painel_estado(string $chave, int $id, int $ativo): void`, `painel_reordenar(string $chave, array $ids): int` (devolve quantas linhas mudaram); e o endpoint `painel/acoes/ordem.php`, que responde JSON `{"ok":true,"atualizados":N}` ou `{"ok":false,"erro":"csrf|tela|sessao"}`.

- [ ] **Passo 1: Escrever os testes que falham**

Acrescente ao final de `testes/casos/80-painel.php`:

```php
teste('painel_estado desativa e reativa sem apagar nada', function (): void {
    $id = (int) db()->query("SELECT id FROM avaliacoes WHERE nome = 'Nany Festa'")->fetchColumn();

    painel_estado('avaliacoes', $id, 0);
    igual(0, (int) painel_linha('avaliacoes', $id)['ativo']);
    igual(13, count(avaliacoes()), 'o site deixa de mostrar');
    verdade(painel_linha('avaliacoes', $id) !== null, 'a linha continua no banco');

    painel_estado('avaliacoes', $id, 1);
    igual(1, (int) painel_linha('avaliacoes', $id)['ativo']);
    igual(14, count(avaliacoes()));
});

teste('painel_estado recusa tabela fora da descricao', function (): void {
    foreach (['usuarios', 'leads', 'config'] as $chave) {
        $pegou = false;
        try {
            painel_estado($chave, 1, 0);
        } catch (InvalidArgumentException $ex) {
            $pegou = true;
        }
        verdade($pegou, "painel_estado tinha que recusar $chave");
    }
});

teste('painel_reordenar renumera de 1 ate N na ordem recebida', function (): void {
    $ids = array_map('intval', array_column(painel_listar('passos', 'pronta'), 'id'));
    igual(5, count($ids));

    $invertido = array_reverse($ids);
    igual(5, painel_reordenar('passos', $invertido));

    $agora = array_map('intval', array_column(painel_listar('passos', 'pronta'), 'id'));
    igual($invertido, $agora, 'a lista do painel segue a nova ordem');
    igual('Chave na mão', passos('pronta')[0]['titulo'], 'o site tambem segue');

    igual(1, (int) painel_linha('passos', $invertido[0])['ordem']);
    igual(5, (int) painel_linha('passos', $invertido[4])['ordem']);

    painel_reordenar('passos', $ids);
    igual('Conversa e projeto', passos('pronta')[0]['titulo'], 'voltou ao normal');
});

teste('reordenar troca quais videos aparecem na home', function (): void {
    igual('8', config_ler('videos_na_home'));
    nao_contem('insta-11', videos()[0]['arquivo'] . videos()[1]['arquivo']);

    $ids = array_map('intval', array_column(painel_listar('videos'), 'id'));
    $ultimo = array_pop($ids);
    array_unshift($ids, $ultimo);
    painel_reordenar('videos', $ids);

    contem('insta-11', videos()[0]['arquivo'], 'o promovido virou o primeiro da home');
    igual(8, count(videos()), 'o limite continua valendo');
    igual(11, count(videos(0)), 'nenhum video sumiu');
});

teste('painel_reordenar ignora id invalido e recusa tabela desconhecida', function (): void {
    $ids = array_map('intval', array_column(painel_listar('portfolio'), 'id'));
    igual(count($ids), painel_reordenar('portfolio', array_merge($ids, [0, -3, 999999])));

    $pegou = false;
    try {
        painel_reordenar('usuarios', [1]);
    } catch (InvalidArgumentException $ex) {
        $pegou = true;
    }
    verdade($pegou);
});
```

- [ ] **Passo 2: Rodar e ver falhar**

Rode: `php testes/smoke.php 80-painel`
Esperado: os 22 anteriores passam, os 5 novos falham com `Call to undefined function painel_estado()`.

- [ ] **Passo 3: Acrescentar as duas funções ao `painel/tabelas.php`**

Cole ao final de `public_html/painel/tabelas.php`:

```php
/** Liga ou desliga um item. Nada e apagado, so deixa de aparecer no site. */
function painel_estado(string $chave, int $id, int $ativo): void
{
    if (painel_tabela($chave) === null) {
        throw new InvalidArgumentException('tela de conteudo desconhecida: ' . $chave);
    }

    db()->prepare('UPDATE ' . $chave . ' SET ativo = ? WHERE id = ?')
        ->execute([$ativo === 1 ? 1 : 0, $id]);
}

/**
 * Grava a nova ordem: o primeiro id da lista vira ordem 1, e assim por diante.
 * Ids que nao existem sao ignorados. Devolve quantas linhas mudaram.
 */
function painel_reordenar(string $chave, array $ids): int
{
    if (painel_tabela($chave) === null) {
        throw new InvalidArgumentException('tela de conteudo desconhecida: ' . $chave);
    }

    $st = db()->prepare('UPDATE ' . $chave . ' SET ordem = ? WHERE id = ?');
    $posicao = 0;
    $mudaram = 0;

    db()->beginTransaction();
    foreach ($ids as $bruto) {
        $id = (int) $bruto;
        if ($id <= 0) {
            continue;
        }
        $posicao++;
        $st->execute([$posicao, $id]);
        $mudaram += $st->rowCount();
    }
    db()->commit();

    return $mudaram;
}
```

- [ ] **Passo 4: Acrescentar o botão de estado e o script à `telas/lista.php`**

Na `public_html/painel/telas/lista.php`, faça três mudanças.

**4a.** Troque a abertura da lista para levar a tabela e o token:

```php
<ul class="p-lista" id="pLista" data-tabela="<?= e($tela) ?>" data-csrf="<?= e(csrf_token()) ?>">
```

**4b.** Troque o bloco `<span class="p-acoes">` inteiro por:

```php
    <span class="p-acoes">
      <a class="p-btn" href="painel.php?tela=<?= e($tela) ?>&amp;editar=<?= (int) $linha['id'] ?><?= $filtro !== null ? '&amp;filtro=' . e($filtro) : '' ?>">Editar</a>
      <form method="post" action="acoes/estado.php">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>" />
        <input type="hidden" name="tela" value="<?= e($tela) ?>" />
        <input type="hidden" name="filtro" value="<?= e((string) $filtro) ?>" />
        <input type="hidden" name="id" value="<?= (int) $linha['id'] ?>" />
        <input type="hidden" name="ativo" value="<?= $inativo ? '1' : '0' ?>" />
        <button class="p-btn p-btn--fraco" type="submit"><?= $inativo ? 'Reativar' : 'Desativar' ?></button>
      </form>
    </span>
```

**4c.** Logo antes de `<ul class="p-lista"` acrescente o aviso da ordem, e depois do `</ul>` acrescente o script:

```php
<p class="p-aviso" id="pOrdemAviso" role="status" hidden></p>
```

```php
<script src="assets/painel.js?v=1"></script>
```

- [ ] **Passo 5: Escrever `acoes/estado.php`**

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../tabelas.php';

auth_exigir();

$tela   = (string) ($_POST['tela'] ?? '');
$id     = (int) ($_POST['id'] ?? 0);
$ativo  = ((string) ($_POST['ativo'] ?? '')) === '1' ? 1 : 0;
$filtro = (string) ($_POST['filtro'] ?? '');

if (painel_tabela($tela) === null) {
    header('Location: ../painel.php?erro=' . rawurlencode('Seção desconhecida.'));
    exit;
}

$lista = '../painel.php?tela=' . rawurlencode($tela) . ($filtro !== '' ? '&filtro=' . rawurlencode($filtro) : '');

if (!csrf_validar($_POST['csrf'] ?? null)) {
    header('Location: ' . $lista . '&erro=' . rawurlencode('A sessão expirou. Entre de novo e repita.'));
    exit;
}

if ($id <= 0 || painel_linha($tela, $id) === null) {
    header('Location: ' . $lista . '&erro=' . rawurlencode('Este item não existe mais.'));
    exit;
}

painel_estado($tela, $id, $ativo);

$recado = $ativo === 1
    ? 'Item reativado. Já aparece no site.'
    : 'Item desativado. Saiu do site, mas continua guardado aqui.';

header('Location: ' . $lista . '&ok=' . rawurlencode($recado));
exit;
```

- [ ] **Passo 6: Escrever `acoes/ordem.php`**

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../tabelas.php';

header('Content-Type: application/json; charset=utf-8');

if (!auth_logado()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'erro' => 'sessao']);
    exit;
}

$corpo = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($corpo)) {
    $corpo = $_POST;
}

if (!csrf_validar(isset($corpo['csrf']) ? (string) $corpo['csrf'] : null)) {
    http_response_code(419);
    echo json_encode(['ok' => false, 'erro' => 'csrf']);
    exit;
}

$tela = (string) ($corpo['tela'] ?? '');
if (painel_tabela($tela) === null) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'erro' => 'tela']);
    exit;
}

$ids = is_array($corpo['ids'] ?? null) ? $corpo['ids'] : [];

echo json_encode(['ok' => true, 'atualizados' => painel_reordenar($tela, $ids)]);
```

- [ ] **Passo 7: Escrever `assets/painel.js`**

```javascript
/* Reordenar arrastando, com ponteiro unico: funciona no mouse e no toque. */
(function () {
  'use strict';

  var lista = document.getElementById('pLista');
  if (!lista) { return; }

  var tabela = lista.getAttribute('data-tabela') || '';
  var csrf   = lista.getAttribute('data-csrf') || '';
  var aviso  = document.getElementById('pOrdemAviso');
  var item   = null;

  function itens() {
    return Array.prototype.slice.call(lista.querySelectorAll('.p-item'));
  }

  function vizinhoAbaixoDe(y) {
    var todos = itens();
    for (var i = 0; i < todos.length; i++) {
      var caixa = todos[i].getBoundingClientRect();
      if (y < caixa.top + caixa.height / 2) { return todos[i]; }
    }
    return null;
  }

  function mostrar(texto, ok) {
    if (!aviso) { return; }
    aviso.textContent = texto;
    aviso.className = ok ? 'p-aviso' : 'p-aviso p-aviso--erro';
    aviso.hidden = false;
  }

  function gravar() {
    var ids = itens().map(function (li) {
      return parseInt(li.getAttribute('data-id'), 10);
    });

    fetch('acoes/ordem.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ csrf: csrf, tela: tabela, ids: ids })
    })
      .then(function (resposta) { return resposta.json(); })
      .then(function (dados) {
        if (dados && dados.ok) {
          mostrar('Ordem salva.', true);
        } else {
          mostrar('Não consegui salvar a ordem. Recarregue a página e tente de novo.', false);
        }
      })
      .catch(function () {
        mostrar('Não consegui salvar a ordem. Recarregue a página e tente de novo.', false);
      });
  }

  lista.addEventListener('pointerdown', function (ev) {
    var pega = ev.target && ev.target.closest ? ev.target.closest('.p-pega') : null;
    if (!pega) { return; }

    item = pega.closest('.p-item');
    if (!item) { return; }

    item.classList.add('is-arrastando');
    pega.setPointerCapture(ev.pointerId);
    ev.preventDefault();
  });

  lista.addEventListener('pointermove', function (ev) {
    if (!item) { return; }
    ev.preventDefault();

    var vizinho = vizinhoAbaixoDe(ev.clientY);
    if (vizinho === item) { return; }

    if (vizinho) {
      lista.insertBefore(item, vizinho);
    } else {
      lista.appendChild(item);
    }
  });

  function soltar() {
    if (!item) { return; }
    item.classList.remove('is-arrastando');
    item = null;
    gravar();
  }

  lista.addEventListener('pointerup', soltar);
  lista.addEventListener('pointercancel', soltar);
})();
```

- [ ] **Passo 8: Rodar e ver passar**

Rode: `php testes/smoke.php 80-painel`
Esperado: 27 ok, 0 falha, 0 pulado.

- [ ] **Passo 9: Conferir no navegador**

Com o servidor local no ar e logado:

1. Em Avaliações, clique em Desativar em um item. A faixa verde aparece, o item fica esmaecido com a etiqueta `desativado` e some da home.
2. Clique em Reativar. Volta para a home.
3. Em Vídeos, arraste o último item para o topo pela alça `≡`. A frase `Ordem salva.` aparece. Recarregue a página: a ordem continua. Abra a home: o vídeo promovido é o primeiro e a home continua com oito.
4. Repita o arrasto no celular, com o dedo, na visão responsiva de 390 px.
5. Teste a recusa de CSRF: no console do navegador, rode
   `fetch('acoes/ordem.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf:'errado',tela:'videos',ids:[1]})}).then(r=>r.status).then(console.log)`
   Esperado: `419`.

- [ ] **Passo 10: Rodar a suíte e commitar**

Rode: `php testes/smoke.php`
Esperado: `todos os casos passaram`.

```bash
git add public_html/painel testes/casos/80-painel.php
git commit -m "feat: desativar, reativar e reordenar arrastando no painel

Reordenar usa ponteiro unico, entao funciona com mouse e com o dedo. A
ordem e gravada por um endpoint JSON protegido por CSRF.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Tarefa 13: Painel — telas de Textos e Configurações

**Arquivos:**
- Modificar: `public_html/painel/tabelas.php` (acrescentar `painel_config_campos`, `painel_config_validar`, `painel_textos_gravar`)
- Modificar: `public_html/painel/painel.php` (acrescentar as duas telas fixas ao menu)
- Criar: `public_html/painel/telas/textos.php`, `public_html/painel/telas/config.php`
- Criar: `public_html/painel/acoes/textos.php`, `public_html/painel/acoes/config.php`
- Teste: acrescentar ao final de `testes/casos/80-painel.php`

**Interfaces:**
- Consome: `config_ler()`, `config_gravar()`, `bloco()`, `db()`, `csrf_token()`, `csrf_validar()`, `auth_exigir()`.
- Produz: `painel_config_campos(): array`, `painel_config_validar(array $entrada): array` devolvendo `array{valores: array<string,string>, erros: array<string,string>}`, `painel_textos_gravar(array $entrada): int` (quantas chaves foram gravadas), `painel_fixas(): array` (mapa `chave => rótulo` das telas que não são de conteúdo).

- [ ] **Passo 1: Escrever os testes que falham**

Acrescente ao final de `testes/casos/80-painel.php`:

```php
teste('painel_textos_gravar atualiza so as chaves que existem em blocos', function (): void {
    $n = painel_textos_gravar([
        'hero_titulo'  => '  A casa que você sempre quis  ',
        'flex_titulo'  => 'Castelo Flex',
        'chave_falsa'  => 'nao pode entrar',
    ]);

    igual(2, $n);
    igual('A casa que você sempre quis', bloco('hero_titulo'), 'o texto e aparado');
    igual('Castelo Flex', bloco('flex_titulo'));
    igual(21, (int) db()->query('SELECT COUNT(*) FROM blocos')->fetchColumn(), 'nenhuma chave nova foi criada');

    painel_textos_gravar(['hero_titulo' => 'A casa dos seus sonhos']);
});

teste('painel_config_campos cobre as dez chaves do contrato', function (): void {
    igual(
        ['videos_na_home', 'email_aviso', 'email_dominio', 'crm_ativo', 'crm_endpoint',
         'crm_metodo', 'crm_cabecalhos', 'crm_mapa_campos', 'crm_timeout', 'reenvio_chave'],
        array_keys(painel_config_campos())
    );
});

teste('painel_config_validar aceita uma configuracao boa', function (): void {
    $r = painel_config_validar([
        'videos_na_home'  => '6',
        'email_aviso'     => 'contato@castellomadeiras.com.br',
        'email_dominio'   => 'castellomadeiras.com.br',
        'crm_ativo'       => '1',
        'crm_endpoint'    => 'https://crm.exemplo.com.br/api/leads',
        'crm_metodo'      => 'POST',
        'crm_cabecalhos'  => '{"Authorization":"Bearer abc"}',
        'crm_mapa_campos' => '{"nome":"nome","whatsapp":"telefone"}',
        'crm_timeout'     => '8',
        'reenvio_chave'   => str_repeat('a1b2', 8),
    ]);

    igual([], $r['erros']);
    igual('6', $r['valores']['videos_na_home']);
    igual('1', $r['valores']['crm_ativo']);
    igual('8', $r['valores']['crm_timeout']);
    igual('{"Authorization":"Bearer abc"}', $r['valores']['crm_cabecalhos']);
});

teste('crm_timeout nunca passa de 10, porque max_execution_time e 60', function (): void {
    $base = [
        'videos_na_home' => '8', 'email_aviso' => 'a@b.com', 'email_dominio' => 'b.com',
        'crm_ativo' => '0', 'crm_endpoint' => '', 'crm_metodo' => 'POST',
        'crm_cabecalhos' => '{}', 'crm_mapa_campos' => '{}', 'reenvio_chave' => str_repeat('c', 32),
    ];

    igual('10', painel_config_validar($base + ['crm_timeout' => '30'])['valores']['crm_timeout']);
    igual('1', painel_config_validar($base + ['crm_timeout' => '0'])['valores']['crm_timeout']);
    igual('10', painel_config_validar($base + ['crm_timeout' => 'texto'])['valores']['crm_timeout']);
});

teste('reenvio_chave em branco gera uma chave nova em vez de apagar', function (): void {
    $base = [
        'videos_na_home' => '8', 'email_aviso' => 'a@b.com', 'email_dominio' => 'b.com',
        'crm_ativo' => '0', 'crm_endpoint' => '', 'crm_metodo' => 'POST',
        'crm_cabecalhos' => '{}', 'crm_mapa_campos' => '{}', 'crm_timeout' => '10',
    ];

    $nova = painel_config_validar($base + ['reenvio_chave' => ''])['valores']['reenvio_chave'];
    verdade(strlen($nova) >= 32, 'chave vazia tem que virar uma chave nova e longa');

    $mantida = str_repeat('d', 40);
    igual($mantida, painel_config_validar($base + ['reenvio_chave' => $mantida])['valores']['reenvio_chave']);
});

teste('painel_config_validar recusa numero fora da faixa, e-mail torto e JSON quebrado', function (): void {
    $r = painel_config_validar([
        'videos_na_home'  => '0',
        'email_aviso'     => 'nao-e-email',
        'email_dominio'   => 'nao vale espaco',
        'crm_ativo'       => '0',
        'crm_endpoint'    => 'isso nao e url',
        'crm_metodo'      => 'DELETE',
        'crm_cabecalhos'  => '{quebrado',
        'crm_mapa_campos' => '',
        'crm_timeout'     => '10',
        'reenvio_chave'   => str_repeat('e', 32),
    ]);

    igual(
        ['videos_na_home', 'email_aviso', 'email_dominio', 'crm_endpoint', 'crm_cabecalhos'],
        array_keys($r['erros'])
    );
    contem('1 a 24', $r['erros']['videos_na_home']);
    contem('e-mail', $r['erros']['email_aviso']);
    contem('domínio', $r['erros']['email_dominio']);
    contem('https://', $r['erros']['crm_endpoint']);
    contem('JSON', $r['erros']['crm_cabecalhos']);

    igual('POST', $r['valores']['crm_metodo'], 'metodo fora da lista cai em POST');
    igual('{}', $r['valores']['crm_mapa_campos'], 'JSON vazio vira objeto vazio');
});

teste('config gravada muda o que a home mostra', function (): void {
    $r = painel_config_validar([
        'videos_na_home'  => '3',
        'email_aviso'     => 'contato@castellomadeiras.com.br',
        'email_dominio'   => 'castellomadeiras.com.br',
        'crm_ativo'       => '0',
        'crm_endpoint'    => '',
        'crm_metodo'      => 'POST',
        'crm_cabecalhos'  => '{}',
        'crm_mapa_campos' => '{"nome":"nome"}',
        'crm_timeout'     => '10',
        'reenvio_chave'   => (string) config_ler('reenvio_chave'),
    ]);
    igual([], $r['erros']);

    foreach ($r['valores'] as $chave => $valor) {
        config_gravar($chave, $valor);
    }

    igual(3, count(videos()));
    config_gravar('videos_na_home', '8');
    igual(8, count(videos()));
});

teste('painel_fixas traz as quatro telas que nao sao de conteudo', function (): void {
    igual(['textos', 'config', 'backup', 'senha'], array_keys(painel_fixas()));
    igual('Textos', painel_fixas()['textos']);
    igual('Configurações', painel_fixas()['config']);
});
```

- [ ] **Passo 2: Rodar e ver falhar**

Rode: `php testes/smoke.php 80-painel`
Esperado: os 27 anteriores passam e os 8 novos falham com `Call to undefined function painel_textos_gravar()`.

- [ ] **Passo 3: Acrescentar as funções ao `painel/tabelas.php`**

Cole ao final de `public_html/painel/tabelas.php`:

```php
/** Telas do painel que nao sao de conteudo. */
function painel_fixas(): array
{
    return [
        'textos' => 'Textos',
        'config' => 'Configurações',
        'backup' => 'Backup',
        'senha'  => 'Trocar senha',
    ];
}

/** Grava os textos avulsos. So chaves que ja existem em blocos sao aceitas. */
function painel_textos_gravar(array $entrada): int
{
    $chaves = db()->query('SELECT chave FROM blocos ORDER BY rowid')->fetchAll(PDO::FETCH_COLUMN);
    $st = db()->prepare('UPDATE blocos SET valor = ? WHERE chave = ?');
    $gravadas = 0;

    foreach ($chaves as $chave) {
        if (!array_key_exists($chave, $entrada)) {
            continue;
        }
        $st->execute([trim((string) $entrada[$chave]), $chave]);
        $gravadas++;
    }

    return $gravadas;
}

/** As dez chaves de config que o cliente edita, com rotulo e tipo. */
function painel_config_campos(): array
{
    return [
        'videos_na_home' => ['rotulo' => 'Quantos vídeos aparecem na home', 'tipo' => 'numero',
                             'min' => 1, 'max' => 24,
                             'ajuda' => 'De 1 a 24. Quanto mais vídeos, mais devagar a página carrega'],
        'email_aviso'    => ['rotulo' => 'E-mail que recebe aviso de pedido novo', 'tipo' => 'texto'],
        'email_dominio'  => ['rotulo' => 'Domínio usado no remetente', 'tipo' => 'texto',
                             'ajuda' => 'Só o domínio, como castellomadeiras.com.br'],
        'crm_ativo'      => ['rotulo' => 'Enviar os pedidos para o CRM', 'tipo' => 'sim_nao',
                             'ajuda' => 'Deixe desligado enquanto o CRM não estiver configurado. O pedido continua sendo gravado e enviado por e-mail'],
        'crm_endpoint'   => ['rotulo' => 'Endereço do CRM', 'tipo' => 'texto',
                             'ajuda' => 'A URL completa, começando com https://'],
        'crm_metodo'     => ['rotulo' => 'Método HTTP', 'tipo' => 'selecao',
                             'opcoes' => ['POST' => 'POST', 'PUT' => 'PUT', 'PATCH' => 'PATCH']],
        'crm_cabecalhos' => ['rotulo' => 'Cabeçalhos do CRM', 'tipo' => 'json',
                             'ajuda' => 'JSON, como {"Authorization":"Bearer sua-chave"}'],
        'crm_mapa_campos' => ['rotulo' => 'De para dos campos', 'tipo' => 'json',
                              'ajuda' => 'JSON ligando o campo do site ao nome que o CRM espera'],
        'crm_timeout'    => ['rotulo' => 'Segundos de espera pelo CRM', 'tipo' => 'numero',
                             'min' => 1, 'max' => 10,
                             'ajuda' => 'De 1 a 10. O servidor derruba a página em 60 segundos, então esperar mais que 10 pelo CRM faria o visitante esperar junto'],
        'reenvio_chave'  => ['rotulo' => 'Chave do reenvio de pendentes', 'tipo' => 'texto',
                             'ajuda' => 'Autoriza o reenvio dos pedidos que não chegaram ao CRM. Apague o campo e salve para gerar uma chave nova'],
    ];
}

/**
 * Valida a configuracao enviada pelo formulario.
 *
 * @return array{valores: array<string,string>, erros: array<string,string>}
 */
function painel_config_validar(array $entrada): array
{
    $valores = [];
    $erros   = [];

    foreach (painel_config_campos() as $chave => $campo) {
        $bruto = trim((string) ($entrada[$chave] ?? ''));

        if ($chave === 'videos_na_home') {
            $numero = (int) $bruto;
            if ($numero < 1 || $numero > 24) {
                $erros[$chave] = 'Escolha um número de 1 a 24.';
                continue;
            }
            $valores[$chave] = (string) $numero;
            continue;
        }

        if ($chave === 'crm_timeout') {
            $numero = (int) $bruto;
            if ($numero < 1) {
                $numero = $bruto === '' || !ctype_digit($bruto) ? 10 : 1;
            }
            if ($numero > 10) {
                $numero = 10;
            }
            $valores[$chave] = (string) $numero;
            continue;
        }

        if ($chave === 'email_aviso') {
            if ($bruto === '' || filter_var($bruto, FILTER_VALIDATE_EMAIL) === false) {
                $erros[$chave] = 'Escreva um e-mail válido, como contato@castellomadeiras.com.br';
                continue;
            }
            $valores[$chave] = $bruto;
            continue;
        }

        if ($chave === 'email_dominio') {
            if ($bruto === '' || !preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/i', $bruto)) {
                $erros[$chave] = 'Escreva só o domínio, como castellomadeiras.com.br';
                continue;
            }
            $valores[$chave] = mb_strtolower($bruto);
            continue;
        }

        if ($chave === 'reenvio_chave') {
            $valores[$chave] = strlen($bruto) >= 32 ? $bruto : bin2hex(random_bytes(16));
            continue;
        }

        if ($chave === 'crm_ativo') {
            $valores[$chave] = $bruto === '1' ? '1' : '0';
            continue;
        }

        if ($chave === 'crm_endpoint') {
            if ($bruto !== '' && filter_var($bruto, FILTER_VALIDATE_URL) === false) {
                $erros[$chave] = 'Escreva a URL completa, começando com https://';
                continue;
            }
            $valores[$chave] = $bruto;
            continue;
        }

        if ($chave === 'crm_metodo') {
            $valores[$chave] = isset($campo['opcoes'][$bruto]) ? $bruto : 'POST';
            continue;
        }

        $decodificado = json_decode($bruto === '' ? '{}' : $bruto, true);
        if (!is_array($decodificado)) {
            $erros[$chave] = 'Este campo precisa ser um JSON válido, como {"chave":"valor"}.';
            continue;
        }
        $valores[$chave] = (string) json_encode($decodificado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    return ['valores' => $valores, 'erros' => $erros];
}
```

- [ ] **Passo 4: Ligar as telas fixas ao menu do `painel.php`**

Em `public_html/painel/painel.php`, troque a linha

```php
$abas = painel_abas();
```

por

```php
$abas = painel_abas() + painel_fixas();
```

O roteamento que já está escrito manda as telas fixas para `telas/<tela>.php`, então nada mais muda ali.

- [ ] **Passo 5: Escrever a tela de Textos**

Crie `public_html/painel/telas/textos.php`:

```php
<?php
/**
 * Textos avulsos do site (tabela blocos). Espera: nada.
 */
declare(strict_types=1);

$blocos = db()->query('SELECT chave, rotulo, valor, tipo FROM blocos ORDER BY rowid')->fetchAll();
?>
<h1>Textos</h1>
<p class="p-sub">Os textos soltos do site: título do topo, chamadas das seções e os prazos de cada modalidade.</p>

<form class="p-form" method="post" action="acoes/textos.php">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>" />

<?php foreach ($blocos as $b): $idc = 'b-' . $b['chave']; ?>
  <div class="p-campo">
    <label for="<?= e($idc) ?>"><?= e($b['rotulo']) ?></label>
<?php if ($b['tipo'] === 'texto_longo'): ?>
    <textarea id="<?= e($idc) ?>" name="valores[<?= e($b['chave']) ?>]" rows="4"><?= e((string) $b['valor']) ?></textarea>
<?php else: ?>
    <input type="text" id="<?= e($idc) ?>" name="valores[<?= e($b['chave']) ?>]" value="<?= e((string) $b['valor']) ?>" />
<?php endif; ?>
  </div>
<?php endforeach; ?>

  <button class="p-btn p-btn--forte" type="submit">Salvar textos</button>
</form>
```

- [ ] **Passo 6: Escrever a tela de Configurações**

Crie `public_html/painel/telas/config.php`:

```php
<?php
/**
 * Configuracoes do site e do conector do CRM (tabela config). Espera: nada.
 */
declare(strict_types=1);

auth_iniciar();
$rascunho = $_SESSION['painel_config'] ?? null;
unset($_SESSION['painel_config']);

$erros = is_array($rascunho) ? (array) ($rascunho['erros'] ?? []) : [];
$sujos = is_array($rascunho) ? (array) ($rascunho['valores'] ?? []) : [];

$ativos = (int) db()->query('SELECT COUNT(*) FROM videos WHERE ativo = 1')->fetchColumn();
?>
<h1>Configurações</h1>
<p class="p-sub">Você tem <?= $ativos ?> vídeos ativos. A home mostra os primeiros da ordem definida na tela Vídeos.</p>

<form class="p-form" method="post" action="acoes/config.php">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>" />

<?php foreach (painel_config_campos() as $chave => $campo):
    $valor = array_key_exists($chave, $sujos) ? (string) $sujos[$chave] : (string) config_ler($chave, '');
    $erro  = (string) ($erros[$chave] ?? '');
    $idc   = 'k-' . $chave;
?>
  <div class="p-campo<?= $erro !== '' ? ' p-campo--erro' : '' ?>">
    <label for="<?= e($idc) ?>"><?= e($campo['rotulo']) ?></label>

<?php if ($campo['tipo'] === 'numero'): ?>
    <input type="number" id="<?= e($idc) ?>" name="<?= e($chave) ?>" value="<?= e($valor) ?>"
           min="<?= (int) ($campo['min'] ?? 1) ?>" max="<?= (int) ($campo['max'] ?? 24) ?>" step="1" />

<?php elseif ($campo['tipo'] === 'sim_nao'): ?>
    <input type="hidden" name="<?= e($chave) ?>" value="0" />
    <input type="checkbox" id="<?= e($idc) ?>" name="<?= e($chave) ?>" value="1"<?= $valor === '1' ? ' checked' : '' ?> />

<?php elseif ($campo['tipo'] === 'selecao'): ?>
    <select id="<?= e($idc) ?>" name="<?= e($chave) ?>">
<?php foreach ($campo['opcoes'] as $opcao => $rotulo): ?>
      <option value="<?= e((string) $opcao) ?>"<?= (string) $opcao === $valor ? ' selected' : '' ?>><?= e($rotulo) ?></option>
<?php endforeach; ?>
    </select>

<?php elseif ($campo['tipo'] === 'json'): ?>
    <textarea id="<?= e($idc) ?>" name="<?= e($chave) ?>" rows="3"><?= e($valor) ?></textarea>

<?php else: ?>
    <input type="text" id="<?= e($idc) ?>" name="<?= e($chave) ?>" value="<?= e($valor) ?>" />
<?php endif; ?>

<?php if (!empty($campo['ajuda'])): ?>
    <span class="p-ajuda"><?= e($campo['ajuda']) ?></span>
<?php endif; ?>
<?php if ($erro !== ''): ?>
    <p class="p-erro"><?= e($erro) ?></p>
<?php endif; ?>
  </div>
<?php endforeach; ?>

  <button class="p-btn p-btn--forte" type="submit">Salvar configurações</button>
</form>
```

- [ ] **Passo 7: Escrever as duas ações**

Crie `public_html/painel/acoes/textos.php`:

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../tabelas.php';

auth_exigir();

if (!csrf_validar($_POST['csrf'] ?? null)) {
    header('Location: ../painel.php?tela=textos&erro=' . rawurlencode('A sessão expirou. Entre de novo e repita.'));
    exit;
}

$quantas = painel_textos_gravar(is_array($_POST['valores'] ?? null) ? $_POST['valores'] : []);

header('Location: ../painel.php?tela=textos&ok=' . rawurlencode($quantas . ' textos salvos.'));
exit;
```

Crie `public_html/painel/acoes/config.php`:

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../tabelas.php';

auth_exigir();

if (!csrf_validar($_POST['csrf'] ?? null)) {
    header('Location: ../painel.php?tela=config&erro=' . rawurlencode('A sessão expirou. Entre de novo e repita.'));
    exit;
}

$r = painel_config_validar($_POST);

if ($r['erros'] !== []) {
    auth_iniciar();
    $_SESSION['painel_config'] = ['valores' => $_POST, 'erros' => $r['erros']];
    header('Location: ../painel.php?tela=config&erro=' . rawurlencode('Veja os campos marcados abaixo.'));
    exit;
}

foreach ($r['valores'] as $chave => $valor) {
    config_gravar($chave, $valor);
}

header('Location: ../painel.php?tela=config&ok=' . rawurlencode('Configurações salvas.'));
exit;
```

- [ ] **Passo 8: Rodar e ver passar**

Rode: `php testes/smoke.php 80-painel`
Esperado: 35 ok, 0 falha, 0 pulado.

- [ ] **Passo 9: Conferir no navegador**

1. Abra Textos. Mude o Prazo da Casa Pronta para `90 a 110 dias` e salve. A faixa verde diz `21 textos salvos.` e o valor persiste ao recarregar. Volte para `90 a 120 dias`.
2. Abra Configurações. Coloque `0` em quantos vídeos aparecem e salve. O campo volta marcado com `Escolha um número de 1 a 24.` e o resto do formulário fica como você deixou.
3. Coloque `4` e salve. Abra a home: quatro vídeos. Volte para `8`.
4. Escreva `{quebrado` nos cabeçalhos do CRM e salve. Mensagem de JSON inválido, nada gravado.
5. Escreva um e-mail sem arroba e salve. Mensagem de e-mail inválido.

- [ ] **Passo 10: Rodar a suíte e commitar**

Rode: `php testes/smoke.php`
Esperado: `todos os casos passaram`.

```bash
git add public_html/painel testes/casos/80-painel.php
git commit -m "feat: telas de Textos e Configuracoes no painel

Textos edita a tabela blocos; Configuracoes edita as dez chaves de config,
incluindo os campos do conector do CRM, com validacao de numero, e-mail,
URL e JSON.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Tarefa 14: Painel — Backup e Trocar senha

**Arquivos:**
- Modificar: `public_html/painel/tabelas.php` (acrescentar `painel_backup` e `painel_trocar_senha`)
- Criar: `public_html/painel/telas/backup.php`, `public_html/painel/telas/senha.php`
- Criar: `public_html/painel/acoes/backup.php`, `public_html/painel/acoes/senha.php`
- Teste: `testes/casos/90-backup.php`

**Interfaces:**
- Consome: `db()`, `agora()`, `auth_exigir()`, `auth_logado()`, `csrf_token()`, `csrf_validar()`; as constantes `CASTELLO_CONFIG` e `CASTELLO_UPLOADS`.
- Produz: `painel_backup(): array` devolvendo `array{ok: bool, arquivo: ?string, erro: ?string}`, e `painel_trocar_senha(int $usuarioId, string $atual, string $nova, string $confirma): array` devolvendo `array{ok: bool, erro: ?string}`.

**O `ZipArchive` só existe no servidor.** Localmente a classe não está instalada e `phar.readonly` está ligado, então o caminho feliz do backup **não pode** ser validado aqui. `painel_backup()` checa `class_exists('ZipArchive')` e devolve erro claro, nunca um fatal. O teste local se marca como pulado dizendo o motivo, e a validação de verdade acontece na Tarefa 16, no servidor.

**Checkpoint do WAL antes de zipar.** O banco roda em modo WAL, então parte das últimas gravações pode estar no arquivo `.db-wal` e não no `.db`. Sem `PRAGMA wal_checkpoint(TRUNCATE)` antes de zipar, o backup sairia desatualizado sem ninguém perceber.

- [ ] **Passo 1: Escrever o teste que falha**

Crie `testes/casos/90-backup.php`:

```php
<?php
declare(strict_types=1);

require_once site() . '/painel/tabelas.php';
require_once site() . '/migrar.php';

banco_com_conteudo();
migrar_usuario('castello', 'senha-de-teste-forte');

teste('painel_backup nunca dispara fatal quando falta o ZipArchive', function (): void {
    if (class_exists('ZipArchive')) {
        pular('ZipArchive existe neste PHP, entao o caminho de falha nao aparece aqui');
    }

    $r = painel_backup();
    falso($r['ok']);
    igual(null, $r['arquivo']);
    contem('ZipArchive', (string) $r['erro']);
    contem('zip do PHP', (string) $r['erro'], 'a mensagem tem que dizer o que pedir para a hospedagem');
});

teste('painel_backup gera um zip com o banco e a pasta uploads', function (): void {
    if (!class_exists('ZipArchive')) {
        pular('ZipArchive nao existe no PHP local e phar.readonly esta ligado, entao o zip so pode ser validado no servidor');
    }

    $r = painel_backup();
    verdade($r['ok'], 'erro: ' . var_export($r['erro'], true));
    verdade(is_file((string) $r['arquivo']));

    $zip = new ZipArchive();
    igual(true, $zip->open((string) $r['arquivo']));

    $dentro = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $dentro[] = (string) $zip->getNameIndex($i);
    }
    $zip->close();

    verdade(in_array('castello.db', $dentro, true), 'o banco precisa estar no zip');
    verdade(count(array_filter($dentro, static fn (string $n): bool => str_starts_with($n, 'uploads/'))) > 20,
        'as fotos e os videos migrados precisam estar no zip');

    unlink((string) $r['arquivo']);
});

teste('trocar senha exige a senha atual correta', function (): void {
    $id = (int) db()->query("SELECT id FROM usuarios WHERE login = 'castello'")->fetchColumn();

    $r = painel_trocar_senha($id, 'errada', 'senha-nova-123', 'senha-nova-123');
    falso($r['ok']);
    contem('senha atual', (string) $r['erro']);

    verdade(password_verify('senha-de-teste-forte',
        (string) db()->query("SELECT senha_hash FROM usuarios WHERE id = $id")->fetchColumn()),
        'a senha nao pode ter mudado');
});

teste('trocar senha exige 8 caracteres e confirmacao igual', function (): void {
    $id = (int) db()->query("SELECT id FROM usuarios WHERE login = 'castello'")->fetchColumn();

    $curta = painel_trocar_senha($id, 'senha-de-teste-forte', 'abc123', 'abc123');
    falso($curta['ok']);
    contem('8 caracteres', (string) $curta['erro']);

    $torta = painel_trocar_senha($id, 'senha-de-teste-forte', 'senha-nova-123', 'senha-nova-124');
    falso($torta['ok']);
    contem('confirmação', (string) $torta['erro']);

    $igual = painel_trocar_senha($id, 'senha-de-teste-forte', 'senha-de-teste-forte', 'senha-de-teste-forte');
    falso($igual['ok']);
    contem('diferente', (string) $igual['erro']);
});

teste('trocar senha grava o hash novo e o antigo deixa de valer', function (): void {
    $id = (int) db()->query("SELECT id FROM usuarios WHERE login = 'castello'")->fetchColumn();

    $r = painel_trocar_senha($id, 'senha-de-teste-forte', 'senha-nova-do-cliente', 'senha-nova-do-cliente');
    verdade($r['ok'], 'erro: ' . var_export($r['erro'], true));
    igual(null, $r['erro']);

    $hash = (string) db()->query("SELECT senha_hash FROM usuarios WHERE id = $id")->fetchColumn();
    verdade(str_starts_with($hash, '$2y$'), 'continua bcrypt');
    verdade(password_verify('senha-nova-do-cliente', $hash));
    falso(password_verify('senha-de-teste-forte', $hash), 'a senha antiga tem que deixar de valer');
});

teste('trocar senha de usuario inexistente devolve erro, nao fatal', function (): void {
    $r = painel_trocar_senha(999999, 'qualquer', 'senha-nova-123', 'senha-nova-123');
    falso($r['ok']);
    contem('não encontrado', (string) $r['erro']);
});
```

- [ ] **Passo 2: Rodar e ver falhar**

Rode: `php testes/smoke.php 90-backup`
Esperado: falha com `Call to undefined function painel_backup()`.

- [ ] **Passo 3: Acrescentar as duas funções ao `painel/tabelas.php`**

Cole ao final de `public_html/painel/tabelas.php`:

```php
/**
 * Gera um zip com o banco e a pasta uploads.
 *
 * ZipArchive nao existe no PHP local, so no servidor. Por isso a checagem:
 * sem a classe, devolve erro claro em vez de derrubar a pagina.
 *
 * @return array{ok: bool, arquivo: ?string, erro: ?string}
 */
function painel_backup(): array
{
    if (!class_exists('ZipArchive')) {
        return [
            'ok'      => false,
            'arquivo' => null,
            'erro'    => 'A extensão ZipArchive não está instalada neste servidor, então não consigo gerar o arquivo. Peça à hospedagem para ligar a extensão zip do PHP.',
        ];
    }

    // O banco roda em WAL: sem o checkpoint, as ultimas gravacoes ficariam so
    // no arquivo .db-wal e o backup sairia desatualizado.
    db()->exec('PRAGMA wal_checkpoint(TRUNCATE)');

    $destino = rtrim(sys_get_temp_dir(), "/\\") . '/castello-backup-' . date('Y-m-d-His') . '.zip';

    $zip = new ZipArchive();
    if ($zip->open($destino, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return ['ok' => false, 'arquivo' => null, 'erro' => 'Não consegui criar o arquivo de backup em disco.'];
    }

    $banco = CASTELLO_CONFIG . '/castello.db';
    if (is_file($banco)) {
        $zip->addFile($banco, 'castello.db');
    }

    if (is_dir(CASTELLO_UPLOADS)) {
        $itens = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(CASTELLO_UPLOADS, FilesystemIterator::SKIP_DOTS)
        );
        $corte = strlen(CASTELLO_UPLOADS) + 1;

        foreach ($itens as $item) {
            if (!$item->isFile()) {
                continue;
            }
            $relativo = 'uploads/' . str_replace('\\', '/', substr($item->getPathname(), $corte));
            $zip->addFile($item->getPathname(), $relativo);
        }
    }

    if (!$zip->close()) {
        return ['ok' => false, 'arquivo' => null, 'erro' => 'Não consegui fechar o arquivo de backup.'];
    }

    return ['ok' => true, 'arquivo' => $destino, 'erro' => null];
}

/**
 * Troca a senha do unico usuario do painel.
 *
 * @return array{ok: bool, erro: ?string}
 */
function painel_trocar_senha(int $usuarioId, string $atual, string $nova, string $confirma): array
{
    $st = db()->prepare('SELECT senha_hash FROM usuarios WHERE id = ?');
    $st->execute([$usuarioId]);
    $hash = $st->fetchColumn();

    if ($hash === false) {
        return ['ok' => false, 'erro' => 'Usuário não encontrado.'];
    }
    if (!password_verify($atual, (string) $hash)) {
        return ['ok' => false, 'erro' => 'A senha atual está errada.'];
    }
    if (mb_strlen($nova) < 8) {
        return ['ok' => false, 'erro' => 'A senha nova precisa ter pelo menos 8 caracteres.'];
    }
    if ($nova !== $confirma) {
        return ['ok' => false, 'erro' => 'A confirmação não bate com a senha nova.'];
    }
    if ($nova === $atual) {
        return ['ok' => false, 'erro' => 'A senha nova precisa ser diferente da atual.'];
    }

    db()->prepare('UPDATE usuarios SET senha_hash = ? WHERE id = ?')
        ->execute([password_hash($nova, PASSWORD_BCRYPT), $usuarioId]);

    return ['ok' => true, 'erro' => null];
}
```

- [ ] **Passo 4: Escrever a tela de Backup**

Crie `public_html/painel/telas/backup.php`:

```php
<?php
/**
 * Tela de backup. Espera: nada.
 */
declare(strict_types=1);

$temZip = class_exists('ZipArchive');

$tamanhoBanco = is_file(CASTELLO_CONFIG . '/castello.db') ? (int) filesize(CASTELLO_CONFIG . '/castello.db') : 0;

$arquivosMidia = 0;
$tamanhoMidia  = 0;
if (is_dir(CASTELLO_UPLOADS)) {
    $itens = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(CASTELLO_UPLOADS, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($itens as $item) {
        if ($item->isFile()) {
            $arquivosMidia++;
            $tamanhoMidia += (int) $item->getSize();
        }
    }
}

$emMega = static fn (int $bytes): string => number_format($bytes / 1048576, 1, ',', '.') . ' MB';
?>
<h1>Backup</h1>
<p class="p-sub">
  Baixa um arquivo zip com tudo que é seu: o banco com os textos e o cadastro,
  mais <?= $arquivosMidia ?> arquivos de foto e vídeo.
  Banco: <?= e($emMega($tamanhoBanco)) ?>. Mídia: <?= e($emMega($tamanhoMidia)) ?>.
</p>

<?php if (!$temZip): ?>
<p class="p-aviso p-aviso--erro" role="alert">
  A extensão ZipArchive não está instalada neste servidor, então não consigo gerar o arquivo.
  Peça à hospedagem para ligar a extensão zip do PHP.
</p>
<?php else: ?>
<form class="p-form" method="post" action="acoes/backup.php">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>" />
  <p class="p-sub">Guarde o arquivo em um lugar seguro. Faça um backup antes de mudanças grandes.</p>
  <button class="p-btn p-btn--forte" type="submit">Baixar backup agora</button>
</form>
<?php endif; ?>
```

- [ ] **Passo 5: Escrever a ação de Backup**

Crie `public_html/painel/acoes/backup.php`:

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../tabelas.php';

auth_exigir();

if (!csrf_validar($_POST['csrf'] ?? null)) {
    header('Location: ../painel.php?tela=backup&erro=' . rawurlencode('A sessão expirou. Entre de novo e repita.'));
    exit;
}

$r = painel_backup();

if (!$r['ok']) {
    header('Location: ../painel.php?tela=backup&erro=' . rawurlencode((string) $r['erro']));
    exit;
}

$arquivo = (string) $r['arquivo'];
$nome    = 'castello-backup-' . date('Y-m-d') . '.zip';

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $nome . '"');
header('Content-Length: ' . (string) filesize($arquivo));
header('Cache-Control: private, no-store');

readfile($arquivo);
unlink($arquivo);
exit;
```

- [ ] **Passo 6: Escrever a tela e a ação de Trocar senha**

Crie `public_html/painel/telas/senha.php`:

```php
<?php
/**
 * Trocar a senha do painel. Espera: nada.
 */
declare(strict_types=1);
?>
<h1>Trocar senha</h1>
<p class="p-sub">A senha precisa ter pelo menos 8 caracteres. Se você esquecer, quem redefine é a Freela In Home.</p>

<form class="p-form" method="post" action="acoes/senha.php" autocomplete="off">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>" />

  <div class="p-campo">
    <label for="s-atual">Senha atual</label>
    <input type="password" id="s-atual" name="atual" autocomplete="current-password" required />
  </div>

  <div class="p-campo">
    <label for="s-nova">Senha nova</label>
    <input type="password" id="s-nova" name="nova" autocomplete="new-password" minlength="8" required />
  </div>

  <div class="p-campo">
    <label for="s-confirma">Repita a senha nova</label>
    <input type="password" id="s-confirma" name="confirma" autocomplete="new-password" minlength="8" required />
  </div>

  <button class="p-btn p-btn--forte" type="submit">Trocar senha</button>
</form>
```

Crie `public_html/painel/acoes/senha.php`:

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../tabelas.php';

auth_exigir();

if (!csrf_validar($_POST['csrf'] ?? null)) {
    header('Location: ../painel.php?tela=senha&erro=' . rawurlencode('A sessão expirou. Entre de novo e repita.'));
    exit;
}

auth_iniciar();
$usuarioId = (int) ($_SESSION['usuario_id'] ?? 0);

$r = painel_trocar_senha(
    $usuarioId,
    (string) ($_POST['atual'] ?? ''),
    (string) ($_POST['nova'] ?? ''),
    (string) ($_POST['confirma'] ?? '')
);

if (!$r['ok']) {
    header('Location: ../painel.php?tela=senha&erro=' . rawurlencode((string) $r['erro']));
    exit;
}

header('Location: ../painel.php?tela=senha&ok=' . rawurlencode('Senha trocada. Use a nova no próximo acesso.'));
exit;
```

- [ ] **Passo 7: Rodar e ver passar**

Rode: `php testes/smoke.php 90-backup`
Esperado no ambiente local: 5 ok, 0 falha, **1 pulado**, e a linha do pulado dizendo `ZipArchive nao existe no PHP local e phar.readonly esta ligado, entao o zip so pode ser validado no servidor`.

Rode a suíte inteira: `php testes/smoke.php`
Esperado: `todos os casos passaram`.

- [ ] **Passo 8: Conferir no navegador**

1. Abra Backup. A tela mostra a contagem de arquivos e os dois tamanhos, e no ambiente local mostra a faixa vermelha avisando que falta o ZipArchive. Isso está certo: o download só funciona no servidor.
2. Abra Trocar senha. Digite a senha atual errada e salve: mensagem `A senha atual está errada.`.
3. Digite a senha atual certa e uma nova de 6 caracteres: mensagem sobre os 8 caracteres.
4. Digite uma senha nova de 10 caracteres com confirmação diferente: mensagem sobre a confirmação.
5. Troque de verdade. Saia e entre com a senha nova. A antiga não entra mais.

- [ ] **Passo 9: Commit**

```bash
git add public_html/painel testes/casos/90-backup.php
git commit -m "feat: backup em zip e troca de senha no painel

O backup checa class_exists('ZipArchive') e avisa com clareza quando falta,
porque a extensao so existe no servidor. Antes de zipar, faz checkpoint do
WAL para nao gerar backup desatualizado.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Tarefa 15: Os três `.htaccess`

**Arquivos:**
- Criar: `public_html/.htaccess`, `public_html/uploads/.htaccess`, `public_html/painel/.htaccess`
- Teste: `testes/casos/95-htaccess.php`

**Interfaces:**
- Consome: `site()` dos testes.
- Produz: três arquivos de configuração de servidor. Não há função nova.

O servidor de teste é **LiteSpeed**, que lê `.htaccess` com a sintaxe do Apache 2.4. As diretivas abaixo usam só o que o LiteSpeed entende, e as que dependem de módulo ficam dentro de `<IfModule>` para não derrubar o site em um servidor que não as tenha.

`uploads/.htaccess` é a trava que importa: mesmo que alguém consiga gravar um arquivo executável ali, ele não roda.

- [ ] **Passo 1: Escrever o teste que falha**

Crie `testes/casos/95-htaccess.php`:

```php
<?php
declare(strict_types=1);

function htaccess(string $pasta): string
{
    $caminho = rtrim(site() . '/' . $pasta, '/') . '/.htaccess';
    $texto   = file_get_contents($caminho);

    if ($texto === false) {
        throw new RuntimeException('nao consegui ler ' . $caminho);
    }

    return $texto;
}

teste('o htaccess da raiz forca HTTPS e nao lista pastas', function (): void {
    $t = htaccess('');
    contem('RewriteEngine On', $t);
    contem('RewriteCond %{HTTPS} !=on', $t);
    contem('R=301', $t);
    contem('Options -Indexes', $t);
    contem('DirectoryIndex index.php', $t);
});

teste('o htaccess da raiz bloqueia banco, schema e caminho-config', function (): void {
    $t = htaccess('');
    contem('caminho-config.php', $t);
    contem('schema.sql', $t);
    contem('.db', $t);
    contem('Require all denied', $t);
});

teste('o htaccess de uploads desliga a execucao de script', function (): void {
    $t = htaccess('uploads');

    contem('Options -ExecCGI', $t);
    contem('RemoveHandler', $t);
    contem('php_flag engine off', $t);
    contem('Require all denied', $t);

    foreach (['php', 'phtml', 'cgi', 'pl', 'py', 'sh'] as $extensao) {
        contem($extensao, $t, "uploads precisa bloquear .$extensao");
    }
});

teste('o htaccess do painel deixa o HTTP Basic pronto para ligar', function (): void {
    $t = htaccess('painel');

    contem('# AuthType Basic', $t);
    contem('# AuthName "Painel Castello"', $t);
    contem('# AuthUserFile', $t);
    contem('# Require valid-user', $t);
    contem('htpasswd', $t, 'a instrucao de como criar o arquivo de senhas tem que estar ali');

    falso(str_contains($t, "\nAuthType Basic"), 'o Basic vem desligado por padrao');
});

teste('nenhum htaccess vaza caminho de disco do ambiente local', function (): void {
    foreach (['', 'uploads', 'painel'] as $pasta) {
        nao_contem('prototipo-site-castello', htaccess($pasta));
        nao_contem('C:\\', htaccess($pasta));
    }
});
```

- [ ] **Passo 2: Rodar e ver falhar**

Rode: `php testes/smoke.php 95-htaccess`
Esperado: todos falham com `nao consegui ler .../public_html/.htaccess`.

- [ ] **Passo 3: Escrever `public_html/.htaccess`**

```apache
# Castello Casas de Madeira. Servidor de teste: LiteSpeed, que le .htaccess
# com a sintaxe do Apache 2.4.

AddDefaultCharset UTF-8
DirectoryIndex index.php index.html
Options -Indexes

# HTTPS obrigatorio. Inegociavel: o painel manda senha por este canal.
<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteCond %{HTTPS} !=on
  RewriteCond %{HTTP:X-Forwarded-Proto} !=https
  RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]
</IfModule>

# Nada de servir banco, schema, segredo ou arquivo de ambiente.
<FilesMatch "(^\.|\.(sql|db|db-wal|db-shm|sqlite|log|ini|bak|old|sh)$|^caminho-config\.php$)">
  Require all denied
</FilesMatch>

# Compressao e cache dos estaticos. As paginas PHP mandam o proprio
# Cache-Control, porque carregam o token do formulario.
<IfModule mod_deflate.c>
  AddOutputFilterByType DEFLATE text/html text/css application/javascript image/svg+xml
</IfModule>

<IfModule mod_expires.c>
  ExpiresActive On
  ExpiresByType text/css "access plus 7 days"
  ExpiresByType application/javascript "access plus 7 days"
  ExpiresByType image/png "access plus 30 days"
  ExpiresByType image/jpeg "access plus 30 days"
  ExpiresByType image/webp "access plus 30 days"
  ExpiresByType video/mp4 "access plus 30 days"
</IfModule>
```

- [ ] **Passo 4: Escrever `public_html/uploads/.htaccess`**

```apache
# Nada executa nesta pasta. Se um arquivo malicioso passar pela validacao do
# upload, ele ainda assim nao roda. Esta e a trava que importa aqui.

Options -ExecCGI -Indexes

RemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8 .phps .cgi .pl .py .jsp .asp .aspx .sh
RemoveType .php .phtml .php3 .php4 .php5 .php7 .php8 .phps

<IfModule mod_php.c>
  php_flag engine off
</IfModule>
<IfModule lsapi_module>
  php_flag engine off
</IfModule>
<IfModule mod_lsapi.c>
  php_flag engine off
</IfModule>

<FilesMatch "\.(php|phtml|php[0-9]|phps|cgi|pl|py|jsp|asp|aspx|sh|htaccess|htpasswd)$">
  Require all denied
</FilesMatch>
```

- [ ] **Passo 5: Escrever `public_html/painel/.htaccess`**

```apache
# Segunda senha, opcional, na frente do painel. Vem desligada.
#
# Para ligar: crie o arquivo de senhas fora do public_html e tire o
# comentario das quatro linhas abaixo.
#
#   htpasswd -c /home/freelain/domains/tohospedando.com.br/castello-config/.htpasswd castello
#
# AuthType Basic
# AuthName "Painel Castello"
# AuthUserFile /home/freelain/domains/tohospedando.com.br/castello-config/.htpasswd
# Require valid-user

Options -Indexes

<FilesMatch "\.(sql|db|db-wal|db-shm|log|ini|bak|old)$">
  Require all denied
</FilesMatch>
```

- [ ] **Passo 6: Rodar e ver passar**

Rode: `php testes/smoke.php 95-htaccess`
Esperado: 5 ok, 0 falha, 0 pulado.

Rode a suíte inteira: `php testes/smoke.php`
Esperado: `todos os casos passaram`.

O servidor embutido do PHP (`php -S`) **ignora** `.htaccess`, então nada disso é testável localmente além do conteúdo do arquivo. A checagem de verdade é a Tarefa 16, com `curl` contra o servidor.

- [ ] **Passo 7: Commit**

```bash
git add public_html/.htaccess public_html/uploads/.htaccess public_html/painel/.htaccess testes/casos/95-htaccess.php
git commit -m "feat: htaccess de HTTPS, de uploads sem execucao e do painel

uploads/ desliga handler, tipo e engine do PHP e nega por extensao: mesmo um
arquivo malicioso que passasse pela validacao nao rodaria. O HTTP Basic do
painel fica pronto e comentado, com a linha de htpasswd na frente.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Tarefa 16: Deploy no subdomínio de teste da EreHost

**Arquivos:**
- Criar, **só no servidor**: `public_html/lib/caminho-config.php`
- Criar, gerado pelo próprio `migrar.php` no servidor: `castello-config/segredos.php`, `castello-config/castello.db`
- Modificar: `README.md` (a seção de como rodar e como publicar)
- Sem teste automatizado: esta tarefa é verificada por `curl` e pelo navegador, contra o servidor real.

**Interfaces:**
- Consome: tudo que as tarefas anteriores produziram.
- Produz: o site no ar em `https://tohospedando.com.br/castello/` e o painel em `https://tohospedando.com.br/castello/painel/`.

**Ambiente de destino, já sondado:**

| | Valor |
|---|---|
| Raiz do site | `/home/freelain/domains/tohospedando.com.br/public_html/castello` |
| Pasta de configuração | `/home/freelain/domains/tohospedando.com.br/castello-config` |
| Servidor | LiteSpeed, PHP 8.5.9 |
| SQLite | 3.26.0 |
| `ZipArchive` | presente |
| `max_execution_time` | 60s |
| FTP | preso ao `public_html`, não alcança a pasta de configuração |

- [ ] **Passo 1: Pedir os acessos**

Peça ao Fabiano, da Freela In Home, os dados da conta EreHost de `tohospedando.com.br`: usuário e senha do painel de hospedagem, e o usuário, a senha e o host de FTP. Sem eles nada nesta tarefa acontece. Não invente credencial e não tente adivinhar caminho.

- [ ] **Passo 2: Subir os arquivos**

Envie **o conteúdo** de `public_html/` do repositório para `/home/freelain/domains/tohospedando.com.br/public_html/castello`, mantendo a estrutura. Inclua os três `.htaccess`, que muitos clientes de FTP escondem por começarem com ponto: ligue a exibição de arquivos ocultos antes.

**Não envie** a pasta `config/` do repositório, nem `testes/`, nem `docs/`, nem `README.md`. Envie `public_html/uploads/` **vazia**, só com o `.htaccess`: a mídia é recriada pelo `migrar.php` a partir de `fotos-casas/`, `videos-instagram/` e `passos/`, que vão junto.

Confira depois do envio:

```bash
curl -I https://tohospedando.com.br/castello/
```

Esperado: `HTTP/1.1 200` ou `HTTP/2 200`. Se vier `500`, o `.htaccess` tem uma diretiva que este servidor não aceita: comente a linha suspeita e suba de novo.

- [ ] **Passo 3: Apontar o `config/` para fora do `public_html`**

Crie no servidor, dentro de `castello/lib/`, o arquivo `caminho-config.php` com exatamente este conteúdo:

```php
<?php
return '/home/freelain/domains/tohospedando.com.br/castello-config';
```

Este arquivo é ignorado pelo git de propósito: o caminho é do servidor, não do projeto. A pasta `castello-config` ainda não existe, e o usuário de FTP não consegue criá-la, porque fica um nível acima do que ele alcança. Quem a cria é o PHP, no passo seguinte, com permissão `0700`.

- [ ] **Passo 4: Rodar a migração**

Abra no navegador:

```
https://tohospedando.com.br/castello/migrar.php
```

O banco ainda está vazio, então o script se libera sozinho, cria a pasta de configuração, grava o banco, carrega o conteúdo, gera o `segredos.php` e imprime, em texto puro:

- as sete linhas de contagem: `modelos 4`, `portfolio 6`, `avaliacoes 14`, `videos 11`, `faq 7`, `passos 5`, `blocos 21`;
- o login `castello` e uma senha sorteada;
- a chave de migração gravada em `segredos.php`;
- a instrução para apagar o `migrar.php`.

**Copie a senha e a chave agora.** Elas não aparecem de novo.

Se a página responder `nao encontrado`, o banco já tinha conteúdo: use `https://tohospedando.com.br/castello/migrar.php?chave=SUA_CHAVE`.

Se der erro de permissão ao criar a pasta, a hospedagem mudou desde a sondagem. Nesse caso pare e avise, em vez de mover o `config/` para dentro do `public_html`: fazer isso publicaria o banco na internet.

- [ ] **Passo 5: Apagar o `migrar.php` do servidor**

Pelo FTP, apague `castello/migrar.php`. Ele já cumpriu a função e não precisa continuar acessível. O arquivo continua no repositório para instalar na hospedagem definitiva do cliente.

- [ ] **Passo 6: Ligar o HTTPS**

No painel da hospedagem, gere o certificado gratuito (AutoSSL ou Let's Encrypt) para `tohospedando.com.br`, se ainda não houver. Depois confirme o redirecionamento:

```bash
curl -sI http://tohospedando.com.br/castello/ | head -3
```

Esperado: `301` e um `Location:` começando com `https://`.

- [ ] **Passo 7: Conferir as travas de segurança com `curl`**

Cada linha abaixo tem um resultado esperado. Qualquer um diferente é um problema para resolver antes de seguir.

```bash
# 1. O banco nao pode ser baixado por caminho relativo
curl -sI https://tohospedando.com.br/castello/../castello-config/castello.db | head -1
# esperado: 403 ou 404, nunca 200

# 2. Nem pela raiz do dominio
curl -sI https://tohospedando.com.br/castello-config/castello.db | head -1
# esperado: 403 ou 404

# 3. O schema e o caminho-config nao sao servidos
curl -sI https://tohospedando.com.br/castello/lib/schema.sql | head -1
curl -sI https://tohospedando.com.br/castello/lib/caminho-config.php | head -1
# esperado: 403 nos dois

# 4. Listagem de pasta desligada
curl -s https://tohospedando.com.br/castello/uploads/modelos/ | head -5
# esperado: 403, nunca a lista de arquivos

# 5. O migrar.php foi apagado
curl -sI https://tohospedando.com.br/castello/migrar.php | head -1
# esperado: 404

# 6. O painel exige login
curl -sI https://tohospedando.com.br/castello/painel/painel.php | head -3
# esperado: 302 com Location para index.php

# 7. O csrf.php entrega o token, e so ele leva no-store
curl -s https://tohospedando.com.br/castello/csrf.php
# esperado: {"token":"..."} com 64 caracteres hexadecimais
curl -sI https://tohospedando.com.br/castello/csrf.php | grep -i "cache-control\|content-type"
# esperado: application/json e private, no-store

# 8. A home continua cacheavel e nao entrega cookie de sessao
curl -sI https://tohospedando.com.br/castello/ | grep -ic "set-cookie\|no-store"
# esperado: 0
```

- [ ] **Passo 8: Provar que `uploads/` não executa script**

Esta é a trava mais importante do painel. Grave o teste, confira e apague.

Suba, pelo FTP, um arquivo `castello/uploads/teste-execucao.php` com o conteúdo:

```php
<?php echo 'ISSO NAO PODE APARECER';
```

Depois:

```bash
curl -s https://tohospedando.com.br/castello/uploads/teste-execucao.php
```

Esperado: `403 Forbidden`, ou o código-fonte em texto puro. **Nunca** a frase `ISSO NAO PODE APARECER`. Se ela aparecer, o `RemoveHandler` não pegou neste servidor: acrescente `SetHandler None` ao `uploads/.htaccess`, suba de novo e repita o teste.

Apague o arquivo de teste pelo FTP assim que terminar.

- [ ] **Passo 9: Conferir o site e o painel no navegador**

Abra `https://tohospedando.com.br/castello/` e confira, com os próprios olhos:

1. Hero com vídeo, quatro modelos com preço, acordeão do portfólio, cinco passos, carrossel de avaliações, oito vídeos do Instagram, sete abas de FAQ, modal de orçamento abrindo pelos CTAs.
2. Nenhum erro no console.
3. No código-fonte, **não** existe nenhuma metatag de token, e o `#quoteForm` tem `<input type="hidden" name="csrf" value="">` vazio. `https://tohospedando.com.br/castello/csrf.php` devolve `{"token":"..."}`.
4. Compare lado a lado com o protótipo antigo. Nada pode ter mudado de aparência.
5. `https://tohospedando.com.br/castello/flex.php` abre sem erro, com nav e rodapé.

Depois entre no painel em `https://tohospedando.com.br/castello/painel/` com `castello` e a senha do Passo 4, e faça o roteiro completo:

6. **Troque a senha na hora**, na tela Trocar senha. A senha sorteada apareceu em uma página HTTP no seu navegador e no seu histórico.
7. Percorra as dez telas: Modelos, Portfólio, Avaliações, Vídeos, FAQ, Passo a passo, Textos, Configurações, Backup, Trocar senha.
8. Adicione um modelo de teste com foto, veja aparecer no site, depois desative e veja sumir.
9. Arraste um vídeo para o topo e recarregue a home: a ordem mudou.
10. Em Backup, baixe o zip. **Aqui o `ZipArchive` existe**, então esta é a primeira validação real do backup. Abra o arquivo baixado e confirme que ele traz o `castello.db` e a pasta `uploads/` com as fotos e os vídeos.
11. Repita os passos 8 e 9 pelo celular, que é como o cliente vai usar.

- [ ] **Passo 10: Atualizar o `README.md`**

Substitua o conteúdo do `README.md` por:

```markdown
# Castello Casas de Madeira

Site institucional e painel de conteúdo. PHP + SQLite, sem framework e sem
build. A pasta `public_html/` é a raiz do site; `config/` fica ao lado dela,
fora do alcance do navegador.

## Rodar local

```bash
php -S localhost:8000 -t public_html
```

Na primeira vez, carregue o conteúdo no banco e crie o acesso do painel:

```bash
php public_html/migrar.php
```

O script imprime o login e uma senha sorteada. Anote: ela não aparece de novo.

Site em http://localhost:8000/ e painel em http://localhost:8000/painel/.

## Testes

```bash
php testes/smoke.php          # todos os casos
php testes/smoke.php 40       # só os casos cujo nome contém "40"
```

O teste de backup se marca como pulado no ambiente local, porque o
`ZipArchive` não está instalado aqui. Ele é validado no servidor.

## Ambiente de teste

https://tohospedando.com.br/castello/ , na conta EreHost da Freela In Home.
A pasta de configuração fica em
`/home/freelain/domains/tohospedando.com.br/castello-config`, apontada por
`public_html/lib/caminho-config.php`, que existe só no servidor.

## Publicar em outra hospedagem

1. Copie o conteúdo de `public_html/` para a raiz pública.
2. Crie `lib/caminho-config.php` devolvendo o caminho absoluto de uma pasta
   fora da raiz pública.
3. Abra `/migrar.php` no navegador uma vez, anote o login e a senha, e apague
   o arquivo do servidor em seguida.
4. Ligue o certificado HTTPS.
```

- [ ] **Passo 11: Commit**

```bash
git add README.md
git commit -m "docs: como rodar local, testar e publicar em outra hospedagem

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

- [ ] **Passo 12: Entregar o acesso**

Passe ao Fabiano, por canal privado, e nunca por commit:

- a URL do site e a do painel;
- o login `castello` e a senha nova escolhida no Passo 9;
- a chave de migração gravada em `segredos.php`;
- o aviso de que o `migrar.php` foi apagado do servidor e continua no repositório para a hospedagem definitiva.

---

## Autorrevisão

Feita ao final da escrita deste plano, conforme a skill manda.

**1. Cobertura da spec e do contrato.** Cada item da Frente 1 tem tarefa:

| Exigência | Onde é atendida |
|---|---|
| Reorganizar em `public_html/` + `config/` com `git mv` | Tarefa 1 |
| `.gitignore` com banco, segredos, uploads e `caminho-config.php` | Tarefa 1 |
| `lib/schema.sql` cópia literal do contrato | Tarefa 2 |
| `db()`, `e()`, `agora()`, `config_ler()`, `config_gravar()` | Tarefa 2 |
| Resolução do `CASTELLO_CONFIG` em quatro níveis, `mkdir` 0700 | Tarefa 2 |
| Dez chaves de `config`, `reenvio_chave` sorteada na instalação | Tarefa 2 |
| Sintaxe compatível com SQLite 3.26 | Tarefa 2, com teste de UPSERT e de `lastInsertId` |
| `modelos()`, `portfolio()`, `avaliacoes()`, `videos()`, `faq()`, `passos()`, `bloco()`, `icone_faq()` | Tarefa 3 |
| Conteúdo real migrado, 4/6/14/11/7/5/21 | Tarefa 4 |
| `insta-06.original.mp4` fora | Tarefa 4, com teste |
| 21 chaves de `blocos`, incluindo as nove `flexpg_` | Tarefa 4 |
| Seis parciais com HTML idêntico | Tarefa 5, comparados com fragmentos do `index.html` |
| `index.php` e `flex.php` | Tarefa 6 |
| `passos.imagem_alt` migrado e impresso | Tarefas 2, 4, 5 e 10 |
| `csrf.php`, sem token no HTML e sem página incacheável | Tarefa 9 |
| `lib/auth.php`, bcrypt, 5 em 15 min, CSRF, sessão de 2h | Tarefa 7 |
| `lib/upload.php`, `finfo_file`, 5 MB e 30 MB, renomeia | Tarefa 8 |
| Painel com CRUD genérico dirigido por `painel/tabelas.php` | Tarefas 10 e 11 |
| Adicionar, editar, desativar, reativar, reordenar arrastando | Tarefas 11 e 12 |
| Filtro por modalidade nos Modelos | Tarefa 10 |
| Seletor visual de ícone no FAQ | Tarefa 11 |
| Telas de Textos e Configurações | Tarefa 13 |
| Backup e Trocar senha | Tarefa 14 |
| `.htaccess` de uploads, painel e HTTPS | Tarefa 15 |
| Deploy no subdomínio de teste | Tarefa 16 |
| `ZipArchive` checado, teste pulado localmente | Tarefa 14, validado na Tarefa 16 |
| Runner `php testes/smoke.php` projetado na primeira tarefa | Tarefa 1 |

**2. Varredura de marcadores.** Nenhum `TBD`, `TODO`, "igual à tarefa N", "adicione tratamento de erro apropriado" ou "escreva os testes para o acima". Todo passo de código traz o código. Os dois únicos lugares que pedem informação de fora são o Passo 1 da Tarefa 16, que pede as credenciais da hospedagem ao Fabiano, e o Passo 12, que entrega os acessos: ambos são ação humana real, não lacuna de plano.

**3. Consistência de tipos e nomes contra o contrato.** Conferido nome a nome: `db`, `e`, `agora`, `config_ler`, `config_gravar`, `modelos`, `portfolio`, `avaliacoes`, `videos`, `faq`, `passos`, `bloco`, `icone_faq`, `upload_receber`, `auth_iniciar`, `auth_logado`, `auth_exigir`, `auth_entrar`, `auth_sair`, `auth_bloqueado`, `auth_registrar_falha`, `csrf_token`, `csrf_validar`. Assinaturas e tipos de retorno iguais aos do contrato. Tabelas e colunas iguais às do schema. As sete chaves de ícone e as dez de `config` conferidas uma a uma contra as seções 2.1 e 2.3.

Funções que o contrato não nomeia e que este plano cria dentro dos arquivos da própria frente: `castello_caminho_config`, `upload_slug`, `migrar`, `migrar_vazia`, `migrar_copiar`, `migrar_usuario`, `migrar_segredos`, `migrar_entrada`, e toda a família `painel_*`. Nenhuma é chamada por outra frente.

Três pontos em que este plano vai além do contrato, todos dentro de pastas da Frente 1 e registrados aqui de propósito:

1. `partials/nav.php`, `partials/rodape.php` e `partials/modal.php`, porque a spec exige nav e rodapé compartilhados entre a home e a página Flex.
2. `AUTH_LOGIN_URL` é relativo (`index.php`) em vez de `/painel/`, porque o servidor de teste hospeda o site em uma subpasta e o caminho absoluto apontaria para fora dela.
3. `migrar.php` pela web se libera sozinho enquanto o banco está vazio, porque o usuário de FTP não alcança a pasta de configuração e não teria como criar o `segredos.php` antes da primeira execução.
