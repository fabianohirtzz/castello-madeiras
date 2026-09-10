# Castello Fase 2 — Painel, CRM e reestruturação da home

**Data:** 2026-09-09
**Status:** Design aprovado. Pronto para virar plano de implementação.
**Origem:** reunião de 2026-09-01 (Castello Madeiras) + spec de 2026-06-18 (painel admin), que este documento substitui.

---

## 1. O que a reunião decidiu

Extraído da ata de 2026-09-01, apenas o que é da Castello.

**Leads**
- O formulário passa a ser o caminho oficial de captação. O WhatsApp continua como botão de contato, mas deixa de ser o destino do formulário.
- Motivo declarado: rastreabilidade e qualificação. WhatsApp é difícil de rastrear.
- O lead cai no CRM que a Castello está implantando, que centralizará site e redes sociais.
- A Castello vai usar primeiro só o chat do CRM; o funil completo fica para depois.

**Home**
- Seções destacadas e separadas para Castelo Flex e Casa Pronta, com distinção clara entre as modalidades.
- Remover as referências fixas de prazo do topo da página.
- Prazos corrigidos: Flex 45 dias, Casa Pronta 90 a 120 dias.

**Castelo Flex** (casas semiprontas, o lançamento)
- Vídeo explicativo e seção de texto com o passo a passo, deixando explícito o que a Castello entrega e onde a entrega dela termina.
- Página própria com as casas Flex.

**Painel**
- O cliente cadastra e atualiza sozinho: modelos de casas, portfólio, avaliações, vídeos e FAQ.
- A ordem dos vídeos é definida manualmente no painel.
- Há limite de quantos vídeos aparecem na home, por causa do tempo de carregamento.

**Fora de escopo, por decisão explícita:** painel de leads. Os leads vivem no CRM. O site guarda um registro local apenas como rede de segurança (seção 6).

---

## 2. Decisões de arquitetura

| Decisão | Escolha | Motivo |
|---|---|---|
| Stack | PHP + SQLite, renderização no servidor | Roda em qualquer hospedagem compartilhada do mercado; migra copiando a pasta e o arquivo `.db`. Preserva 100% do HTML, CSS e JS que já passaram no QA |
| Hospedagem durante o projeto | Subdomínio de teste na conta EreHost da Freela In Home | O cliente ainda não sabe que hospedagem tem. Ele acompanha pelo navegador desde o início e a entrega final é um zip |
| Fonte do conteúdo | SQLite único, sem arquivos JSON | Uma fonte da verdade só. Ordenação, ativar/desativar e busca saem de graça. Backup é um `.db` mais a pasta `/uploads`. Substitui a decisão de JSON da spec de junho |
| Mídia | Arquivos em `/uploads`, banco guarda só o caminho | Binário dentro do SQLite incha o arquivo e complica backup |
| CRM | Conector configurável, sem CRM escolhido ainda | O cliente não confirmou qual CRM. Endpoint, método, cabeçalhos e mapa de campos vêm da tabela `config`. Quando confirmarem, é configuração, não código |
| Login | Um usuário só, sem recuperação automática de senha | Suficiente para o cliente. Menos superfície de ataque. A Freela redefine manualmente |
| Exclusão | Desativar, não apagar | Reversível. Seguro contra apagar sem querer |
| URL do painel | `/painel` | Corta bot automático que varre `/admin` e `/wp-admin`. Primeira camada apenas, não substitui as travas reais |

**Por que não Vercel.** A Vercel não roda PHP com disco persistente: não há pasta de uploads que sobreviva nem arquivo SQLite que aceite escrita. Ir para a Vercel significaria reescrever a landing em React e depender de banco na nuvem com mensalidade, fechando a porta da hospedagem do cliente de forma definitiva. PHP roda em toda hospedagem compartilhada brasileira; Next.js não roda em nenhuma. A indefinição do cliente é argumento a favor do PHP.

---

## 3. Estrutura de arquivos

