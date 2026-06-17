# Castello — Redesign editorial do site (Editorial × Faceta)

**Data:** 2026-06-17
**Tipo:** Redesign da landing única (protótipo para reunião com o cliente)
**Stack:** HTML + CSS + JS vanilla, self-contained, sem build step (mantém `index.html` + `css/style.css` + `js/main.js`)
**Design skill base:** `castello-design` (este redesign empurra além dela; ver "Backport para a skill")

---

## Por que estamos refazendo

O build atual é tecnicamente correto (float header, parallax, reveal, count-up, WhatsApp flutuante) mas **tímido**: toda seção usa a mesma fôrma `eyebrow → título centrado → grid de cards auto-fit`, o movimento é só `fade-up` genérico, o fundo é `--bone` chapado e a tipografia nunca explode. Lê como "site de IA" porque escolhe sempre a opção segura. O objetivo do redesign é **personalidade e momentos memoráveis**, sem perder a solidez da marca.

## A grande ideia

**Editorial cinematográfico (espinha) × Arquitetura da Faceta (assinatura).**

- **Editorial (A):** fotografia real em escala, tipografia Sora gigante que invade a imagem, números explodidos, layouts assimétricos que quebram o ritmo, e um momento de **scrollytelling sticky** ("do terreno à chave").
- **Faceta (B):** o **telhado vermelho facetado do logo** vira fio condutor geométrico — recorte angular (`clip-path`) nas transições de seção, topo do card de modelo em destaque, faixa de CTA e um destaque da galeria. Calibragem **equilibrada**: aparece em momentos-chave, não em toda seção.
- **Vermelho continua tempero** (CTAs, números, acentos, faceta), nunca fundo. O calor vem da fotografia sobre canvas `--bone`.

## Escopo

**Dentro:** reescrita de `index.html`, `css/style.css`, `js/main.js` mantendo o conteúdo real (modelos, preços, 5,0★/56, +18.000 m², reviews reais, contatos, mapa, WhatsApp). Mesmas seções do CLAUDE.md, redesenhadas com os momentos abaixo.

**Fora (segue como pendência de produção, não muda aqui):** og:image absoluto + canonical, remover noindex, analytics/pixel, vídeo real no hero (hero usa foto por enquanto, já preparado pra `<video>` trocar a `<img>`), confirmação de nomes/preços com o cliente.

**Assets disponíveis:** 8 fotos reais (`fotos-casas/Screenshot_*.png`), logos (h/v, colorido/branco), ícone, 7 prints de avaliações. Sem vídeo.

---

## Sistema de design (deltas sobre o atual)

Mantém todos os tokens da `castello-design` (cores, Sora × Inter, raios, `--ease`). Adições:

- **Escala tipográfica editorial:** títulos de seção podem chegar a `clamp(2.6rem, 6vw, 4.4rem)`; headline do hero `clamp(2.6rem, 6.2vw, 5.2rem)`. Peso 800, `letter-spacing -.03em`, `line-height .98–1.05`.
- **Atmosfera:** camada de **grão sutil** (SVG noise via `background-image` data-URI, `opacity ~.03`, `mix-blend-mode`) sobre seções claras pra tirar o "chapado". Profundidade com sombras longas já existentes.
- **Faceta — técnica:** silhueta de telhado via `clip-path: polygon(...)` em pseudo-elementos de borda de seção (zigue-zague anguloso assimétrico, não simétrico). Tokens novos:
  - `--facet-hero`, `--facet-cta`, `--facet-card` (polígonos reutilizáveis documentados no CSS).
- **Marcador editorial:** eyebrow com traço vermelho curto antes do texto (`::before` 26px).

## Seções e momentos-assinatura

