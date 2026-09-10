# Castello Fase 2 — Frente 4: Costura e produção

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Juntar as três frentes num site único no ar, com o conteúdo vindo do banco, o formulário entregando ao CRM e as pendências de produção resolvidas.

**Architecture:** A frente 2 entregou marcação estática em `front/`. A frente 1 entregou os partials e o painel. A frente 3 entregou o formulário e o conector. Esta frente transplanta a marcação nova para dentro dos partials, gera o `index.php` e o `flex.php` definitivos, funde os testes, e fecha o checklist de lançamento.

**Tech Stack:** PHP escrito para 8.1 (roda em 8.3 local e 8.5 no servidor), SQLite 3.26 no servidor, HTML, CSS, JS vanilla. Playwright para o QA de navegador.

**Spec:** [2026-09-09-castello-fase2-design.md](../specs/2026-09-09-castello-fase2-design.md)
**Contrato:** [2026-09-09-castello-contrato.md](2026-09-09-castello-contrato.md)

**Pré-requisito:** as frentes 1, 2 e 3 concluídas e mescladas no `main`. Esta frente não começa antes disso.

## Global Constraints

- Piso de compatibilidade PHP 8.1, sem framework, sem Composer, sem dependência externa. SQLite do servidor é 3.26: sem `RETURNING`, sem `ALTER TABLE DROP COLUMN`.
- `declare(strict_types=1);` em toda a `lib/`. Sem `?>` no fim de arquivo PHP.
- Toda saída de dado do banco no HTML passa por `e()`.
- Caminhos de mídia sempre relativos ao `public_html`, começando por `uploads/`.
- Copy do site sem travessões, sem emojis, números concretos.
- Prazos: Casa Pronta `90 a 120 dias`, Castelo Flex `45 dias`. Nenhum prazo fixo no topo da home.
- `ZipArchive` não existe no ambiente local. Backup só é validável no servidor.
- Mensagens de commit em português, terminando com `Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>`.

---

## File Structure

| Arquivo | Responsabilidade |
|---|---|
| `testes/smoke.php` | Runner único, absorve o `smoke-f3.php` da frente 3 |
| `public_html/partials/*.php` | Recebem a marcação nova da frente 2 |
| `public_html/index.php` | Home final, montada a partir de `front/home.html` |
| `public_html/flex.php` | Página Flex final, montada a partir de `front/flex.html` |
| `front/` | Apagada ao fim da costura |
| `public_html/.htaccess` | HTTPS forçado, páginas de erro |
| `docs/guia-do-painel.md` | Mini-guia de uso entregue ao cliente |

---

### Task 1: Fundir os testes numa suíte só

**Files:**
- Modify: `testes/smoke.php`
- Delete: `testes/smoke-f3.php`

**Interfaces:**
- Consumes: o runner de asserção da frente 1 e os testes da frente 3.
- Produces: `php testes/smoke.php` roda tudo e devolve código de saída zero só quando tudo passa.

- [ ] **Step 1: Rodar as duas suítes separadas e anotar o resultado**

```bash
php testes/smoke.php; echo "saida f1: $?"
php testes/smoke-f3.php; echo "saida f3: $?"
```

Esperado: as duas passam. Se alguma falha, pare e corrija na frente de origem antes de fundir.

- [ ] **Step 2: Converter o `smoke-f3.php` num arquivo de caso**

Não copie os testes para dentro do `smoke.php` e **não acrescente `require` no topo dele**. O runner da frente 1 despacha cada caso num processo PHP separado, com pasta de configuração e de uploads temporárias próprias, e não carrega `lib/` nenhuma: quem carrega é cada arquivo de caso. Um `require` no topo do runner não teria efeito e daria a impressão de estar funcionando.

A fusão certa é transformar `testes/smoke-f3.php` em `testes/casos/85-crm.php`, no formato que os demais casos usam: o próprio arquivo carrega o que precisa de `lib/`, declara seus testes e devolve o resultado ao runner.