```
config/                        (fora do public_html)
  castello.db                  SQLite: conteúdo, usuário e registro de leads
  segredos.php                 credenciais do CRM, chave de sessão, dados de e-mail

public_html/
  index.php                    home
  flex.php                     página Castelo Flex
  enviar.php                   recebe o formulário, grava o lead, envia ao CRM
  lib/
    db.php                     abre o SQLite, aplica o schema, expõe a conexão
    conteudo.php               leitura do conteúdo por seção
    auth.php                   sessão, login, trava de força bruta, CSRF
    upload.php                 validação e gravação de imagem e vídeo
    leads.php                  gravação e reenvio dos leads
    crm.php                    conector configurável
    email.php                  aviso de lead novo e de falha no CRM
  partials/
    modelos.php  portfolio.php  avaliacoes.php
    videos.php   faq.php        passos.php
  uploads/
    modelos/ portfolio/ videos/ passos/
    .htaccess                  proíbe execução de script nesta pasta
  painel/
    index.php                  login
    painel.php                 dashboard e roteamento das telas
    telas/                     uma tela por tipo de conteúdo
    acoes/                     salvar, desativar, reordenar, upload, backup
    assets/
    .htaccess                  segunda senha HTTP Basic, opcional, pronta para ativar
  css/ js/ images/ fotos-casas/ videos-instagram/ passos/ video-hero/

testes/
  smoke.php                    executado por linha de comando
  crm-falso.php                servidor de teste que finge ser o CRM
```

O `index.html` atual vira `index.php`. O visual não muda: as seções dinâmicas passam a ser montadas por PHP lendo o banco, com o mesmo HTML de saída.

---

## 4. Modelo de dados

```sql
CREATE TABLE usuarios (
  id            INTEGER PRIMARY KEY,
  login         TEXT NOT NULL UNIQUE,
  senha_hash    TEXT NOT NULL,
  nome          TEXT,
  criado_em     TEXT NOT NULL,
  ultimo_acesso TEXT
);

CREATE TABLE login_tentativas (
  ip            TEXT PRIMARY KEY,
  tentativas    INTEGER NOT NULL DEFAULT 0,
  bloqueado_ate TEXT
);

-- Uma tabela para as duas modalidades. O preço é opcional porque o material
-- da Castelo Flex ainda não foi enviado pelo cliente.
CREATE TABLE modelos (
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

CREATE TABLE portfolio (
  id INTEGER PRIMARY KEY, titulo TEXT NOT NULL, categoria TEXT,
  foto TEXT, foto_alt TEXT,
  ativo INTEGER NOT NULL DEFAULT 1, ordem INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE avaliacoes (
  id INTEGER PRIMARY KEY, nome TEXT NOT NULL, texto TEXT NOT NULL,
  estrelas INTEGER NOT NULL DEFAULT 5,
  ativo INTEGER NOT NULL DEFAULT 1, ordem INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE videos (
  id INTEGER PRIMARY KEY, arquivo TEXT NOT NULL, poster TEXT,
  legenda TEXT,
  ativo INTEGER NOT NULL DEFAULT 1, ordem INTEGER NOT NULL DEFAULT 0
);

-- O campo icone guarda a chave de um ícone do conjunto fixo do site (seção 5.3).
-- O campo contexto separa o FAQ geral da home do FAQ da página Flex.
CREATE TABLE faq (
  id INTEGER PRIMARY KEY, pergunta TEXT NOT NULL, resposta TEXT NOT NULL,
  icone TEXT NOT NULL DEFAULT 'relogio',
  contexto TEXT NOT NULL DEFAULT 'geral' CHECK (contexto IN ('geral','flex')),
  ativo INTEGER NOT NULL DEFAULT 1, ordem INTEGER NOT NULL DEFAULT 0
);

-- Passo a passo. O contexto pronta alimenta a seção Como funciona da home;
-- o contexto flex alimenta o passo a passo pedido na reunião para a página Flex.
CREATE TABLE passos (
  id INTEGER PRIMARY KEY,
  contexto TEXT NOT NULL CHECK (contexto IN ('pronta','flex')),
  titulo TEXT NOT NULL, texto TEXT, imagem TEXT,
  ativo INTEGER NOT NULL DEFAULT 1, ordem INTEGER NOT NULL DEFAULT 0
);

-- Textos avulsos editáveis: chamada da seção Flex, prazos exibidos,
-- título e subtítulo do hero, e afins. Chaves criadas na migração.
CREATE TABLE blocos (
  chave TEXT PRIMARY KEY, rotulo TEXT NOT NULL, valor TEXT,
  tipo TEXT NOT NULL DEFAULT 'texto' CHECK (tipo IN ('texto','texto_longo'))
);

-- Configuração do site e do conector do CRM.
CREATE TABLE config (chave TEXT PRIMARY KEY, valor TEXT);

-- Rede de segurança. Não existe tela de leads no painel.
CREATE TABLE leads (
  id INTEGER PRIMARY KEY,
  nome TEXT, whatsapp TEXT, busca TEXT, modelo TEXT, cidade TEXT, mensagem TEXT,
  pagina TEXT, referrer TEXT,
  utm_source TEXT, utm_medium TEXT, utm_campaign TEXT, utm_term TEXT, utm_content TEXT,
  criado_em TEXT NOT NULL,
  crm_status TEXT NOT NULL DEFAULT 'pendente'
    CHECK (crm_status IN ('pendente','enviado','erro','desativado')),
  crm_tentativas INTEGER NOT NULL DEFAULT 0,
  crm_ultima_tentativa TEXT,
  crm_resposta TEXT
);
```

