<?php
declare(strict_types=1);

require_once site() . '/lib/auth.php';
require_once site() . '/migrar.php';

migrar_usuario('castello', 'senha-de-teste-forte');

teste('a senha e guardada em bcrypt e nunca em texto puro', function (): void {
    $hash = (string) db()->query("SELECT senha_hash FROM usuarios WHERE login = 'castello'")->fetchColumn();
    verdade(str_starts_with($hash, '$2y$'), 'o hash precisa ser bcrypt');
    verdade(password_verify('senha-de-teste-forte', $hash));
    nao_contem('senha-de-teste-forte', $hash);
});

teste('login com a senha certa entra e marca o ultimo acesso', function (): void {
    verdade(auth_entrar('castello', 'senha-de-teste-forte'));
    verdade(auth_logado());

    $u = db()->query("SELECT ultimo_acesso FROM usuarios WHERE login = 'castello'")->fetch();
    verdade((bool) preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $u['ultimo_acesso']));

    auth_sair();
    falso(auth_logado());
});

teste('login com senha errada e com login inexistente recusa', function (): void {
    falso(auth_entrar('castello', 'errada'));
    falso(auth_logado());
    falso(auth_entrar('naoexiste', 'seja-la-o-que-for'));
    db()->exec('DELETE FROM login_tentativas');
});

teste('cinco tentativas erradas bloqueiam o IP por 15 minutos', function (): void {
    db()->exec('DELETE FROM login_tentativas');
    $ip = '203.0.113.7';

    for ($i = 1; $i <= 4; $i++) {
        auth_registrar_falha($ip);
        falso(auth_bloqueado($ip), "com $i tentativas o IP ainda nao pode estar bloqueado");
    }

    auth_registrar_falha($ip);
    verdade(auth_bloqueado($ip), 'na quinta tentativa o IP bloqueia');

    $linha = db()->query("SELECT * FROM login_tentativas WHERE ip = '203.0.113.7'")->fetch();
    igual(5, (int) $linha['tentativas']);

    $faltam = strtotime((string) $linha['bloqueado_ate']) - time();
    verdade($faltam > 13 * 60 && $faltam <= 15 * 60, 'o bloqueio dura cerca de 15 minutos, faltam ' . $faltam . 's');
});

