# Painel Administrativo — Castello Casas de Madeira

**Data:** 2026-06-18
**Status:** Plano aprovado (negociação). Executar quando o cliente fechar.
**Tipo:** Design/spec — guia de execução. Nenhum código escrito ainda.

---

## 1. Objetivo

Dar ao cliente (Castello) um painel para gerenciar sozinho, sem mexer em código, quatro tipos de conteúdo do site:

1. **Modelos chave na mão**
2. **Portfólio** (casas reais entregues)
3. **Avaliações** (nome + texto, sem integração com Google)
4. **Instagram** (vídeos / reels — upload do `.mp4`, sem integração direta com o Instagram)

Ao salvar no painel, o site público reflete a mudança.

---

## 2. Decisões fechadas (com o cliente Freela)

| Decisão | Escolha | Motivo |
|---|---|---|
| Hospedagem | **EreHost** (compartilhada, PHP + disco) | Tem servidor de verdade; dispensa Supabase/Firebase |
| Site público | **Migra inteiro para a EreHost** (sai do GitHub Pages) | Caixa única, sem CORS, resolve domínio de produção |
| Onde guardar o conteúdo | **Arquivos JSON** no servidor (sem MySQL) | Volume baixo; mais simples de construir, manter e migrar |
| URL do painel | **`/painel`** (não `/admin` nem `/wp-admin`) | Corta bots automáticos (1ª camada). Não substitui as travas reais |
| Login | **1 login só** | Suficiente para o cliente |
| Recuperação de senha | **Sem fluxo automático.** Freela redefine manualmente | Menos complexidade e menos superfície de ataque |
| Exclusão de itens | **Desativar** (esconde do site, não apaga; reativável) | Seguro contra apagar sem querer |
| 2ª senha do servidor (HTTP Basic) na frente do `/painel` | **Opcional** — deixar pronta, cliente decide ativar | Camada dupla opcional |
| Backup | **Botão "Baixar backup" (.zip)** no painel | Empacota JSON + uploads; barato e seguro |

---

## 3. Arquitetura geral

Tudo dentro da mesma hospedagem (EreHost):

```
castellomadeiras.com.br/
├─ (site público)   → as 4 seções dinâmicas são montadas (server-side PHP)
│                      a partir dos arquivos JSON
├─ /painel          → painel com login (PHP), CRUD das 4 telas
├─ /uploads         → fotos e vídeos enviados pelo cliente
└─ /data            → os arquivos JSON com o conteúdo
```

### Renderização do site público
- O `index.html` atual vira **`index.php`** (invisível para o visitante).
- As 4 seções (Modelos, Portfólio, Avaliações, Instagram) passam a ser **renderizadas pelo PHP** lendo os JSON — renderização **no servidor** (server-side), para manter o **SEO** e a velocidade (sem depender de JS no carregamento).
- O HTML/CSS/JS visual e as animações **não mudam** — só a origem do conteúdo dessas 4 seções (de "escrito no código" para "lido do JSON").

---

## 4. As 4 telas do painel (campos)

Cada tela tem: **adicionar · editar · desativar/reativar · reordenar** (arrastar).

### 4.1 🏠 Modelos chave na mão
- **Foto** (upload)
- **Tipo de parede** (texto curto — ex: "Parede dupla")
- **Nome** (ex: "Família")
- **Área** (ex: "51,00 m²")
- **Preço** (ex: "87.997")
- **Destaque "Mais escolhida"** (liga/desliga — só um modelo por vez)
- *Botão "Pedir orçamento" do WhatsApp: gerado automaticamente a partir do nome + área (cliente não mexe).*

### 4.2 📸 Portfólio (casas reais entregues)
- **Foto** (upload)
- **Categoria** (texto curto — ex: "Beira da água", "Térrea")
- **Título** (ex: "Sobrado à beira da água")
- **Descrição da foto** (alt, acessibilidade/SEO) — opcional, com sugestão automática

### 4.3 ⭐ Avaliações
- **Nome do cliente**
- **Texto da avaliação**
- **Nº de estrelas** (padrão 5, editável)
- *Inicial colorida do avatar e selo "Avaliação no Google": automáticos.*

