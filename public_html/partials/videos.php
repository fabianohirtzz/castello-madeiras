<?php
/**
 * Trilha de videos do Instagram. Espera: nada.
 * A quantidade exibida vem de config.videos_na_home.
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/conteudo.php';

$lista_videos = videos();
?>
<div class="insta__rail reveal" id="instaRail" aria-label="Vídeos do Instagram da Castello">
<?php foreach ($lista_videos as $v): ?>
      <article class="ivid"><div class="ivid__frame"><video class="ivid__video" playsinline loop preload="none" poster="<?= e($v['poster']) ?>"><source src="<?= e($v['arquivo']) ?>" type="video/mp4" /></video></div></article>
<?php endforeach; ?>
    </div>
