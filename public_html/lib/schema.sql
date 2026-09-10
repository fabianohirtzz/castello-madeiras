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