Remova o `testes/apoio-f1.php`, que a frente 3 usava para simular `db()`, `e()`, `agora()`, `config_ler()`, `config_gravar()`, `csrf_token()` e `csrf_validar()` enquanto a frente 1 não existia. Troque os `require` guardados por `function_exists` pelos `require` diretos da `lib` real.

- [ ] **Step 3: Rodar a suíte fundida**

```bash
php testes/smoke.php; echo "saida: $?"
```

Esperado: todos os testes das duas frentes passam, saída zero. O teste de backup aparece como pulado, com o motivo `ZipArchive ausente`.

- [ ] **Step 4: Apagar o arquivo antigo e commitar**

```bash
git mv testes/smoke-f3.php testes/casos/85-crm.php
git rm testes/apoio-f1.php
git add testes/smoke.php
git commit -m "$(printf 'test: funde a suite da frente 3 no runner unico\n\nCo-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>')"
```

---

### Task 2: Transplantar a marcação nova para os partials

**Files:**
- Modify: `public_html/partials/modelos.php`, `portfolio.php`, `avaliacoes.php`, `videos.php`, `faq.php`, `passos.php`
- Read: `front/home.html`, `front/flex.html`

**Interfaces:**
- Consumes: `modelos()`, `portfolio()`, `avaliacoes()`, `videos()`, `faq()`, `passos()`, `bloco()`, `icone_faq()`, `e()` da frente 1.
- Produces: partials que imprimem a marcação da frente 2 em vez da marcação antiga.

- [ ] **Step 1: Escrever o teste que compara partial e marcação de referência**

Adicione a `testes/smoke.php`:

```php
teste('partial de modelos usa a marcacao nova', function () {
    $esperado = file_get_contents(__DIR__ . '/../front/home.html');
    preg_match('#<!-- inicio:modelos -->(.*?)<!-- fim:modelos -->#s', $esperado, $m);
    afirmar(!empty($m[1]), 'front/home.html precisa marcar o trecho com <!-- inicio:modelos --> e <!-- fim:modelos -->');

    $classes = [];
    preg_match_all('#class="([^"]+)"#', $m[1], $c);
    foreach ($c[1] as $lista) {
        foreach (explode(' ', $lista) as $classe) { $classes[trim($classe)] = true; }
    }

    ob_start();
    $modalidade = 'pronta';
    include __DIR__ . '/../public_html/partials/modelos.php';
    $saida = ob_get_clean();

    foreach (array_keys($classes) as $classe) {
        if ($classe === '') { continue; }
        afirmar(str_contains($saida, $classe), "partial nao imprime a classe {$classe}");
    }
});
```

- [ ] **Step 2: Rodar e ver falhar**

```bash
php testes/smoke.php
```

Esperado: FALHA, porque os partials ainda imprimem a marcação antiga, ou porque o `front/home.html` ainda não tem os comentários de marcação.

- [ ] **Step 3: Marcar os trechos no `front/`**

Envolva cada seção dinâmica de `front/home.html` e `front/flex.html` com comentários de fronteira, para que a costura seja mecânica e verificável:

```html
<!-- inicio:modelos --> ... <!-- fim:modelos -->
<!-- inicio:portfolio --> ... <!-- fim:portfolio -->
<!-- inicio:avaliacoes --> ... <!-- fim:avaliacoes -->
<!-- inicio:videos --> ... <!-- fim:videos -->
<!-- inicio:faq --> ... <!-- fim:faq -->
<!-- inicio:passos --> ... <!-- fim:passos -->
```

- [ ] **Step 4: Reescrever cada partial com a marcação nova**

Para cada partial, copie o HTML de dentro dos comentários correspondentes e troque os valores de exemplo pelas chamadas ao banco. Exemplo, `public_html/partials/modelos.php`:

```php
<?php
/** @var string $modalidade */
$lista = modelos($modalidade);
if ($lista === []) { return; }
foreach ($lista as $m): ?>
  <article class="mod<?= $m['destaque'] ? ' mod--destaque' : '' ?>">
    <div class="mod__foto">
      <img src="<?= e($m['foto']) ?>" alt="<?= e($m['foto_alt']) ?>" loading="lazy" />
    </div>
    <div class="mod__corpo">
      <span class="mod__parede"><?= e($m['parede']) ?></span>
      <h3 class="mod__nome"><?= e($m['nome']) ?></h3>
      <p class="mod__area"><?= e($m['area']) ?></p>
      <?php if ($m['preco'] !== null && $m['preco'] !== ''): ?>
        <p class="mod__preco">R$ <?= e($m['preco']) ?></p>
      <?php endif; ?>
      <a class="btn btn--primario" href="#orcamento" data-quote-open
         data-modelo="<?= e($m['nome']) ?>">Pedir orçamento</a>
    </div>
  </article>
<?php endforeach;
```

As classes exatas saem do `front/home.html` da frente 2, não deste exemplo. O `if` do preço existe porque a Castelo Flex pode não ter preço definido.

- [ ] **Step 5: Rodar o teste até passar**

```bash
php testes/smoke.php
```

Esperado: PASSA para os seis partials.

- [ ] **Step 6: Commitar**

```bash
git add public_html/partials/ front/ testes/smoke.php
git commit -m "$(printf 'feat: partials passam a imprimir a marcacao nova do front\n\nCo-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>')"
```

---

### Task 3: Montar o `index.php` e o `flex.php` definitivos

**Files:**
- Modify: `public_html/index.php`, `public_html/flex.php`
- Delete: `front/`

**Interfaces:**
- Consumes: os partials da Task 2, `bloco()`, `csrf_token()`.
- Produces: as duas páginas públicas finais.

- [ ] **Step 1: Escrever o teste de renderização das duas páginas**

```php
teste('home renderiza sem erro e com as secoes esperadas', function () {
    $html = renderizar(__DIR__ . '/../public_html/index.php');
    foreach (['id="modelos"', 'id="portfolio"', 'id="depoimentos"',
              'id="instagram"', 'id="faq"', 'id="flex"', 'id="modalidades"'] as $marca) {
        afirmar(str_contains($html, $marca), "home nao tem {$marca}");
    }
    afirmar(!str_contains($html, 'em até 120 dias'), 'prazo fixo continua no topo da home');
});

teste('pagina flex renderiza sem erro', function () {
    $html = renderizar(__DIR__ . '/../public_html/flex.php');
    afirmar(str_contains($html, 'id="passos-flex"'), 'pagina flex nao tem o passo a passo');
    afirmar(str_contains($html, '45 dias'), 'pagina flex nao mostra o prazo');
});
```

A função `renderizar()` vem do runner da frente 1: faz `ob_start()`, inclui o arquivo e devolve a saída.

- [ ] **Step 2: Rodar e ver falhar**

```bash
php testes/smoke.php
```

Esperado: FALHA, porque `index.php` ainda é a conversão direta do HTML antigo e `flex.php` ainda é o esqueleto.

- [ ] **Step 3: Montar as páginas**

Use `front/home.html` como base. Substitua cada trecho entre comentários de fronteira pelo `include` do partial correspondente:

```php
<section class="section section--sand modelos" id="modelos">
  <div class="container">
    <h2 class="section__title"><?= e(bloco('pronta_titulo')) ?></h2>
    <p class="section__lead"><?= e(bloco('pronta_texto')) ?></p>
    <p class="secao__prazo">Pronta em <?= e(bloco('pronta_prazo')) ?></p>
    <div class="modelos__grade">
      <?php $modalidade = 'pronta'; include __DIR__ . '/partials/modelos.php'; ?>
    </div>
  </div>
</section>
```

O mesmo para `flex.php`, usando `front/flex.html` e os blocos `flex_titulo`, `flex_texto`, `flex_prazo`, `flex_video`, `flex_video_poster`.

O formulário das duas páginas recebe o campo oculto de CSRF:

```php
<input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>" />
```

- [ ] **Step 4: Rodar o teste até passar**

```bash
php testes/smoke.php
```

Esperado: PASSA.

- [ ] **Step 5: Conferir no navegador**

```bash
php -S localhost:8000 -t public_html
```

