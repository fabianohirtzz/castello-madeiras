# Castello Fase 2 — Frente 2 (Front) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Entregar `front/home.html` e `front/flex.html`, arquivos estáticos com dados de exemplo escritos no HTML, com a home reorganizada da seção 7.1 da spec e a página Castelo Flex da seção 7.2, mais o CSS novo em `css/style.css`.

**Architecture:** Nenhum PHP, nenhum banco, nenhum JS novo. Os dois arquivos são HTML estático servido por `python -m http.server` e carregam o `css/style.css` e o `js/main.js` que já existem, por caminho relativo `../`. Todo comportamento novo (FAQ da Flex, modal de orçamento, reveal, drawer, nav) reaproveita os `id` e as classes que o `js/main.js` atual já procura, então nenhuma linha de JS precisa mudar. Todo CSS novo é acrescentado numa região demarcada no fim do `css/style.css`, com nomes de classe novos, para não sobrescrever nada que já passou no QA.

**Tech Stack:** HTML5 + CSS3 (custom properties, grid, clip-path) + o `js/main.js` existente. Verificação por Playwright MCP (`mcp__plugin_playwright_playwright__*`).

**Spec:** [docs/superpowers/specs/2026-09-09-castello-fase2-design.md](../specs/2026-09-09-castello-fase2-design.md) — seção 7 é a desta frente.
**Contrato:** [docs/superpowers/plans/2026-09-09-castello-contrato.md](2026-09-09-castello-contrato.md) — fonte da verdade.
**Design:** skill `castello-design` (`SKILL.md`, `references/DESIGN.md`, `references/COMPONENTS.md`, `references/ANIMATIONS.md`).

---

## Global Constraints

Valem para toda tarefa deste plano. Não repetidas em cada uma.

- **Arquivos que esta frente escreve:** `front/home.html`, `front/flex.html`, `css/style.css`. Nada mais. (Contrato §7.)
- **Proibido tocar:** qualquer `.php`, `index.html`, `js/main.js`, `js/formulario.js`, banco, painel. A frente 2 não adiciona uma linha de JavaScript em lugar nenhum.
- **Caminhos de asset:** dentro de `front/` todo asset é `../` relativo (`../css/style.css`, `../images/...`, `../fotos-casas/...`, `../videos-instagram/web/...`, `../passos/...`, `../js/main.js`). Na costura o `../` cai fora, porque os arquivos definitivos nascem na raiz do `public_html`.
- **Tokens:** só os do `:root` do `css/style.css` (que já são os da skill). Nenhuma cor literal nova exceto `#fff` e as rgba de branco já usadas no arquivo.
- **Contraste medido, não presumido.** Texto branco pequeno (abaixo de 18,66px em negrito) sobre vermelho usa `--red-deep` (`#B3141B`, 6,9:1 contra branco), nunca `--red` (`#ED2128`, 4,33:1, reprova AA). `--red` fica para superfícies grandes, ícones e o que já existe no site.
- **Copy padrão Freela:** sem travessões, sem emojis, números concretos, frases curtas e quentes.
- **Números oficiais:** Casa Pronta `90 a 120 dias`. Castelo Flex `45 dias`. Prova social `5,0`, `56 avaliações`, `12 anos`, `+18.000 m²`.
- **`noindex` fica.** Os dois arquivos mantêm `<meta name="robots" content="noindex, nofollow" />`. Sair do noindex é tarefa da costura, não desta frente.
- **Todo CSS novo** entra na região `FASE 2` no fim do `css/style.css`, aberta na Tarefa 1. Nada de editar regra antiga sem que o passo mande explicitamente, com o texto exato.
- **Servidor local para toda verificação:** `python -m http.server 8000` rodando na raiz do repositório. URLs: `http://localhost:8000/front/home.html` e `http://localhost:8000/front/flex.html`.

### Ganchos do `js/main.js` que o HTML é obrigado a fornecer

O `js/main.js` roda sem guarda em três pontos. Se qualquer um faltar, a página quebra com `TypeError` no console:

| Gancho | Onde | Por quê |
|---|---|---|
| `#nav` | header | `onScroll` chama `nav.classList` direto |
| `#burger`, `#drawer`, `#drawerBackdrop` | nav mobile | `toggleDrawer` e os `addEventListener` são chamados sem checagem |
| `#topo` | primeira seção da página | `wo.observe(hero)` do botão flutuante |

Tudo o mais é guardado por `if`: `#heroTrack`, `#heroVideo`, `#processSteps`, `#reviewsTrack`, `#whyCarousel`, `#accordion`, `#faqTabs`, `#instaRail`, `#reelbox`, `#quoteModal`, `[data-count]`. A página Flex simplesmente não tem esses blocos e o JS ignora.

Dentro de `if (qModal)` o JS acessa sem guarda: `#quoteForm`, `#quoteDone`, `#quoteDoneMsg`, `#quoteWppLink`, `#quoteStatus`, `#quoteSubmit`, `#q-busca`, `#qGroupModelo`, `#q-modelo`, `#q-whatsapp`, `#q-nome`, `#quoteBack`. Ou o modal inteiro está na página, ou nenhum pedaço dele está.

### Textos provisórios (trocáveis pelo painel, sem material do cliente)

O material definitivo da Castelo Flex ainda não chegou. Tudo abaixo é escrito a partir do que a empresa já publica no Instagram e do que está no `CLAUDE.md`, e entra no banco pelas chaves de `blocos` e pelas tabelas `modelos`/`faq`/`passos` com contexto `flex`.

| O que | Onde | Chave de destino no banco |
|---|---|---|
| Título e texto do bloco Modalidades | home | `modalidades_titulo`, `modalidades_texto` |
| Título, texto e prazo da seção Flex da home | home | `flex_titulo`, `flex_texto`, `flex_prazo` |
| Vídeo explicativo da Flex (usa provisoriamente `insta-04.mp4`) | home e flex | `flex_video`, `flex_video_poster` |
| Hero, resumo e diferenciais da página Flex | flex | `blocos` a criar na costura |
| 5 passos da Flex e a lista do que fica por conta do cliente | flex | `passos` contexto `flex` |
| 3 modelos Flex, áreas e "Sob consulta" no lugar do preço | flex | `modelos` modalidade `flex` |
| 5 perguntas do FAQ da Flex | flex | `faq` contexto `flex` |

Nada disso vai ao ar como verdade fechada: o texto da seção de catálogo diz, na própria página, que a tabela está em fechamento.

---

## Estrutura de arquivos

```
front/
  home.html    home reorganizada (spec 7.1) — dados de exemplo no HTML
  flex.html    página Castelo Flex (spec 7.2) — dados de exemplo no HTML
css/
  style.css    região "FASE 2" acrescentada no fim + 2 edições cirúrgicas em regras antigas
```

`index.html` na raiz não é tocado. Ele continua sendo a origem que a frente 1 converte em `index.php`.

### Ordem das seções e cadeia de facetas

A faceta (`.facet`) recorta um telhado da cor da seção seguinte e sobe por cima da anterior com `margin-top:-var(--facet-h)`. Toda seção seguida de faceta precisa de `padding-bottom: calc(var(--sec-pad) + var(--facet-h))`, entregue pela classe utilitária `.section--facetada` criada na Tarefa 1.

**home.html**

| # | Seção | Fundo | Faceta depois |
|---|---|---|---|
| 1 | `.hero#topo` | ink | `.facet--red` |
| 2 | `.proof#prova` | red | `.facet--bone` |
| 3 | `.modalidades#modalidades` **novo** | bone | `.facet--sand` |
| 4 | `.modelos#casa-pronta` **renomeado** | sand | `.facet--ink` |
| 5 | `.flexhome#castelo-flex` **novo** | ink | `.facet--bone` |
| 6 | `.vantagens#vantagens` | bone | nenhuma |
| 7 | `.portfolio#portfolio` | bone | `.facet--ink` |
| 8 | `.process#como-funciona` | ink | nenhuma |
| 9 | `.reviews#depoimentos` | sand | `.facet--ink` |
| 10 | `.insta#instagram` | ink | `.facet--sand` |
| 11 | `.faq#faq` | sand | a do `.cta-band::before` |
| 12 | `.cta-band` | red | nenhuma |
| 13 | `.footer#contato` | ink | nenhuma |

Vantagens e Portfólio passam a ser duas seções `bone` seguidas: a faceta que hoje existe entre elas sai, e o `padding-bottom` extra de `.vantagens` sai junto (Tarefa 2).

**flex.html**

| # | Seção | Fundo | Faceta depois |
|---|---|---|---|
| 1 | `.pagehero#topo` | ink | `.facet--bone` |
| 2 | `#o-que-e` | bone | `.facet--sand` |
| 3 | `#passos-flex` | sand | `.facet--bone` |
| 4 | `#modelos-flex` | bone | `.facet--ink` |
| 5 | `#diferenciais` | ink | `.facet--sand` |
| 6 | `.faq#faq` | sand | a do `.cta-band::before` |
| 7 | `.cta-band#orcamento` | red | nenhuma |
| 8 | `.footer#contato` | ink | nenhuma |

---

## Decisões tomadas neste plano

1. **O prazo sai do `<title>` também.** A spec manda tirar o prazo do topo (`index.html:102`). O `<title>` atual é "Sua casa pronta em até 120 dias", que é o topo do topo e passa a contradizer os 45 dias da Flex. O `<title>` da home vira "Casa Pronta e Castelo Flex em Tubarão SC" e a `<meta description>` cita os dois prazos, cada um com o nome da sua modalidade.
2. **`#modelos` vira `#casa-pronta`.** O nome da âncora acompanha o nome novo da seção. Os quatro lugares que apontavam para `#modelos` (nav, drawer, CTA do hero, e agora o card de Modalidades) são atualizados no mesmo passo.
3. **A Flex entra na nav como link de página, não como âncora.** `href="flex.html"`. O scroll-spy do `js/main.js` só observa `a[href^="#"]`, então o link novo é ignorado por ele de graça.
4. **O "Formulário" da página Flex (spec 7.2, item 8) é o mesmo modal `#quoteModal` do site inteiro**, aberto pela faixa de CTA `#orcamento` e por todos os `[data-quote-open]` da página. Dois formulários no DOM significariam `id` duplicado, foco duplicado e dois contratos de envio. Um formulário só, um contrato só. Está registrado como divergência no fim deste documento.
5. **O honeypot passa a se chamar `empresa`**, como manda o contrato §6.1, no lugar do `_gotcha` de hoje. Consequência conhecida e registrada: a checagem client-side do `js/main.js` lê `_gotcha` e vira letra morta nos arquivos de `front/`. A defesa real é server-side (`enviar.php`, frente 3). Está registrado como pendência de costura no fim deste documento.

---

## Tarefas

Dez tarefas. Cada uma termina com uma página que abre no navegador e pode ser reprovada sozinha.

- **Tarefa 1** — Andaime `front/home.html`: cópia do site atual com caminhos `../`, hero sem prazo, nav e drawer com Castelo Flex, região CSS da Fase 2 aberta.
- **Tarefa 2** — Bloco Modalidades na home e reencadeamento das facetas.
- **Tarefa 3** — Seção Casa Pronta: renome, âncora nova e selo de 90 a 120 dias.
- **Tarefa 4** — Seção Castelo Flex na home: resumo, vídeo explicativo, 45 dias e chamada para a página Flex.
- **Tarefa 5** — Andaime `front/flex.html`: head, nav, drawer, hero próprio, rodapé, modal e botão flutuante.
- **Tarefa 6** — Flex: o que é a Castelo Flex, com o vídeo explicativo.
- **Tarefa 7** — Flex: passo a passo com a fronteira de entrega explícita.
- **Tarefa 8** — Flex: catálogo das casas Flex, selo de 45 dias e diferenciais.
- **Tarefa 9** — Flex: FAQ da Flex e faixa de orçamento.
- **Tarefa 10** — Formulário alinhado ao contrato §6.1 nas duas páginas e varredura final de responsivo, contraste e teclado.

---

### Tarefa 1: Andaime da home em `front/`

Copia o site atual para `front/home.html`, corrige os caminhos, tira o prazo do hero, coloca a Castelo Flex na nav e no drawer, e abre a região de CSS da Fase 2.

**Files:**
- Create: `front/home.html`
- Modify: `css/style.css` (acrescenta a região `FASE 2` no fim)
- Test: verificação por navegador (não há framework de teste para HTML e CSS)

**Interfaces:**
- Consome: `css/style.css`, `js/main.js`, `images/`, `fotos-casas/`, `passos/`, `videos-instagram/web/` — todos por `../`
- Produz: `front/home.html` com os ganchos obrigatórios `#nav`, `#burger`, `#drawer`, `#drawerBackdrop`, `#topo`; a âncora `#casa-pronta`; a classe utilitária `.section--facetada`; o componente `.nav__tag`; a região `FASE 2` no fim do `css/style.css`, onde todas as tarefas seguintes acrescentam CSS

- [x] **Step 1: Subir o servidor local**

```bash
cd "E:/Clientes/Castello Madeiras/prototipo-site-castello"
python -m http.server 8000
```

Deixe rodando em segundo plano durante todo o plano. Confirme com `http://localhost:8000/index.html` abrindo o site atual.

- [x] **Step 2: Criar `front/` e copiar o `index.html`**

```bash
cd "E:/Clientes/Castello Madeiras/prototipo-site-castello"
mkdir -p front
cp index.html front/home.html
```

- [x] **Step 3: Corrigir todos os caminhos de asset para `../`**

Em `front/home.html`, todo `src` e `href` de asset local ganha `../` na frente:

```bash
cd "E:/Clientes/Castello Madeiras/prototipo-site-castello/front"
sed -i \
  -e 's|href="css/|href="../css/|g' \
  -e 's|src="js/|src="../js/|g' \
  -e 's|src="images/|src="../images/|g' \
  -e 's|href="images/|href="../images/|g' \
  -e 's|src="fotos-casas/|src="../fotos-casas/|g' \
  -e 's|content="fotos-casas/|content="../fotos-casas/|g' \
  -e 's|poster="fotos-casas/|poster="../fotos-casas/|g' \
  -e 's|src="passos/|src="../passos/|g' \
  -e 's|src="videos-instagram/|src="../videos-instagram/|g' \
  -e 's|poster="videos-instagram/|poster="../videos-instagram/|g' \
  -e 's|href="video-hero/|href="../video-hero/|g' \
  -e 's|src="video-hero/|src="../video-hero/|g' \
  home.html
```

