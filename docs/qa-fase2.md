# QA da Fase 2 (costura e produção)

Data: 2026-09-10. Ambiente local: `php -S localhost:8000 -t public_html`. Servidor de teste: `https://castello.tohospedando.com.br` (LiteSpeed, PHP 8.5.9, SQLite 3.26), alcançado com `--resolve castello.tohospedando.com.br:443:200.11.120.114` porque o DNS ainda aponta para um CDN errado.

## 1. Auditoria de navegador do site (web-qa-reviewer)

Agente `web-qa-reviewer` sobre `http://localhost:8000/` e `/flex.php`, Chrome via Playwright, 1440x900 e 390x844. Relatório na íntegra resumido abaixo, com o que foi feito em cada item.

### Crítico

Nenhum.

### Alto

| # | Achado | O que foi feito |
|---|---|---|
| A1 | Peso de mídia: home com ~24 MB rolada até o fim. Vídeo do hero de 8,9 MB baixado inteiro também em 390 px (`video-mobile.mp4` de 9 MB existia sem uso); fotos de modelos, portfólio e passos em PNG de 700 KB a 2,2 MB (1086x1448) exibidas a 258 a 607 px; hero da Flex com `casa7.png` de 2,2 MB sem `fetchpriority`. | **Corrigido.** As 8 fotos viraram WebP a 1400 px (147 a 353 KB cada, de 9 MB para 2 MB no total); `passo-5.png` (918 KB) virou JPG de 86 KB; poster do hero virou `video-hero/poster.jpg` (228 KB) em vez de PNG de 695 KB; hero da Flex usa `casa7.webp` com `fetchpriority="high"`. O hero ganhou `video-hero/video-mobile.mp4` reencodado (960 px, all-intra, 2,5 MB) escolhido pelo `main.js` abaixo de 760 px, com `preload="metadata"` na marcação para a troca acontecer antes do download. Os PNG originais saíram do `public_html` (ficam no histórico do git). No servidor, um script temporário (`otimizar-midia.php`, protegido pela chave de migração, apagado depois) trocou os caminhos gravados no banco e apagou os PNG de `uploads/`. Não feito: `srcset` com dois tamanhos (o `lib/upload.php` do painel teria que gerar variantes; fica como melhoria). |
| A2 | Reels `insta-06.mp4` (7,3 MB) e `insta-07.mp4` (9,1 MB) no rail do Instagram. | **Parcialmente corrigido.** Os dois têm 79 s e 99 s de duração a 635 kbps; a sugestão de "~2 MB" não cabe sem destruir a imagem. Reencodados a 480 px, CRF 30, áudio mono 64 kbps: 4,0 MB e 6,7 MB (menos 46 % e 27 %). `preload="none"` continua, então só baixam ao toque. Recomendação ao cliente: cortar esses dois reels para 30 a 40 s. |

### Médio

| # | Achado | O que foi feito |
|---|---|---|
| M3 | Depois de um envio com sucesso, "Voltar e revisar os dados" devolvia o botão habilitado com o texto "Enviando..." e permitia reenviar, criando lead duplicado. | **Corrigido** em `js/formulario.js`: `restaurar(rotulo)` antes de `mostrarDone(true, ...)`. |
| M4 | Contraste abaixo de AA em texto pequeno sobre vermelho: `.proof__label` e `.model__flag` sobre `--red` (4,33:1); `.cta-band p` e `.wpp-float__label` sobre `--red-bright` (4,04:1); `.faq__foot-link` (3,53:1). | **Corrigido em parte**, ver seção 2: `.proof`, `.model__flag` e `.facet--red` passam a `--red-deep` (6,92:1); `.cta-band` vira gradiente `--red-deep` a `#8E1015`; `.faq__foot-link` usa `--red-deep`. **Pendente de decisão de design:** `.btn--primary` (todos os CTAs, 15 px branco sobre gradiente `--red-bright` a `--red-deep`) mede 4,04:1 na ponta clara; passar os botões para `--red-deep` muda a cara da marca, então fica para o designer decidir (ou subir o texto dos botões para 18,7 px bold, limiar de texto grande). |
| M5 | `og:image` retrato de 696 KB e 2,2 MB, sem dimensões, sem `og:site_name`/`twitter:card`; WhatsApp costuma não renderizar acima de ~600 KB. | **Corrigido:** `images/og-home.jpg` (163 KB) e `images/og-flex.jpg` (119 KB) em 1200x630, com `og:image:width/height`, `og:site_name` e `twitter:card`. |
| M6 | Conteúdo abaixo do hero depende de JS (`.reveal{opacity:0}` até o `main.js` marcar `.is-in`). | **Não corrigido.** Sugestão registrada: classe `no-js` no `<html>` removida por script inline e `.no-js .reveal{opacity:1}`. Baixo risco real (JS quebrado derruba também o hero e o menu). |
| M7 | Pontos do carrossel de vantagens com 9x9 px de área de toque. | **Não corrigido.** Sugestão: `min-width/min-height: 24px` no botão mantendo o ponto visual. |