**Chaves iniciais de `config`:** `crm_endpoint`, `crm_metodo`, `crm_cabecalhos`, `crm_mapa_campos`, `crm_ativo`, `videos_na_home` (limite acordado na reunião), `email_aviso`.

---

## 5. Conteúdo real a migrar

O protótipo não tem conteúdo de exemplo. O que está no HTML hoje é material real do cliente e deve ser migrado item a item, conferido, e não recriado.

| Tabela | Origem no protótipo | Quantidade |
|---|---|---|
| `modelos` (modalidade `pronta`) | seção `#modelos`, `index.html:222` | 4, com foto real e alt escrito |
| `portfolio` | seção `#portfolio`, `index.html:294` | 6 casas entregues |
| `avaliacoes` | seção `#depoimentos`, `index.html:391` | 14 avaliações reais do Google, nome e texto completos |
| `videos` | seção `#instagram`, `index.html:541` | 11 vídeos reais com poster já gerado |
| `faq` (contexto `geral`) | seção `#faq`, `index.html:574` | 7 perguntas, cada uma com ícone próprio |
| `passos` (contexto `pronta`) | seção `#como-funciona`, `index.html:359` | 5 etapas com imagem em `passos/` |
| `modelos` (modalidade `flex`) | não existe ainda | aguarda material do cliente |
| `passos` (contexto `flex`) | não existe ainda | aguarda material do cliente |

### 5.1 Vídeos
Os `.mp4` em `videos-instagram/web/` já estão otimizados para web, entre 0,5 MB e 9 MB. Migram para `uploads/videos/` mantendo o poster `.jpg` correspondente. O `insta-06.original.mp4`, de 25 MB, não migra.

### 5.2 Limite de vídeos na home
A reunião acordou limitar quantos vídeos aparecem na home por causa do carregamento. O limite fica em `config.videos_na_home`, com valor inicial **8**. Os 11 vídeos migram todos e continuam cadastrados e ativos; a home mostra os 8 primeiros da ordem definida no painel, e os demais ficam disponíveis para o cliente promover trocando a ordem. O painel mostra quantos ativos existem e quantos estão aparecendo.

### 5.3 Ícones do FAQ
Cada pergunta do FAQ atual usa um SVG desenhado à mão. Se o painel deixar cadastrar pergunta sem ícone, o visual quebra. Solução: os 7 SVGs existentes viram um conjunto fixo nomeado, o campo `icone` é obrigatório, e a tela do painel mostra os ícones para o cliente escolher clicando.

| Chave | Pergunta de origem |
|---|---|
| `relogio` | Quanto tempo leva pra minha casa ficar pronta? |
| `chave` | O que está incluso no chave na mão? |
| `planta` | Posso personalizar a planta e os acabamentos? |
| `clima` | Casa de madeira é confortável o ano todo? |
| `escudo` | A casa é resistente e dura com o tempo? |
| `fundacao` | Vocês cuidam da fundação e do terreno? |
| `garantia` | Que garantias eu tenho com a Castello? |

O conjunto é fixo: se o cliente precisar de um ícone novo, a Freela adiciona. Cadastrar pergunta sem ícone não é possível.

---

## 6. O caminho do lead

