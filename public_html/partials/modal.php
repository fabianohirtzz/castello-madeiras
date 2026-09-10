<?php
/**
 * Modal de orcamento, lightbox dos reels e botao flutuante.
 * Compartilhado por todas as paginas, menos a de contato, que embute o mesmo
 * formulario direto na pagina (partials/formulario.php) e por isso nao pode
 * incluir este arquivo: os ids duplicariam.
 *
 * Espera: string $pagina (mesma chave da nav): muda o titulo do modal e a
 * lista de modelos do select.
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/conteudo.php';

$pagina          = $pagina ?? 'home';
$modal_flex      = $pagina === 'flex';
$form_modalidade = $modal_flex ? 'flex' : 'pronta';
$modal_videos    = count(videos());
?>
  <!-- ============ MODAL DE ORÇAMENTO ============ -->
  <div class="qmodal" id="quoteModal" hidden role="dialog" aria-modal="true" aria-labelledby="qmodalTitle">
    <div class="qmodal__backdrop" data-quote-close></div>

    <div class="qmodal__panel">
      <button class="qmodal__close" type="button" data-quote-close aria-label="Fechar formulário">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
      </button>

      <div class="qmodal__head">
        <span class="eyebrow">Orçamento sem compromisso</span>
<?php if ($modal_flex): ?>
        <h2 id="qmodalTitle">Vamos falar da sua Castelo Flex</h2>
        <p>Conta pra gente o terreno e o tamanho que você imagina. A Castello volta com a tabela da Flex e o prazo.</p>
<?php else: ?>
        <h2 id="qmodalTitle">Vamos falar da sua casa</h2>
        <p>Conta pra gente o que você procura. A Castello volta com uma proposta sob medida.</p>
<?php endif; ?>
        <div class="qmodal__trust">
          <span><strong>5,0 ★</strong> no Google</span>
          <span><strong>56</strong> avaliações</span>
          <span><strong>12 anos</strong> de mercado</span>
        </div>
      </div>

<?php include __DIR__ . '/formulario.php'; ?>
    </div>
  </div>

<?php if ($pagina === 'home'): ?>
  <!-- ============ LIGHTBOX DOS REELS (tela cheia) ============ -->
  <div class="reelbox" id="reelbox" hidden role="dialog" aria-modal="true" aria-label="Vídeos do Instagram em tela cheia">
    <button class="reelbox__close" id="reelboxClose" type="button" aria-label="Fechar tela cheia">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
    </button>

    <button class="reelbox__nav reelbox__nav--prev" id="reelboxPrev" type="button" aria-label="Vídeo anterior">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
    </button>

    <div class="reelbox__stage">
      <video class="reelbox__video" id="reelboxVideo" playsinline controls loop preload="auto"></video>
    </div>

    <button class="reelbox__nav reelbox__nav--next" id="reelboxNext" type="button" aria-label="Próximo vídeo">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
    </button>

    <div class="reelbox__count"><span id="reelboxCount">1 / <?= $modal_videos ?></span></div>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/flutuante.php'; ?>