Abra `http://localhost:8000/` e `http://localhost:8000/flex.php` com Playwright, em 1440px e 390px. Confira: nenhum erro no console, nenhuma rolagem horizontal, as fotos e vídeos carregam dos caminhos de `uploads/`, o menu leva à página Flex e volta.

- [ ] **Step 6: Apagar o `front/` e commitar**

```bash
git rm -r front/
git add public_html/index.php public_html/flex.php testes/smoke.php
git commit -m "$(printf 'feat: home e pagina flex montadas a partir do banco\n\nA pasta front/ cumpriu o papel de permitir o paralelismo e sai.\n\nCo-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>')"
```

---

### Task 4: Ligar o formulário nas duas páginas

**Files:**
- Modify: `public_html/index.php`, `public_html/flex.php`

**Interfaces:**
- Consumes: `js/formulario.js` e `enviar.php` da frente 3.
- Produces: envio real funcionando nas duas páginas.

- [ ] **Step 1: Escrever o teste de ponta a ponta do envio**

```php
teste('envio de lead pela home grava no banco', function () {
    $antes = (int) db()->query('SELECT COUNT(*) FROM leads')->fetchColumn();
    $resposta = postar_formulario('http://localhost:8000/enviar.php', [
        'nome' => 'Teste Costura', 'whatsapp' => '48998244494',
        'busca' => 'casa pronta', 'pagina' => '/', 'ts' => (string) ((time() - 10) * 1000),
        'empresa' => '', 'csrf' => token_de_teste(),
    ]);
    $dados = json_decode($resposta, true);
    afirmar($dados['ok'] === true, 'envio nao devolveu ok');
    $depois = (int) db()->query('SELECT COUNT(*) FROM leads')->fetchColumn();
    afirmar($depois === $antes + 1, 'lead nao foi gravado');
});
```

- [ ] **Step 2: Rodar com o servidor local ligado e ver falhar**

```bash
php -S localhost:8000 -t public_html &
php testes/smoke.php
```

Esperado: FALHA, porque as páginas ainda não carregam o `js/formulario.js` nem apontam o formulário para `enviar.php`.

- [ ] **Step 3: Limpar a lógica antiga de formulário do `js/main.js`**

O `js/main.js` traz, desde o protótipo, uma implementação própria de envio: abertura do modal, máscara de WhatsApp, honeypot `_gotcha`, time-trap e a constante `FORM_ENDPOINT`. A frente 3 escreveu a versão definitiva em `js/formulario.js`. Deixar as duas no ar faz duas rotinas disputarem o mesmo evento de `submit`.

Nenhuma frente podia fazer esta remoção: o arquivo é da frente 2, que não mexe em lógica de formulário, e a frente 3 foi proibida de tocar no arquivo. É tarefa da costura.

Apague de `js/main.js`, no bloco entre as linhas 683 e 860 do arquivo original:
- o `addEventListener('submit', ...)` do `#quoteForm` e tudo que ele chama para enviar;
- a constante `FORM_ENDPOINT`;
- a checagem do honeypot `_gotcha`, que virou letra morta quando o campo passou a se chamar `empresa`;
- o time-trap.

Mantenha em `js/main.js`, porque é comportamento de interface e não de envio:
- a abertura e o fechamento do modal por `data-quote-open`, incluindo Esc e backdrop;
- o laço de foco do modal;
- a máscara de WhatsApp;
- o campo condicional de modelo de interesse.

- [ ] **Step 4: Confirmar que só uma rotina responde ao envio**

```bash
grep -n "addEventListener('submit'\|FORM_ENDPOINT\|_gotcha" public_html/js/main.js public_html/js/formulario.js
```

Esperado: nenhuma ocorrência em `main.js`, e o `submit` aparecendo uma única vez, em `formulario.js`.

- [ ] **Step 5: Carregar o script e apontar o formulário**

Nas duas páginas, antes do `</body>`:

```php
<script src="js/formulario.js" defer></script>
```

E no formulário:

```html
<form id="quoteForm" method="post" action="enviar.php" novalidate>
```

