<?php
declare(strict_types=1);

/**
 * Apoio de testes da frente 3.
 *
 * Fornece db(), e(), agora(), config_ler(), config_gravar(), csrf_token() e
 * csrf_validar() enquanto lib/db.php e lib/auth.php da frente 1 nao existem.
 * Cada funcao so e definida se ainda nao existir: quando a lib real for
 * carregada antes deste arquivo, nada aqui entra em acao.
 *
 * ARQUIVO TEMPORARIO. Removido na Tarefa 9 do plano da frente 3.
 */

date_default_timezone_set('America/Sao_Paulo');

if (!defined('CASTELLO_DB_TESTE')) {
    define('CASTELLO_DB_TESTE', sys_get_temp_dir() . '/castello-teste-f3.db');
}

if (!function_exists('db')) {
    function db(): PDO
    {
        static $pdo = null;
        if ($pdo instanceof PDO) {
            return $pdo;
        }
        $pdo = new PDO('sqlite:' . CASTELLO_DB_TESTE);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('CREATE TABLE IF NOT EXISTS config (chave TEXT PRIMARY KEY, valor TEXT)');
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS leads (
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
             )"
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_leads_pendentes ON leads (crm_status, criado_em)');
        return $pdo;
    }
}

if (!function_exists('e')) {
    function e(?string $texto): string
    {
        return htmlspecialchars((string) $texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('agora')) {
    function agora(): string
    {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('config_ler')) {
    function config_ler(string $chave, ?string $padrao = null): ?string
    {
        $st = db()->prepare('SELECT valor FROM config WHERE chave = :c');
        $st->execute([':c' => $chave]);
        $valor = $st->fetchColumn();
        return $valor === false ? $padrao : (string) $valor;
    }
}

if (!function_exists('config_gravar')) {
    function config_gravar(string $chave, string $valor): void
    {
        $st = db()->prepare(
            'INSERT INTO config (chave, valor) VALUES (:c, :v)
             ON CONFLICT (chave) DO UPDATE SET valor = excluded.valor'
        );
        $st->execute([':c' => $chave, ':v' => $valor]);
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return 'token-de-teste-frente-3';
    }
}

if (!function_exists('csrf_validar')) {
    function csrf_validar(?string $token): bool
    {
        return is_string($token) && $token !== '' && hash_equals(csrf_token(), $token);
    }
}

/** Apaga o arquivo do banco de teste. So faz efeito antes da primeira chamada a db(). */
if (!function_exists('teste_banco_apagar')) {
    function teste_banco_apagar(): void
    {
        foreach ([CASTELLO_DB_TESTE, CASTELLO_DB_TESTE . '-wal', CASTELLO_DB_TESTE . '-shm'] as $arquivo) {
            if (is_file($arquivo)) {
                @unlink($arquivo);
            }
        }
    }
}

/** Esvazia leads e config, mantendo a conexao aberta. */
if (!function_exists('teste_banco_limpar')) {
    function teste_banco_limpar(): void
    {
        db()->exec('DELETE FROM leads');
        db()->exec('DELETE FROM config');
        /* O schema do contrato usa INTEGER PRIMARY KEY sem AUTOINCREMENT, entao
           sqlite_sequence normalmente nem existe. So limpa se existir. */
        $temSequencia = db()->query("SELECT name FROM sqlite_master WHERE name = 'sqlite_sequence'")->fetchColumn();
        if ($temSequencia) {
            db()->exec("DELETE FROM sqlite_sequence WHERE name = 'leads'");
        }
    }
}
