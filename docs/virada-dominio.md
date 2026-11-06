# Virada para castellomadeiras.com.br

Passo a passo do dia da publicação. O site novo é PHP + SQLite; o atual é um WordPress com Yoast. A ordem importa: o DNS só muda depois que o site novo estiver conferido na hospedagem nova.

## 1. Antes de mexer em qualquer coisa

- [ ] No registro.br, anotar **todos** os registros de DNS atuais (print ou cópia): A, CNAME, **MX**, **TXT** (SPF, DKIM, DMARC, verificações do Google).
- [ ] Descobrir onde está o e-mail `@castellomadeiras.com.br`. Se os registros MX forem da hospedagem antiga, o e-mail cai quando ela sair do ar: migrar antes.
- [ ] Baixar o backup completo do WordPress (arquivos e banco) e guardar fora do servidor.
- [ ] Baixar o catálogo e qualquer PDF que precise continuar acessível.
- [ ] Baixar o backup do painel do servidor de teste (tela **Backup**): é dele que saem o banco e as fotos atuais, com o que a Castello já editou.
- [ ] Baixar o TTL dos registros que vão mudar, se o DNS deixar, para a troca propagar rápido.

## 2. Hospedagem

Confirmar no servidor:

- [ ] PHP 8 com `pdo_sqlite`, `curl`, `mbstring`, `fileinfo`, `gd` e `zip` (o backup do painel depende do `zip`).
- [ ] `.htaccess` ativo (Apache ou LiteSpeed com `mod_rewrite`).
- [ ] Limite de envio do PHP de pelo menos 32 MB (vídeos de até 30 MB no painel).
- [ ] Uma pasta **fora** do `public_html` para o `config/` (o banco e o token do Agendor ficam lá).
- [ ] FTP funcionando.

Se a hospedagem for outra, ajustar:

- `.credenciais-deploy`: `FTP_HOST`, `FTP_USER`, `FTP_PASS`, `FTP_RAIZ`, `URL`.
- `ferramentas/caminho-config.servidor.php`: caminho do `config/` no servidor novo.
- `public_html/painel/.htaccess`: caminho do `.htpasswd`, se o HTTP Basic for ligado.
- Apagar o manifesto do deploy ou usar `ferramentas/deploy.sh --tudo`, para enviar todos os arquivos.

## 3. Código

```bash
bash ferramentas/virar-dominio.sh
php testes/smoke.php
git diff --stat
git add -A && git commit -m "site no dominio definitivo" && git push origin main
bash ferramentas/deploy.sh --tudo
```

## 4. Dados no servidor novo

- [ ] Copiar `castello.db` do backup para o `config/` do servidor novo.
- [ ] Copiar a pasta `uploads/` do backup para `public_html/uploads/`.
- [ ] Regravar o token do Agendor com `ferramentas/instalar-token.php`: o token fica no `config/` e não vai no deploy.
- [ ] Conferir que `migrar.php`, `atualizar-conteudo.php` e `instalar-token.php` **não** ficaram no servidor.

## 5. Conferir antes de virar o DNS

Testar a hospedagem nova sem mudar o DNS, com `curl --resolve castellomadeiras.com.br:443:IP` ou uma linha no arquivo `hosts`:

- [ ] Todas as páginas abrem, com fotos e vídeos.
- [ ] Login do painel funciona.
- [ ] Formulário envia: o pedido aparece no painel e no Agendor, e o e-mail de aviso chega.

## 6. Virar o DNS

- [ ] Apontar o registro A de `castellomadeiras.com.br` e de `www` para o IP novo. **Não mexer em MX e TXT de e-mail.**
- [ ] Emitir o certificado SSL para os dois nomes.
- [ ] Conferir: `http://` e `www` redirecionam para `https://castellomadeiras.com.br/` com 301.
- [ ] Conferir: `/catalogo/` redireciona para `/casa-pronta.php`.
- [ ] Conferir: `/llms.txt`, `/robots.txt` e `/sitemap.xml` abrem com o domínio novo dentro.
- [ ] Enviar um pedido de teste real pelo formulário e apagar depois do Agendor.

## 7. Google, no mesmo dia

- [ ] **Search Console:** criar a propriedade de domínio (registro TXT no DNS), enviar `sitemap.xml` e pedir indexação da home, Casa Pronta e Castello Flex.
- [ ] **Teste de resultados ricos** (search.google.com/test/rich-results) nas páginas home, Casa Pronta e Flex.
- [ ] **Perfil da Empresa:** conferir que o link do site é `https://castellomadeiras.com.br/`.
- [ ] **Analytics e pixel:** colar os IDs no painel, em **Configurações**, e enviar um pedido de teste para ver o evento chegar (`generate_lead` no Google, `Lead` no Meta).
- [ ] Trocar o link da bio do Instagram para `https://castellomadeiras.com.br/link`.
- [ ] Revisar links de anúncios ativos e de perfis que apontem para o site antigo.

## 8. Depois da virada

- [ ] Manter o subdomínio de teste por alguns dias, com `noindex`, e depois tirar do ar.
- [ ] Olhar o Search Console depois de uma semana: páginas indexadas e erros 404.
- [ ] Atualizar o `CLAUDE.md`: endereço, servidor e o aviso do DNS.
