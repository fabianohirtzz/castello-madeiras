<?php
/**
 * Passo a passo. Espera: string $contexto ('pronta' ou 'flex').
 *
 * Casa Pronta: as etapas da obra (carrossel "Como tudo acontece" do
 * Instagram) em cards com foto, numa trilha horizontal. O js/main.js trava
 * a secao na tela e a rolagem da pagina empurra a trilha para o lado
 * ([data-hscroll]); sem JS ou com movimento reduzido, a trilha rola com o
 * dedo. Etapa sem foto mostra um icone no lugar.
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
<?php
/* Icone das etapas sem foto, pela primeira palavra que bater no titulo
   (entrega vem antes de checklist: a ultima etapa tem as duas). */
$icones_etapa = [
    'entrega'   => '<circle cx="15" cy="9" r="4"/><path d="m12 12-8 8M8 16l2 2M6 18l1.6 1.6"/>',
    'contato'   => '<path d="M4 4h16a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H8.6L4 20.5V5a1 1 0 0 1 1-1Z"/><path d="M8 9h8M8 12.5h5"/>',
    'estudo'    => '<path d="M3 4h18v16H3z"/><path d="M3 10h8v10M11 14h10M15 4v6"/>',
    'projeto'   => '<path d="M3 4h18v16H3z"/><path d="M3 10h8v10M11 14h10M15 4v6"/>',
    'contrato'  => '<path d="M6 3h9l4 4v14H6z"/><path d="M15 3v4h4M9 12h7M9 16h4"/><path d="m14 17 1.5 1.5L19 15"/>',
    'checklist' => '<path d="M9 6h11M9 12h11M9 18h11"/><path d="m3.5 6 1.5 1.5L7.5 5M3.5 12l1.5 1.5L7.5 11M3.5 18l1.5 1.5L7.5 17"/>',
    'vistoria'  => '<path d="M9 6h11M9 12h11M9 18h11"/><path d="m3.5 6 1.5 1.5L7.5 5M3.5 12l1.5 1.5L7.5 11M3.5 18l1.5 1.5L7.5 17"/>',
];
$icone_casa = '<path d="M3 21h18M5 21V10l7-5 7 5v11M9 21v-6h6v6"/>';
?>
<div class="hscroll__janela">
        <ol class="hscroll__trilho" data-hscroll-trilho aria-label="Etapas da obra, do terreno aos acabamentos">
<?php foreach ($lista_passos as $i => $p):
    $foto = trim((string) $p['imagem']);
    $svg  = $icone_casa;
    foreach ($icones_etapa as $palavra => $desenho) {
        if (mb_stripos((string) $p['titulo'], $palavra) !== false) {
            $svg = $desenho;
            break;
        }
    }
?>
          <li class="hpasso<?= $foto === '' ? ' hpasso--ico' : '' ?>">
            <div class="hpasso__foto">
<?php if ($foto !== ''): ?>
              <img src="<?= e($foto) ?>" alt="<?= e((string) $p['imagem_alt']) ?>" loading="lazy" />
<?php else: ?>
              <svg viewBox="0 0 24 24" aria-hidden="true"><?= $svg ?></svg>
<?php endif; ?>
              <span class="hpasso__num"><?= sprintf('%02d', $i + 1) ?></span>
            </div>
            <h3><?= e($p['titulo']) ?></h3>
<?php if (trim((string) $p['texto']) !== ''): ?>
            <p><?= e($p['texto']) ?></p>
<?php endif; ?>
          </li>
<?php endforeach; ?>
        </ol>
      </div>
      <div class="hscroll__barra" aria-hidden="true"><span class="hscroll__fill" data-hscroll-fill></span></div>
      <p class="hscroll__dica">Role para ver as <?= $total_passos ?> etapas da obra.</p>
