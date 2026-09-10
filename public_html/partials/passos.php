<?php
/**
 * Passo a passo com imagem sticky. Espera: string $contexto ('pronta' ou 'flex').
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/conteudo.php';

$lista_passos = passos($contexto ?? 'pronta');
$total_passos = count($lista_passos);
?>
<!-- véu (mobile): apaga o texto do passo antes dele encostar na imagem sticky -->
        <div class="process__veil" aria-hidden="true"></div>
        <div class="process__media" id="processMedia">
<?php foreach ($lista_passos as $i => $p): ?>
          <div class="process__media-fig<?= $i === 0 ? ' is-active' : '' ?>" data-step="<?= $i ?>"><img src="<?= e($p['imagem']) ?>" alt="<?= e($p['imagem_alt']) ?>" loading="lazy" /></div>
<?php endforeach; ?>
          <div class="process__counter" id="processCounter"><span>01</span> / <?= sprintf('%02d', $total_passos) ?></div>
        </div>

        <ol class="process__steps" id="processSteps">
<?php foreach ($lista_passos as $i => $p): ?>
          <li class="process__step<?= $i === 0 ? ' is-active' : '' ?>" data-step="<?= $i ?>"><span class="process__step-num">PASSO <?= sprintf('%02d', $i + 1) ?></span><h3><?= e($p['titulo']) ?></h3><p><?= e($p['texto']) ?></p></li>
<?php endforeach; ?>
        </ol>