Confira que sobrou zero caminho sem `../`:

```bash
cd "E:/Clientes/Castello Madeiras/prototipo-site-castello"
grep -n 'src="\(css\|js\|images\|fotos-casas\|passos\|videos-instagram\|video-hero\)/\|href="\(css\|images\|video-hero\)/' front/home.html
```

Esperado: nenhuma linha.

- [x] **Step 4: Trocar o `<title>`, as metas e o cache-buster do CSS**

Substitua o bloco de `<title>` e as metas por este, que tira o prazo do topo e coloca cada prazo ao lado do nome da sua modalidade:

```html
  <title>Castello Casas de Madeira | Casa Pronta e Castelo Flex em Tubarão SC</title>
  <meta name="description" content="Casas de madeira em Tubarão e região. Casa Pronta chave na mão em 90 a 120 dias e Castelo Flex semipronta em 45 dias. 5,0 estrelas no Google, 56 avaliações." />

  <!-- Open Graph -->
  <meta property="og:type" content="website" />
  <meta property="og:title" content="Castello Casas de Madeira | Casa Pronta e Castelo Flex" />
  <meta property="og:description" content="Casa Pronta chave na mão em 90 a 120 dias e Castelo Flex semipronta em 45 dias. 5,0 estrelas no Google." />
  <meta property="og:image" content="../fotos-casas/casa3.png" />
  <meta property="og:locale" content="pt_BR" />
```

E o cache-buster, porque a folha vai mudar:

```html
  <link rel="stylesheet" href="../css/style.css?v=13" />
```

- [x] **Step 5: Trocar a lista de links da nav**

Substitua o `<nav class="nav__links" ...>` inteiro por este. São sete links: "Modelos" vira "Casa Pronta" apontando para a âncora nova, e a Castelo Flex entra como link de página com etiqueta de lançamento.

```html
      <nav class="nav__links" aria-label="Navegação principal">
        <a href="#vantagens"><svg class="nav__ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M13 2 4 14h6l-1 8 9-12h-6l1-8Z"/></svg>Vantagens</a>
        <a href="#casa-pronta"><svg class="nav__ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 21h18M5 21V10l7-5 7 5v11M9 21v-6h6v6"/></svg>Casa Pronta</a>
        <a href="flex.html" class="nav__link--flex"><svg class="nav__ico" viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 9 5-9 5-9-5 9-5ZM3 12l9 5 9-5M3 16l9 5 9-5"/></svg>Castelo Flex<span class="nav__tag">Novo</span></a>
        <a href="#portfolio"><svg class="nav__ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 5h18v14H3zM3 15l5-5 4 4 3-3 6 6"/><circle cx="8.5" cy="9" r="1.4"/></svg>Portfólio</a>
        <a href="#instagram"><svg class="nav__ico" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.3" cy="6.7" r="1.1" fill="currentColor" stroke="none"/></svg>Instagram</a>
        <a href="#como-funciona"><svg class="nav__ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 4v6a4 4 0 0 0 4 4h4a4 4 0 0 1 4 4v2M6 4H4m2 0h2M18 20h-2m2 0h2"/></svg>Como funciona</a>
        <a href="#contato"><svg class="nav__ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h4l2 5-3 2a12 12 0 0 0 5 5l2-3 5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2Z"/></svg>Contato</a>
      </nav>
```

- [x] **Step 6: Trocar o drawer mobile**

Substitua o `<div class="drawer" id="drawer" aria-hidden="true">` inteiro por este:

```html
  <div class="drawer" id="drawer" aria-hidden="true">
    <a href="#vantagens">Vantagens</a>
    <a href="#casa-pronta">Casa Pronta</a>
    <a href="flex.html">Castelo Flex</a>
    <a href="#portfolio">Portfólio</a>
    <a href="#instagram">Instagram</a>
    <a href="#como-funciona">Como funciona</a>
    <a href="#contato">Contato</a>
    <button type="button" class="btn btn--primary" data-quote-open>Pedir orçamento</button>
  </div>
```

- [x] **Step 7: Tirar o prazo do hero**

Substitua o bloco `<!-- Beat 2 — ~3s ao fim -->` inteiro por este. A linha "pronta em até 120 dias" morre; o segundo beat fecha a frase pelo benefício, não pelo prazo, e o CTA secundário aponta para o bloco de Modalidades:

```html
        <!-- Beat 2 — ~3s ao fim -->
        <div class="hero__beat hero__beat--two" id="heroBeatTwo">
          <p class="hero__headline-sub">pronta pra morar, <span class="hl">chave na mão</span></p>
          <div class="hero__actions">
            <button type="button" class="btn btn--primary btn--lg" data-quote-open>
              <svg viewBox="0 0 24 24" class="ico-quote" aria-hidden="true"><path d="M4 4h16a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H8.6L4 20.5V5a1 1 0 0 1 1-1Zm3 5h10v1.7H7V9Zm0 3.6h6.6v1.7H7v-1.7Z"/></svg>
              Pedir orçamento
            </button>
            <a href="#modalidades" class="btn btn--ghost-light btn--lg">Ver as duas modalidades</a>
          </div>
        </div>
```

- [x] **Step 8: Renomear a âncora da seção de modelos**

Uma troca só, para a nav e o drawer não apontarem para o vazio. O conteúdo da seção muda na Tarefa 3.

```bash
cd "E:/Clientes/Castello Madeiras/prototipo-site-castello/front"
sed -i 's|<section class="section section--sand modelos" id="modelos">|<section class="section section--sand modelos" id="casa-pronta">|' home.html
grep -n 'id="casa-pronta"\|href="#modelos"' home.html
```

Esperado: uma linha com `id="casa-pronta"` e nenhuma com `href="#modelos"`.

- [x] **Step 9: Abrir a região FASE 2 no `css/style.css`**

Acrescente no **fim** do `css/style.css`, depois do bloco `@media(prefers-reduced-motion:reduce)`:

```css

/* ============================================================
   FASE 2 — Modalidades, Casa Pronta, Castelo Flex
   Tudo o que a Fase 2 acrescenta vive daqui pra baixo, com nomes
   de classe novos. Nenhuma regra acima é sobrescrita sem que o
   plano mande, com o texto exato da regra antiga.
   Texto branco pequeno sobre vermelho usa --red-deep (6,9:1).
   --red (4,33:1) fica para superfície grande, ícone e o que já existia.
   ============================================================ */

/* seção seguida de faceta precisa do respiro da altura dela */
.section--facetada{padding-bottom:calc(var(--sec-pad) + var(--facet-h))}

/* link da Flex na nav: um fio mais forte, por ser o único que troca de página.
   Seletor com a e a classe juntos para vencer `.nav__links a` (0,1,1) sem
   atropelar o :hover, que continua com especificidade maior. */
.nav__links a.nav__link--flex{font-weight:700}

/* etiqueta de lançamento no link da Flex, na nav */
.nav__tag{
  display:inline-block;margin-left:7px;padding:2px 7px;border-radius:999px;
  background:var(--red-deep);color:#fff;
  font-family:var(--font-display);font-weight:600;font-size:.62rem;
  letter-spacing:.08em;text-transform:uppercase;line-height:1.6;
}
.nav.is-scrolled .nav__tag{background:var(--red-deep);color:#fff}

/* sete links com ícone não cabem abaixo de 1180px: o ícone sai e o padding aperta */
@media(max-width:1180px){
  .nav__links a{padding:9px 10px;font-size:.86rem;gap:0}
  .nav__ico{display:none}
  .nav__cta{padding:12px 18px;font-size:.88rem}
}
/* abaixo de 1000px sobra pouco: Portfólio e Instagram ficam só no drawer */
@media(max-width:1000px){
  .nav__links a[href="#portfolio"],.nav__links a[href="#instagram"]{display:none}
}
```

- [x] **Step 10: Verificar no navegador**

1. `browser_navigate` para `http://localhost:8000/front/home.html`.
2. `browser_console_messages`. Esperado: nenhuma mensagem de erro. Um `TypeError` aqui quer dizer que um gancho obrigatório do `js/main.js` sumiu na cópia.
3. `browser_resize` 1440x900 e `browser_take_screenshot` em `f2-t1-1440.png`. Olhe: o logo carrega (o `../images/` funcionou), o hero mostra "A casa dos seus sonhos" e, ao rolar, "pronta pra morar, chave na mão" sem nenhuma menção a 120 dias, e a nav mostra sete links com "Castelo Flex" e a etiqueta "Novo" vermelha.
4. `browser_resize` 1180x900 e depois 1024x800, com `browser_take_screenshot` em `f2-t1-1180.png` e `f2-t1-1024.png`. Olhe: em 1180 os links perdem o ícone e continuam numa linha só, sem estourar a pílula da nav; em 1024 Portfólio e Instagram somem da nav e sobram cinco links.
5. `browser_resize` 390x844 e `browser_take_screenshot` em `f2-t1-390.png`. Olhe: só logo e hambúrguer na nav.
6. `browser_evaluate` com `() => ({ doc: document.documentElement.scrollWidth, win: window.innerWidth })` em 390px. Esperado: `doc` menor ou igual a `win`. Diferença positiva é rolagem horizontal e reprova a tarefa.
7. `browser_click` no `#burger` em 390px e `browser_snapshot`. Esperado: os sete links do drawer, com "Castelo Flex" entre "Casa Pronta" e "Portfólio", e o botão "Pedir orçamento".
8. `browser_press_key` `Escape`. Esperado: drawer fecha.
9. Em 1440px, `browser_press_key` `Tab` quatro vezes a partir do topo, com `browser_snapshot` a cada duas. Esperado: o foco passa pelo logo e pelos links na ordem visual, com anel vermelho visível (`outline:3px solid var(--red-bright)`), e o terceiro link é "Castelo Flex".
10. `browser_evaluate` com `() => getComputedStyle(document.querySelector('.nav__tag')).backgroundColor`. Esperado: `rgb(179, 20, 27)`.
11. `browser_click` no link "Castelo Flex" da nav. Esperado: 404 do servidor, porque `front/flex.html` só nasce na Tarefa 5. É o resultado correto nesta altura. Volte com `browser_navigate_back`.

- [x] **Step 11: Commit**

```bash
cd "E:/Clientes/Castello Madeiras/prototipo-site-castello"
git add front/home.html css/style.css
git commit -F- <<'MSG'
feat(front): andaime da home em front/ com a Castelo Flex na navegacao

Copia o index.html para front/home.html com os caminhos de asset em ../,
tira "pronta em ate 120 dias" do hero, renomeia a ancora #modelos para
#casa-pronta e poe Castelo Flex na nav e no drawer. Abre a regiao FASE 2
no css/style.css com .section--facetada, .nav__tag e a nav compacta.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
MSG
```

---

### Tarefa 2: Bloco de Modalidades na home

Entrega o bloco novo que separa Casa Pronta de Castelo Flex logo depois da prova social, e reencadeia as facetas para a ordem nova da spec 7.1.

**Files:**
- Modify: `front/home.html` (insere a seção depois da faceta que sai da prova social; move `.vantagens` para depois da Flex)
- Modify: `css/style.css` (região FASE 2)

**Interfaces:**
- Consome: `.section`, `.section--facetada`, `.container`, `.eyebrow`, `.section__title`, `.section__lead`, `.reveal`, `.btn--primary`, `.facet`
- Produz: `#modalidades` (alvo do CTA secundário do hero), `.modalidades`, `.modalidades__head`, `.modalidades__grid`, `.modalidade`, `.modalidade--flex`, `.modalidade__tag`, `.modalidade__name`, `.modalidade__prazo`, `.modalidade__text`, `.checklist` e `.checklist--light` (usadas de novo nas Tarefas 6 e 7)

- [x] **Step 1: Mover a seção de Vantagens para depois de onde a Flex vai entrar**

A ordem da spec 7.1 é Modalidades, Casa Pronta, Castelo Flex, Vantagens. Hoje Vantagens vem antes de Modelos. Recorte de `front/home.html` o bloco inteiro que começa em `<!-- ============ POR QUE MADEIRA (editorial assimétrico) ============ -->` e termina no `</section>` que fecha `.vantagens`, junto com a faceta que vem logo depois dele:

```html
  <!-- faceta: transição para os modelos -->
  <div class="facet facet--sand" aria-hidden="true"></div>
```

Cole o bloco de Vantagens (sem essa faceta, que é descartada) imediatamente **antes** de `<!-- ============ PORTFÓLIO (galeria editorial assimétrica) ============ -->`. Vantagens e Portfólio ficam bone contra bone, sem faceta entre eles.

Na tag de abertura da seção movida, tire nada e mude nada: continua

```html
  <section class="section vantagens" id="vantagens">
```

- [x] **Step 2: Tirar o respiro de faceta que Vantagens não precisa mais**

Em `css/style.css`, apague estas duas linhas (a regra e o comentário acima dela), porque não há mais faceta depois de Vantagens e o espaço vira um buraco:

```css
/* vantagens precede a faceta de telhado: folga extra embaixo p/ não ser coberta */
.vantagens{padding-bottom:calc(var(--sec-pad) + var(--facet-h))}
```

- [x] **Step 3: Inserir a seção de Modalidades**

Em `front/home.html`, logo depois de

```html
  <!-- faceta: telhado recorta a saída da faixa vermelha -->
  <div class="facet facet--bone" aria-hidden="true"></div>
```

cole:

```html
  <!-- ============ MODALIDADES (Casa Pronta x Castelo Flex) ============ -->
  <!-- Copy provisória da Flex: sai do material do Instagram até o cliente enviar o definitivo. -->
  <section class="section modalidades section--facetada" id="modalidades">
    <div class="container">
      <div class="modalidades__head">
        <span class="eyebrow reveal">Duas formas de construir</span>
        <h2 class="section__title reveal">Escolha como a sua casa<br>sai do papel.</h2>
        <p class="section__lead reveal">A Castello entrega a casa completa, pronta pra morar, e agora entrega também a casa semipronta, para quem quer a estrutura no terreno e o acabamento no próprio ritmo.</p>
      </div>

      <div class="modalidades__grid">
        <article class="modalidade reveal">
          <span class="modalidade__tag">Chave na mão</span>
          <h3 class="modalidade__name">Casa Pronta</h3>
          <p class="modalidade__prazo">Pronta em <strong>90 a 120 dias</strong></p>
          <p class="modalidade__text">A Castello faz tudo: projeto, fundação, estrutura, elétrica, hidráulica, revestimento e acabamento. Você recebe a chave e entra pra morar.</p>
          <ul class="checklist">
            <li>Projeto exclusivo, planta do seu jeito</li>
            <li>Obra completa do primeiro ao último dia</li>
            <li>Quatro modelos com preço de referência</li>
          </ul>
          <a href="#casa-pronta" class="btn btn--primary">Ver modelos e preços</a>
        </article>

        <article class="modalidade modalidade--flex reveal">
          <span class="modalidade__tag">Lançamento</span>
          <h3 class="modalidade__name">Castelo Flex</h3>
          <p class="modalidade__prazo">No seu terreno em <strong>45 dias</strong></p>
          <p class="modalidade__text">A casa semipronta da Castello. Entregamos a estrutura de madeira montada, coberta e fechada no seu terreno, e você conduz o acabamento no seu tempo.</p>
          <ul class="checklist checklist--light">
            <li>Estrutura montada, coberta e fechada</li>
            <li>Entrega em 45 dias</li>
            <li>Acabamento no seu ritmo e no seu orçamento</li>
          </ul>
          <a href="flex.html" class="btn btn--primary">Conhecer a Castelo Flex</a>
        </article>
      </div>
    </div>
  </section>

  <!-- faceta: transição para a Casa Pronta -->
  <div class="facet facet--sand" aria-hidden="true"></div>
```

- [x] **Step 4: Acrescentar o CSS na região FASE 2**

No fim do `css/style.css`, dentro da região FASE 2:

```css
/* ---------- Lista com marcador de telhado (reusada na Flex) ---------- */
.checklist{display:grid;gap:12px;margin-top:22px}
.checklist li{position:relative;padding-left:28px;font-size:1.02rem;line-height:1.55;color:var(--ink)}
.checklist li::before{
  content:"";position:absolute;left:0;top:8px;width:0;height:0;
  border-left:7px solid transparent;border-right:7px solid transparent;
  border-bottom:10px solid var(--red);
}
.checklist--light li{color:rgba(255,255,255,.82)}
.checklist--light li::before{border-bottom-color:var(--red-bright)}

/* ---------- Modalidades: o garfo Casa Pronta x Castelo Flex ---------- */
.modalidades__head{max-width:760px;margin-bottom:clamp(36px,5vh,56px)}
.modalidades__grid{
  display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));
  gap:clamp(18px,2.6vw,32px);
}
.modalidade{
  position:relative;display:flex;flex-direction:column;
  background:#fff;border:1px solid var(--line);border-radius:var(--r-lg);
  box-shadow:var(--shadow-soft);padding:clamp(26px,3.2vw,40px);
  transition:transform .3s var(--ease),box-shadow .3s var(--ease);
}
.modalidade:hover{transform:translateY(-4px);box-shadow:var(--shadow-card)}
.modalidade--flex{background:var(--ink);color:#fff;border-color:transparent}
.modalidade__tag{
  align-self:flex-start;padding:6px 13px;border-radius:999px;
  background:var(--red-tint);color:var(--red-deep);
  font-family:var(--font-display);font-weight:600;font-size:.72rem;
  letter-spacing:.1em;text-transform:uppercase;
}
.modalidade--flex .modalidade__tag{background:var(--red-deep);color:#fff}
.modalidade__name{
  margin-top:18px;font-family:var(--font-display);font-weight:800;
  font-size:clamp(1.7rem,3vw,2.3rem);letter-spacing:-.025em;line-height:1.04;
}
.modalidade__prazo{margin-top:10px;font-family:var(--font-display);font-weight:600;font-size:1.02rem;color:var(--red-deep)}
.modalidade__prazo strong{font-weight:800}
.modalidade--flex .modalidade__prazo{color:var(--red-bright)}
.modalidade__text{margin-top:14px;color:var(--muted);font-size:1.02rem;line-height:1.6;max-width:40ch}
.modalidade--flex .modalidade__text{color:rgba(255,255,255,.76)}
.modalidade .btn{margin-top:28px;align-self:flex-start}
```

- [x] **Step 5: Verificar no navegador**

1. `browser_navigate` para `http://localhost:8000/front/home.html` e `browser_console_messages`. Esperado: nenhum erro.
2. `browser_resize` 1440x900. Role até o bloco novo e `browser_take_screenshot` em `f2-t2-1440.png`. Olhe: dois cards lado a lado, o da esquerda branco e o da direita preto; o telhadinho vermelho antes de cada item das listas; o card preto com a etiqueta "Lançamento" vermelha e texto branco; nenhum corte de faceta comendo o topo dos cards.
3. Role a página inteira e `browser_take_screenshot` da emenda entre Vantagens e Portfólio em `f2-t2-emenda.png`. Olhe: fundo bone contínuo, sem sobra de espaço vazio nem telhado órfão entre as duas seções.
4. `browser_evaluate` com `() => getComputedStyle(document.querySelector('.modalidade--flex .modalidade__tag')).backgroundColor`. Esperado: `rgb(179, 20, 27)`.
5. `browser_evaluate` com `() => getComputedStyle(document.querySelector('.modalidade--flex .modalidade__prazo')).color`. Esperado: `rgb(241, 47, 55)`, que sobre `#141414` dá 4,57:1 e passa AA para texto normal.
6. `browser_resize` 390x844, role até o bloco e `browser_take_screenshot` em `f2-t2-390.png`. Olhe: os dois cards empilhados, texto sem estourar, botões dentro do card.
7. `browser_evaluate` com `() => ({ doc: document.documentElement.scrollWidth, win: window.innerWidth })` em 390px. Esperado: `doc` menor ou igual a `win`.
8. Em 1440px, `browser_click` no CTA "Ver as duas modalidades" do hero. Esperado: a página rola até `#modalidades` com o título do bloco visível.
9. Em 1440px, `browser_click` em "Ver modelos e preços" do card Casa Pronta. Esperado: rola até a seção de modelos, agora `#casa-pronta`.
10. `browser_press_key` `Tab` até chegar nos dois CTAs do bloco e `browser_snapshot`. Esperado: anel de foco visível nos dois botões, inclusive no card preto.

- [x] **Step 6: Commit**

```bash
cd "E:/Clientes/Castello Madeiras/prototipo-site-castello"
git add front/home.html css/style.css
git commit -F- <<'MSG'
feat(front): bloco de Modalidades separando Casa Pronta e Castelo Flex

Insere a secao #modalidades logo depois da prova social, com um card para
cada modalidade e o prazo de cada uma dentro dela. Move Vantagens para
depois de onde a secao Flex entra, reencadeando as facetas na ordem da
spec 7.1. Acrescenta .checklist, .modalidade e a utilitaria .modalidades__grid.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
MSG
```

---

### Tarefa 3: Seção Casa Pronta com o prazo dentro dela

Renomeia a seção de modelos para Casa Pronta e coloca o prazo de 90 a 120 dias dentro dela, que é onde ele deixa de brigar com os 45 dias da Flex.

**Files:**
- Modify: `front/home.html` (cabeçalho da seção `#casa-pronta` e classe da tag de abertura)
- Modify: `css/style.css` (região FASE 2: componente `.stamp`)

**Interfaces:**
- Consome: `.section--sand`, `.section--facetada`, `.section__head`, `.eyebrow`, `.section__title`, `.section__lead`, `.reveal`, `.grid--models`, `.model`
- Produz: `.stamp` e `.stamp--light` (usados de novo nas Tarefas 5 e 8)

- [x] **Step 1: Trocar a tag de abertura da seção**

Em `front/home.html`, substitua

```html
  <section class="section section--sand modelos" id="casa-pronta">
```

por

```html
  <section class="section section--sand section--facetada modelos" id="casa-pronta">
```

- [x] **Step 2: Trocar o cabeçalho da seção**

Substitua o `<div class="section__head">` inteiro dessa seção por este, que renomeia a modalidade no eyebrow e traz o prazo para dentro:

```html
      <div class="section__head">
        <span class="eyebrow reveal">Casa Pronta · chave na mão</span>
        <h2 class="section__title reveal">Escolha o tamanho.<br>A gente entrega completa.</h2>
        <p class="section__lead reveal">Todos os modelos saem prontos pra morar: laje aérea, elétrica, hidráulica, cerâmica, fossa, sumidouro, vidros e aberturas.</p>
        <p class="stamp reveal">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v4l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
          Pronta pra morar em <strong>90 a 120 dias</strong>
        </p>
      </div>
```

- [x] **Step 3: Trocar a faceta que vem depois da seção**

A seção de modelos passa a ser seguida pela seção escura da Flex. Depois da Tarefa 2 a ordem no arquivo é Modalidades, faceta creme, Casa Pronta, Vantagens, Portfólio. Logo depois do `</section>` que fecha `#casa-pronta`, e antes de `<!-- ============ POR QUE MADEIRA (editorial assimétrico) ============ -->`, insira:

```html
  <!-- faceta: transição escura para a Castelo Flex -->
  <div class="facet facet--ink" aria-hidden="true"></div>
```

A seção da Flex entra exatamente nesse ponto na Tarefa 4. Até lá a faceta escura encosta nas Vantagens e fica um telhado preto sobre fundo bone: é esperado nesta altura e some na tarefa seguinte.

- [x] **Step 4: Acrescentar o CSS do selo na região FASE 2**

```css
/* ---------- Selo de prazo (vive dentro da seção da modalidade) ---------- */
.stamp{
  display:inline-flex;align-items:center;gap:10px;margin-top:24px;
  padding:11px 20px;border-radius:999px;
  background:var(--red-tint);color:var(--red-deep);
  font-family:var(--font-display);font-weight:600;font-size:.98rem;line-height:1.3;
}
.stamp svg{
  width:19px;height:19px;flex:none;fill:none;stroke:currentColor;
  stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round;
}
.stamp strong{font-weight:800}
.stamp--light{background:rgba(255,255,255,.1);color:#fff;border:1px solid rgba(255,255,255,.24)}
@media(max-width:420px){
  .stamp{font-size:.9rem;padding:10px 16px}
}
```

- [x] **Step 5: Verificar no navegador**

1. `browser_navigate` para `http://localhost:8000/front/home.html` e `browser_console_messages`. Esperado: nenhum erro.
2. `browser_resize` 1440x900, role até `#casa-pronta` e `browser_take_screenshot` em `f2-t3-1440.png`. Olhe: o eyebrow diz "Casa Pronta · chave na mão", o selo rosa claro com o relógio e "Pronta pra morar em 90 a 120 dias" aparece abaixo do lead, e os quatro cards de modelo continuam intactos com os preços.
3. `browser_evaluate` com `() => { const s = getComputedStyle(document.querySelector('.stamp')); return { bg: s.backgroundColor, fg: s.color }; }`. Esperado: `bg` `rgb(251, 233, 233)` e `fg` `rgb(179, 20, 27)`, que dão contraste bem acima de 7:1.
4. `browser_evaluate` com `() => document.querySelectorAll('.hero, #modalidades, #casa-pronta').length` e confira pela ordem no `browser_snapshot` que a sequência da página é hero, prova social, modalidades, Casa Pronta.
5. `browser_resize` 390x844, role até a seção e `browser_take_screenshot` em `f2-t3-390.png`. Olhe: o selo cabe numa linha ou quebra sem estourar a lateral.
6. `browser_evaluate` com `() => ({ doc: document.documentElement.scrollWidth, win: window.innerWidth })` em 390px. Esperado: `doc` menor ou igual a `win`.
7. Em 1440px, `browser_click` no link "Casa Pronta" da nav. Esperado: rola até a seção certa e o link ganha o estado `is-current` do scroll-spy.

- [x] **Step 6: Commit**

```bash
cd "E:/Clientes/Castello Madeiras/prototipo-site-castello"
git add front/home.html css/style.css
git commit -F- <<'MSG'
feat(front): secao Casa Pronta com o prazo de 90 a 120 dias dentro dela

Renomeia a secao de modelos para Casa Pronta no eyebrow, acrescenta o selo
.stamp com o prazo logo abaixo do lead e prepara a faceta escura que leva
para a secao da Castelo Flex.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
MSG
```

---

### Tarefa 4: Seção Castelo Flex na home

Entrega a seção nova pedida na spec 7.1 item 6: resumo, vídeo explicativo, prazo de 45 dias e chamada para a página Flex.

**Files:**
- Modify: `front/home.html` (insere a seção entre a faceta escura e o Portfólio)
- Modify: `css/style.css` (região FASE 2)

**Interfaces:**
- Consome: `.section`, `.section--dark`, `.section--facetada`, `.container`, `.eyebrow--light`, `.reveal`, `.btn--primary`, `.btn--ghost-light`, `.btn--lg`, `.facet`, `[data-quote-open]`
- Produz: `#castelo-flex`, `.flexhome`, `.flexhome__title`, `.flexhome__lead`, `.flexhome__facts`, `.flexhome__fact`, `.flexhome__actions`, `.split2` e `.vexp` (as duas últimas reusadas na Tarefa 6)

**Copy provisória.** Todo o texto desta seção sai do material do Instagram e é substituível pelas chaves `flex_titulo`, `flex_texto`, `flex_prazo`, `flex_video` e `flex_video_poster` da tabela `blocos` (contrato §2.2). O vídeo é o `insta-04.mp4`, que já existe no repositório, marcando o lugar do explicativo definitivo.

- [x] **Step 1: Inserir a seção**

Em `front/home.html`, logo depois de

```html
  <!-- faceta: transição escura para a Castelo Flex -->
  <div class="facet facet--ink" aria-hidden="true"></div>
```

cole:

```html
  <!-- ============ CASTELO FLEX (resumo + vídeo explicativo) ============ -->
  <!-- Copy e vídeo provisórios: entram pelo painel nas chaves flex_* de `blocos`. -->
  <section class="section section--dark section--facetada flexhome" id="castelo-flex">
    <div class="container">
      <div class="split2">
        <div class="flexhome__text">
          <span class="eyebrow eyebrow--light reveal">Lançamento Castello</span>
          <h2 class="flexhome__title reveal">Castelo Flex: a casa semipronta no seu terreno em 45 dias.</h2>
          <p class="flexhome__lead reveal">Você recebe a casa de madeira estruturada, coberta e fechada. O acabamento fica no seu ritmo e no seu bolso, com a mesma madeira e o mesmo padrão de montagem das casas chave na mão.</p>

          <div class="flexhome__facts reveal">
            <div class="flexhome__fact"><strong>45 dias</strong><span>da assinatura à entrega</span></div>
            <div class="flexhome__fact"><strong>Casa fechada</strong><span>coberta, com portas e janelas</span></div>
            <div class="flexhome__fact"><strong>Você termina</strong><span>acabamento no seu tempo</span></div>
          </div>

          <div class="flexhome__actions reveal">
            <a href="flex.html" class="btn btn--primary btn--lg">Conhecer a Castelo Flex</a>
            <button type="button" class="btn btn--ghost-light btn--lg" data-quote-open>Pedir orçamento</button>
          </div>
        </div>

        <figure class="vexp vexp--reel reveal">
          <div class="vexp__frame">
            <video controls playsinline preload="none" poster="../videos-instagram/web/insta-04.jpg">
              <source src="../videos-instagram/web/insta-04.mp4" type="video/mp4" />
              Seu navegador não abre vídeo. <a href="../videos-instagram/web/insta-04.mp4">Baixe o vídeo da Castelo Flex</a>.
            </video>
          </div>
          <figcaption class="vexp__cap vexp__cap--light">A Castello mostra como funciona a Castelo Flex, do terreno à casa fechada.</figcaption>
        </figure>
      </div>
    </div>
  </section>

  <!-- faceta: volta ao canvas claro nas vantagens -->
  <div class="facet facet--bone" aria-hidden="true"></div>
```

- [x] **Step 2: Acrescentar o CSS na região FASE 2**

```css
/* ---------- Grid de duas colunas com quebra em coluna única ---------- */
.split2{
  display:grid;grid-template-columns:1fr 1fr;
  gap:clamp(30px,5vw,72px);align-items:center;
}
@media(max-width:900px){.split2{grid-template-columns:1fr}}

/* ---------- Vídeo explicativo (home e página Flex) ---------- */
.vexp{margin:0}
.vexp__frame{
  position:relative;border-radius:var(--r-lg);overflow:hidden;
  box-shadow:var(--shadow-card);background:#000;
}
.vexp__frame video{
  display:block;width:100%;height:auto;aspect-ratio:9/16;
  object-fit:cover;background:#000;
}
.vexp--wide .vexp__frame video{aspect-ratio:16/9}
/* reel vertical não pode virar uma torre de 900px numa coluna larga */
.vexp--reel{max-width:330px;margin-inline:auto}
.vexp__cap{margin-top:14px;font-size:.9rem;color:var(--muted);line-height:1.5;text-align:center}
.vexp__cap--light{color:rgba(255,255,255,.72)}

/* ---------- Castelo Flex na home ---------- */
/* mesma grade técnica do process e do insta, para a seção escura não ficar chapada */
.flexhome{position:relative}
.flexhome::before{
  content:"";position:absolute;inset:0;z-index:0;pointer-events:none;
  background-image:linear-gradient(rgba(255,255,255,.05) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.05) 1px,transparent 1px);
  background-size:56px 56px;
  -webkit-mask-image:radial-gradient(ellipse 78% 56% at 50% 30%,#000 35%,transparent 100%);
  mask-image:radial-gradient(ellipse 78% 56% at 50% 30%,#000 35%,transparent 100%);
}
.flexhome .container{position:relative;z-index:1}
.flexhome__title{
  font-family:var(--font-display);font-weight:800;
  font-size:clamp(2rem,4.4vw,3.4rem);line-height:1.03;letter-spacing:-.03em;color:#fff;
}
.flexhome__lead{
  margin-top:20px;color:rgba(255,255,255,.78);
  font-size:clamp(1.02rem,1.35vw,1.2rem);line-height:1.6;max-width:46ch;
}
.flexhome__facts{
  display:grid;grid-template-columns:repeat(auto-fit,minmax(148px,1fr));gap:18px;
  margin-top:30px;padding-top:26px;border-top:1px solid rgba(255,255,255,.14);
}
.flexhome__fact strong{
  display:block;font-family:var(--font-display);font-weight:800;
  font-size:1.3rem;line-height:1.1;letter-spacing:-.02em;color:var(--red-bright);
}
.flexhome__fact span{display:block;margin-top:5px;color:rgba(255,255,255,.72);font-size:.9rem;line-height:1.45}
.flexhome__actions{display:flex;flex-wrap:wrap;gap:14px;margin-top:32px}
@media(max-width:520px){
  .flexhome__actions{flex-direction:column;align-items:stretch}
  .flexhome__actions .btn{width:100%}
}
```

- [x] **Step 3: Verificar no navegador**

1. `browser_navigate` para `http://localhost:8000/front/home.html` e `browser_console_messages`. Esperado: nenhum erro.
2. `browser_resize` 1440x900, role até `#castelo-flex` e `browser_take_screenshot` em `f2-t4-1440.png`. Olhe: fundo preto com a grade técnica suave, texto à esquerda e o vídeo vertical à direita com no máximo 330px de largura; as três métricas numa linha só, com "45 dias" em vermelho claro; o telhado bone recortando a saída da seção para as Vantagens.
3. `browser_click` no botão de play do vídeo. Esperado: o vídeo toca com controles nativos e não quebra o layout. `browser_take_screenshot` em `f2-t4-video.png`.
4. `browser_evaluate` com `() => getComputedStyle(document.querySelector('.flexhome__fact strong')).color`. Esperado: `rgb(241, 47, 55)`. Sobre `#141414` dá 4,57:1, e o texto tem 1,3rem em peso 800 (20,8px negrito), acima do limiar de texto grande, então passa AA com folga.
5. `browser_evaluate` com `() => document.querySelector('.vexp--reel').getBoundingClientRect().width`. Esperado: no máximo 330.
6. `browser_resize` 900x900 e `browser_take_screenshot` em `f2-t4-900.png`. Olhe: `.split2` já quebrou em coluna única, com o vídeo abaixo do texto e centralizado.
7. `browser_resize` 390x844, role até a seção e `browser_take_screenshot` em `f2-t4-390.png`. Olhe: os dois botões ocupam a largura toda, empilhados; a legenda do vídeo legível.
8. `browser_evaluate` com `() => ({ doc: document.documentElement.scrollWidth, win: window.innerWidth })` em 390px. Esperado: `doc` menor ou igual a `win`.
9. Em 1440px, `browser_press_key` `Tab` até os dois CTAs da seção e `browser_snapshot`. Esperado: anel de foco branco visível sobre o fundo escuro (regra `.section--dark a:focus-visible{outline-color:#fff}`) e o botão do vídeo alcançável pelo teclado.
10. `browser_click` em "Pedir orçamento" da seção. Esperado: o modal `#quoteModal` abre com o foco no campo de nome.

- [x] **Step 4: Commit**

```bash
cd "E:/Clientes/Castello Madeiras/prototipo-site-castello"
git add front/home.html css/style.css
git commit -F- <<'MSG'
feat(front): secao Castelo Flex na home com video explicativo e 45 dias

Insere #castelo-flex entre a Casa Pronta e as Vantagens, com resumo da
modalidade, tres metricas, video explicativo provisorio e chamada para a
pagina Flex. Acrescenta .split2, .vexp e .flexhome na regiao FASE 2.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
MSG
```

---

### Tarefa 5: Andaime da página Castelo Flex

Cria `front/flex.html` com o esqueleto que a página inteira vai usar: head, nav e drawer compartilhados, hero próprio, rodapé, modal de orçamento e botão flutuante. As seções de conteúdo entram nas Tarefas 6 a 9.

**Files:**
- Create: `front/flex.html`
- Modify: `css/style.css` (região FASE 2: `.pagehero`)

**Interfaces:**
- Consome: `.nav`, `.drawer`, `.footer`, `.qmodal`, `.wpp-float`, `.btn--primary`, `.btn--ghost-light`, `.stamp--light`, `.facet`, `../css/style.css`, `../js/main.js`
- Produz: `front/flex.html` com `#topo` no hero, os ganchos obrigatórios do `js/main.js`, as âncoras `#o-que-e`, `#passos-flex`, `#modelos-flex`, `#faq`, `#orcamento`, `#contato`, e o componente `.pagehero`

- [x] **Step 1: Gerar a base a partir da home**

O rodapé, o modal e o botão flutuante são idênticos aos da home. Comece copiando e depois recorte:

```bash
cd "E:/Clientes/Castello Madeiras/prototipo-site-castello/front"
cp home.html flex.html
```

Em `flex.html`, apague tudo o que fica **entre** a tag de fechamento do drawer (`</div>` do `#drawer`) e o comentário `<!-- ============ CTA BAND (faceta vermelha) ============ -->`. Some com: hero de scrub, prova social, modalidades, Casa Pronta, Flex, vantagens, portfólio, processo, depoimentos, instagram, FAQ e todas as facetas entre eles. Ficam de pé: head, sprite do Google, nav, drawer, CTA band, footer, modal, lightbox de reels e botão flutuante.

Apague também o bloco inteiro do lightbox de reels, que não tem vídeo nenhum nesta página:

```html
  <!-- ============ LIGHTBOX DOS REELS (tela cheia) ============ -->
```
até o `</div>` que fecha `#reelbox`.

E o `<link rel="preload" as="video" ...>` do vídeo do hero, que esta página não usa.

- [x] **Step 2: Trocar o head**

Substitua `<title>` e as metas de descrição e Open Graph por:

```html
  <title>Castelo Flex | Casa de madeira semipronta em 45 dias | Castello</title>
  <meta name="description" content="Castelo Flex é a casa de madeira semipronta da Castello: estrutura montada, coberta e fechada no seu terreno em 45 dias. Você faz o acabamento no seu ritmo." />

  <!-- Open Graph -->
  <meta property="og:type" content="website" />
  <meta property="og:title" content="Castelo Flex | Casa de madeira semipronta em 45 dias" />
  <meta property="og:description" content="Estrutura montada, coberta e fechada no seu terreno em 45 dias. O acabamento fica no seu ritmo." />
  <meta property="og:image" content="../fotos-casas/casa7.png" />
  <meta property="og:locale" content="pt_BR" />
```

- [x] **Step 3: Trocar os links da nav para os da página Flex**

Substitua o `<nav class="nav__links" ...>` inteiro por:

```html
      <nav class="nav__links" aria-label="Navegação principal">
        <a href="#o-que-e"><svg class="nav__ico" viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 9 5-9 5-9-5 9-5ZM3 12l9 5 9-5M3 16l9 5 9-5"/></svg>A Flex</a>
        <a href="#passos-flex"><svg class="nav__ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 4v6a4 4 0 0 0 4 4h4a4 4 0 0 1 4 4v2M6 4H4m2 0h2M18 20h-2m2 0h2"/></svg>Passo a passo</a>
        <a href="#modelos-flex"><svg class="nav__ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 21h18M5 21V10l7-5 7 5v11M9 21v-6h6v6"/></svg>Casas Flex</a>
        <a href="#faq"><svg class="nav__ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M9.1 9a3 3 0 1 1 4.2 2.8c-.8.4-1.3 1.1-1.3 2M12 17.5h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>Perguntas</a>
        <a href="home.html#casa-pronta"><svg class="nav__ico" viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 8 4v6c0 4.4-3.4 7.4-8 8-4.6-.6-8-3.6-8-8V7l8-4Z"/><path d="m9 12 2 2 4-4"/></svg>Casa Pronta</a>
        <a href="#contato"><svg class="nav__ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h4l2 5-3 2a12 12 0 0 0 5 5l2-3 5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2Z"/></svg>Contato</a>
      </nav>
```

O logo da nav também deixa de apontar para `#topo` e passa a levar de volta para a home:

```html
      <a href="home.html" class="nav__logo" aria-label="Castello Casas de Madeira">
```

- [x] **Step 4: Trocar o drawer**

Substitua o `<div class="drawer" id="drawer" aria-hidden="true">` inteiro por:

```html
  <div class="drawer" id="drawer" aria-hidden="true">
    <a href="#o-que-e">A Flex</a>
    <a href="#passos-flex">Passo a passo</a>
    <a href="#modelos-flex">Casas Flex</a>
    <a href="#faq">Perguntas</a>
    <a href="home.html#casa-pronta">Casa Pronta</a>
    <a href="home.html">Voltar para a home</a>
    <a href="#contato">Contato</a>
    <button type="button" class="btn btn--primary" data-quote-open>Pedir orçamento</button>
  </div>
```

- [x] **Step 5: Inserir o hero da Flex**

Logo depois do fechamento do drawer, cole:

```html
  <!-- ============ HERO DA CASTELO FLEX ============ -->
  <!-- Copy provisória: entra pelo painel quando o material da Flex chegar. -->
  <section class="pagehero" id="topo" aria-label="Castelo Flex">
    <div class="pagehero__media">
      <img src="../fotos-casas/casa7.png" alt="Sobrado de madeira Castello com sacada e fachada de réguas" />
    </div>
    <div class="pagehero__overlay" aria-hidden="true"></div>

    <div class="container">
      <span class="eyebrow eyebrow--light reveal">Lançamento Castello · casas semiprontas</span>
      <h1 class="pagehero__title reveal">A casa de madeira <span class="hl">montada e fechada</span> no seu terreno em 45 dias.</h1>
      <p class="pagehero__lead reveal">A Castelo Flex é a modalidade semipronta da Castello. A gente entrega a estrutura completa, coberta, com portas e janelas instaladas. Você conduz o acabamento no seu ritmo, com a economia de quem faz por etapas.</p>
      <p class="stamp stamp--light reveal">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v4l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
        Entrega em <strong>45 dias</strong>
      </p>
      <div class="pagehero__actions reveal">
        <button type="button" class="btn btn--primary btn--lg" data-quote-open>
          <svg viewBox="0 0 24 24" class="ico-quote" aria-hidden="true"><path d="M4 4h16a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H8.6L4 20.5V5a1 1 0 0 1 1-1Zm3 5h10v1.7H7V9Zm0 3.6h6.6v1.7H7v-1.7Z"/></svg>
          Pedir orçamento da Flex
        </button>
        <a href="#modelos-flex" class="btn btn--ghost-light btn--lg">Ver as casas Flex</a>
      </div>
    </div>
  </section>

  <!-- faceta: telhado recorta a saída do hero -->
  <div class="facet facet--bone" aria-hidden="true"></div>
```

- [x] **Step 6: Ajustar a faixa de CTA e o `hidden` do campo de modelo**

Substitua o bloco `<section class="cta-band">` inteiro por este, que ganha âncora e a copy da Flex:

```html
  <!-- ============ CTA BAND / FORMULÁRIO DA FLEX ============ -->
  <section class="cta-band" id="orcamento">
    <div class="container cta-band__inner">
      <div class="reveal">
        <h2>Quer a Castelo Flex no seu terreno?</h2>
        <p>Peça seu orçamento. A Castello volta com a tabela atualizada da Flex e o prazo para o seu terreno.</p>
      </div>
      <button type="button" class="btn btn--light btn--lg reveal" data-quote-open>
        <svg viewBox="0 0 24 24" class="ico-quote" aria-hidden="true"><path d="M4 4h16a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H8.6L4 20.5V5a1 1 0 0 1 1-1Zm3 5h10v1.7H7V9Zm0 3.6h6.6v1.7H7v-1.7Z"/></svg>
        Pedir orçamento da Flex
      </button>
    </div>
  </section>
```

No modal desta página, o grupo de modelo nasce visível, porque quem chega aqui já está escolhendo entre casas Flex. Troque

```html
        <div class="qform__group qform__group--modelo" id="qGroupModelo" hidden>
```

por

```html
        <div class="qform__group qform__group--modelo" id="qGroupModelo">
```

O `id` continua, que é o que o `js/main.js` exige. Sem o `hidden`, o campo aparece de cara e continua sendo escondido pelo JS se o visitante trocar o "o que você busca". As opções do select passam a ser as da Flex:

```html
            <select id="q-modelo" name="modelo">
              <option value="">Ainda não sei</option>
              <option value="Castelo Flex 36 · 36 m² · semipronta">Castelo Flex 36 · 36 m²</option>
              <option value="Castelo Flex 48 · 48 m² · semipronta">Castelo Flex 48 · 48 m²</option>
              <option value="Castelo Flex 60 · 60 m² · semipronta">Castelo Flex 60 · 60 m²</option>
              <option value="Quero a Casa Pronta, chave na mão">Quero a Casa Pronta, chave na mão</option>
            </select>
```

E o cabeçalho do modal ganha a copy da Flex:

```html
      <div class="qmodal__head">
        <span class="eyebrow">Orçamento sem compromisso</span>
        <h2 id="qmodalTitle">Vamos falar da sua Castelo Flex</h2>
        <p>Conta pra gente o terreno e o tamanho que você imagina. A Castello volta com a tabela da Flex e o prazo.</p>
        <div class="qmodal__trust">
          <span><strong>5,0 ★</strong> no Google</span>
          <span><strong>56</strong> avaliações</span>
          <span><strong>12 anos</strong> de mercado</span>
        </div>
      </div>
```

- [x] **Step 7: Acrescentar o CSS do hero de página na região FASE 2**

```css
/* ---------- Hero de página interna (Castelo Flex) ---------- */
.pagehero{
  position:relative;display:flex;align-items:flex-end;
  min-height:clamp(560px,80svh,780px);
  padding-block:clamp(130px,18vh,180px) clamp(56px,9vh,96px);
  background:var(--ink);color:#fff;overflow:hidden;
}
.pagehero__media{position:absolute;inset:0;z-index:0}
.pagehero__media img{width:100%;height:100%;object-fit:cover}
.pagehero__overlay{
  position:absolute;inset:0;z-index:1;
  background:linear-gradient(180deg,rgba(20,20,20,.66) 0%,rgba(20,20,20,.42) 38%,rgba(20,20,20,.88) 100%);
}
.pagehero .container{position:relative;z-index:2}
.pagehero__title{
  font-family:var(--font-display);font-weight:800;
  font-size:clamp(2.3rem,5.6vw,4.4rem);line-height:1.02;letter-spacing:-.035em;
  max-width:17ch;text-shadow:0 2px 40px rgba(0,0,0,.5);
}
.pagehero__title .hl{color:var(--red-bright)}
.pagehero__lead{
  margin-top:20px;max-width:54ch;line-height:1.6;
  font-size:clamp(1.04rem,1.4vw,1.24rem);color:rgba(255,255,255,.86);
}
.pagehero__actions{display:flex;flex-wrap:wrap;gap:14px;margin-top:32px}
@media(max-width:560px){
  .pagehero__actions{flex-direction:column;align-items:stretch}
  .pagehero__actions .btn{width:100%}
}
```

- [x] **Step 8: Verificar no navegador**

1. `browser_navigate` para `http://localhost:8000/front/flex.html`.
2. `browser_console_messages`. Esperado: nenhum erro. Um `TypeError` aqui quer dizer que `#nav`, `#burger`, `#drawer`, `#drawerBackdrop` ou `#topo` não sobreviveram ao recorte.
3. `browser_resize` 1440x900 e `browser_take_screenshot` em `f2-t5-1440.png`. Olhe: hero com a foto da casa e o gradiente escuro, título branco legível sobre a foto, o selo translúcido "Entrega em 45 dias", dois botões, e logo abaixo a faixa vermelha de CTA e o rodapé. A página é curta nesta altura, e isso é o esperado.
4. `browser_evaluate` com `() => document.title`. Esperado: começa com "Castelo Flex".
5. Role até o fim e `browser_take_screenshot` em `f2-t5-rodape.png`. Olhe: o rodapé completo com mapa, e o telhado vermelho da `.cta-band::before` recortando a entrada da faixa. Nesta altura a `.cta-band` vem depois do hero e o telhado recorta de `--sand` sobre `--bone`: a emenda fica imperfeita até a Tarefa 9 encaixar o FAQ antes dela. Registre e siga.
6. `browser_resize` 390x844 e `browser_take_screenshot` em `f2-t5-390.png`. Olhe: título quebrando em várias linhas sem cortar, botões em coluna ocupando a largura.
7. `browser_evaluate` com `() => ({ doc: document.documentElement.scrollWidth, win: window.innerWidth })` em 390px. Esperado: `doc` menor ou igual a `win`.
8. `browser_click` no `#burger` em 390px e `browser_snapshot`. Esperado: os sete itens do drawer da Flex e o CTA. `browser_press_key` `Escape` fecha.
9. Em 1440px, `browser_click` em "Pedir orçamento da Flex" no hero. `browser_snapshot`. Esperado: o modal abre, o título diz "Vamos falar da sua Castelo Flex", o campo "Modelo de interesse" já está visível com as três opções Flex, e o foco está em "Nome completo".
10. Com o modal aberto, `browser_press_key` `Tab` seis vezes e `browser_snapshot`. Esperado: o foco circula dentro do painel do modal e não escapa para a página atrás.
11. `browser_press_key` `Escape`. Esperado: o modal fecha e o foco volta para o botão que o abriu.
12. `browser_click` no link "Casa Pronta" da nav. Esperado: vai para `home.html#casa-pronta` e cai na seção certa.

- [x] **Step 9: Commit**

```bash
cd "E:/Clientes/Castello Madeiras/prototipo-site-castello"
git add front/flex.html css/style.css
git commit -F- <<'MSG'
feat(front): andaime da pagina Castelo Flex com hero proprio

Cria front/flex.html com nav e rodape compartilhados com a home, hero de
pagina interna com foto e selo de 45 dias, faixa de orcamento com ancora
#orcamento e o modal de orcamento com as opcoes da Flex. Acrescenta o
componente .pagehero na regiao FASE 2.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
MSG
```

---

### Tarefa 6: Flex — o que é a Castelo Flex, com o vídeo explicativo

Entrega a seção 3 da spec 7.2: o que é a modalidade, com o vídeo explicativo pedido na reunião.

**Files:**
- Modify: `front/flex.html` (insere `#o-que-e` depois da faceta do hero)
- Modify: `css/style.css` (região FASE 2: nada novo, só o ajuste de `.vexp--wide` já criado na Tarefa 4 se o vídeo definitivo for horizontal)

**Interfaces:**
- Consome: `.section`, `.section--facetada`, `.container`, `.eyebrow`, `.section__title`, `.section__lead`, `.reveal`, `.split2`, `.vexp`, `.vexp--reel`, `.vexp__frame`, `.vexp__cap`, `.checklist` (Tarefas 2 e 4)
- Produz: `#o-que-e`, alvo do primeiro link da nav da Flex

**Copy e vídeo provisórios.** Texto escrito a partir do material do Instagram. O vídeo é o mesmo `insta-04.mp4` usado na home, marcando o lugar do explicativo definitivo. Quando o vídeo real chegar e for horizontal, troque `vexp--reel` por `vexp--wide` na `<figure>`.

- [x] **Step 1: Inserir a seção**

Em `front/flex.html`, logo depois de

```html
  <!-- faceta: telhado recorta a saída do hero -->
  <div class="facet facet--bone" aria-hidden="true"></div>
```

cole:

```html
  <!-- ============ O QUE É A CASTELO FLEX ============ -->
  <!-- Copy e vídeo provisórios: entram pelo painel nas chaves flex_* de `blocos`. -->
  <section class="section section--facetada" id="o-que-e">
    <div class="container">
      <div class="split2">
        <div>
          <span class="eyebrow reveal">O que é a Castelo Flex</span>
          <h2 class="section__title reveal">A estrutura pronta.<br>O acabamento no seu tempo.</h2>
          <p class="section__lead reveal">A Castello monta a casa de madeira no seu terreno e entrega ela fechada: estrutura, telhado, portas e janelas. Daí em diante você escolhe quando e como fazer o acabamento, sem prazo de obra correndo atrás de você.</p>
          <ul class="checklist reveal">
            <li>A mesma madeira e o mesmo prego galvanizado das casas chave na mão</li>
            <li>Montagem pela equipe que constrói casas Castello há 12 anos</li>
            <li>Planta ajustada ao seu terreno antes da produção</li>
            <li>Você paga o acabamento por etapa, no ritmo do seu bolso</li>
          </ul>
        </div>

        <figure class="vexp vexp--reel reveal">
          <div class="vexp__frame">
            <video controls playsinline preload="none" poster="../videos-instagram/web/insta-04.jpg">
              <source src="../videos-instagram/web/insta-04.mp4" type="video/mp4" />
              Seu navegador não abre vídeo. <a href="../videos-instagram/web/insta-04.mp4">Baixe o vídeo da Castelo Flex</a>.
            </video>
          </div>
          <figcaption class="vexp__cap">A Castello explica a Castelo Flex, do terreno à casa fechada.</figcaption>
        </figure>
      </div>
    </div>
  </section>

  <!-- faceta: transição para o passo a passo -->
  <div class="facet facet--sand" aria-hidden="true"></div>
```

- [x] **Step 2: Verificar no navegador**

1. `browser_navigate` para `http://localhost:8000/front/flex.html` e `browser_console_messages`. Esperado: nenhum erro.
2. `browser_resize` 1440x900, role até `#o-que-e` e `browser_take_screenshot` em `f2-t6-1440.png`. Olhe: texto à esquerda com o telhadinho vermelho em cada item da lista, vídeo vertical à direita limitado a 330px, legenda cinza abaixo dele, e o telhado creme recortando a saída da seção.
3. `browser_click` no play do vídeo e `browser_take_screenshot` em `f2-t6-video.png`. Esperado: toca com controles nativos, sem empurrar o layout.
4. `browser_evaluate` com `() => getComputedStyle(document.querySelector('#o-que-e .checklist li')).color`. Esperado: `rgb(20, 20, 20)`, que é `--ink` sobre `--bone` e passa AAA.
5. `browser_resize` 900x900 e `browser_take_screenshot` em `f2-t6-900.png`. Olhe: coluna única, vídeo centralizado abaixo do texto.
6. `browser_resize` 390x844, role até a seção e `browser_take_screenshot` em `f2-t6-390.png`.
7. `browser_evaluate` com `() => ({ doc: document.documentElement.scrollWidth, win: window.innerWidth })` em 390px. Esperado: `doc` menor ou igual a `win`.
8. Em 1440px, `browser_click` no link "A Flex" da nav. Esperado: rola até a seção com o título visível abaixo da nav fixa.
9. `browser_press_key` `Tab` até o vídeo e `browser_press_key` `Space`. Esperado: o vídeo toca pelo teclado, com anel de foco visível no player.

- [x] **Step 3: Commit**

```bash
cd "E:/Clientes/Castello Madeiras/prototipo-site-castello"
git add front/flex.html
git commit -F- <<'MSG'
feat(front): secao "o que e a Castelo Flex" com video explicativo

Insere #o-que-e na pagina Flex, com o resumo da modalidade, a lista do que
vem no padrao Castello e o video explicativo provisorio ao lado.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
MSG
```

---

### Tarefa 7: Flex — passo a passo com a fronteira de entrega explícita

Entrega a seção 4 da spec 7.2, que é o coração da página: deixar claro o que a Castello entrega e **onde a entrega dela termina**.

**Files:**
- Modify: `front/flex.html` (insere `#passos-flex` depois da faceta creme)
- Modify: `css/style.css` (região FASE 2)

**Interfaces:**
- Consome: `.section`, `.section--sand`, `.section--facetada`, `.container`, `.section__head`, `.eyebrow`, `.section__title`, `.section__lead`, `.reveal`, `.facet`
- Produz: `#passos-flex`, `.epasso__grid`, `.epasso`, `.epasso__num`, `.limite`, `.limite__txt`, `.depois__head`, `.depois__grid`, `.depois__item`, `.depois__nota`

**Copy provisória.** Os cinco passos e a lista do que fica por conta do cliente entram no banco como `passos` com contexto `flex` e são editáveis pelo painel.

- [x] **Step 1: Inserir a seção**

Em `front/flex.html`, logo depois de

```html
  <!-- faceta: transição para o passo a passo -->
  <div class="facet facet--sand" aria-hidden="true"></div>
```

cole:

