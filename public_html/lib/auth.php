<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Sessao, login, trava de forca bruta e CSRF do painel.
 *
 * Um usuario so, sem recuperacao automatica de senha, como decidiu a spec.
 * A Freela redefine manualmente quando precisar.
 */

const AUTH_MAX_TENTATIVAS   = 5;
const AUTH_BLOQUEIO_MINUTOS = 15;
const AUTH_INATIVIDADE      = 7200;

/**
 * Destino do redirecionamento de quem nao esta logado, relativo a pasta do
 * painel. E relativo de proposito: no servidor de teste o site fica em uma
 * subpasta, e um caminho comecando com barra apontaria para fora dele.
 */
const AUTH_LOGIN_URL = 'index.php';

/**
 * Abre a sessao e aplica a expiracao por inatividade.
 *
 * Na linha de comando, quando ja houve saida, session_start() nao funciona.
 * Nesse caso $_SESSION vira um array simples em memoria, o que mantem toda a
 * logica testavel sem afetar o comportamento no navegador.
 */
function auth_iniciar(): void
{
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
              || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_name('castello_painel');
        session_start();
    }

    if (!isset($_SESSION) || !is_array($_SESSION)) {
        $_SESSION = [];
    }

    if (isset($_SESSION['visto_em']) && (time() - (int) $_SESSION['visto_em']) > AUTH_INATIVIDADE) {
        $_SESSION = [];
    }

    $_SESSION['visto_em'] = time();
}

function auth_logado(): bool
{
    auth_iniciar();

    return isset($_SESSION['usuario_id']) && (int) $_SESSION['usuario_id'] > 0;
}

/**
 * Manda para o login quem nao esta logado.
 *
 * Os arquivos de painel/acoes/ estao um nivel abaixo, entao o destino ganha
 * o ../ na frente. Tudo relativo, para o painel funcionar tambem quando o
 * site mora em uma subpasta do dominio.
 */
function auth_exigir(): void
{
    if (auth_logado()) {
        return;
    }

    $script  = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $destino = str_contains($script, '/acoes/') ? '../' . AUTH_LOGIN_URL : AUTH_LOGIN_URL;

    header('Location: ' . $destino);
    exit;
}

function auth_entrar(string $login, string $senha): bool
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli');

    if (auth_bloqueado($ip)) {
        return false;
    }

    $st = db()->prepare('SELECT id, nome, senha_hash FROM usuarios WHERE login = ?');
    $st->execute([$login]);
    $usuario = $st->fetch();

    if ($usuario === false || !password_verify($senha, (string) $usuario['senha_hash'])) {
        auth_registrar_falha($ip);
        return false;
    }

    auth_iniciar();
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    $_SESSION['usuario_id']   = (int) $usuario['id'];
    $_SESSION['usuario_nome'] = (string) ($usuario['nome'] ?? '');
    $_SESSION['visto_em']     = time();

    db()->prepare('UPDATE usuarios SET ultimo_acesso = ? WHERE id = ?')->execute([agora(), $usuario['id']]);
    db()->prepare('DELETE FROM login_tentativas WHERE ip = ?')->execute([$ip]);

    return true;
}

function auth_sair(): void
{
    auth_iniciar();
    $_SESSION = [];

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_unset();
        session_destroy();
    }

    $_SESSION = ['visto_em' => time()];
}

function auth_bloqueado(string $ip): bool
{
    $st = db()->prepare('SELECT bloqueado_ate FROM login_tentativas WHERE ip = ?');
    $st->execute([$ip]);
    $ate = $st->fetchColumn();

    if ($ate === false || $ate === null || $ate === '') {
        return false;
    }

    if (strtotime((string) $ate) > time()) {
        return true;
    }

    db()->prepare('DELETE FROM login_tentativas WHERE ip = ?')->execute([$ip]);

    return false;
}

function auth_registrar_falha(string $ip): void
{
    $st = db()->prepare('SELECT tentativas FROM login_tentativas WHERE ip = ?');
    $st->execute([$ip]);
    $tentativas = (int) $st->fetchColumn() + 1;

    $bloqueado = $tentativas >= AUTH_MAX_TENTATIVAS
        ? date('Y-m-d H:i:s', time() + AUTH_BLOQUEIO_MINUTOS * 60)
        : null;

    db()->prepare(
        'INSERT INTO login_tentativas (ip, tentativas, bloqueado_ate) VALUES (?, ?, ?)
         ON CONFLICT(ip) DO UPDATE SET tentativas = excluded.tentativas, bloqueado_ate = excluded.bloqueado_ate'
    )->execute([$ip, $tentativas, $bloqueado]);
}

function csrf_token(): string
{
    auth_iniciar();

    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['csrf'];
}

function csrf_validar(?string $token): bool
{
    auth_iniciar();

    if ($token === null || $token === '' || empty($_SESSION['csrf'])) {
        return false;
    }

    return hash_equals((string) $_SESSION['csrf'], $token);
}