1. O formulário posta em `enviar.php`, com honeypot e time-trap, exatamente como já funciona hoje no protótipo.
2. O `enviar.php` valida e **grava o lead no SQLite antes de tentar qualquer coisa**, com status `pendente`.
3. Só então chama o `crm.php`, que monta a requisição a partir da tabela `config`.
4. Resposta bem-sucedida: status vira `enviado`. Falha, tempo esgotado ou CRM desligado: status vira `erro` ou continua `pendente`, e a resposta bruta é guardada em `crm_resposta` para diagnóstico.
5. Em qualquer caso, um e-mail com os dados do lead vai para a Castello na hora. Uma falha no CRM nunca faz o lead sumir.
6. Os pendentes são reenviados por uma rotina em `leads.php`, disparada por cron da hospedagem ou por uma chamada protegida por chave.
7. Enquanto o CRM não estiver configurado (`crm_ativo` desligado), tudo funciona: o lead é gravado e o e-mail é enviado.

### 6.1 Contrato do formulário
Campos visíveis, iguais aos de hoje: `nome`, `whatsapp`, `busca`, `modelo`, `cidade`, `mensagem`.

Campos ocultos preenchidos por JS: `pagina`, `referrer`, `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`.

Campos de defesa: `empresa` (honeypot, precisa vir vazio), `ts` (time-trap, envio abaixo de 3 segundos é recusado), `csrf`.

As UTM são lidas da URL na primeira visita e guardadas em `sessionStorage`, para sobreviver à navegação entre a home e a página Flex antes do envio.

### 6.2 Conector do CRM
O `crm.php` expõe uma função só: recebe o lead, lê a `config`, monta e envia a requisição, devolve sucesso ou erro com a resposta bruta. O mapa de campos é um JSON em `config.crm_mapa_campos` que liga o nome do campo do site ao nome esperado pelo CRM. Trocar de CRM é trocar esse JSON e o endpoint.

---

## 7. Estrutura das páginas

### 7.1 Home (`index.php`)

Ordem nova, com as mudanças marcadas:

1. Nav
2. Hero — **remover "pronta em até 120 dias"** (`index.html:102`)
3. Prova social (5,0 estrelas, 56 avaliações, 12 anos, mais de 18.000 m²)
4. **Modalidades (novo)** — bloco curto que separa Casa Pronta de Castelo Flex e manda cada visitante para o caminho certo
5. **Casa Pronta** — a seção de modelos atual, renomeada, com o prazo de 90 a 120 dias dentro da seção
6. **Castelo Flex (novo)** — resumo, vídeo explicativo, prazo de 45 dias, chamada para a página Flex
7. Vantagens
8. Portfólio
9. Como funciona
10. Depoimentos
11. Instagram
12. FAQ
13. CTA final e contato
14. Rodapé

O prazo sai do topo mas não some do site: passa a viver dentro da seção de cada modalidade, que é onde faz sentido e onde os dois números não se contradizem.

### 7.2 Página Castelo Flex (`flex.php`)

1. Nav e rodapé compartilhados com a home
2. Hero próprio da Flex
3. O que é a Castelo Flex, com o vídeo explicativo pedido na reunião
4. Passo a passo, deixando explícito o que a Castello entrega e onde a entrega dela termina
5. Catálogo das casas Flex, alimentado por `modelos` com modalidade `flex`
6. Prazo de 45 dias e diferenciais
7. FAQ da Flex, alimentado por `faq` com contexto `flex`
8. Formulário
9. Rodapé

Enquanto o material da Flex não chegar, a página é construída com a estrutura pronta e os textos que o cliente já usa no Instagram, e o conteúdo definitivo entra pelo painel.

Visual e copy seguem a skill `castello-design` e o tom padrão da Freela: sem travessões, sem emojis, números concretos.

---

## 8. Painel

`/painel`, responsivo, usável no celular, porque o cliente sobe foto e vídeo pelo telefone.

**Telas:** Modelos (com filtro por modalidade), Portfólio, Avaliações, Vídeos, FAQ, Passo a passo, Textos, Configurações, Backup, Trocar senha.

Cada tela de conteúdo tem adicionar, editar, desativar, reativar e reordenar arrastando. Toda ação dá confirmação visível na tela.

**Configurações** reúne o limite de vídeos da home, o e-mail de aviso de lead e os campos do conector do CRM.

**Backup** gera um `.zip` com o `castello.db` e a pasta `uploads/`.