```html
  <!-- ============ PASSO A PASSO DA FLEX (com a fronteira de entrega) ============ -->
  <!-- Passos provisórios: migram para a tabela `passos` com contexto flex. -->
  <section class="section section--sand section--facetada" id="passos-flex">
    <div class="container">
      <div class="section__head">
        <span class="eyebrow reveal">Passo a passo</span>
        <h2 class="section__title reveal">O que a Castello entrega,<br>e onde a nossa entrega termina.</h2>
        <p class="section__lead reveal">Sem letra miúda. Estas são as cinco etapas que a Castello executa na Castelo Flex, e logo abaixo está a lista do que fica por sua conta depois que a gente sai do terreno.</p>
      </div>

      <div class="epasso__grid">
        <article class="epasso reveal">
          <span class="epasso__num">01</span>
          <h3>Projeto e modelo</h3>
          <p>Você escolhe o modelo Flex e a gente ajusta a planta ao seu terreno e ao seu orçamento.</p>
        </article>
        <article class="epasso reveal">
          <span class="epasso__num">02</span>
          <h3>Fundação</h3>
          <p>A Castello prepara a base da casa, no padrão que a estrutura de madeira exige.</p>
        </article>
        <article class="epasso reveal">
          <span class="epasso__num">03</span>
          <h3>Estrutura montada</h3>
          <p>Paredes e estrutura montadas com madeira de qualidade e prego galvanizado em toda a obra.</p>
        </article>
        <article class="epasso reveal">
          <span class="epasso__num">04</span>
          <h3>Cobertura</h3>
          <p>Telhado completo, com a casa protegida da chuva e do sol desde o primeiro dia.</p>
        </article>
        <article class="epasso reveal">
          <span class="epasso__num">05</span>
          <h3>Portas e janelas</h3>
          <p>Aberturas instaladas e a casa entregue fechada e trancada no seu terreno, em 45 dias.</p>
        </article>
      </div>

      <div class="limite reveal">
        <span class="limite__txt">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v18M5 8h14M5 16h14"/></svg>
          A entrega da Castello termina aqui
        </span>
      </div>

      <div class="depois__head reveal">
        <h3 class="depois__title">O que fica por sua conta</h3>
        <p class="depois__text">Daqui pra frente a casa é sua e o ritmo é seu. Você contrata quem quiser, na ordem que quiser, e paga por etapa. A Castello segue disponível para orientar, mas o acabamento não está incluso na Castelo Flex.</p>
      </div>

      <div class="depois__grid">
        <div class="depois__item reveal"><strong>Elétrica e hidráulica</strong>Fiação, pontos, tubulação e louças.</div>
        <div class="depois__item reveal"><strong>Revestimentos e piso</strong>Cerâmica, forro, banheiro e cozinha.</div>
        <div class="depois__item reveal"><strong>Pintura e acabamentos</strong>Interna, externa e os detalhes finos.</div>
        <div class="depois__item reveal"><strong>Fossa e sumidouro</strong>Ligação de esgoto e de água no terreno.</div>
      </div>

      <p class="depois__nota reveal">Quer tudo isso incluso e a chave na mão no fim? Então a sua modalidade é a
        <a href="home.html#casa-pronta">Casa Pronta, completa em 90 a 120 dias</a>.
      </p>
    </div>
  </section>

  <!-- faceta: transição para o catálogo Flex -->
  <div class="facet facet--bone" aria-hidden="true"></div>
```

- [x] **Step 2: Acrescentar o CSS na região FASE 2**

```css
/* ---------- Passo a passo da Flex ---------- */
.epasso__grid{
  display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
  gap:clamp(14px,2vw,24px);
}
.epasso{
  display:flex;flex-direction:column;
  background:#fff;border:1px solid var(--line);border-radius:var(--r-lg);
  box-shadow:var(--shadow-soft);padding:26px 24px 28px;
  transition:transform .3s var(--ease),box-shadow .3s var(--ease);
}
.epasso:hover{transform:translateY(-4px);box-shadow:var(--shadow-card)}
.epasso__num{
  font-family:var(--font-display);font-weight:800;font-size:2.1rem;
  line-height:1;letter-spacing:-.02em;color:var(--wood);
}
.epasso h3{
  margin-top:14px;font-family:var(--font-display);font-weight:700;
  font-size:1.16rem;letter-spacing:-.01em;line-height:1.18;
}
.epasso p{margin-top:10px;color:var(--muted);font-size:.98rem;line-height:1.55}

/* ---------- A linha que separa o que é nosso do que é seu ---------- */
.limite{display:flex;align-items:center;gap:16px;margin:clamp(36px,5.5vh,58px) 0 clamp(28px,4vh,40px)}
.limite::before,.limite::after{
  content:"";flex:1;height:2px;
  background:linear-gradient(90deg,transparent,var(--red),transparent);
}
.limite__txt{
  flex:none;display:inline-flex;align-items:center;gap:10px;
  padding:13px 22px;border-radius:999px;text-align:center;
  background:var(--red-deep);color:#fff;
  font-family:var(--font-display);font-weight:700;font-size:.98rem;line-height:1.3;
  box-shadow:0 14px 32px -14px rgba(179,20,27,.7);
}
.limite__txt svg{
  width:19px;height:19px;flex:none;fill:none;stroke:currentColor;
  stroke-width:2;stroke-linecap:round;stroke-linejoin:round;
}
@media(max-width:640px){
  .limite{flex-direction:column;gap:14px}
  .limite::before,.limite::after{width:100%;flex:none}
  .limite__txt{font-size:.92rem;padding:12px 18px}
}

/* ---------- O que fica por conta do cliente ---------- */
.depois__head{max-width:62ch;margin-bottom:clamp(22px,3.2vh,32px)}
.depois__title{
  font-family:var(--font-display);font-weight:800;
  font-size:clamp(1.4rem,2.8vw,2rem);letter-spacing:-.02em;line-height:1.1;
}
.depois__text{margin-top:12px;color:var(--muted);font-size:1.02rem;line-height:1.6}
.depois__grid{
  display:grid;grid-template-columns:repeat(auto-fit,minmax(215px,1fr));
  gap:clamp(12px,1.8vw,20px);
}
.depois__item{
  background:#fff;border:1px solid var(--line);border-radius:var(--r-md);
  padding:20px 22px;color:var(--muted);font-size:.96rem;line-height:1.5;
}
.depois__item strong{
  display:block;margin-bottom:6px;color:var(--ink);
  font-family:var(--font-display);font-weight:700;font-size:1.04rem;
}
.depois__nota{
  margin-top:clamp(24px,3.4vh,34px);max-width:62ch;
  color:var(--muted);font-size:1rem;line-height:1.6;
}
.depois__nota a{color:var(--red-deep);font-weight:600}
.depois__nota a:hover{text-decoration:underline}
```

- [x] **Step 3: Verificar no navegador**

1. `browser_navigate` para `http://localhost:8000/front/flex.html` e `browser_console_messages`. Esperado: nenhum erro.
2. `browser_resize` 1440x900, role até `#passos-flex` e `browser_take_screenshot` em `f2-t7-1440.png`. Olhe: cinco cards brancos numa faixa creme, cada um com o número em madeira; abaixo deles a pílula vermelha escura "A entrega da Castello termina aqui" com as duas linhas vermelhas saindo dos lados; depois o bloco "O que fica por sua conta" com quatro itens; e a nota final com o link para a Casa Pronta.
3. `browser_evaluate` com `() => getComputedStyle(document.querySelector('.limite__txt')).backgroundColor`. Esperado: `rgb(179, 20, 27)`, que dá 6,9:1 contra o texto branco.
4. `browser_evaluate` com `() => getComputedStyle(document.querySelector('.epasso__num')).color`. Esperado: `rgb(176, 122, 67)`, o `--wood`.
5. `browser_evaluate` com `() => document.querySelector('.limite__txt').textContent.trim()`. Esperado: exatamente `A entrega da Castello termina aqui`. Este é o requisito literal da spec 7.2 item 4: se a frase não estiver na tela, a tarefa está reprovada.
6. `browser_resize` 640x900 e `browser_take_screenshot` em `f2-t7-640.png`. Olhe: a pílula do limite empilha com as linhas acima e abaixo dela, sem sobrepor texto.
7. `browser_resize` 390x844, role até a seção e `browser_take_screenshot` em `f2-t7-390.png`. Olhe: cards em coluna única, pílula do limite legível numa ou duas linhas.
8. `browser_evaluate` com `() => ({ doc: document.documentElement.scrollWidth, win: window.innerWidth })` em 390px. Esperado: `doc` menor ou igual a `win`.
9. Em 1440px, `browser_click` no link da nota "Casa Pronta, completa em 90 a 120 dias". Esperado: vai para `home.html#casa-pronta`.
10. `browser_press_key` `Tab` até esse link e `browser_snapshot`. Esperado: anel de foco vermelho visível.
11. `browser_click` no link "Passo a passo" da nav. Esperado: rola até a seção com o título visível.

- [x] **Step 4: Commit**

```bash
cd "E:/Clientes/Castello Madeiras/prototipo-site-castello"
git add front/flex.html css/style.css
git commit -F- <<'MSG'
feat(front): passo a passo da Flex com a fronteira de entrega explicita

Insere #passos-flex com as cinco etapas que a Castello executa, a faixa
"A entrega da Castello termina aqui" e a lista do que fica por conta do
cliente, mais a nota que leva quem quer tudo incluso para a Casa Pronta.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
MSG
```

---

### Tarefa 8: Flex — catálogo das casas e diferenciais de 45 dias

Entrega as seções 5 e 6 da spec 7.2: o catálogo das casas Flex e o bloco de prazo com os diferenciais.

**Files:**
- Modify: `front/flex.html` (insere `#modelos-flex` e `#diferenciais`)
- Modify: `css/style.css` (região FASE 2)

**Interfaces:**
- Consome: `.section`, `.section--dark`, `.section--facetada`, `.container`, `.section__head`, `.eyebrow`, `.eyebrow--light`, `.section__title`, `.section__lead`, `.reveal`, `.grid`, `.grid--models`, `.model`, `.model__media`, `.model__badge`, `.model__body`, `.model__name`, `.model__area`, `.price`, `.price__label`, `.price__val`, `.btn--primary`, `.btn--block`, `.stamp--light`, `[data-quote-open]`, `[data-modelo]`
- Produz: `#modelos-flex`, `#diferenciais`, `.price__val--sob`, `.modelos__note` reusado, `.bene__grid`, `.bene`, `.bene__ico`

**Dados provisórios.** Nomes, áreas e o "Sob consulta" no lugar do preço são marcadores até o cliente enviar a tabela da Flex. Migram para `modelos` com modalidade `flex` (contrato §2), onde a coluna `preco` já é opcional exatamente por causa disso. As fotos são casas Castello reais reaproveitadas, e a legenda da seção avisa na própria página que a tabela está em fechamento.

- [x] **Step 1: Inserir o catálogo**

Em `front/flex.html`, logo depois de

```html
  <!-- faceta: transição para o catálogo Flex -->
  <div class="facet facet--bone" aria-hidden="true"></div>
```

cole:

```html
  <!-- ============ CATÁLOGO DAS CASAS FLEX ============ -->
  <!-- Modelos provisórios: nome, área e preço entram pelo painel em `modelos` modalidade flex. -->
  <section class="section section--facetada" id="modelos-flex">
    <div class="container">
      <div class="section__head">
        <span class="eyebrow reveal">Casas Castelo Flex</span>
        <h2 class="section__title reveal">Três tamanhos<br>para começar.</h2>
        <p class="section__lead reveal">Cada modelo Flex sai da fábrica com a estrutura completa e é montado no seu terreno em 45 dias. A planta é ajustada antes da produção.</p>
      </div>

      <div class="grid grid--models">
        <article class="model reveal">
          <div class="model__media">
            <img src="../fotos-casas/casa4.png" alt="Casa de madeira Castello compacta de dois pavimentos" loading="lazy" />
            <span class="model__badge">Semipronta</span>
          </div>
          <div class="model__body">
            <span class="eyebrow">Entrega em 45 dias</span>
            <h3 class="model__name">Castelo Flex 36</h3>
            <p class="model__area">36,00 m² de área construída</p>
            <div class="price">
              <span class="price__label">Valor</span>
              <span class="price__val price__val--sob">Sob consulta</span>
            </div>
            <button type="button" class="btn btn--primary btn--block" data-quote-open data-modelo="Castelo Flex 36 · 36 m² · semipronta">Pedir orçamento</button>
          </div>
        </article>

        <article class="model reveal">
          <div class="model__media">
            <img src="../fotos-casas/casa2.png" alt="Casa de madeira Castello térrea com telhado de telhas e varanda" loading="lazy" />
            <span class="model__badge">Semipronta</span>
          </div>
          <div class="model__body">
            <span class="eyebrow">Entrega em 45 dias</span>
            <h3 class="model__name">Castelo Flex 48</h3>
            <p class="model__area">48,00 m² de área construída</p>
            <div class="price">
              <span class="price__label">Valor</span>
              <span class="price__val price__val--sob">Sob consulta</span>
            </div>
            <button type="button" class="btn btn--primary btn--block" data-quote-open data-modelo="Castelo Flex 48 · 48 m² · semipronta">Pedir orçamento</button>
          </div>
        </article>

        <article class="model reveal">
          <div class="model__media">
            <img src="../fotos-casas/casa6.png" alt="Casa de madeira Castello térrea com varanda ampla e garagem coberta" loading="lazy" />
            <span class="model__badge">Semipronta</span>
          </div>
          <div class="model__body">
            <span class="eyebrow">Entrega em 45 dias</span>
            <h3 class="model__name">Castelo Flex 60</h3>
            <p class="model__area">60,00 m² de área construída</p>
            <div class="price">
              <span class="price__label">Valor</span>
              <span class="price__val price__val--sob">Sob consulta</span>
            </div>
            <button type="button" class="btn btn--primary btn--block" data-quote-open data-modelo="Castelo Flex 60 · 60 m² · semipronta">Pedir orçamento</button>
          </div>
        </article>
      </div>

      <p class="modelos__note reveal">Tamanhos e valores da Castelo Flex em fechamento com a fábrica. Peça o seu orçamento e receba a tabela atualizada, com o prazo para o seu terreno.</p>
    </div>
  </section>

  <!-- faceta: transição escura para os diferenciais -->
  <div class="facet facet--ink" aria-hidden="true"></div>
```

- [x] **Step 2: Inserir o bloco de prazo e diferenciais**

Logo depois dessa faceta escura, cole:

```html
  <!-- ============ PRAZO E DIFERENCIAIS DA FLEX ============ -->
  <section class="section section--dark section--facetada" id="diferenciais">
    <div class="container">
      <div class="section__head">
        <span class="eyebrow eyebrow--light reveal">Por que a Flex</span>
        <h2 class="section__title reveal">Quarenta e cinco dias<br>e a casa é sua.</h2>
        <p class="section__lead reveal">A Castelo Flex encurta a parte difícil da obra. O que costuma levar meses de canteiro sai em 45 dias, com a estrutura fechada e a casa protegida.</p>
        <p class="stamp stamp--light reveal">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v4l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
          Entrega em <strong>45 dias</strong>
        </p>
      </div>

      <div class="bene__grid">
        <article class="bene reveal">
          <span class="bene__ico"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v4l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg></span>
          <h3>45 dias de obra</h3>
          <p>Da assinatura à casa fechada no seu terreno, com data combinada em contrato.</p>
        </article>
        <article class="bene reveal">
          <span class="bene__ico"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 8h15a3 3 0 0 1 3 3v6a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V8Zm0 0a3 3 0 0 1 3-3h9"/><circle cx="17" cy="14" r="1.4" fill="currentColor" stroke="none"/></svg></span>
          <h3>Você paga por etapa</h3>
          <p>O acabamento vem depois, no seu ritmo. Sem obra inteira travando o seu orçamento.</p>
        </article>
        <article class="bene reveal">
          <span class="bene__ico"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 8 4v6c0 4.4-3.4 7.4-8 8-4.6-.6-8-3.6-8-8V7l8-4Z"/><path d="m9 12 2 2 4-4"/></svg></span>
          <h3>Mesma madeira, mesma equipe</h3>
          <p>Prego galvanizado e madeira de qualidade, montados por quem constrói Castello há 12 anos.</p>
        </article>
        <article class="bene reveal">
          <span class="bene__ico"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 21h18M5 21V10l7-5 7 5v11M9 21v-6h6v6"/></svg></span>
          <h3>Pode virar renda</h3>
          <p>Muita gente fecha a Flex para aluguel, hospedagem ou revenda, e termina o acabamento depois.</p>
        </article>
      </div>
    </div>
  </section>

  <!-- faceta: transição para as perguntas -->
  <div class="facet facet--sand" aria-hidden="true"></div>
```

- [x] **Step 3: Acrescentar o CSS na região FASE 2**

```css
/* ---------- Preço ainda sem tabela fechada ---------- */
.price__val--sob{
  font-size:clamp(1.3rem,2.2vw,1.7rem);font-weight:700;
  color:var(--muted);letter-spacing:-.01em;
}

/* ---------- Cards de diferencial ---------- */
.bene__grid{
  display:grid;grid-template-columns:repeat(auto-fit,minmax(228px,1fr));
  gap:clamp(14px,2vw,24px);
}
.bene{
  background:#fff;border:1px solid var(--line);border-radius:var(--r-lg);
  box-shadow:var(--shadow-soft);padding:28px 24px 30px;
  transition:transform .3s var(--ease),box-shadow .3s var(--ease);
}
.bene:hover{transform:translateY(-4px);box-shadow:var(--shadow-card)}
.bene__ico{
  display:grid;place-items:center;width:54px;height:54px;
  border-radius:15px;background:var(--red-tint);
}
.bene__ico svg{
  width:26px;height:26px;fill:none;stroke:var(--red);
  stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round;
}
.bene h3{
  margin-top:18px;font-family:var(--font-display);font-weight:700;
  font-size:1.16rem;letter-spacing:-.01em;line-height:1.18;color:var(--ink);
}
.bene p{margin-top:9px;color:var(--muted);font-size:.98rem;line-height:1.55}
```

- [x] **Step 4: Verificar no navegador**

1. `browser_navigate` para `http://localhost:8000/front/flex.html` e `browser_console_messages`. Esperado: nenhum erro.
2. `browser_resize` 1440x900, role até `#modelos-flex` e `browser_take_screenshot` em `f2-t8-catalogo.png`. Olhe: três cards de modelo com foto, selo "Semipronta", eyebrow "Entrega em 45 dias", nome, área e "Sob consulta" no lugar do preço, mais a nota abaixo dizendo que a tabela está em fechamento.
3. `browser_evaluate` com `() => [...document.querySelectorAll('#modelos-flex .price__val')].map(el => el.textContent.trim())`. Esperado: três vezes `Sob consulta`. Nenhum valor em reais pode aparecer aqui: preço inventado é erro grave.
4. Role até `#diferenciais` e `browser_take_screenshot` em `f2-t8-diferenciais.png`. Olhe: seção preta com o selo translúcido de 45 dias e quatro cards brancos com o ícone em badge rosa claro.
5. `browser_evaluate` com `() => getComputedStyle(document.querySelector('.bene__ico svg')).stroke`. Esperado: `rgb(237, 33, 40)`, o `--red` sobre `--red-tint`, que é ícone e não texto.
6. `browser_click` no botão "Pedir orçamento" do card Castelo Flex 48. `browser_snapshot`. Esperado: o modal abre com "Modelo de interesse" preenchido com `Castelo Flex 48 · 48 m²`, porque o `data-modelo` do card bate com o `value` da opção do select. Se ficar em "Ainda não sei", os dois textos divergiram e a tarefa está reprovada.
7. `browser_press_key` `Escape` para fechar o modal.
8. `browser_resize` 390x844, role pelas duas seções e `browser_take_screenshot` em `f2-t8-390.png`. Olhe: cards em coluna única, botões ocupando a largura do card.
9. `browser_evaluate` com `() => ({ doc: document.documentElement.scrollWidth, win: window.innerWidth })` em 390px. Esperado: `doc` menor ou igual a `win`.
10. Em 1440px, `browser_click` no link "Casas Flex" da nav. Esperado: rola até `#modelos-flex`.

- [x] **Step 5: Commit**

```bash
cd "E:/Clientes/Castello Madeiras/prototipo-site-castello"
git add front/flex.html css/style.css
git commit -F- <<'MSG'
feat(front): catalogo das casas Flex e bloco de 45 dias com diferenciais

Insere #modelos-flex com tres modelos provisorios sem preco fechado, com
"Sob consulta" no lugar do valor e aviso na propria pagina de que a tabela
esta em fechamento, e #diferenciais com o selo de 45 dias e quatro cards.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
MSG
```

---

### Tarefa 9: Flex — FAQ da Castelo Flex

Entrega a seção 7 da spec 7.2. Reaproveita o componente de tabs vertical do FAQ da home, com os mesmos `id` e classes, para o `js/main.js` dirigir a interação sem uma linha nova de JavaScript.

**Files:**
- Modify: `front/flex.html` (insere `#faq` antes da `.cta-band`)
- Modify: `css/style.css` (nenhuma regra nova, o componente `.faq` já existe)

**Interfaces:**
- Consome: `.faq`, `.faq__head`, `.faq__board`, `#faqTabs`, `.faq__tablist`, `.faq__tab`, `.faq__ico`, `.faq__panels`, `.faq__content`, `.faq__content-title`, `.faq__content-body`, `.faq__foot`, `.faq__foot-link`
- Produz: `#faq` na página Flex, com cinco perguntas usando cinco das sete chaves de ícone do conjunto fechado do contrato §2.3: `relogio`, `planta`, `chave`, `escudo`, `fundacao`

**Copy provisória.** As cinco perguntas migram para `faq` com contexto `flex`. Os SVG são exatamente os mesmos do FAQ da home, o que garante que o conjunto de ícones continua fechado, como manda a spec 5.3.

- [x] **Step 1: Inserir o FAQ**

Em `front/flex.html`, logo depois de

```html
  <!-- faceta: transição para as perguntas -->
  <div class="facet facet--sand" aria-hidden="true"></div>
```

e imediatamente **antes** de `<!-- ============ CTA BAND / FORMULÁRIO DA FLEX ============ -->`, cole:

```html
  <!-- ============ FAQ DA CASTELO FLEX ============ -->
  <!-- Perguntas provisórias: migram para `faq` com contexto flex. Ícones do conjunto fechado. -->
  <section class="section faq" id="faq" aria-label="Perguntas frequentes sobre a Castelo Flex">
    <div class="container">
      <div class="section__head faq__head">
        <span class="eyebrow reveal">Perguntas frequentes</span>
        <h2 class="section__title reveal">O que todo mundo pergunta<br>antes de fechar a Flex.</h2>
        <p class="section__lead reveal">Reunimos as dúvidas que mais aparecem sobre a modalidade semipronta. Ficou com outra pergunta? É só chamar a gente.</p>
      </div>

      <div class="faq__board reveal" id="faqTabs">
        <div class="faq__tablist" role="tablist" aria-orientation="vertical" aria-label="Perguntas sobre a Castelo Flex">
          <button class="faq__tab is-active" type="button" role="tab" id="faq-tab1" aria-selected="true" aria-controls="faq-panel1" aria-label="Em quanto tempo a Castelo Flex fica pronta?" title="Em quanto tempo a Castelo Flex fica pronta?">
            <span class="faq__ico"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v4l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg></span>
          </button>
          <button class="faq__tab" type="button" role="tab" id="faq-tab2" aria-selected="false" aria-controls="faq-panel2" tabindex="-1" aria-label="O que exatamente vem na entrega da Flex?" title="O que exatamente vem na entrega da Flex?">
            <span class="faq__ico"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="15" cy="9" r="4"/><path d="m12 12-8 8M8 16l2 2M6 18l1.6 1.6"/></svg></span>
          </button>
          <button class="faq__tab" type="button" role="tab" id="faq-tab3" aria-selected="false" aria-controls="faq-panel3" tabindex="-1" aria-label="Posso mudar a planta da Castelo Flex?" title="Posso mudar a planta da Castelo Flex?">
            <span class="faq__ico"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h7M15 6h5M4 12h2M10 12h10M4 18h9M17 18h3"/><circle cx="13" cy="6" r="2" fill="currentColor" stroke="none"/><circle cx="8" cy="12" r="2" fill="currentColor" stroke="none"/><circle cx="15" cy="18" r="2" fill="currentColor" stroke="none"/></svg></span>
          </button>
          <button class="faq__tab" type="button" role="tab" id="faq-tab4" aria-selected="false" aria-controls="faq-panel4" tabindex="-1" aria-label="A madeira é a mesma das casas chave na mão?" title="A madeira é a mesma das casas chave na mão?">
            <span class="faq__ico"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 8 4v6c0 4.4-3.4 7.4-8 8-4.6-.6-8-3.6-8-8V7l8-4Z"/><path d="m9 12 2 2 4-4"/></svg></span>
          </button>
          <button class="faq__tab" type="button" role="tab" id="faq-tab5" aria-selected="false" aria-controls="faq-panel5" tabindex="-1" aria-label="Vocês cuidam da fundação e do terreno?" title="Vocês cuidam da fundação e do terreno?">
            <span class="faq__ico"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 9 5-9 5-9-5 9-5ZM3 12l9 5 9-5M3 16l9 5 9-5"/></svg></span>
          </button>
        </div>

        <div class="faq__panels">
          <div class="faq__content is-active" role="tabpanel" id="faq-panel1" aria-labelledby="faq-tab1" tabindex="0">
            <h3 class="faq__content-title">Em quanto tempo a Castelo Flex fica pronta?</h3>
            <p class="faq__content-body">A entrega da estrutura montada, coberta e fechada é em 45 dias, contados da assinatura e da liberação do terreno. O acabamento depois disso corre no seu ritmo, sem prazo de obra em cima de você.</p>
          </div>
          <div class="faq__content" role="tabpanel" id="faq-panel2" aria-labelledby="faq-tab2" tabindex="0" hidden>
            <h3 class="faq__content-title">O que exatamente vem na entrega da Flex?</h3>
            <p class="faq__content-body">Fundação preparada, estrutura de madeira montada, telhado completo, portas e janelas instaladas. A casa é entregue fechada e trancada no seu terreno. Elétrica, hidráulica, revestimento, piso e pintura ficam por sua conta.</p>
          </div>
          <div class="faq__content" role="tabpanel" id="faq-panel3" aria-labelledby="faq-tab3" tabindex="0" hidden>
            <h3 class="faq__content-title">Posso mudar a planta da Castelo Flex?</h3>
            <p class="faq__content-body">Sim. A planta é ajustada ao seu terreno e à sua rotina antes da produção. Depois que a estrutura entra em fabricação, as mudanças passam a ser de acabamento, que é justamente a parte que fica com você.</p>
          </div>
          <div class="faq__content" role="tabpanel" id="faq-panel4" aria-labelledby="faq-tab4" tabindex="0" hidden>
            <h3 class="faq__content-title">A madeira é a mesma das casas chave na mão?</h3>
            <p class="faq__content-body">É a mesma. Madeira de qualidade e prego galvanizado em toda a estrutura, montados pela mesma equipe que constrói as casas Castello há 12 anos. A Flex muda o escopo da entrega, nunca o padrão da construção.</p>
          </div>
          <div class="faq__content" role="tabpanel" id="faq-panel5" aria-labelledby="faq-tab5" tabindex="0" hidden>
            <h3 class="faq__content-title">Vocês cuidam da fundação e do terreno?</h3>
            <p class="faq__content-body">A fundação faz parte da entrega da Flex. A gente avalia o seu terreno e prepara a base certa pra receber a estrutura, com técnica e segurança, antes de a casa subir.</p>
          </div>
        </div>
      </div>

      <p class="faq__foot reveal">A Castello fica em Tubarão e atende toda a região, com casas já entregues em diversas cidades de Santa Catarina.
        <button type="button" class="faq__foot-link" data-quote-open>Tirar uma dúvida sobre a Flex</button>
      </p>
    </div>
  </section>
```

- [x] **Step 2: Verificar no navegador**

1. `browser_navigate` para `http://localhost:8000/front/flex.html` e `browser_console_messages`. Esperado: nenhum erro.
2. `browser_resize` 1440x900, role até `#faq` e `browser_take_screenshot` em `f2-t9-1440.png`. Olhe: rail vertical com cinco ícones à esquerda, o primeiro em vermelho cheio, e o painel branco à direita com a primeira pergunta aberta. Logo abaixo, a faixa vermelha de CTA com o telhado recortando a entrada a partir do creme do FAQ: a emenda que ficou torta na Tarefa 5 agora fecha.
3. `browser_click` no terceiro ícone do rail e `browser_snapshot`. Esperado: o painel troca para "Posso mudar a planta da Castelo Flex?", o ícone clicado fica vermelho cheio e o primeiro volta ao rosa claro. É o `js/main.js` dirigindo, sem JS novo.
4. `browser_press_key` `ArrowDown` com o foco no rail e `browser_snapshot`. Esperado: vai para a quarta pergunta e o foco acompanha. `Home` volta para a primeira, `End` vai para a quinta.
5. `browser_evaluate` com `() => document.querySelectorAll('#faqTabs .faq__tab').length` e `() => document.querySelectorAll('#faqTabs .faq__content').length`. Esperado: 5 nos dois. Contagem diferente quebra o pareamento por índice do `selectTab`.
6. `browser_evaluate` com `() => [...document.querySelectorAll('#faqTabs .faq__content:not([hidden])')].length`. Esperado: 1.
7. `browser_resize` 560x900 e `browser_take_screenshot` em `f2-t9-560.png`. Olhe: o rail vira uma faixa horizontal de cinco ícones acima do painel, sem estourar a largura.
8. `browser_resize` 390x844 e `browser_take_screenshot` em `f2-t9-390.png`.
9. `browser_evaluate` com `() => ({ doc: document.documentElement.scrollWidth, win: window.innerWidth })` em 390px. Esperado: `doc` menor ou igual a `win`.
10. `browser_click` no botão "Tirar uma dúvida sobre a Flex". Esperado: o modal abre.
11. Em 1440px, `browser_click` no link "Perguntas" da nav. Esperado: rola até o FAQ.

