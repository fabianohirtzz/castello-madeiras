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