### 4.4 🎬 Instagram (vídeos)
Seção já existe no site (`#instagram`, trilho de vídeos verticais em loop — [index.html:539](index.html#L539)).
- **Vídeo** (upload `.mp4` — reel já pronto/otimizado)
- **Capa/poster** (imagem mostrada antes de tocar) — upload
- **Título/legenda** — opcional. *Design atual não mostra título (mural visual). Manter sem título por enquanto; campo fica disponível.*

> **Vídeo — atenção a banda/disco:** hospedagem compartilhada não converte/comprime vídeo sozinha. O painel assume `.mp4` **já otimizado** (guarda e exibe). Incluir aviso de tamanho recomendado no upload. Compressão automática (ffmpeg) só se a EreHost oferecer — **verificar na execução**.

---

## 5. Login, segurança e uso

### 5.1 Login
- `castellomadeiras.com.br/painel` → tela de login (usuário + senha).
- Sessão PHP; logout automático após inatividade.
- 1 login. Sem recuperação automática (Freela redefine).

### 5.2 Travas de segurança (por baixo do `/painel`)
1. **HTTPS obrigatório** (SSL grátis da EreHost). Inegociável.
2. **Senha em hash bcrypt** (`password_hash`), nunca em texto puro.
3. **Trava de força bruta** — bloqueia o IP após N tentativas erradas por alguns minutos.
4. **Arquivo de credenciais fora da pasta pública** (`public_html`).
5. **Pasta de uploads sem execução de código** (`.htaccess` desativa scripts) — impede upload malicioso disfarçado de vídeo/imagem.
6. **Proteção CSRF** em todos os formulários.
7. **2ª senha do servidor (HTTP Basic) na frente do `/painel`** — opcional, pronta para ativar.

### 5.3 Validação de upload
- Aceitar só os tipos certos: imagens (`jpg/png/webp`) e vídeo (`mp4`).
- Conferir tipo real do arquivo (não só a extensão).
- Renomear no upload; limitar tamanho.

### 5.4 Uso no dia a dia
- Painel **simples e responsivo** (funciona no celular — sobe foto/vídeo do telefone).
- Cada ação dá **confirmação visível** ("Modelo salvo", "Avaliação publicada").
- Salvou → site público atualiza na hora.

### 5.5 Backup
- Botão **"Baixar backup"** → `.zip` com `data/` + `uploads/`.

---

## 6. Estrutura de arquivos (proposta para execução)

```
(home da conta EreHost)
├─ config/                       ← FORA do public_html
│   ├─ credenciais.php           ← usuário + hash da senha
│   └─ tentativas-login.json     ← controle de força bruta
│
public_html/
├─ index.php                     ← era index.html (renderiza seções via PHP)
├─ css/  js/  images/  fotos-casas/  passos/  videos-instagram/ ...
├─ partials/                     ← trechos PHP que montam cada seção a partir do JSON
│   ├─ modelos.php
│   ├─ portfolio.php
│   ├─ avaliacoes.php
│   └─ instagram.php
├─ data/
│   ├─ modelos.json
│   ├─ portfolio.json
│   ├─ avaliacoes.json
│   ├─ videos.json
│   └─ .htaccess                 ← bloqueia leitura direta pelo navegador
├─ uploads/
│   ├─ modelos/  portfolio/  videos/
│   └─ .htaccess                 ← proíbe execução de scripts
└─ painel/
    ├─ index.php                 ← login + dashboard
    ├─ (handlers de salvar/excluir/reordenar/backup)
    ├─ assets do painel
    └─ .htaccess                 ← (opcional) 2ª senha HTTP Basic
```

### Formato dos JSON (rascunho)
```jsonc
// modelos.json
[ { "id": "m1", "nome": "Família", "parede": "Parede dupla",
    "area": "51,00 m²", "preco": "87.997", "foto": "uploads/modelos/familia.jpg",
    "destaque": true, "ativo": true, "ordem": 3 } ]

// portfolio.json
[ { "id": "p1", "titulo": "Sobrado à beira da água", "categoria": "Beira da água",
    "foto": "uploads/portfolio/casa3.jpg", "alt": "Sobrado de madeira à beira da água",
    "ativo": true, "ordem": 1 } ]

// avaliacoes.json
[ { "id": "a1", "nome": "Joana Lazzaris", "texto": "Tivemos uma excelente experiência...",
    "estrelas": 5, "ativo": true, "ordem": 1 } ]

// videos.json
[ { "id": "v1", "video": "uploads/videos/insta-01.mp4",
    "poster": "uploads/videos/insta-01.jpg", "legenda": "",
    "ativo": true, "ordem": 1 } ]
```

---

## 7. A verificar na execução (depende do que a EreHost oferece)

- [ ] Confirmar versão do **PHP** disponível.
- [ ] Confirmar **SSL grátis** ativável (Let's Encrypt) e forçar HTTPS.
- [ ] Limites de **disco e banda** do plano (impacto direto nos vídeos).
- [ ] Há **ffmpeg**/ferramenta de compressão de vídeo no servidor? (define se a compressão é automática ou manual no upload).
- [ ] Tamanho máximo de upload do PHP (`upload_max_filesize`, `post_max_size`) — ajustar para vídeos.
- [ ] Acesso a pasta **fora do `public_html`** para `config/`.

---

## 8. Plano de migração (resumo da execução)

1. Provisionar EreHost (PHP + SSL + domínio).
2. Migrar o site estático atual para a EreHost; `index.html` → `index.php`.
3. Extrair o conteúdo hoje fixo no HTML para os JSON iniciais (`modelos`, `portfolio`, `avaliacoes`, `videos`).
4. Converter as 4 seções para renderização via PHP a partir dos JSON (visual idêntico).
5. Construir o `/painel`: login + segurança + as 4 telas (CRUD + reordenar + desativar) + backup.
6. QA de browser (web-qa-reviewer): segurança do login, upload, responsividade, e o site público idêntico ao atual.
7. Tirar `noindex`, ajustar og:image absoluto + canonical (pendências de produção já listadas no CLAUDE.md).
8. Entregar credenciais ao cliente + mini-guia de uso.

---

## 9. Próximo passo (quando o cliente fechar)

Voltar a esta conversa/projeto e, a partir deste documento, gerar o **plano de implementação detalhado** (skill `writing-plans`) e executar pela cronologia do `freela-method`.