### Baixo

| # | Achado | O que foi feito |
|---|---|---|
| B8 | Aviso `<link rel=preload> uses an unsupported 'as' value`. | **Corrigido:** tag removida; o `<video>` já busca o arquivo. |
| B9 | Erro de validação não limpava no campo Nome; nenhum campo recebia `aria-invalid`. | **Corrigido:** `aria-invalid` em `marcarErros`/`limparErros`; `input` no Nome limpa o erro. |
| B10 | Sem `<main>`, sem skip link, `h4` no rodapé sem `h3` antes. | Não corrigido (muda a marcação de referência das duas páginas; fica para a próxima rodada). |
| B11 | FAQ só com ícones nas abas, pergunta escondida atrás de `title`/`aria-label`. | Não corrigido; decisão de design da frente 2. |
| B12 | Grupo "Modelo de interesse" nasce visível na Flex e oculto na home. | Não corrigido; comportamento desenhado pela frente 2 (na Flex o modelo é a pergunta principal). Registrado. |
| B13 | Sem `robots.txt`, `sitemap.xml` e JSON-LD `LocalBusiness`. | **Parcialmente corrigido:** `robots.txt` (bloqueia painel, enviar, csrf, reenviar) e `sitemap.xml` criados, ambos marcados para trocar o domínio no deploy final. JSON-LD fica pendente. |
| B14 | `index.php#casa-pronta` gera URL variante de `/`. | Não corrigido; canonical resolve. |
| B15 | Arquivos pesados duplicados servidos publicamente (`*.original.mp4`, `videos-instagram/web/*` duplicando `uploads/videos/*`, `fotos-casas/*` duplicando `uploads/`). | **Parcialmente:** os `*.original.mp4` são ignorados pelo git e nunca sobem no deploy (só existem na máquina local); `video-mobile.mp4` passou a ser usado; as fontes de migração (`fotos-casas/`, `videos-instagram/web/`, `passos/`) continuam no servidor porque o `migrar.php` precisa delas numa reinstalação. |
| B16 | `lib/schema.sql` e `lib/*.php` acessíveis no `php -S` local. | Conferido no servidor: `lib/` e `partials/` inteiros respondem 403 (seção 3.7). |
| B17 | Cookie de sessão `castello_painel` emitido ao visitante que abre o modal. | Não alterado; decisão do contrato 6.1 (sessão única). Registrado. |
| B18 | Textos de 9,9 px (`.nav__tag`, `.hero__status-label`) e eyebrows de 11,2 px. | Não corrigido; decorativos. |
| B19 | Posters dos 8 reels (~520 KB) baixam no carregamento. | Não corrigido; melhoria futura (posters menores). |

### Passou

Console limpo (após a remoção do preload: 0 avisos); nenhum 404 nas ~45 URLs; `enviar.php` GET 405, `reenviar.php` 403; âncoras e links cruzados home/Flex; sem rolagem horizontal; gaveta mobile com backdrop, X, Esc e `aria-expanded`; modal aberto por todos os `data-quote-open` (12 na home, 9 na Flex), foco inicial, token CSRF só na abertura, ocultos e UTM em `sessionStorage`, pré-seleção pelo card; validação com `role="status"` e foco no primeiro inválido; envio real gravando lead com `pagina` correta; CTAs de WhatsApp, telefone, Instagram e Facebook; `lang`, title, description, canonical, og, um h1 por página, `alt` em 100 % das imagens; `:focus-visible`, labels, `aria-label` em ícones, FAQ com `role=tab` e setas, `prefers-reduced-motion`; `loading="lazy"`, `preload="none"` nos reels, preconnect das fontes, expires e deflate no `.htaccess`.

### Não auditado pelo agente

Percurso completo por Tab em 1440, lightbox dos reels, carrossel de avaliações e scrollytelling por interação, Lighthouse/CWV, entrega de e-mail e CRM.

