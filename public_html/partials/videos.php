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
<div class="insta__trilho">
      <button class="insta__seta insta__seta--prev" type="button" data-rail-prev aria-label="Vídeos anteriores" disabled>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
      </button>
      <div class="insta__rail reveal" id="instaRail" aria-label="<?= $videos_contexto === 'depoimentos' ? 'Vídeos de depoimentos de clientes da Castello' : 'Vídeos do Instagram da Castello' ?>">
<?php foreach ($lista_videos as $v): ?>
      <article class="ivid"><div class="ivid__frame"><video class="ivid__video" playsinline loop preload="none" poster="<?= e($v['poster']) ?>"><source src="<?= e($v['arquivo']) ?>" type="video/mp4" /></video></div></article>
<?php endforeach; ?>
    </div>
      <button class="insta__seta insta__seta--next" type="button" data-rail-next aria-label="Próximos vídeos">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
      </button>
    </div>