### 8.1 Segurança
1. HTTPS obrigatório, com o SSL grátis da hospedagem. Inegociável.
2. Senha em hash bcrypt via `password_hash`, nunca em texto puro.
3. Trava de força bruta: bloqueia o IP por alguns minutos após N tentativas erradas.
4. Credenciais e banco fora do `public_html`.
5. `uploads/` com `.htaccess` que desativa execução de script, impedindo upload malicioso disfarçado de imagem ou vídeo.
6. Proteção CSRF em todo formulário do painel.
7. Segunda senha HTTP Basic na frente do `/painel`, opcional, deixada pronta para ativar.
8. Upload validado por tipo real do arquivo, não pela extensão; arquivo renomeado na gravação; tamanho limitado.
9. Sessão com expiração por inatividade.

---

## 9. As três frentes

Antes de qualquer frente abrir, uma sessão curta escreve o **contrato**: o schema SQL da seção 4, a assinatura de cada partial e o contrato do formulário da seção 6.1. É o que permite as três rodarem em paralelo sem conflito de merge. Sem ele, paralelismo vira retrabalho.

**Frente 1 — Infra e painel.** Provisiona o subdomínio na EreHost. Converte `index.html` em `index.php`. Cria o banco, a camada de acesso e o script de migração do conteúdo real da seção 5. Constrói o `/painel` completo com as travas da seção 8.1. É a frente mais longa.

**Frente 2 — Front.** Trabalha só em HTML e CSS, com dados de exemplo em vez de PHP. Entrega a home reorganizada da seção 7.1 e a página Flex da seção 7.2.

**Frente 3 — Formulário e CRM.** Entrega o `enviar.php`, a tabela de leads, a captura de UTM, o conector, o e-mail de aviso e o reenvio dos pendentes. Testa contra `testes/crm-falso.php`, porque as credenciais reais ainda não existem.

**Costura e produção**, ao final: trocar os dados de exemplo da frente 2 pela leitura do banco, rodar o `web-qa-reviewer` no site e no painel, remover o `noindex`, ajustar canonical e og:image absoluto, plugar analytics e pixel, entregar credenciais com um mini-guia de uso.

### 9.1 Divisão de arquivos entre as frentes
Para evitar conflito, cada frente é dona dos seus arquivos e **nenhuma escreve no arquivo de outra**:

| Frente | Arquivos que escreve |
|---|---|
| 1 | `lib/`, `painel/`, `config/`, `partials/`, `index.php`, script de migração |
| 2 | `css/`, `js/`, `front/home.html`, `front/flex.html` |
| 3 | `enviar.php`, `lib/leads.php`, `lib/crm.php`, `lib/email.php`, `testes/crm-falso.php` |

A frente 2 não toca em `index.php`. Ela entrega a marcação nova em `front/home.html` e `front/flex.html`, arquivos estáticos abertos direto no navegador, com dados de exemplo escritos no HTML. A frente 1 converte o `index.html` atual em `index.php` sem mudar layout.

A costura, ao final, é uma tarefa explícita: pegar a marcação de `front/`, trocar os dados de exemplo pelas chamadas aos partials da frente 1, e gerar o `index.php` e o `flex.php` definitivos. A pasta `front/` é descartada depois. Isso torna o ponto de encontro um passo planejado em vez de um conflito de merge.

O `css/style.css` é escrito só pela frente 2. Se a frente 1 precisar de estilo para o painel, usa `painel/assets/painel.css`, que é dela.

---

## 10. Verificação

- `testes/smoke.php`, por linha de comando: cria o banco do zero, aplica o schema, faz um ciclo completo de gravar, editar, desativar e reordenar em cada tabela, recusa upload com extensão falsa, e roda o conector do CRM contra o `crm-falso.php` cobrindo sucesso, erro e tempo esgotado.
- Verificação manual do login: senha errada N vezes bloqueia, formulário sem token CSRF é recusado, arquivo `.php` renomeado para `.jpg` é recusado no upload.
- `web-qa-reviewer` no site público e no painel, ao final, contra o launch-checklist da Freela.
- Comparação visual entre o site atual e o migrado, para provar que a mudança de origem do conteúdo não alterou o resultado.

---

## 11. Pendências que dependem do cliente

Nenhuma bloqueia o início. Todas entram pelo painel depois, que é justamente o motivo de ele existir.

- Material, fotos e vídeos da Castelo Flex.
- Portfólio atualizado, FAQ atualizada e novas avaliações.
- Credenciais e documentação da API do CRM.
- Qual hospedagem a Castello tem, para a migração final.
- Confirmação dos nomes e preços dos modelos Casa Pronta, pendente desde o protótipo.
