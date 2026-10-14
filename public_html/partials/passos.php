<?php
/**
 * Passo a passo. Espera: string $contexto ('pronta' ou 'flex').
 *
 * Casa Pronta: linha do tempo da obra, horizontal no desktop e vertical no
 * celular (as etapas do carrossel "Como tudo acontece" do Instagram).
 * Castello Flex: cartoes numerados sem imagem (.epasso), como a frente 2
 * desenhou para a pagina Flex.
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/conteudo.php';

$contexto     = $contexto ?? 'pronta';
$lista_passos = passos($contexto);
$total_passos = count($lista_passos);

if ($contexto === 'flex'): ?>
<div class="epasso__grid">
<?php foreach ($lista_passos as $i => $p): ?>
        <article class="epasso reveal">
          <span class="epasso__num"><?= sprintf('%02d', $i + 1) ?></span>
          <h3><?= e($p['titulo']) ?></h3>
          <p><?= e($p['texto']) ?></p>
        </article>
<?php endforeach; ?>
      </div>
<?php return; endif; ?>
<div class="tline-obra reveal">
        <ol class="tline-obra__trilho" aria-label="Etapas da obra, da primeira conversa à entrega">
<?php foreach ($lista_passos as $i => $p): ?>
          <li class="tline-obra__item">
            <span class="tline-obra__num" aria-hidden="true"><?= sprintf('%02d', $i + 1) ?></span>
            <h3><?= e($p['titulo']) ?></h3>
<?php if (trim((string) $p['texto']) !== ''): ?>
            <p><?= e($p['texto']) ?></p>
<?php endif; ?>
          </li>
<?php endforeach; ?>
        </ol>
        <p class="tline-obra__dica">Arraste para o lado e veja as <?= $total_passos ?> etapas.</p>
      </div>
