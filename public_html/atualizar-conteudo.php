<?php
declare(strict_types=1);

/**
 * Aplica no banco a rodada de conteudo de outubro de 2026
 * (migrar_atualizar_2026_10, _fotos, _videos e _etapas, em migrar.php).
 *
 * Linha de comando:  php public_html/atualizar-conteudo.php
 * Pela web:          so com o login do painel, por POST com o token de csrf.
 *
 * Arquivo temporario, como o migrar.php: sobe junto com ele, roda uma vez e
 * os dois saem do servidor. Rodar de novo nao muda nada.
 */

require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/migrar.php';

if (PHP_SAPI === 'cli') {
    foreach (array_merge(migrar_atualizar_2026_10(), migrar_atualizar_2026_10_fotos(), migrar_atualizar_2026_10_videos(), migrar_atualizar_2026_10_etapas(), migrar_atualizar_2026_10_textos(), migrar_atualizar_2026_10_passos_flex(), migrar_atualizar_2026_10_faq(), migrar_atualizar_2026_10_flex()) as $linha) {
        echo $linha, "\n";
    }
    return;
}

auth_iniciar();
auth_exigir();
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    echo "token: ", csrf_token(), "\n";
    echo "mande POST com csrf=<token> para aplicar.\n";
    return;
}

$token = isset($_POST['csrf']) && is_string($_POST['csrf']) ? $_POST['csrf'] : null;
if (!csrf_validar($token)) {
    http_response_code(419);
    echo "token invalido\n";
    return;
}

foreach (array_merge(migrar_atualizar_2026_10(), migrar_atualizar_2026_10_fotos(), migrar_atualizar_2026_10_videos(), migrar_atualizar_2026_10_etapas(), migrar_atualizar_2026_10_textos(), migrar_atualizar_2026_10_passos_flex(), migrar_atualizar_2026_10_faq(), migrar_atualizar_2026_10_flex()) as $linha) {
    echo $linha, "\n";
}
echo "\napague atualizar-conteudo.php e migrar.php do servidor.\n";