- [ ] **Step 6: Rodar o teste até passar e conferir no navegador**

```bash
php testes/smoke.php
```

Depois, com Playwright: preencher e enviar o formulário na home e na página Flex, conferir a mensagem de sucesso acessível, e conferir no banco que os dois leads chegaram com o campo `pagina` diferente.

- [ ] **Step 7: Conferir a captura de UTM**

Abra `http://localhost:8000/?utm_source=meta&utm_campaign=flex-setembro`, navegue para a página Flex sem parâmetro na URL, envie o formulário de lá, e confira no banco que `utm_source` e `utm_campaign` do lead vieram preenchidos. É o comportamento de `sessionStorage` da seção 6.4 do contrato, e é o que a reunião pediu ao trocar WhatsApp por formulário.

- [ ] **Step 8: Commitar**

```bash
git add public_html/index.php public_html/flex.php testes/smoke.php
git commit -m "$(printf 'feat: formulario ligado nas duas paginas com captura de utm\n\nCo-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>')"
```

---

### Task 5: Conferência do conteúdo migrado

**Files:**
- Read: banco e páginas renderizadas

**Interfaces:**
- Consumes: o `migrar.php` da frente 1.
- Produces: prova de que nenhum conteúdo real se perdeu na migração.

- [ ] **Step 1: Escrever o teste de contagem**

```php
teste('conteudo real migrou por inteiro', function () {
    $esperado = ['modelos' => 4, 'portfolio' => 6, 'avaliacoes' => 14, 'videos' => 11,
                 'faq' => 7, 'passos' => 5];
    foreach ($esperado as $tabela => $quantos) {
        $tem = (int) db()->query("SELECT COUNT(*) FROM {$tabela}")->fetchColumn();
        afirmar($tem >= $quantos, "{$tabela}: esperado ao menos {$quantos}, tem {$tem}");
    }
    $sem_alt = (int) db()->query("SELECT COUNT(*) FROM portfolio WHERE foto_alt IS NULL OR foto_alt = ''")->fetchColumn();
    afirmar($sem_alt === 0, 'ha item de portfolio sem texto alternativo');
});
```

Os números saem da seção 5 da spec: 4 modelos, 6 itens de portfólio, 14 avaliações reais do Google, 11 vídeos, 7 perguntas de FAQ, 5 passos.

- [ ] **Step 2: Rodar**

```bash
php testes/smoke.php
```

Se falhar, o problema está no `migrar.php` da frente 1. Corrija lá, não crie conteúdo à mão.

- [ ] **Step 3: Conferir as 14 avaliações uma a uma**

```bash
php -r "require 'public_html/lib/db.php'; foreach (db()->query('SELECT nome, substr(texto,1,60) t FROM avaliacoes ORDER BY ordem') as \$a) { echo \$a['nome'], ' | ', \$a['t'], PHP_EOL; }"
```

Compare com a seção `#depoimentos` do `index.html` no commit anterior à migração. São avaliações reais de clientes no Google: um nome trocado ou um texto cortado é erro grave, não detalhe.

- [ ] **Step 4: Conferir que os 11 vídeos tocam**

Com Playwright, abrir a home, rolar até a seção do Instagram, confirmar que os 8 primeiros aparecem, que os posters carregam e que um deles toca ao clicar. O limite de 8 é o `config.videos_na_home` acordado na reunião.

- [ ] **Step 5: Commitar**

```bash
git add testes/smoke.php
git commit -m "$(printf 'test: confere que o conteudo real migrou por inteiro\n\nCo-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>')"
```

---

### Task 6: Pendências de produção

**Files:**
- Modify: `public_html/index.php`, `public_html/flex.php`, `public_html/.htaccess`

**Interfaces:**
- Produces: site pronto para ser indexado e medido.

- [ ] **Step 1: Escrever o teste das metatags**

