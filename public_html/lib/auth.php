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

/** Quanto tempo o "continuar conectado" vale. Cada revivida renova o prazo. */
const AUTH_LEMBRAR_DIAS = 30;

/** Prova de quem e a pessoa. Formato seletor:validador, so o hash vai ao banco. */
const AUTH_COOKIE_LEMBRAR = 'castello_lembrar';

/** So o nome de usuario, para o campo vir preenchido. Nada sensivel aqui. */
const AUTH_COOKIE_LOGIN = 'castello_login';

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

    if (isset($_SESSION['usuario_id']) && (int) $_SESSION['usuario_id'] > 0) {
        return true;
    }

    // Sem sessao, o cookie de continuar conectado ainda pode trazer a pessoa
    // de volta. Cada cookie e tentado uma vez so: auth_logado() e chamada
    // varias vezes por requisicao e um cookie ruim faria o banco trabalhar a
    // toa em todas elas.
    static $recusados = [];

    $cookie = (string) ($_COOKIE[AUTH_COOKIE_LEMBRAR] ?? '');
    if ($cookie === '' || isset($recusados[$cookie])) {
        return false;
    }

    if (auth_lembrar_tentar()) {
        return true;
    }

    $recusados[$cookie] = true;

    return false;
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

function auth_entrar(string $login, string $senha, bool $lembrar = false): bool
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

    // Marcar ou desmarcar a opcao vale para agora: desmarcar apaga o que
    // tinha ficado de uma vez anterior, nesta maquina e no banco.
    auth_lembrar_esquecer();

    if ($lembrar) {
        auth_lembrar_criar((int) $usuario['id']);
        auth_cookie(AUTH_COOKIE_LOGIN, $login, time() + AUTH_LEMBRAR_DIAS * 86400);
    } else {
        auth_cookie(AUTH_COOKIE_LOGIN, '', 0);
    }

    return true;
}

function auth_sair(): void
{
    auth_iniciar();

    // O login salvo sobrevive de proposito: sair nao e esquecer quem entrou.
    auth_lembrar_esquecer();

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

/**
 * Continuar conectado.
 *
 * O cookie carrega seletor:validador. O seletor acha a linha, o validador
 * prova quem e a pessoa, e do validador so o hash SHA-256 vai ao banco: quem
 * ler o arquivo do banco nao consegue montar um cookie que funcione.
 *
 * O validador troca toda vez que o cookie revive uma sessao. Como a sessao do
 * PHP assume dai em diante, isso acontece raramente, e um cookie copiado para
 * de valer assim que o dono volta ao painel.
 */
function auth_lembrar_criar(int $usuarioId): void
{
    $seletor   = bin2hex(random_bytes(9));
    $validador = bin2hex(random_bytes(32));

    db()->prepare(
        'INSERT INTO login_lembrado (seletor, usuario_id, validador_hash, expira_em, criado_em)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([
        $seletor,
        $usuarioId,
        hash('sha256', $validador),
        date('Y-m-d H:i:s', time() + AUTH_LEMBRAR_DIAS * 86400),
        agora(),
    ]);

    auth_cookie(AUTH_COOKIE_LEMBRAR, $seletor . ':' . $validador, time() + AUTH_LEMBRAR_DIAS * 86400);
}

/** Tenta reabrir a sessao pelo cookie. Devolve se conseguiu. */
function auth_lembrar_tentar(): bool
{
    $cookie = (string) ($_COOKIE[AUTH_COOKIE_LEMBRAR] ?? '');
    if ($cookie === '' || !str_contains($cookie, ':')) {
        return false;
    }

    [$seletor, $validador] = explode(':', $cookie, 2);
    if ($seletor === '' || $validador === '') {
        return false;
    }

    db()->prepare('DELETE FROM login_lembrado WHERE expira_em <= ?')->execute([agora()]);

    $st = db()->prepare('SELECT * FROM login_lembrado WHERE seletor = ?');
    $st->execute([$seletor]);
    $linha = $st->fetch();

    if ($linha === false) {
        auth_cookie(AUTH_COOKIE_LEMBRAR, '', 0);
        return false;
    }

    // Seletor certo com validador errado nao acontece por acaso: ou o cookie
    // foi copiado, ou e chute. Nos dois casos o token cai e a pessoa digita a
    // senha de novo.
    if (!hash_equals((string) $linha['validador_hash'], hash('sha256', $validador))) {
        db()->prepare('DELETE FROM login_lembrado WHERE seletor = ?')->execute([$seletor]);
        auth_cookie(AUTH_COOKIE_LEMBRAR, '', 0);
        return false;
    }

    $st = db()->prepare('SELECT id, nome FROM usuarios WHERE id = ?');
    $st->execute([(int) $linha['usuario_id']]);
    $usuario = $st->fetch();

    if ($usuario === false) {
        db()->prepare('DELETE FROM login_lembrado WHERE seletor = ?')->execute([$seletor]);
        auth_cookie(AUTH_COOKIE_LEMBRAR, '', 0);
        return false;
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    $_SESSION['usuario_id']   = (int) $usuario['id'];
    $_SESSION['usuario_nome'] = (string) ($usuario['nome'] ?? '');
    $_SESSION['visto_em']     = time();

    // Token novo, prazo novo. O cookie antigo morre aqui.
    db()->prepare('DELETE FROM login_lembrado WHERE seletor = ?')->execute([$seletor]);
    auth_lembrar_criar((int) $usuario['id']);

    return true;
}

/** Apaga o token desta maquina, no banco e no navegador. */
function auth_lembrar_esquecer(): void
{
    $cookie = (string) ($_COOKIE[AUTH_COOKIE_LEMBRAR] ?? '');

    if ($cookie !== '' && str_contains($cookie, ':')) {
        [$seletor] = explode(':', $cookie, 2);
        db()->prepare('DELETE FROM login_lembrado WHERE seletor = ?')->execute([$seletor]);
    }

    auth_cookie(AUTH_COOKIE_LEMBRAR, '', 0);
}

/**
 * Derruba todo aparelho lembrado de um usuario.
 *
 * Chamado na troca de senha: senha nova sem isso nao expulsaria ninguem, que
 * e justamente o que se espera dela quando a antiga vazou.
 */
function auth_lembrar_derrubar(int $usuarioId): void
{
    db()->prepare('DELETE FROM login_lembrado WHERE usuario_id = ?')->execute([$usuarioId]);
}

/** O login salvo da ultima vez, para o campo vir preenchido. */
function auth_login_salvo(): string
{
    return trim((string) ($_COOKIE[AUTH_COOKIE_LOGIN] ?? ''));
}

/**
 * Grava um cookie do painel e mantem $_COOKIE em dia.
 *
 * Escrever tambem em $_COOKIE deixa a mesma requisicao enxergar o que acabou
 * de mudar, e faz a logica toda funcionar na linha de comando, onde
 * setcookie() nao tem cabecalho para escrever. Mesmo motivo do $_SESSION em
 * auth_iniciar().
 */
function auth_cookie(string $nome, string $valor, int $expira): void
{
    if (!headers_sent()) {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
              || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        setcookie($nome, $valor, [
            'expires'  => $valor === '' ? time() - 3600 : $expira,
            'path'     => '/',
            'secure'   => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    if ($valor === '') {
        unset($_COOKIE[$nome]);
        return;
    }

    $_COOKIE[$nome] = $valor;
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