- [x] **Step 3: Commit**

```bash
cd "E:/Clientes/Castello Madeiras/prototipo-site-castello"
git add front/flex.html
git commit -F- <<'MSG'
feat(front): FAQ da Castelo Flex reusando as tabs verticais da home

Insere #faq na pagina Flex com cinco perguntas provisorias e cinco icones
do conjunto fechado do contrato, com os mesmos ids e classes que o
js/main.js ja dirige. Fecha a emenda de faceta antes da faixa de orcamento.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
MSG
```

---

### Tarefa 10: Formulário alinhado ao contrato e varredura final

Alinha a marcação do formulário ao contrato §6.1 nas duas páginas e faz a passada final de responsivo, contraste e teclado. Nenhuma linha de JavaScript é escrita: os campos ocultos nascem vazios e quem os preenche é o `js/formulario.js` da frente 3.

**Files:**
- Modify: `front/home.html` (formulário do `#quoteModal`)
- Modify: `front/flex.html` (formulário do `#quoteModal`)

**Interfaces:**
- Consome: contrato §6.1 (campos), §6.2 (regras de recusa), `.qform`, `.qform__hp`
- Produz: um `<form id="quoteForm">` em cada página com exatamente os campos que o `enviar.php` da frente 3 espera receber

- [x] **Step 1: Trocar o honeypot e acrescentar os campos ocultos, nas duas páginas**

Em `front/home.html` e em `front/flex.html`, substitua

```html
        <!-- honeypot anti-spam: humanos não veem, não preencher -->
        <input type="text" name="_gotcha" class="qform__hp" tabindex="-1" autocomplete="off" aria-hidden="true" />
```

por

```html
        <!-- honeypot anti-spam (contrato 6.1: precisa chegar vazio). Humanos não veem. -->
        <input type="text" name="empresa" class="qform__hp" tabindex="-1" autocomplete="off" aria-hidden="true" />

        <!-- Ocultos do contrato 6.1. Nascem vazios de propósito: quem preenche é
             o js/formulario.js da frente 3, e o csrf é impresso pelo PHP na costura. -->
        <input type="hidden" name="pagina" value="" />
        <input type="hidden" name="referrer" value="" />
        <input type="hidden" name="utm_source" value="" />
        <input type="hidden" name="utm_medium" value="" />
        <input type="hidden" name="utm_campaign" value="" />
        <input type="hidden" name="utm_term" value="" />
        <input type="hidden" name="utm_content" value="" />
        <input type="hidden" name="ts" value="" />
        <input type="hidden" name="csrf" value="" />
```

Os `<input type="hidden">` não têm `offsetParent`, então o laço de foco do modal no `js/main.js` já os ignora e a ordem de tabulação não muda.

- [x] **Step 2: Conferir que os dois formulários batem com o contrato**

```bash
cd "E:/Clientes/Castello Madeiras/prototipo-site-castello/front"
for f in home.html flex.html; do
  echo "== $f"
  grep -o 'name="[a-z_]*"' "$f" | sort -u
done
```

Esperado nos dois arquivos, e nada além disto entre as tags do formulário: `nome`, `whatsapp`, `busca`, `modelo`, `cidade`, `mensagem`, `pagina`, `referrer`, `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`, `empresa`, `ts`, `csrf`. Se `_gotcha` ainda aparecer, o passo 1 não pegou nos dois arquivos.

- [x] **Step 3: Varredura final da home**

`browser_navigate` para `http://localhost:8000/front/home.html` e, para **cada** largura da lista 1440, 1180, 1024, 900, 768, 560, 390:

1. `browser_resize` para a largura, com altura 900.
2. `browser_evaluate` com `() => ({ doc: document.documentElement.scrollWidth, win: window.innerWidth })`. Esperado: `doc` menor ou igual a `win` em todas as sete larguras. Qualquer diferença positiva é rolagem horizontal e reprova.
3. `browser_take_screenshot` de página inteira em `f2-t10-home-<largura>.png`.

Depois, em 1440px:

4. `browser_console_messages`. Esperado: zero erros.
5. `browser_evaluate` com `() => [...document.querySelectorAll('img')].filter(i => !i.complete || i.naturalWidth === 0).map(i => i.src)`. Esperado: lista vazia. Qualquer item aqui é um `../` que ficou faltando.
6. `browser_evaluate` com `() => [...document.querySelectorAll('img')].filter(i => !i.alt).length`. Esperado: 0.
7. `browser_evaluate` com `() => [...document.querySelectorAll('h1,h2')].map(h => h.tagName + ' ' + h.textContent.trim().slice(0,40))`. Esperado: um `H1` só, o do hero, e nenhum título com "120 dias" fora da seção Casa Pronta.
8. `browser_evaluate` com `() => document.body.innerText.includes('pronta em até 120 dias')`. Esperado: `false`.
9. `browser_evaluate` com `() => [...document.querySelectorAll('a[href^="#"]')].map(a => a.getAttribute('href')).filter(h => h !== '#' && !document.querySelector(h))`. Esperado: lista vazia. Âncora apontando para o nada reprova.
10. `browser_press_key` `Tab` vinte vezes seguidas, com `browser_snapshot` a cada cinco. Esperado: o foco só entra em elementos visíveis, sempre com anel visível, e nunca cai dentro do drawer fechado nem do modal fechado.

- [x] **Step 4: Varredura final da página Flex**

`browser_navigate` para `http://localhost:8000/front/flex.html` e repita, para as mesmas sete larguras, os passos 1 a 3 do passo anterior, salvando em `f2-t10-flex-<largura>.png`.

Depois, em 1440px:

4. `browser_console_messages`. Esperado: zero erros.
5. `browser_evaluate` com `() => [...document.querySelectorAll('img')].filter(i => !i.complete || i.naturalWidth === 0).map(i => i.src)`. Esperado: lista vazia.
6. `browser_evaluate` com `() => [...document.querySelectorAll('img')].filter(i => !i.alt).length`. Esperado: 0.
7. `browser_evaluate` com `() => [...document.querySelectorAll('h1,h2')].map(h => h.tagName)`. Esperado: exatamente um `H1`, o do `.pagehero`.
8. `browser_evaluate` com `() => [...document.querySelectorAll('a[href^="#"]')].map(a => a.getAttribute('href')).filter(h => h !== '#' && !document.querySelector(h))`. Esperado: lista vazia.
9. `browser_evaluate` com `() => [...document.querySelectorAll('[id]')].map(e => e.id).filter((v,i,a) => a.indexOf(v) !== i)`. Esperado: lista vazia. `id` duplicado quebra o `js/main.js` e o leitor de tela.
10. `browser_evaluate` com `() => document.body.innerText.includes('R$')`. Esperado: `false` na página Flex, porque a tabela ainda não está fechada.
11. `browser_press_key` `Tab` vinte vezes, com `browser_snapshot` a cada cinco. Mesma expectativa da home.

- [x] **Step 5: Ida e volta entre as duas páginas**

1. Em `home.html`, `browser_click` no link "Castelo Flex" da nav. Esperado: chega em `flex.html` com o hero da Flex.
2. Em `flex.html`, `browser_click` no logo da nav. Esperado: volta para `home.html`.
3. Em `flex.html`, `browser_click` no link "Casa Pronta" da nav. Esperado: chega em `home.html` já rolada até `#casa-pronta`.
4. Em 390px, faça o mesmo caminho pelo drawer nas duas páginas. Esperado: o drawer fecha ao clicar e a navegação acontece.
5. Nas duas páginas, role até passar do primeiro dobra e confira que o botão flutuante `#wppFloat` aparece. `browser_click` nele. Esperado: o modal de orçamento abre.

- [x] **Step 6: Commit**

```bash
cd "E:/Clientes/Castello Madeiras/prototipo-site-castello"
git add front/home.html front/flex.html
git commit -F- <<'MSG'
feat(front): formulario das duas paginas alinhado ao contrato 6.1

Troca o honeypot _gotcha por empresa e acrescenta os ocultos pagina,
referrer, utm_*, ts e csrf, todos vazios, para o js/formulario.js da
frente 3 preencher e o enviar.php ler. Fecha a varredura de responsivo,
contraste e teclado nas duas paginas.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
MSG
```

---

## Cobertura da spec

| Requisito | Onde |
|---|---|
| 7.1.2 remover "pronta em até 120 dias" do hero (`index.html:102`) | Tarefa 1, passo 7, com checagem automática na Tarefa 10 |
| 7.1.4 bloco novo de Modalidades depois da prova social | Tarefa 2 |
| 7.1.5 seção de modelos renomeada para Casa Pronta com o prazo dentro | Tarefa 3 |
| 7.1.6 seção nova Castelo Flex com resumo, vídeo, 45 dias e chamada para a página Flex | Tarefa 4 |
| 7.1.7 a 7.1.14 resto da home preservado | Tarefas 1 e 2 (só a ordem de Vantagens muda) |
| 7.2.1 nav e rodapé compartilhados | Tarefa 5 |
| 7.2.2 hero próprio da Flex | Tarefa 5 |
| 7.2.3 o que é a Flex, com vídeo explicativo | Tarefa 6 |
| 7.2.4 passo a passo com o que a Castello entrega e onde a entrega termina | Tarefa 7 |
| 7.2.5 catálogo das casas Flex | Tarefa 8 |
| 7.2.6 prazo de 45 dias e diferenciais | Tarefa 8 |
| 7.2.7 FAQ da Flex | Tarefa 9 |
| 7.2.8 formulário | Tarefas 5 e 10, pelo modal compartilhado (ver divergência 1) |
| 7.2.9 rodapé | Tarefa 5 |
| 9.1 frente 2 escreve só `css/`, `js/`, `front/home.html`, `front/flex.html` | Global Constraints |
| Contrato §6.1 campos do formulário | Tarefa 10 |
| Contrato §2.3 conjunto fechado de ícones do FAQ | Tarefa 9 |

## Onde o contrato ou a spec pareceram errados ou insuficientes

Cinco pontos. Nenhum bloqueia a frente 2, mas os cinco precisam de decisão antes da costura.

1. **Spec 7.2 item 8 pede "Formulário" na página Flex, e o site tem um formulário só, dentro de um modal.** Colocar um segundo `<form>` inline na página significaria `id` duplicado (`q-nome`, `q-busca`, `quoteForm`), dois laços de foco e dois contratos de envio. Este plano entrega o item como a faixa `#orcamento` que abre o mesmo `#quoteModal`, com as opções da Flex no select de modelo. Se o cliente ou a spec quiserem mesmo um formulário inline, isso muda o contrato §6 e a frente 3, e precisa de uma decisão antes, não depois.

2. **O contrato dá `js/main.js` para a frente 2 e proíbe a frente 2 de escrever lógica de formulário, mas a lógica de formulário já está dentro do `js/main.js` hoje** (modal, máscara, honeypot, time-trap, `FORM_ENDPOINT`, linhas 683 a 860). O contrato §7 diz que a frente 3 escreve `js/formulario.js` e não toca em `js/main.js`. Ninguém está autorizado a tirar a lógica velha de lá. Este plano não toca em JS nenhum e deixa o problema visível: **a costura tem que decidir quem extrai o bloco do modal do `js/main.js` para o `js/formulario.js`**, senão as duas implementações vão coexistir e brigar pelo `submit`.

3. **O honeypot muda de nome e a checagem client-side vira letra morta.** O contrato §6.1 exige `empresa`; o `js/main.js` lê `_gotcha` na linha 810. Depois da Tarefa 10, essa linha passa a ler um campo que não existe e a checagem sempre passa. A defesa real é o `enviar.php` (contrato §6.2), então nada fica desprotegido em produção, mas **alguém precisa trocar `'_gotcha'` por `'empresa'` no `js/main.js`, ou apagar o bloco inteiro ao mover a lógica para `js/formulario.js`**. Item de costura, registrado aqui porque a frente 2 não pode resolver.

4. **O contrato §2.2 não tem chaves de `blocos` para a página Flex inteira.** Existem `flex_titulo`, `flex_texto`, `flex_prazo`, `flex_video`, `flex_video_poster`, que cobrem a seção da Flex na home. A página Flex tem, além disso, hero (eyebrow, título, lead), o texto de "o que é", o cabeçalho do bloco "o que fica por sua conta", a nota do catálogo e a copy da faixa de orçamento. Ou a frente 1 cria essas chaves na migração, ou esses textos ficam fixos no `flex.php` e o cliente não consegue editá-los pelo painel, o que contraria o espírito da seção 8 da spec. **Sugestão de chaves a acrescentar:** `flexpg_hero_titulo`, `flexpg_hero_texto`, `flexpg_oque_titulo`, `flexpg_oque_texto`, `flexpg_depois_titulo`, `flexpg_depois_texto`, `flexpg_catalogo_nota`, `flexpg_cta_titulo`, `flexpg_cta_texto`.

5. **A spec 7.1 manda tirar o prazo do topo mas não fala do `<title>`,** que hoje diz "Sua casa pronta em até 120 dias" e passa a contradizer os 45 dias da Flex logo abaixo. Este plano troca o `<title>` e a `<meta description>` por conta própria, com os dois prazos nomeados por modalidade. Se o cliente tiver preferência de SEO para o título da home, é agora que ele decide, porque depois isso vira `blocos` e migração.

**Ponto menor, resolvido dentro do plano:** o `.model__flag` e a faixa `.proof` que já estão no ar usam texto branco sobre `--red`, que mede 4,33:1 e reprova o AA para texto normal, apesar de a skill afirmar que passa. Nada foi mexido no que já existe (não é escopo desta frente), mas todo componente novo usa `--red-deep` para texto branco pequeno. Vale um item de QA na costura para revisar os componentes antigos.
