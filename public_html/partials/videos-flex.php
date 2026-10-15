<?php
/**
 * Galeria dos videos da Castello Flex: um video por vez, setas dos lados e
 * pontos embaixo (js/main.js, [data-vcarrossel]). Usada na home e na pagina
 * Flex. Os videos vem do painel (Videos, "Pagina Castello Flex"); sem nenhum
 * cadastrado, vale o video unico do bloco flex_video.
 *
 * Espera: bool $vflex_escuro (true na secao escura da home).
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/conteudo.php';

$vflex_escuro = $vflex_escuro ?? false;
$vflex_lista  = videos(0, 'flex');
if ($vflex_lista === [] && bloco('flex_video') !== '') {
    $vflex_lista = [['arquivo' => bloco('flex_video'), 'poster' => bloco('flex_video_poster'), 'legenda' => '']];
}
$vflex_varios = count($vflex_lista) > 1;
$vflex_seta   = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="%s"/></svg>';

if ($vflex_lista === []) {
    return;
}
?>
        <div class="vcarrossel<?= $vflex_escuro ? ' vcarrossel--escuro' : '' ?> reveal" data-vcarrossel aria-roledescription="carrossel" aria-label="Vídeos sobre a Castello Flex">
          <div class="vcarrossel__palco">
<?php foreach ($vflex_lista as $i => $v): ?>
            <figure class="vcarrossel__slide vexp"<?= $i > 0 ? ' hidden' : '' ?>>
              <div class="vexp__frame">
                <video controls playsinline preload="none" poster="<?= e((string) $v['poster']) ?>">
                  <source src="<?= e((string) $v['arquivo']) ?>" type="video/mp4" />
                  Seu navegador não abre vídeo. <a href="<?= e((string) $v['arquivo']) ?>">Baixe o vídeo da Castello Flex</a>.
                </video>
              </div>
<?php if (trim((string) $v['legenda']) !== ''): ?>
              <figcaption class="vexp__cap<?= $vflex_escuro ? ' vexp__cap--light' : '' ?>"><?= e((string) $v['legenda']) ?></figcaption>
<?php endif; ?>
            </figure>
<?php endforeach; ?>
<?php if ($vflex_varios): ?>
            <button class="vcarrossel__seta vcarrossel__seta--prev" type="button" data-vcarrossel-prev aria-label="Vídeo anterior"><?= sprintf($vflex_seta, 'm15 18-6-6 6-6') ?></button>
            <button class="vcarrossel__seta vcarrossel__seta--next" type="button" data-vcarrossel-next aria-label="Próximo vídeo"><?= sprintf($vflex_seta, 'm9 6 6 6-6 6') ?></button>
<?php endif; ?>
          </div>
<?php if ($vflex_varios): ?>
          <div class="vcarrossel__ctrl">
            <div class="vcarrossel__pontos"></div>
            <span class="vcarrossel__cont" aria-live="polite"></span>
          </div>
<?php endif; ?>
        </div>