```php
teste('metatags de producao estao corretas', function () {
    foreach (['index.php' => 'https://castellomadeiras.com.br/',
              'flex.php'  => 'https://castellomadeiras.com.br/flex.php'] as $arquivo => $canonical) {
        $html = renderizar(__DIR__ . '/../public_html/' . $arquivo);
        afirmar(!str_contains($html, 'noindex'), "{$arquivo} ainda tem noindex");
        afirmar(str_contains($html, '<link rel="canonical" href="' . $canonical . '"'),
                "{$arquivo} sem canonical correto");
        afirmar(preg_match('#<meta property="og:image" content="https://#', $html) === 1,
                "{$arquivo} sem og:image absoluto");
    }
});
```

- [ ] **Step 2: Rodar e ver falhar**

```bash
php testes/smoke.php
```

Esperado: FALHA. O `noindex` está no site desde o protótipo, listado como pendência no `CLAUDE.md`.

- [ ] **Step 3: Remover o `noindex` e ajustar canonical e og:image**

Tire a metatag de `noindex` das duas páginas. Adicione o canonical de cada uma e troque o `og:image` relativo por URL absoluta. O domínio de produção é `castellomadeiras.com.br`; enquanto o site estiver no subdomínio de teste, use o domínio de teste e troque no deploy final.

- [ ] **Step 4: Forçar HTTPS no `.htaccess`**

```apache
RewriteEngine On
RewriteCond %{HTTPS} !=on
RewriteRule ^(.*)$ https://%{HTTP_HOST}/$1 [R=301,L]
```

- [ ] **Step 5: Rodar até passar e commitar**

```bash
php testes/smoke.php
git add public_html/
git commit -m "$(printf 'feat: tira noindex, ajusta canonical e og:image, forca https\n\nCo-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>')"
```

---

### Task 7: QA de navegador

**Files:**
- Create: `docs/qa-fase2.md`

- [ ] **Step 1: Rodar o `web-qa-reviewer` no site público**

Suba o servidor local e peça uma auditoria completa de `http://localhost:8000/` e `http://localhost:8000/flex.php` contra o launch-checklist da Freela: erros de console, links quebrados, responsividade, formulário, CTAs, WhatsApp, SEO on-page, acessibilidade e performance.

- [ ] **Step 2: Rodar o `web-qa-reviewer` no painel**

Mesma auditoria em `http://localhost:8000/painel/`, logado, cobrindo cada tela. O painel é usado no celular, então a checagem em 390px não é opcional.

- [ ] **Step 3: Conferir o contraste de branco sobre vermelho**

A frente 2 mediu o branco sobre `--red` em `.model__flag` e na faixa `.proof`, componentes que já estão no ar desde o protótipo: **4,33:1**, que reprova AA para texto normal, apesar de a skill `castello-design` afirmar que passa. Os componentes novos da frente 2 já usam `--red-deep`, que mede 6,9:1.

Meça os dois de novo e, se confirmar, troque o fundo dos componentes antigos para `--red-deep` também. Corrija a afirmação na skill `castello-design`, senão o erro se repete no próximo componente.

- [ ] **Step 4: Registrar os achados**

Escreva `docs/qa-fase2.md` com os achados por severidade e o que foi feito com cada um.

- [ ] **Step 4: Corrigir os críticos e os altos**

Nenhum crítico pode sobrar. Cada correção é um commit próprio, com o achado citado na mensagem.

- [ ] **Step 5: Reauditar e commitar**

```bash
git add docs/qa-fase2.md
git commit -m "$(printf 'docs: relatorio de qa da fase 2\n\nCo-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>')"
```

---

### Task 8: Verificação de segurança no servidor

**Files:**
- Read: painel no subdomínio de teste

Os itens abaixo não são testáveis na máquina local. São verificados no servidor, um a um, e o resultado vai para `docs/qa-fase2.md`.

- [ ] **Step 1: Confirmar que o banco e os segredos não são alcançáveis pelo navegador**

Abra `https://<subdominio>/config/castello.db` e `https://<subdominio>/../config/castello.db`. Esperado: 404 ou 403 nos dois casos. Se algum baixar o arquivo, pare tudo: a pasta `config/` está dentro do `public_html` e precisa sair.

- [ ] **Step 2: Confirmar que a pasta de uploads não executa script**

