<?php
/**
 * <noscript> do Google Tag Manager, logo depois do <body>. Vazio no painel, nao
 * imprime nada. Espera: nada.
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/conteudo.php';

$medicao_gtm_corpo = trim((string) config_ler('gtm_id', ''));
if ($medicao_gtm_corpo !== ''): ?>
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?= e($medicao_gtm_corpo) ?>" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<?php endif;
