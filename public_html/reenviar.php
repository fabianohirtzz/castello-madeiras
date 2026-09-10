<?php
declare(strict_types=1);

// Reenvio dos leads que nao chegaram ao CRM.
//
// Cron da hospedagem, a cada 15 minutos:
//   */15 * * * * /usr/bin/php /home/USUARIO/public_html/reenviar.php >/dev/null 2>&1
//
// Ou por URL, quando a hospedagem so oferece cron por HTTP:
//   https://SEUDOMINIO/reenviar.php?chave=CHAVE
//
// A chave fica em config.reenvio_chave e precisa de pelo menos 16 caracteres.
// Enquanto estiver vazia ou curta, o caminho web fica fechado e so o cron por
// linha de comando roda. Gerar assim:
//   php -r "echo bin2hex(random_bytes(24)), PHP_EOL;"
//
// Este cabecalho usa comentario de linha de proposito: a linha do cron contem
// */15, e dentro de um docblock esse */ fecharia o comentario e quebraria o
// arquivo. Nao converter em docblock.

require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/leads.php';
require_once __DIR__ . '/lib/crm.php';

if (PHP_SAPI === 'cli') {
    $resumo = leads_reenviar();
    echo 'tentados=' . $resumo['tentados']
        . ' enviados=' . $resumo['enviados']
        . ' falhas=' . $resumo['falhas'] . PHP_EOL;
    exit($resumo['falhas'] > 0 ? 1 : 0);
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$chave = trim((string) config_ler('reenvio_chave', ''));
$enviada = isset($_GET['chave']) && is_string($_GET['chave']) ? $_GET['chave'] : '';

if (strlen($chave) < 16 || !hash_equals($chave, $enviada)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'erro' => 'chave']);
    exit;
}

$resumo = leads_reenviar();
echo json_encode(['ok' => true] + $resumo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
