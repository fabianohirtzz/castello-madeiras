<?php
/**
 * Google Analytics 4 e pixel do Meta. So imprime o que estiver preenchido em
 * Configuracoes, no painel; vazio, a pagina nao carrega script nenhum. O evento
 * de pedido enviado sai do js/formulario.js. Espera: nada.
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/conteudo.php';

$medicao_ga    = trim((string) config_ler('ga4_id', ''));
$medicao_pixel = trim((string) config_ler('meta_pixel_id', ''));
?>
<meta name="google-site-verification" content="cZdOXeHcUlHkH_l2egbT9nHjNCqCuAV8679TWxQH0w0" />
<?php
if ($medicao_ga !== ''): ?>
  <script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($medicao_ga) ?>"></script>
  <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','<?= e($medicao_ga) ?>');</script>
<?php endif;
if ($medicao_pixel !== ''): ?>
  <script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','<?= e($medicao_pixel) ?>');fbq('track','PageView');</script>
<?php endif;
