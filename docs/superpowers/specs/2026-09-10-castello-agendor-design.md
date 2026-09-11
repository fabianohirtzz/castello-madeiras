# Castello — Integração do formulário com o CRM Agendor

**Data:** 2026-09-10
**Status:** Design aprovado. Pronto para virar plano de implementação.
**Origem:** definição do cliente de que o CRM é o [Agendor](https://www.agendor.com.br/), mais leitura da conta real da Castello em 2026-09-10.
**Substitui:** as seções 6.1 e 6.2 da spec de 2026-09-09 (`2026-09-09-castello-fase2-design.md`), que descreviam um conector genérico para um CRM ainda desconhecido.

---

## 1. O que a leitura da conta revelou

A ferramenta `ferramentas/agendor-ler.php` leu a conta da Castello (id 792016, criada em 2026-08-03). Tudo que segue é observação da conta real, não suposição. É a base de todas as decisões deste documento.

**Usuários (3):** `CASTELLO MADEIRAS` (987229, dono do token), `ComercialDai` (989735), `comercialCarlos` (989737).

**Um funil só:** "Funil de Vendas", id 904296, padrão. Nove etapas:

| Sequência | Id | Nome |
|---|---|---|
| 1 | 3845540 | Contato |
| 2 | 3876251 | DAIANE |
| 3 | 3876252 | CARLOS EDUARDO |
| 4 | 3874797 | Desqualificado |
| 5 | 3845541 | Envio de proposta |
| 6 | 3845543 | Fechamento |
| 7 | 3845542 | Follow-up |
| 8 | 3846001 | Venda futura |
| 9 | 3846006 | Perdido |

As etapas 2 e 3 são **caixas de vendedor**, não estágios de progresso. O lead cai em "Contato" e um humano arrasta para a Daiane ou para o Carlos. Distribuição é decisão deles.

**Já existe um canal automatizado no funil.** Dos 20 negócios lidos, 18 têm título começando em `[META]`, e um deles carrega o número `1429258566027893`, que é identificador de formulário de Lead Ads do Meta. A convenção de título estabelecida é:

```
[META] - Até 3 meses - Nome do Contato
```

O segmento do meio é o **prazo para iniciar a obra**, o principal critério de qualificação deles: `Imediato` (3), `Até 3 meses` (6), `Até 6 meses` (5), `Só pesquisando` (5).

**Distribuição dos negócios:** 17 em "Contato", 3 em "Follow-up". Todos ligados a **pessoa**, nenhum a empresa. Responsáveis repartidos entre os três usuários.

**Campos customizados de pessoa (3, todos em uso real).** Numa amostra de 6 pessoas, 5 tinham os três preenchidos:

| Identifier | Rótulo | Tipo |
|---|---|---|
| `cidade_da_obra` | Cidade da Obra | text |
| `anuncio_de_origem` | Anúncio de Origem | text |
| `pretende_iniciar_a_obra_em` | Pretende iniciar a obra em: | text |

**Campos customizados de negócio:** nenhum.

**Origens de lead cadastradas:** Indicação (2656387), Evento (2656388), **Site (2656389)**, Instagram (2656594), Facebook (2656595).

**Categorias de contato:** Cliente efetivo (4187394), **Cliente em potencial (4187395)**, Concorrente, Fornecedor, Parceiro.

**Produtos:** `CASA PRONTA HORIZONTAL` (2480035) e `CASA PRONTA VERTICAL` (2480036). Não existe produto de Castelo Flex.

**Horizontal e vertical são tipologias, não paredes** (confirmado pelo cliente em 2026-09-10): **horizontal é casa térrea, vertical é casa com mais de um pavimento, com escada**. A leitura inicial deste documento dizia "tipo de parede", por contaminação da coluna `parede` da tabela `modelos` do site, e estava errada.

A distinção importa além do CRM: é um eixo do catálogo da Castello que **o site hoje não representa em lugar nenhum**. Ver seção 13.

**Nunca preenchidos, em 100% dos 20 negócios:** `leadOrigin`, `value`, `description`, `products`.

**18 motivos de perda cadastrados**, vários revelando o que trava a venda: "Ainda não possui terreno", "Não tem valor da entrada suficiente", "Não conseguiu financiamento", "Terreno inadequado".

---

## 2. A API do Agendor

- **Base:** `https://api.agendor.com.br/v3`
- **Autenticação:** cabeçalho `Authorization: Token <uuid>`. Token de conta, obtido em Menu > Integrações. Dá acesso total de leitura e escrita.
- **Limite:** 4 requisições por segundo; acima disso, HTTP 429.
- **Respostas:** sempre `{"data": ...}`. Erros vêm como `{"errors":["..."]}` com o HTTP correspondente.
- **Especificação:** `https://api.agendor.com.br/v3/swagger.json` (a página `/docs/` é só um ReDoc em cima dele).

**Armadilha documentada:** no corpo de criação de negócio, o campo `dealStage` é descrito no swagger como *"Deal stage sequence number"*. Espera a **sequência** (`1` para "Contato"), não o **id** (`3845540`). Os dois são inteiros, então o valor errado não gera erro de validação — só põe o negócio no lugar errado, em silêncio. Ver seção 11.

---

## 3. Decisões de arquitetura

### 3.1 O conector genérico é substituído

O `lib/crm.php` de hoje foi escrito para um CRM desconhecido: monta um JSON **plano** a partir de `config.crm_mapa_campos` e faz **uma** requisição. O Agendor exige JSON **aninhado** (`contact.whatsapp`, `customFields.*`) e **três** requisições encadeadas, onde a resposta de uma alimenta a seguinte. Não é distância que se cubra com configuração; é a forma do conector.

O `crm.php` passa a ser o conector do Agendor. As chaves `crm_endpoint`, `crm_metodo`, `crm_cabecalhos` e `crm_mapa_campos` saem do painel e da `config`.

**Continua intacto**, porque é bom e independe de CRM: a gravação do lead antes de qualquer chamada externa, os estados em `crm_status`, o `lead_marcar`, o `reenviar.php` e o e-mail que sempre sai.

### 3.2 O site se adapta ao CRM, não o contrário

O time comercial trabalha no Agendor todos os dias e já tem convenção estabelecida pelo canal do Meta. O lead do site entra **no mesmo formato**, para que os dois canais fiquem comparáveis no mesmo funil. Nada na conta do Agendor precisa ser criado ou alterado para a integração funcionar.

### 3.3 Deduplicação por WhatsApp

O endpoint `POST /people/upsert` exige `contact.email` e o formulário da Castello não pede e-mail. Em vez de adicionar um campo que derruba conversão, a busca é por telefone: `GET /people?phone=<dígitos>`, que procura em todos os campos de telefone.

### 3.4 Pessoa existente não é alterada

Se a busca acha a pessoa, o site **reaproveita o id e não a modifica**. Se a Daiane corrigiu a cidade na mão e o visitante preenche o formulário de novo, o site não pode passar por cima do trabalho dela. O dado novo entra na descrição do negócio novo, onde é informação e não sobrescrita.

### 3.5 A origem é sempre "Site"

Mesmo quando a UTM diz que o visitante veio de anúncio, `leadOrigin` recebe **Site (2656389)**. O canal que capturou o lead foi o site; a campanha vai em `customFields.anuncio_de_origem`. Marcar "Instagram" quando a UTM diz Instagram tornaria o funil ambíguo em relação aos leads `[META]`, que chegam por outro caminho.

---

## 4. O caminho do lead

Substitui os passos 3 e 4 da seção 6 da spec da Fase 2. O resto (gravar antes, e-mail sempre, fila de pendentes) continua igual.

```
enviar.php
  1. lead_gravar()                       grava no SQLite ANTES de tudo, status pendente
  2. crm_enviar($lead)
       a. GET  /people?phone=<dígitos>   procura duplicata
       b. achou    -> usa data[0].id, não altera a pessoa
          não achou -> POST /people, guarda o id
       c. grava leads.crm_pessoa_id
       d. POST /people/{id}/deals
  3. lead_marcar(enviado | erro | pendente)
  4. email_lead_novo(), sempre, com o link do negócio quando houver
```

Se `crm_ativo` estiver desligado, nada disso roda: o lead é gravado e o e-mail sai, exatamente como hoje.

---

## 5. Mapeamento de campos

### 5.1 Pessoa — `POST /people`

| Campo Agendor | Origem | Observação |
|---|---|---|
| `name` | `nome` | |
| `contact.whatsapp` | `whatsapp` | só dígitos, com DDI: `5548998244494` |
| `contact.mobile` | `whatsapp` | como o visitante digitou |
| `leadOrigin` | fixo | `config.crm_origem`, padrão `2656389` (Site) |
| `category` | fixo | `config.crm_categoria`, padrão `4187395` (Cliente em potencial) |
| `ownerUser` | fixo | `config.crm_responsavel`; vazio significa "não enviar", e o Agendor atribui ao dono do token |
| `customFields.cidade_da_obra` | `cidade` | |
| `customFields.pretende_iniciar_a_obra_em` | `prazo` | campo novo do formulário, seção 7 |
| `customFields.anuncio_de_origem` | UTM | `utm_campaign`, senão `utm_source`, senão `direto` |

**Normalização do WhatsApp: dois formatos, de propósito.** Esta é a parte da integração que mais silenciosamente pode dar errado, e a seção 11 mostra a medição que a sustenta.

O `enviar.php` já valida 10 ou 11 dígitos.

- `contact.whatsapp` recebe **com DDI**: `5548998244494`. É o que a documentação do Agendor pede, e é o que faz o botão de WhatsApp dentro do CRM abrir a conversa certa.
- `contact.mobile` recebe **sem DDI**, como o visitante digitou: `(48) 99824-4494`.

Os dois são obrigatórios, e o motivo é a deduplicação. O filtro `GET /people?phone=` **não encontra nada quando a busca leva o prefixo `55`**, e a busca do site usa os 10 ou 11 dígitos sem DDI, que é o formato em que os leads do Meta já estão gravados na base. Como o filtro `phone` varre **todos** os campos de telefone, é o `contact.mobile` que faz o site reencontrar os próprios leads na visita seguinte.

Gravar só o `contact.whatsapp` com DDI deixaria a integração incapaz de achar as pessoas que ela mesma criou, e o efeito seria uma pessoa nova a cada formulário enviado, sem erro nenhum aparecendo.

**Campos vazios não são enviados**, nem como string vazia: nada de `customFields` em branco ocupando o lugar de dado bom.

### 5.2 Negócio — `POST /people/{id}/deals`

| Campo Agendor | Valor |
|---|---|
| `title` | `{marcador} - {prazo} - {nome}` |
| `funnel` | `config.crm_funil`, padrão `904296` |
| `dealStage` | `config.crm_etapa`, padrão `1` (sequência de "Contato") |
| `description` | bloco de texto, formato abaixo |
| `ownerUser` | `config.crm_responsavel`, quando preenchido |

**Título.** O marcador vem de `config.crm_marcador` e **inclui os colchetes no próprio valor da configuração** (`[SITE]`, não `SITE`). O conector não acrescenta nem remove colchetes: escreve o que está na config. Assim a Castello pode mudar para `[SITE FLEX]` ou o que quiser pelo painel, sem código.

Quando o prazo vier vazio, o segmento do meio usa `busca` (que é obrigatório no formulário), para o título nunca degenerar em `[SITE] -  - Nome`. Limite de 120 caracteres, cortando o **nome** e preservando marcador e prazo, que é o que se lê na coluna do funil. Os títulos existentes têm de 27 a 53 caracteres.

**Descrição.** Texto simples, porque não há campo customizado de negócio na conta:

```
Lead do site.

O que busca: Modelo pronto do catálogo
Modelo de interesse: Conforto 42,75 m²
Cidade / região do terreno: Tubarão SC
Prazo para iniciar: Até 3 meses

Mensagem:
Gostaria de saber sobre financiamento.

Origem
Página: /flex.php
Campanha: google / cpc / institucional-set
Enviado em: 10/09/2026 14:32
```

Linhas de campo vazio são omitidas inteiras.

`value` e `products` não são enviados: a conta nunca os preenche e não cabe ao site inventar preço.

---

## 6. Configuração e segredos

### 6.1 O token fica fora do banco

O backup do painel empacota o `castello.db` (`painel/tabelas.php`, função de backup). Um token na tabela `config` viajaria dentro de todo zip que o cliente baixa. O token vai para `config/segredos.php`, que fica fora do `public_html`, fora do git e fora do backup:

```php
define('CASTELLO_AGENDOR_TOKEN', '');
```

Isso é o que o `config/segredos.php.exemplo` já previa: *"Credenciais do CRM e do envio de e-mail entram aqui quando a Frente 3 precisar."* O `lib/db.php` já carrega esse arquivo quando existe.

Ligado e sem token são situações diferentes, e o diagnóstico precisa distingui-las: `crm_ativo` desligado devolve `crm_desativado` e é o estado normal de repouso; `crm_ativo` ligado sem token devolve `crm_sem_token` e é configuração incompleta, que merece aparecer no painel.

O `config/segredos.php.exemplo` ganha a constante nova, documentada.

### 6.2 Chaves da `config`, editáveis no painel

| Chave | Padrão | Rótulo no painel |
|---|---|---|
| `crm_ativo` | `0` | Enviar os pedidos para o CRM |
| `crm_funil` | `904296` | Funil do Agendor |
| `crm_etapa` | `1` | Etapa onde o lead nasce (sequência) |
| `crm_origem` | `2656389` | Origem do lead |
| `crm_categoria` | `4187395` | Categoria do contato |
| `crm_marcador` | `[SITE]` | Marcador no título do negócio |
| `crm_responsavel` | vazio | Responsável pelo lead (vazio = conta principal) |
| `crm_timeout` | `10` | Segundos de espera pelo CRM |

**Removidas:** `crm_endpoint`, `crm_metodo`, `crm_cabecalhos`, `crm_mapa_campos`. Saem do `CASTELLO_CONFIG_PADRAO` em `lib/db.php` e da tela de configurações em `painel/tabelas.php`.

Os padrões são os valores reais da conta, então a integração funciona assim que o token entrar, sem ninguém configurar nada.

---

## 7. Mudanças no formulário

### 7.1 Campo novo: prazo para iniciar a obra

É o principal critério de qualificação da Castello, está no título de todo negócio do funil, alimenta um campo customizado que o time preenche na mão, e o formulário do site não pergunta. Sem ele, o lead do site nasce mais pobre que o lead do Meta no mesmo funil.

Select **opcional**, rotulado "Quando pretende iniciar a obra?", com exatamente os quatro valores que a conta já usa, na ordem em que fazem sentido:

```
Imediato
Até 3 meses
Até 6 meses
Só pesquisando
```

Os textos são idênticos aos existentes, de propósito: qualquer variação ("3 meses", "até três meses") quebraria a comparação entre os dois canais no funil.

Posição: depois de "O que você busca?" e antes de "Modelo de interesse", onde a pergunta segue a linha de raciocínio de quem está preenchendo.

### 7.2 O que o campo novo arrasta junto

- `public_html/partials/formulario.php` — o select
- `public_html/enviar.php` — limite de tamanho e validação contra a lista fechada de valores. Valor fora da lista vira string vazia, não HTTP 422: o campo é opcional e um POST adulterado não deve custar o lead.
- `lib/leads.php` e `lib/schema.sql` — coluna `prazo`
- `lib/email.php` — a linha no e-mail de aviso
- **`testes/casos/50-paginas.php`** e as cinco bases — ver 7.3

Não há nada a fazer no painel: **o painel não tem tela de leads**, por decisão da spec da Fase 2. O e-mail de aviso é o único lugar onde a equipe lê o lead fora do CRM, o que torna a linha do prazo nele mais importante, não menos.

### 7.3 A comparação byte a byte, nas cinco páginas

O `testes/casos/50-paginas.php` compara o corpo de **cada página do site** com uma base conferida no navegador, guardada em `testes/base/pagina-<chave>.html`. São cinco: `pagina-home.html`, `pagina-casa-pronta.html`, `pagina-flex.html`, `pagina-portfolio.html` e `pagina-contato.html`.

O `partials/formulario.php` é um só e aparece **uma vez em cada uma das cinco**: dentro do `partials/modal.php` em quatro delas, e embutido direto na `contato.php`, que por isso não inclui o modal. Ou seja, acrescentar um campo ao formulário invalida **as cinco bases de uma vez**.

Regenerar as cinco bases faz parte da **mesma tarefa** que altera o formulário. Se ficar para depois, a suíte inteira fica vermelha e deixa de servir como rede de segurança para o resto do trabalho. E o cabeçalho do arquivo é explícito quanto ao procedimento: base nova só depois de conferir a página no navegador, não gerada às cegas a partir da própria saída.

O mesmo arquivo tem um teste que afirma o conteúdo do select de modelos (`'<option value="Compacta · 39 m² · R$ 69.900">'`). Ele não é afetado pelo campo novo, mas é o teste que vai quebrar quando o catálogo da seção 13.1 for corrigido.

---

## 8. Modelo de dados

Duas colunas novas em `leads`:

```sql
prazo          TEXT,
crm_pessoa_id  INTEGER
```

`prazo` guarda a resposta do campo novo. `crm_pessoa_id` guarda o id da pessoa no Agendor assim que ela é criada ou encontrada, e existe para resolver o caso descrito em 9.2.

**Migração, e por que ela não pode morar no `migrar.php`.** O `CREATE TABLE IF NOT EXISTS` do `schema.sql` não adiciona coluna a tabela que já existe, e o banco no servidor já tem leads gravados. Falta um `ALTER TABLE leads ADD COLUMN`.

O lugar óbvio seria o `migrar.php`, e é o lugar errado: **ele é apagado do servidor depois da instalação**, então uma migração escondida ali nunca rodaria em produção.

O lugar certo é a `db()`, em `lib/db.php`, que já aplica o `schema.sql` inteiro e já semeia a `config` com `INSERT OR IGNORE` a cada conexão. Entra ali uma função `db_garantir_colunas(PDO $pdo): array` que lê `PRAGMA table_info(leads)` e acrescenta o que faltar, devolvendo o que acrescentou. Uma consulta a mais por requisição, ao lado de um `exec` de schema inteiro que já acontece: irrelevante.

Ser idempotente não é detalhe, é o requisito: essa função roda em toda requisição do site.

---

## 9. Erro, tempo e retomada

### 9.1 Orçamento de tempo compartilhado

O contrato fixa **10 segundos no total** para o CRM, porque o `max_execution_time` do servidor é 60 e um CRM lento não pode segurar a resposta ao visitante. Isso não muda com três requisições: o teto continua sendo do conjunto.

O conector marca o instante inicial e, antes de cada chamada, calcula quanto sobrou. Se sobrou menos de 2 segundos, aborta, deixa o lead como `pendente` e devolve o erro `crm_tempo`. O `reenviar.php` termina o serviço depois.

### 9.2 Pessoa criada, negócio não

É o caso novo que as três requisições introduzem. Se o reenvio recomeçasse do zero, criaria uma pessoa duplicada a cada tentativa.

Por isso o `crm_pessoa_id` é gravado **assim que a pessoa nasce**, antes de tentar o negócio. No reenvio, lead com `crm_pessoa_id` preenchido pula direto para a criação do negócio.

### 9.3 Códigos de erro

Mantém a convenção do conector atual, acrescentando o que é do Agendor:

| Erro | Quando |
|---|---|
| `crm_desativado` | `crm_ativo` desligado |
| `crm_sem_token` | `CASTELLO_AGENDOR_TOKEN` ausente ou vazio |
| `crm_sem_telefone` | telefone que não normaliza para 10 ou 11 dígitos, e o lead ainda não tem `crm_pessoa_id`. Sem telefone não há como buscar duplicata nem como o vendedor responder |
| `crm_tempo` | orçamento de tempo esgotado |
| `crm_conexao` | falha de rede |
| `crm_auth` | HTTP 401 ou 403, token inválido ou revogado |
| `crm_limite` | HTTP 429, limite de requisições |
| `crm_http` | qualquer outro HTTP fora da faixa 2xx |
| `crm_resposta_invalida` | corpo que não é JSON, ou JSON sem `data.id` |

A busca de duplicata é o único passo **tolerante a falha**: se ela falhar por qualquer motivo, o conector segue e cria a pessoa. Um lead duplicado no CRM é muito menos grave que um lead perdido.

### 9.4 O link do negócio no e-mail

A resposta da criação do negócio traz `_webUrl`, o endereço dele dentro do Agendor. Quando existir, entra no e-mail de aviso, para a equipe pular do e-mail direto para o negócio.

---

## 10. Testes

O `testes/casos/85-crm.php` já sobe um servidor falso em `127.0.0.1:8765+`. Ele passa a responder as três rotas do Agendor, devolvendo `{"data":{"id":...}}`.

Casos a cobrir:

1. Pessoa nova: as três chamadas na ordem, `crm_pessoa_id` gravado, status `enviado`.
2. Pessoa já existente: a busca encontra, `POST /people` **não** acontece, o negócio é criado assim mesmo.
3. Busca de duplicata falhando: o conector segue e cria a pessoa.
4. Tempo estourando entre a pessoa e o negócio: status `pendente`, `crm_pessoa_id` gravado.
5. Reenvio de lead com `crm_pessoa_id` preenchido: vai direto ao negócio, não cria pessoa de novo.
6. HTTP 401: erro `crm_auth`, lead preservado, e-mail enviado.
7. Token ausente: erro `crm_sem_token`, nada de requisição.
8. Formato do payload: `contact.whatsapp` **com** DDI e `contact.mobile` **sem**, `customFields` com os três identificadores certos, campos vazios ausentes do JSON.
8b. Ida e volta da deduplicação: criar uma pessoa pelo conector e, no envio seguinte com o mesmo telefone, conferir que a busca a encontra. É o teste que pegaria a regressão descrita em 5.1, e o servidor falso precisa imitar o comportamento medido em 11.1, incluindo devolver vazio quando a busca chega com `55`.
9. Formato do título: com prazo, sem prazo (cai para `busca`), e nome longo cortado em 120 caracteres.
10. Migração idempotente: rodar duas vezes sobre um banco que já tem as colunas não falha.

E o `node testes/formulario.test.js` cobre o campo novo no lado do JS.

---

## 11. Validação contra a conta real

Nada aqui se resolve com servidor falso: depende de como o Agendor de verdade se comporta. Em 2026-09-10 a integração foi ligada no servidor de teste e quatro leads reais foram enviados pelo formulário publicado. Tudo abaixo, menos 11.5, está medido contra a conta da Castello.

### 11.1 Filtro de telefone — MEDIDO em 2026-09-10

Leitura feita contra a conta real, tomando como referência uma pessoa que já existe na base, com telefone de 11 dígitos gravado no campo `work`:

| Filtro | Formato buscado | Resultado |
|---|---|---|
| `phone` | 11 dígitos, sem DDI | **encontra** |
| `phone` | número formatado `(48) 9xxxx-xxxx` | **encontra** |
| `phone` | 13 dígitos, com `55` | vazio |
| `phone` | `+55...` | vazio |
| `whatsapp` | qualquer formato | vazio |
| `mobile_phone` | qualquer formato | vazio |

Três conclusões, todas já refletidas na seção 5.1:

1. **O filtro é `phone`**, não `whatsapp` nem `mobile_phone`. Os dois últimos não acharam nem a pessoa de referência, porque o telefone dela mora no campo `work`. Que o `phone` varra todos os campos veio da documentação, não desta medição; a medição no campo que o site usa está em 11.3.
2. **A busca vai sem DDI.** Com `55` na frente, o retorno é vazio.
3. O filtro ignora a pontuação, então dígitos puros e número formatado dão no mesmo.

Foi esta medição que revelou o defeito corrigido em 5.1: gravar o telefone só com DDI tornaria a integração incapaz de reencontrar as pessoas que ela mesma cria.

### 11.2 `dealStage`: sequência ou id — MEDIDO em 2026-09-10

**Sequência.** Com `crm_etapa = 1`, o negócio nasceu em `dealStage: {"id": 3845540, "name": "Contato", "sequence": 1}`, dentro do funil 904296. Se a API quisesse o id, `1` não corresponderia a etapa nenhuma. Medido com leads de verdade enviados pelo formulário do site publicado, listados em 11.4.

### 11.3 Dedup por telefone gravado em `contact.mobile` — MEDIDO em 2026-09-10

Esta era a única afirmação da seção 11.1 que vinha da documentação e não de medição: o filtro `phone` foi medido contra uma pessoa cujo telefone morava em `contact.work`, e o conector grava a chave de busca em `contact.mobile`.

Agora está medido no campo certo. O formulário foi enviado duas vezes com o mesmo telefone, e o `GET /people?phone=48990000001` encontrou a pessoa que o próprio site tinha criado, com `contact.mobile` gravado como `(48) 99000-0001`. O segundo envio **não criou pessoa nova**: anexou um segundo negócio à mesma pessoa.

O mesmo teste rodou com um número de DDD 55 (`(55) 99000-0002`, Santa Maria/RS), que é o caso em que a normalização poderia confundir DDD com DDI. Uma pessoa, um negócio, sem duplicata.

### 11.4 Título repetido recusa o negócio — MEDIDO em 2026-09-10

Descoberto no teste em produção, e o achado mais caro desta validação. O Agendor **recusa um segundo negócio com o mesmo título para a mesma pessoa**:

```
HTTP 400
{"errors":["Title There can only be one deal with this title for this organization/person"]}
```

O título sai de marcador + prazo + nome (seção 5.2), então é inteiramente determinístico: o mesmo visitante mandando o formulário duas vezes com os mesmos dados produz o mesmo título. Antes da correção o lead ficava com `crm_status = 'erro'` e a fila de reenvio gastava as cinco tentativas sem nenhuma chance de sucesso, porque a resposta nunca mudaria.

A recusa passou a contar como entrega: a pessoa está no CRM e o negócio com aquele título já está no funil. A equipe fica sabendo do contato novo pelo e-mail de aviso, que sai sempre, antes de qualquer chamada ao CRM. O corpo da recusa continua guardado em `crm_resposta`, para a auditoria enxergar que nenhum negócio novo nasceu.

O CRM falso não pegava o caso porque imitava só o caminho feliz. Agora impõe a restrição.

### 11.5 Campos customizados — CONFERIDO no painel web em 2026-09-10

O conector manda `customFields` como o swagger documenta: um mapa de `identifier` para valor. **Grava.** Conferido na pessoa de teste pelo painel web do Agendor.

A conferência precisou ser visual porque a API não ajuda: **o Agendor não devolve valores de campo customizado em pessoa nenhuma**. Nas 50 pessoas lidas da conta, criadas pela própria equipe da Castello pelo CRM, a chave `customFields` não aparece em nenhuma, embora o `PersonEntity` do swagger a documente, e não existe endpoint que leia esses valores — `/custom_fields/people` devolve só as definições.

Duas consequências para quem mexer nisso depois:

1. **Nenhum teste automatizado cobre a gravação desses três campos contra a API real.** O que a suíte cobre é o payload: `crm_payload_pessoa` monta o `customFields` certo. Que o Agendor aceite é fato observado uma vez, no painel, não medido continuamente.
2. `anuncio_de_origem` está com `accessLevel: "read_only"` na conta e ainda assim a escrita passou. Ou seja, `read_only` ali restringe a edição por usuário no painel, não a escrita por integração. Não tirar essa chave do payload achando que ela é recusada.

---

## 12. O que fica de fora

- **Webhooks do Agendor.** Existem, mas o fluxo é de mão única: site manda, CRM recebe. Nada volta.
- **Empresas (`/organizations`).** Os 20 negócios lidos são todos de pessoa, nenhum de empresa.
- **Produtos e valor no negócio.** A conta nunca preenche. E ligar o produto exigiria saber se o interesse é térreo ou sobrado, que é justamente o eixo que o site ainda não representa (13.1). Quando o catálogo for alinhado, mandar `products` no negócio passa a ser barato e vale reabrir.
- **Atualização de pessoa existente.** Decisão 3.4.
- **Distribuição automática entre Daiane e Carlos.** As etapas-caixa mostram que a triagem é humana e visual. O site não adivinha rodízio.
- **Mapear UTM para origem Instagram ou Facebook.** Decisão 3.5.

---

## 13. Pendências que dependem do cliente

- ~~Conferir os três campos customizados no painel do Agendor.~~ **Feito em 2026-09-10**, ver 11.5: gravam.
- ~~Apagar os registros de teste.~~ **Feito em 2026-09-10.** Fica a lição, para a próxima validação em conta de cliente: a API do Agendor apaga pessoa (`DELETE /people/{id}`) mas **não apaga negócio** — `/deals/{id}` só aceita `get` e `put`. Apagar a pessoa deixa o negócio órfão no funil, visível para a equipe de vendas. Quem for testar de novo já sabe que a limpeza final passa pelo painel web.
- **Token do Agendor em produção.** O token já está gravado em `config/segredos.php` no servidor de teste, por `ferramentas/instalar-token.php`. Quando o site mudar para o domínio definitivo, repetir esse passo no servidor novo.
- **Confirmar o campo de prazo no formulário.** A decisão de adicionar foi tomada aqui com base no uso real da conta; vale confirmar com a Castello, junto com as outras pendências.
- **Catálogo da Casa Pronta.** Assunto separado desta integração, que não a bloqueia, mas que ficou visível durante o diagnóstico e está descrito em detalhe abaixo.

### 13.1 O catálogo da Casa Pronta não segue o material do cliente

O material em `informacoes/drive-flex/` mostra que a Castello trabalha com **cinco projetos, identificados por número e área**: 36,00 / 39,00 / 42,75 / 51,00 / 57,75 m². A tabela manuscrita de setembro de 2026 confirma os preços da Flex, e o banco do site está **fiel a ela** nos cinco modelos Flex (43, 47, 51, 59 e 69 mil).

O lado da Casa Pronta é que destoa, em três pontos:

1. **Os nomes são invenção do protótipo.** Compacta, Conforto, Família e Ampla não aparecem em material nenhum do cliente, que numera os projetos de 1 a 5.
2. **Uma área não existe no catálogo deles.** O site anuncia um modelo de **59,75 m²**; o material da Castello tem **57,75 m²**. São quatro modelos no site contra cinco projetos no material.
3. **A tipologia térrea/sobrado não aparece no site**, embora seja como o CRM classifica os produtos. Pior: as fotos cadastradas se contradizem com os nomes. O modelo chamado "Compacta" tem foto descrita como casa "de dois pavimentos", e o "Ampla" tem foto de "sobrado".

Nada disso quebra a integração com o Agendor, que manda o texto do modelo escolhido dentro da descrição do negócio, seja ele qual for. Mas enquanto o catálogo não for alinhado, o campo "Modelo de interesse" leva ao CRM um vocabulário que o time comercial não usa.

O caminho depende de confirmação da Castello: quais dos cinco projetos são vendidos como Casa Pronta, quais são térreos e quais são sobrados, e quanto custa cada um na modalidade pronta. É trabalho de conteúdo e catálogo, com spec própria.
- **Autorização para o teste de escrita** da seção 11.