Suba pelo painel um arquivo `.php` renomeado para `.jpg`. Esperado: o upload é recusado por `finfo_file`. Depois, coloque um `.php` de teste direto em `uploads/` por FTP e abra pelo navegador. Esperado: o navegador baixa ou mostra o texto, nunca executa. Apague o arquivo de teste em seguida.

- [ ] **Step 3: Confirmar a trava de força bruta**

Erre a senha 5 vezes. Esperado: bloqueio com mensagem clara e desbloqueio depois de 15 minutos.

- [ ] **Step 4: Confirmar o backup**

Baixe o backup pelo painel. Esperado: um `.zip` que abre e contém o `castello.db` e a pasta `uploads/`. Este é o teste que não roda local, porque o `ZipArchive` não existe lá.

- [ ] **Step 5: Confirmar HTTPS e certificado**

Abra o site por `http://` e confirme o redirecionamento para `https://`. Confirme o certificado válido.

- [ ] **Step 6: Registrar tudo em `docs/qa-fase2.md` e commitar**

---

### Task 9: Analytics e pixel

**Files:**
- Modify: `public_html/index.php`, `public_html/flex.php`

- [ ] **Step 1: Pedir os identificadores**

Google Analytics e pixel do Meta. A reunião registrou que a Castello alinhou uma consultoria de tráfego pago, então a medição é requisito, não enfeite. Sem os identificadores em mãos, esta tarefa fica parada e não bloqueia as outras.

- [ ] **Step 2: Instalar os scripts nas duas páginas**

- [ ] **Step 3: Disparar o evento de lead no envio bem-sucedido**

O evento é disparado quando `enviar.php` responde `ok: true`, dentro do `js/formulario.js`. O nome do evento acompanha o que a consultoria de tráfego usar; na falta de definição, use `lead`.

- [ ] **Step 4: Conferir com Playwright que os dois scripts carregam e o evento dispara**

- [ ] **Step 5: Commitar**

---

### Task 10: Entrega ao cliente

**Files:**
- Create: `docs/guia-do-painel.md`

- [ ] **Step 1: Escrever o mini-guia**

Uma página, linguagem de quem não é técnico, com prints: como entrar, como cadastrar um modelo, como subir um vídeo e mudar a ordem, como publicar uma avaliação, como desativar um item sem apagar, como editar os textos, e como baixar o backup. Explique também por que o limite de vídeos existe.

- [ ] **Step 2: Criar o usuário do cliente e definir a senha**

Pelo painel, na tela de trocar senha, com senha forte gerada na hora. A senha vai para o cliente por canal separado, nunca junto com o link no mesmo lugar.

- [ ] **Step 3: Configurar o CRM se as credenciais já tiverem chegado**

Na tela de Configurações: endpoint, método, cabeçalhos e mapa de campos. Ligue `crm_ativo`, envie um lead de teste e confirme que ele apareceu no CRM. Se as credenciais ainda não chegaram, deixe `crm_ativo` desligado: o lead continua sendo gravado e o e-mail de aviso continua saindo.

- [ ] **Step 4: Atualizar o `CLAUDE.md`**

Estado do projeto, o que ficou pendente do cliente, e como rodar o projeto local. Substitua a seção de estado atual, que ainda descreve o protótipo em landing única.

- [ ] **Step 5: Commitar e subir**

```bash
git add docs/guia-do-painel.md CLAUDE.md
git commit -m "$(printf 'docs: guia do painel e estado do projeto atualizado\n\nCo-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>')"
git push origin main
```

---

## Pendências que continuam dependendo do cliente

Nenhuma bloqueia a entrega. Todas entram pelo painel depois.

- Material, fotos, vídeos e preços da Castelo Flex. Até chegarem, a página Flex fica com estrutura pronta e textos provisórios.
- Portfólio atualizado, FAQ atualizada e novas avaliações.
- Credenciais do CRM.
- Qual hospedagem a Castello tem, para a migração final para fora do subdomínio de teste.
- Confirmação dos nomes e preços dos modelos Casa Pronta, pendente desde o protótipo.