## 1b. Auditoria do painel (web-qa-reviewer)

Agente sobre `http://localhost:8000/painel/`, logado, todas as telas, 1440x900 e 390x844. Itens de teste criados pelo agente ficaram desativados no banco local (modelos #8, faq #13, avaliacoes #15).

### Crítico

Nenhum: sem erro de console, link quebrado, 404 nem rolagem horizontal em nenhuma tela.

### Alto

| # | Achado | O que foi feito |
|---|---|---|
| P1 | Limite de upload do PHP abaixo do que o painel promete (vídeo até 30 MB, imagem até 5 MB): no `php -S` local, `upload_max_filesize=2M`/`post_max_size=8M`; POST acima do limite chega com `$_POST` vazio, o CSRF falha e a mensagem é "A sessão expirou". | **Sondado no servidor:** `upload_max_filesize=64M`, `post_max_size=64M`, `memory_limit=1G`, `max_execution_time=60`, ou seja, o problema era só do ambiente local. Mesmo assim: `public_html/.user.ini` com `upload_max_filesize=32M` e `post_max_size=40M` (coerente com o painel em qualquer host que leia `.user.ini`), e `acoes/salvar.php` compara `CONTENT_LENGTH` com `post_max_size` antes do CSRF e devolve "O arquivo passou do limite do servidor (N MB)". |
| P2 | Reordenar por arrastar não chega ao topo em listas mais altas que a tela no celular (Avaliações com 14 itens, Vídeos com 11): sem auto-scroll. | **Corrigido** em `painel/assets/painel.js`: perto de 70 px da borda de cima ou de baixo a página rola sozinha durante o arrasto (`requestAnimationFrame`), e para ao soltar. Alça `≡` ganhou área de toque de 44x44 px. |

### Médio

| # | Achado | O que foi feito |
|---|---|---|
| P3 | Item novo aberto pelo filtro Flex (ou FAQ flex, Passos flex) vem com "Casa Pronta"/"Home" selecionado. | **Corrigido** em `telas/form.php`: o filtro da lista semeia a coluna de modalidade/contexto no item novo. Teste em `80-painel.php`. |
| P4 | Erro de validação perde o filtro; Cancelar leva à lista errada. | **Corrigido** em `acoes/salvar.php`: `&filtro=` no retorno ao formulário. |
| P5 | Estrelas sem validação: aceita 9; vazio vira 0 (site mostra 1 estrela). | **Corrigido**: `min 1`, `max 5`, padrão 5 na definição; `painel_erros` valida faixa; input com `min`/`max`. Teste em `80-painel.php`. |
| P6 | Tag "desativado" vira barra cinza de largura total. | **Corrigido** em `painel.css` (`.p-dados .p-tag` inline-block). |
| P7 | Ícones do FAQ sem nome acessível e sem foco visível; sem `fieldset`. | Não corrigido; registrado. |
| P8 | Áreas de toque abaixo de 24 px ("Sair", alça, checkboxes). | **Parcial**: alça com 44 px. "Sair" e checkboxes ficam. |
| P9 | Menu de abas corta em 390 px sem indicar mais telas; filtro ativo com o mesmo estilo do botão de ação. | Não corrigido; registrado. |
| P10 | Textos: campos longos em `input`; vídeo/capa da Flex digitados à mão; asteriscos de destaque sem ajuda. | Não corrigido no painel; a marcação `*texto*` está explicada em `docs/guia-do-painel.md`, seção 7. |
| P11 | Item novo nasce ativo. | Não alterado (decisão da spec: sem rascunho; existe Desativar). |

### Baixo

P12 contraste de 4,33:1 nos botões e abas do painel; P13 `opacity:.55` no item inativo; P14 concordância "Pergunta adicionado" (**corrigido**: "Adicionado: pergunta."); P15 tela de login sem `role="alert"`, sem preservar o login, logo pequena; P16 sem `aria-invalid` nos campos; P17 alturas diferentes entre `a.p-btn` e `button.p-btn`; P18 recado de sucesso/erro vindo da query string (escapado, sem XSS; permite link de engenharia social); P19 vídeos listados pelo caminho do arquivo, sem marcar os 8 da home; P20 resumo com rótulo vazio ("Preço:" na Flex); P21 passos Flex sem imagem (conteúdo pendente); P22 e-mail e URL como `type="text"`; P23 desativar sem confirmação; P24 aviso verbose do Chrome em Trocar senha. Registrados para a próxima rodada.

### Passou

Login, sair e bloqueio; rotas de ação exigem sessão (302, `ordem.php` 401 JSON); cookie `HttpOnly; SameSite=Lax`, `Cache-Control: no-store`; zero erros de console e zero 404 em todas as telas; sem rolagem horizontal; `lang`, um `h1` por tela, `noindex`, `label for` em todos os inputs; inputs de 16 px (sem zoom no iOS); criar, editar, desativar, reativar com `role="status"` e contador "N itens, M aparecendo"; reordenar com mouse persiste; validação server-side com rascunho preservado e `role="alert"`; Configurações salvam sem alterar; Trocar senha rejeita senha atual errada; Backup mostra a mensagem certa sem ZipArchive.

## 2. Contraste de branco sobre vermelho

Medido de novo (fórmula WCAG 2.x sobre os tokens de `css/style.css`):

| Par | Razão | AA texto normal (4,5) | AA texto grande (3,0) |
|---|---|---|---|
| branco sobre `--red` #ED2128 | 4,33:1 | reprova | passa |
| branco sobre `--red-bright` #F12F37 | 4,04:1 | reprova | passa |
| branco sobre `--red-deep` #B3141B | 6,92:1 | passa | passa |
| `--red` sobre `--bone` #FAF7F2 | 4,05:1 | reprova | passa |
| `--red-deep` sobre `--bone` | 6,48:1 | passa | passa |

Confirmada a medição da frente 2. A skill `castello-design` afirmava "branco sobre `--red` (ok ≥ 4.5)"; a afirmação foi corrigida em `references/DESIGN.md` com os números acima e a regra: texto branco em fundo vermelho usa `--red-deep`; `--red` e `--red-bright` ficam para superfícies sem texto ou texto grande e bold.

Aplicado em `css/style.css`: `.proof`, `.model__flag` e `.facet--red` com `--red-deep`; `.cta-band` com gradiente `--red-deep` a `#8E1015`; `.faq__foot-link` com `--red-deep`. Remedido depois da troca: branco sobre `--red-deep` 6,92:1 nos dois componentes antigos. `.btn--primary` continua com o gradiente da marca (4,04:1 na ponta clara), pendente de decisão de design.

## 3. Verificação de segurança no servidor

Todas as requisições abaixo foram feitas com `--resolve` para o IP 200.11.120.114 e `-k` (ver seção 3.6).

### 3.1 Banco e segredos fora do alcance do navegador

| URL | Esperado | Obtido |
|---|---|---|
| `https://castello.tohospedando.com.br/config/castello.db` | 404 ou 403 | 404 |
| `https://castello.tohospedando.com.br/../config/castello.db` | 404 ou 403 | 404 |
| `https://tohospedando.com.br/castello-config/castello.db` (domínio pai, onde a pasta realmente mora) | 404 ou 403 | 404 |
| `https://tohospedando.com.br/castello-config/segredos.php` | 404 ou 403 | 404 |
| `https://castello.tohospedando.com.br/lib/schema.sql` | 403 | 403 |
| `https://castello.tohospedando.com.br/lib/caminho-config.php` | 403 | 403 |
| `https://castello.tohospedando.com.br/.htaccess` | 403 | 403 |
| `https://castello.tohospedando.com.br/lib/db.php` | não servir código | 200 com corpo vazio (o PHP executou sem imprimir). Corrigido: `lib/.htaccess` e `partials/.htaccess` com `Require all denied` (commit `fix(seguranca)`), conferido de novo após o redeploy na seção 3.7. |

A pasta `castello-config` fica um nível acima do `public_html` do domínio pai, criada pelo `db()` na primeira execução, como o contrato 3.2 previa.

### 3.2 A pasta de uploads não executa script

| Teste | Esperado | Obtido |
|---|---|---|
| Painel, Portfólio, subir `sonda-php.jpg` (arquivo PHP puro renomeado) | recusado por `finfo_file` | recusado: "Este arquivo não é uma imagem. Envie em JPG, PNG ou WEBP." Nada gravado. |
| Painel, subir `sonda-falsa.jpg` (cabeçalho GIF com PHP dentro) | recusado | recusado com a mesma mensagem (GIF não está na lista jpeg/png/webp). Nada gravado. |
| `uploads/sonda-exec.php` colocado por FTP e aberto no navegador | baixar ou mostrar texto, nunca executar | 403 Forbidden (o `uploads/.htaccess` nega `.php`). Arquivo apagado do servidor em seguida. |

### 3.3 Trava de força bruta

| Tentativa | Obtido |
|---|---|
| 1 a 4 com senha errada | "Login ou senha incorretos." |
| 5 com senha errada | "Muitas tentativas erradas. Espere 15 minutos e tente de novo." |
| 6 com senha errada | mesma mensagem de bloqueio |
| senha certa durante o bloqueio | não entra |

O desbloqueio automático em 15 minutos é coberto pelo teste `60-auth.php` (bloqueio vencido limpa o contador); no servidor foi conferido só o bloqueio, para não segurar a verificação por um quarto de hora.

### 3.4 Backup

Baixado pelo painel (`acoes/backup.php`): `application/zip`, 46,8 MB, 40 entradas. Aberto localmente com Python `zipfile`: `castello.db` (94 KB) presente, 39 arquivos em `uploads/` (fotos de modelos, portfólio, passos, os 11 vídeos e posters), `testzip()` sem erro. O `ZipArchive` só existe no servidor, então este é o único ambiente em que o teste vale.

### 3.5 HTTPS forçado

`http://castello.tohospedando.com.br/flex.php` (porta 80, `--resolve`) responde `301` para `https://castello.tohospedando.com.br/flex.php`.

### 3.6 Certificado

O certificado servido é **autoassinado** (subject e issuer `CN=castello.tohospedando.com.br`, emitido em 2026-09-10, SAN com e sem `www`). O Let's Encrypt da hospedagem não consegue validar o subdomínio enquanto o DNS apontar para o CDN errado. Consequência: navegador mostra aviso de certificado até o DNS ser corrigido. Pendência de infraestrutura, fora do código.

### 3.7 Depois do redeploy com as correções

| URL | Esperado | Obtido |
|---|---|---|
| `/lib/db.php`, `/lib/conteudo.php` | 403 | 403 |
| `/partials/nav.php`, `/partials/modal.php` | 403 | 403 |
| `/`, `/flex.php`, `/csrf.php` | continuam 200 | 200, 200, 200 (JSON com token) |

Depois do redeploy das correções do QA (mídia, contraste, painel): `/`, `/flex.php` e `/painel/` continuam 200; `robots.txt` e `sitemap.xml` 200; `fotos-casas/*.png` 404 (apagados); `uploads/modelos/casa4.webp` 200 (217 KB); `video-hero/video-mobile.mp4` 200 (2,5 MB). Scripts temporários (`otimizar-midia.php`, sonda de `ini_get`) e `migrar.php` apagados; `otimizar-midia.php` e `migrar.php` respondem 404.

## 4. Instalação e painel no servidor

- `migrar.php` chamado pela web com o banco vazio: 7 modelos, 6 portfólio, 14 avaliações, 11 vídeos, 12 FAQ, 10 passos, 21 blocos. Usuário `castello` criado, chave de migração gravada em `segredos.php`. `migrar.php` apagado do servidor por FTP em seguida (o arquivo continua no repositório).
- `config.email_aviso` trocado pelo painel para `freelainhome@gmail.com` **antes** de qualquer lead de teste.
- Lead de teste pelo `enviar.php` do servidor (token do `csrf.php` com cookie): `{"ok":true,"id":1}`. Mesmo token sem o cookie: `419`. Honeypot preenchido: `{"ok":true,"id":0}` e nada gravado.
- Login no painel com a senha gerada e contagem das listas (links `editar=` por tela): Modelos 4 (Casa Pronta) e 3 (Flex), Portfólio 6, Avaliações 14, Vídeos 11, FAQ 7 (geral) e 5 (Flex), Passos 5 (Casa Pronta) e 5 (Flex).
- Home no servidor: título novo, 4 modelos, 14 avaliações, 8 vídeos (limite da config), canonical e og:image absolutos, sem `noindex`, mídia servida de `uploads/`.

## 5. Pendências fora do código

- DNS de `castello.tohospedando.com.br` apontando para o CDN errado (quebra o certificado e o acesso normal ao subdomínio).
- Identificadores do Google Analytics e do pixel do Meta (Task 9 do plano, não executada).
- Credenciais do CRM (`crm_ativo` segue desligado; leads gravados e enviados por e-mail).
- Domínio definitivo: trocar `CASTELLO_URL` em `lib/conteudo.php` no deploy final.
