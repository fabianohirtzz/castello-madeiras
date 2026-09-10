# Castello Fase 2 — Frente 1: Infra e Painel — Plano de Implementação

> **Para agentes executores:** SUB-SKILL OBRIGATÓRIA: use `superpowers:subagent-driven-development` (recomendado) ou `superpowers:executing-plans` para implementar este plano tarefa a tarefa. Os passos usam caixinhas (`- [ ]`) para acompanhamento.

**Objetivo:** transformar o protótipo estático da Castello em um site PHP + SQLite servido de `public_html/`, com todo o conteúdo real já migrado para o banco e um painel em `/painel` onde o cliente cadastra, edita, desativa e reordena esse conteúdo sozinho.

**Arquitetura:** PHP 8.3 sem framework e sem Composer, renderizando no servidor. O banco é um único arquivo SQLite em `config/`, fora do `public_html`. As seções dinâmicas da home viram parciais que imprimem exatamente o mesmo HTML de hoje, lendo do banco. O painel não tem seis telas copiadas: tem uma tela de lista e uma tela de formulário genéricas, dirigidas por uma descrição declarativa das tabelas em `painel/tabelas.php`.

**Stack:** PHP 8.3.32, `pdo_sqlite`, `fileinfo`, `gd`, `curl`, `mbstring`, `session`. SQLite em modo WAL. HTML, CSS e JS existentes preservados sem alteração. Testes por um runner próprio em `testes/smoke.php`, sem PHPUnit.

**Spec:** [`docs/superpowers/specs/2026-09-09-castello-fase2-design.md`](../specs/2026-09-09-castello-fase2-design.md)

**Contrato (fonte da verdade):** [`docs/superpowers/plans/2026-09-09-castello-contrato.md`](2026-09-09-castello-contrato.md)

---

## Restrições globais

Todo requisito abaixo vale para todas as tarefas. Valores copiados literalmente do contrato.

- **PHP 8.3**, sem framework, sem Composer, sem dependência externa.
- Todo arquivo PHP começa com a tag de abertura do PHP e **não** termina com tag de fechamento. Arquivos que alternam PHP e HTML (parciais, telas do painel, `index.php`, `flex.php`) fecham a tag para sair do modo PHP, mas o arquivo nunca termina com a tag de fechamento.
- `declare(strict_types=1);` em todo arquivo de `lib/`, em `migrar.php`, nos parciais e nos arquivos do painel.
- Codificação UTF-8 sem BOM. Fuso `America/Sao_Paulo`. Datas gravadas em `Y-m-d H:i:s`.
- Nomes de função, tabela e coluna em português, sem acento, `snake_case`.
- Toda saída de dado do banco no HTML passa por `e()`. Sem exceção. A única saída de HTML cru permitida é `icone_faq()`, que devolve SVG de uma lista fechada escrita no código.
- Copy do site segue o tom da Freela: sem travessões, sem emojis, números concretos.
- `ZipArchive` **não existe** no ambiente local e `phar.readonly` está ligado. Todo código que usa `ZipArchive` checa `class_exists('ZipArchive')` e devolve erro claro, nunca fatal. O teste de backup **pula** localmente, dizendo o motivo.
- Não existe PHPUnit. O ciclo de teste é `php testes/smoke.php`.
- Nenhuma frente escreve arquivo de outra. A Frente 1 escreve: `public_html/lib/db.php`, `public_html/lib/conteudo.php`, `public_html/lib/auth.php`, `public_html/lib/upload.php`, `public_html/lib/schema.sql`, `public_html/partials/`, `public_html/painel/`, `public_html/index.php`, `public_html/flex.php`, `public_html/migrar.php`, os `.htaccess`, e `testes/` (exceto `testes/crm-falso.php`, que é da Frente 3).
- **Fora de escopo desta frente, não escrever:** `enviar.php`, `lib/leads.php`, `lib/crm.php`, `lib/email.php`, `js/formulario.js`, `css/style.css`, `js/main.js`, `front/home.html`, `front/flex.html`.
- Toda mensagem de commit é em português e termina com a linha:
  `Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>`
- Caminhos gravados em `foto`, `arquivo`, `poster` e `imagem` são **sempre relativos ao `public_html`**, começando por `uploads/`. Nunca absolutos, nunca com barra no início.
- Ícones válidos de FAQ, conjunto fechado: `relogio`, `chave`, `planta`, `clima`, `escudo`, `fundacao`, `garantia`.
- Trava de força bruta: 5 tentativas erradas bloqueiam o IP por 15 minutos. Sessão expira com 2 horas de inatividade.
- Upload: imagem `image/jpeg`, `image/png`, `image/webp`, máximo 5 MB. Vídeo `video/mp4`, máximo 30 MB. Tipo conferido com `finfo_file`, nunca pela extensão. Arquivo renomeado para `slug-do-nome-original` mais 6 caracteres aleatórios.

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
    migrar.php                   T4   carrega o conteudo real no banco
    .htaccess                    T15  HTTPS, DirectoryIndex, bloqueios
    lib/
      schema.sql                 T2   schema do contrato, copia literal
      db.php                     T2   db(), e(), agora(), config_ler(), config_gravar()
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
      index.php                  T9   login
      painel.php                 T9   shell, menu e roteamento das telas
      sair.php                   T9   encerra a sessao
      tabelas.php                T9   descricao declarativa + logica do CRUD
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
        painel.css               T9
        painel.js                T12  arrastar para reordenar
      .htaccess                  T15  HTTP Basic opcional, pronto para ativar
    css/ js/ images/ fotos-casas/ videos-instagram/ passos/ video-hero/   T1 (git mv)
  testes/
    smoke.php                    T1   runner, roda cada caso em processo separado
    executar-caso.php            T1   ambiente isolado de um caso
    assertivas.php               T1   teste(), igual(), verdade(), contem(), pular()
    base/                        T5   fragmentos de HTML de referencia
    casos/
      10-db.php                  T2
      20-migracao.php            T4
      30-conteudo.php            T3
      40-partials.php            T5
      50-paginas.php             T6
      60-auth.php                T7
      70-upload.php              T8
      80-painel.php              T9, T11, T12, T13
      90-backup.php              T14
  docs/
  README.md  hdr-top.png  preview-mountain-vista.html   (ficam na raiz, fora do site)
```

**Por que os parciais `nav.php`, `rodape.php` e `modal.php` existem.** A spec, na seção 7.2, exige que a página Flex compartilhe nav e rodapé com a home. O contrato lista só os seis parciais de conteúdo. Como `partials/` é pasta da Frente 1 e nenhuma outra frente chama esses três arquivos, extrair nav, rodapé e modal para lá não quebra o contrato e evita duplicar cem linhas em `flex.php`. O HTML impresso continua idêntico ao de hoje.

---

## Como o runner de testes funciona

`php testes/smoke.php` procura `testes/casos/*.php`, ordena por nome e roda **cada caso em um processo PHP separado**. Cada processo recebe um diretório temporário próprio para `config/` e para `uploads/`, então nenhum caso enxerga o banco do outro e nenhum caso suja o banco real de desenvolvimento. O runner imprime a saída de cada caso, soma passou/falhou/pulou e sai com código diferente de zero se houver qualquer falha.

Dentro de um caso, `banco_com_conteudo()` roda o `migrar()` de verdade no banco temporário, então os casos que precisam do conteúdo real o têm sem depender da ordem de execução.

---