teste('o bloqueio vence sozinho e limpa o contador', function (): void {
    db()->exec("INSERT OR REPLACE INTO login_tentativas (ip, tentativas, bloqueado_ate)
                VALUES ('198.51.100.9', 5, '" . date('Y-m-d H:i:s', time() - 60) . "')");

    falso(auth_bloqueado('198.51.100.9'), 'bloqueio vencido nao bloqueia mais');
    igual(0, (int) db()->query("SELECT COUNT(*) FROM login_tentativas WHERE ip = '198.51.100.9'")->fetchColumn());
});

teste('IP bloqueado nao entra nem com a senha certa', function (): void {
    db()->exec('DELETE FROM login_tentativas');
    $_SERVER['REMOTE_ADDR'] = '203.0.113.20';

    for ($i = 1; $i <= 5; $i++) {
        falso(auth_entrar('castello', 'errada'));
    }
    verdade(auth_bloqueado('203.0.113.20'));
    falso(auth_entrar('castello', 'senha-de-teste-forte'), 'IP bloqueado nao entra nem acertando');

    db()->exec('DELETE FROM login_tentativas');
    verdade(auth_entrar('castello', 'senha-de-teste-forte'), 'sem bloqueio, entra normalmente');
    igual(0, (int) db()->query("SELECT COUNT(*) FROM login_tentativas WHERE ip = '203.0.113.20'")->fetchColumn(),
        'entrar limpa as tentativas do IP');
    unset($_SERVER['REMOTE_ADDR']);
});

teste('a sessao expira com duas horas de inatividade', function (): void {
    igual(7200, AUTH_INATIVIDADE);
    verdade(auth_entrar('castello', 'senha-de-teste-forte'));
    verdade(auth_logado());

    $_SESSION['visto_em'] = time() - (AUTH_INATIVIDADE + 60);
    falso(auth_logado(), 'passou o tempo de inatividade, tem que deslogar');

    verdade(auth_entrar('castello', 'senha-de-teste-forte'));
    $_SESSION['visto_em'] = time() - (AUTH_INATIVIDADE - 60);
    verdade(auth_logado(), 'dentro do tempo, continua logado');
});

teste('csrf_token e estavel na sessao e csrf_validar so aceita o token certo', function (): void {
    $token = csrf_token();
    verdade(strlen($token) >= 32, 'token curto demais');
    igual($token, csrf_token(), 'o token nao pode mudar a cada chamada');

    verdade(csrf_validar($token));
    falso(csrf_validar('outro'));
    falso(csrf_validar(''));
    falso(csrf_validar(null));
});

teste('sair apaga a sessao e o token', function (): void {
    verdade(auth_entrar('castello', 'senha-de-teste-forte'));
    csrf_token();
    auth_sair();

    falso(auth_logado());
    igual([], array_diff_key($_SESSION, ['visto_em' => 1]), 'a sessao fica so com o carimbo de tempo');
});

teste('auth_exigir passa direto quando o usuario esta logado', function (): void {
    verdade(auth_entrar('castello', 'senha-de-teste-forte'));
    auth_exigir();
    verdade(true, 'auth_exigir nao pode interromper quem esta logado');
});

teste('as constantes da trava sao as do contrato', function (): void {
    igual(5, AUTH_MAX_TENTATIVAS);
    igual(15, AUTH_BLOQUEIO_MINUTOS);
    igual(7200, AUTH_INATIVIDADE);
});

teste('o destino do login e relativo, para funcionar em subpasta', function (): void {
    igual('index.php', AUTH_LOGIN_URL);
    falso(str_starts_with(AUTH_LOGIN_URL, '/'), 'caminho absoluto quebraria o painel em subpasta');
});

/** Zera sessao e cookies entre os casos do continuar conectado. */
function lembrar_zerar(): void
{
    $_SESSION = [];
    $_COOKIE  = [];
    db()->exec('DELETE FROM login_lembrado');
    db()->exec('DELETE FROM login_tentativas');
}

teste('marcar continuar conectado grava o token e guarda so o hash do validador', function (): void {
    lembrar_zerar();
    verdade(auth_entrar('castello', 'senha-de-teste-forte', true));

    $cookie = (string) ($_COOKIE[AUTH_COOKIE_LEMBRAR] ?? '');
    verdade($cookie !== '', 'o cookie de lembrar precisa existir');
    verdade(str_contains($cookie, ':'), 'o cookie e seletor:validador');
    [$seletor, $validador] = explode(':', $cookie, 2);
    verdade(strlen($seletor) >= 16 && strlen($validador) >= 32, 'seletor e validador curtos demais');

    $linha = db()->query('SELECT * FROM login_lembrado')->fetch();
    verdade($linha !== false, 'o token precisa estar no banco');
    igual($seletor, (string) $linha['seletor']);
    igual(hash('sha256', $validador), (string) $linha['validador_hash']);
    nao_contem($validador, (string) $linha['validador_hash'], 'o validador nunca pode ficar em claro no banco');

    $faltam = strtotime((string) $linha['expira_em']) - time();
    verdade($faltam > 29 * 86400 && $faltam <= 30 * 86400, 'o token dura cerca de 30 dias, faltam ' . $faltam . 's');

    igual('castello', (string) ($_COOKIE[AUTH_COOKIE_LOGIN] ?? ''), 'o login fica salvo para vir preenchido');
});

teste('sem marcar a opcao nada e guardado, e marcar antes nao deixa sobra', function (): void {
    lembrar_zerar();
    verdade(auth_entrar('castello', 'senha-de-teste-forte', true));
    igual(1, (int) db()->query('SELECT COUNT(*) FROM login_lembrado')->fetchColumn());

    $_SESSION = [];
    verdade(auth_entrar('castello', 'senha-de-teste-forte', false));
    igual(0, (int) db()->query('SELECT COUNT(*) FROM login_lembrado')->fetchColumn(),
        'entrar sem marcar limpa o token que existia');
    igual('', (string) ($_COOKIE[AUTH_COOKIE_LEMBRAR] ?? ''), 'e apaga o cookie');
    igual('', (string) ($_COOKIE[AUTH_COOKIE_LOGIN] ?? ''), 'e o login salvo');
});

teste('o cookie revive a sessao depois que ela morre', function (): void {
    lembrar_zerar();
    verdade(auth_entrar('castello', 'senha-de-teste-forte', true));

    $_SESSION = [];
    verdade(auth_logado(), 'com o cookie valido a sessao volta sozinha');
    igual(1, (int) db()->query("SELECT id FROM usuarios WHERE login = 'castello'")->fetchColumn() > 0 ? 1 : 0);
    verdade((int) ($_SESSION['usuario_id'] ?? 0) > 0, 'a sessao volta com o usuario dentro');
});

teste('o validador roda a cada revivida e o cookie antigo perde o valor', function (): void {
    lembrar_zerar();
    verdade(auth_entrar('castello', 'senha-de-teste-forte', true));
    $antigo = (string) $_COOKIE[AUTH_COOKIE_LEMBRAR];

    $_SESSION = [];
    verdade(auth_logado());
    $novo = (string) $_COOKIE[AUTH_COOKIE_LEMBRAR];
    verdade($antigo !== $novo, 'o cookie precisa mudar depois de reviver a sessao');

    $_SESSION = [];
    $_COOKIE[AUTH_COOKIE_LEMBRAR] = $antigo;
    falso(auth_logado(), 'o cookie antigo nao pode mais valer');
});

teste('validador errado com seletor certo e recusado e derruba o token', function (): void {
    lembrar_zerar();
    verdade(auth_entrar('castello', 'senha-de-teste-forte', true));
    [$seletor] = explode(':', (string) $_COOKIE[AUTH_COOKIE_LEMBRAR], 2);

    $_SESSION = [];
    $_COOKIE[AUTH_COOKIE_LEMBRAR] = $seletor . ':' . str_repeat('a', 64);
    falso(auth_logado(), 'validador errado nao entra');
    igual(0, (int) db()->query('SELECT COUNT(*) FROM login_lembrado')->fetchColumn(),
        'validador errado cheira a roubo de cookie, entao o token cai');
});

teste('cookie sem sentido nao derruba nem estoura', function (): void {
    lembrar_zerar();
    foreach (['', 'abc', ':', 'sem-dois-pontos', 'naoexiste:' . str_repeat('b', 64)] as $lixo) {
        $_SESSION = [];
        $_COOKIE[AUTH_COOKIE_LEMBRAR] = $lixo;
        falso(auth_logado(), "cookie '$lixo' nao pode logar ninguem");
    }
});

teste('token vencido e recusado e sai do banco', function (): void {
    lembrar_zerar();
    verdade(auth_entrar('castello', 'senha-de-teste-forte', true));

    db()->prepare('UPDATE login_lembrado SET expira_em = ?')->execute([date('Y-m-d H:i:s', time() - 60)]);

    $_SESSION = [];
    falso(auth_logado(), 'token vencido nao entra');
    igual(0, (int) db()->query('SELECT COUNT(*) FROM login_lembrado')->fetchColumn(), 'e e varrido do banco');
});

teste('sair apaga o token e o cookie, mas mantem o login salvo', function (): void {
    lembrar_zerar();
    verdade(auth_entrar('castello', 'senha-de-teste-forte', true));

    auth_sair();
    falso(auth_logado());
    igual(0, (int) db()->query('SELECT COUNT(*) FROM login_lembrado')->fetchColumn());
    igual('', (string) ($_COOKIE[AUTH_COOKIE_LEMBRAR] ?? ''), 'o cookie de continuar conectado cai');
    igual('castello', auth_login_salvo(), 'o login continua salvo para preencher o campo');
});

teste('trocar a senha derruba todos os tokens do usuario', function (): void {
    lembrar_zerar();
    verdade(auth_entrar('castello', 'senha-de-teste-forte', true));
    $usuarioId = (int) $_SESSION['usuario_id'];

    // um segundo aparelho do mesmo usuario
    auth_lembrar_criar($usuarioId);
    igual(2, (int) db()->query('SELECT COUNT(*) FROM login_lembrado')->fetchColumn());

    require_once site() . '/painel/tabelas.php';
    $r = painel_trocar_senha($usuarioId, 'senha-de-teste-forte', 'outra-senha-forte', 'outra-senha-forte');
    verdade($r['ok'], (string) ($r['erro'] ?? ''));

    igual(0, (int) db()->query('SELECT COUNT(*) FROM login_lembrado')->fetchColumn(),
        'senha nova tem que expulsar todo aparelho lembrado');

    // devolve a senha original para os outros casos
    db()->prepare('UPDATE usuarios SET senha_hash = ? WHERE id = ?')
        ->execute([password_hash('senha-de-teste-forte', PASSWORD_BCRYPT), $usuarioId]);
    lembrar_zerar();
});

teste('o prazo de continuar conectado e o combinado', function (): void {
    igual(30, AUTH_LEMBRAR_DIAS);
    igual('castello_lembrar', AUTH_COOKIE_LEMBRAR);
    igual('castello_login', AUTH_COOKIE_LOGIN);
});
