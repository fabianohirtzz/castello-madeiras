<?php
/**
 * Trilha de videos. Espera, opcional: string $videos_contexto ('instagram',
 * o padrao, na home; 'depoimentos' na pagina Depoimentos) e int $videos_limite
 * (null usa config.videos_na_home; 0 mostra todos).
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/conteudo.php';

$videos_contexto = $videos_contexto ?? 'instagram';
$lista_videos    = videos($videos_limite ?? null, $videos_contexto);
?>
<div class="insta__rail reveal" id="instaRail" aria-label="<?= $videos_contexto === 'depoimentos' ? 'Vídeos de depoimentos de clientes da Castello' : 'Vídeos do Instagram da Castello' ?>">
<?php foreach ($lista_videos as $v): ?>
      <article class="ivid"><div class="ivid__frame"><video class="ivid__video" playsinline loop preload="none" poster="<?= e($v['poster']) ?>"><source src="<?= e($v['arquivo']) ?>" type="video/mp4" /></video></div></article>
<?php endforeach; ?>
    </div>