1. **Hero cinematográfico** — foto full-bleed, nav glass (logo branco → escuro ao rolar, já existe), headline gigante com palavra em vermelho, lead, CTA WhatsApp + ghost, faixa de prova explodida. Parallax + fade no scroll (já existe). **Faceta:** transição facetada (telhado) pra próxima seção. Preparado pra trocar `<img>` por `<video>`.
2. **Prova social** — 5,0★ · 56 avaliações · +18.000 m² · 12 anos · 90–120 dias, com **count-up** ao entrar (já existe; reaproveitar). Layout em faixa, não cards.
3. **Por que casa de madeira** — **layout editorial assimétrico**: foto sangrando até a borda de um lado + bloco de texto grande e 4–6 diferenciais (conforto térmico, prego galvanizado, 100% personalizável, patrimônio, obra rápida, zero dor de cabeça) do outro. Quebra o ritmo de grid.
4. **Modelos com preços** — 4 cards (Compacta/Conforto/Família/Ampla), **preço como herói** (Sora gigante, R$ menor, "a partir de" em caixa-alta). Card "Família" em destaque com **topo facetado**. Nota de rodapé sobre o que "Completa/chave na mão" inclui.
5. **Portfólio editorial** — galeria **assimétrica** (uma foto dominante + menores), legendas que sobem no hover, **máscara facetada** num destaque. Mobile: scroll-snap horizontal.
6. **Do terreno à chave (scrollytelling sticky)** — **o momento principal.** Uma foto/coluna fica **fixa (`position: sticky`)** enquanto os ~5 passos da obra (terreno → projeto → fundação → montagem → chave) rolam ao lado e se destacam um a um. Fallback: vira lista vertical simples em mobile e com `prefers-reduced-motion`.
7. **Depoimentos** — reviews **reais** do Google em cards quentes, nota + estrela dourada visíveis, cabeçalho com o 5,0 grande.
8. **CTA final** — **faixa vermelha com recorte de telhado (faceta)** nas bordas, headline forte, WhatsApp como ação única. Contato/horário/mapa logo abaixo (footer).
9. **Footer** — logo, marca, colunas de navegação/contato, mapa embed, Instagram. (Mantém estrutura atual.)
10. **WhatsApp flutuante** — aparece após o hero, pulse suave (já existe; manter).

## Vocabulário de movimento

Mantém `--ease` expo-out e `prefers-reduced-motion`. Reaproveita reveal+stagger, parallax do hero, count-up, pulse do WhatsApp. **Novos:**

- **Reveal com mais caráter:** além do fade-up, variações por seção (ex.: máscara que abre, número que conta, foto que dá leve `scale`).
- **Scrollytelling sticky** (momento 6): JS observando progresso de scroll na seção pra destacar o passo ativo; CSS `position: sticky` na coluna da foto.
- Tudo animando só `transform`/`opacity`. `will-change` só durante animação ativa. Sem libs novas — vanilla + IntersectionObserver + rAF (consistente com o build atual).

## Responsivo, acessibilidade e performance

- Mobile-first nos breakpoints já usados (980/760/560). Faceta e scrollytelling **degradam pra layout simples** no mobile.
- `prefers-reduced-motion`: desliga parallax, scrub e count-up (mostra valor final), mantém fades curtos ou nada.
- Foco de teclado visível (já existe), drawer mobile acessível (X + backdrop + Esc — já existe), alt em imagens, contraste mantido sobre fotos via overlay.
- Fotos são grandes (~1.5MB cada PNG). **Otimizar:** converter as fotos usadas pra `.webp` e dimensionar; `loading="lazy"` fora do hero; `fetchpriority="high"` na imagem do hero.

## Backport para a skill (depois, não agora)

Decisões deste redesign que devem virar referência na `castello-design` quando der certo: arquivos `LAYOUT.md` (assimetria editorial, scrollytelling sticky), `INTERACTIONS.md`, `INSPIRATION.md`; e uma regra de ouro nova empurrando pra **um momento-assinatura por página** e pro **sistema da faceta** (tokens `--facet-*`). Hoje a skill só tem DESIGN/COMPONENTS/ANIMATIONS e regras de contenção — esse é o gargalo que gerou o build tímido.

## Critérios de sucesso

- Nenhuma seção repete a fôrma "título centrado + grid de cards" mais de duas vezes seguidas.
- Existe pelo menos **um momento que faz parar** (scrollytelling do terreno à chave).
- A faceta aparece como assinatura coerente em ≥3 pontos (hero, card destaque, CTA).
- Mantém QA aprovado: sem erros de console, responsivo, CTAs WhatsApp funcionando, foco de teclado, `prefers-reduced-motion`.
- Roda local em `python -m http.server` sem build step.
