<?php
/**
 * Modal de orcamento, lightbox dos reels e botao flutuante.
 * Compartilhado pela home e pela pagina Flex. Espera: nada.
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/conteudo.php';

$modal_modelos = modelos('pronta');
$modal_videos  = count(videos());
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
        <h2 id="qmodalTitle">Vamos falar da sua casa</h2>
        <p>Conta pra gente o que você procura. A Castello volta com uma proposta sob medida.</p>
        <div class="qmodal__trust">
          <span><strong>5,0 ★</strong> no Google</span>
          <span><strong>56</strong> avaliações</span>
          <span><strong>12 anos</strong> de mercado</span>
        </div>
      </div>

      <form class="qform" id="quoteForm" novalidate>
        <input type="hidden" name="csrf" value="" />
        <!-- honeypot anti-spam: humanos não veem, não preencher -->
        <input type="text" name="_gotcha" class="qform__hp" tabindex="-1" autocomplete="off" aria-hidden="true" />

        <div class="qform__row">
          <div class="qform__group">
            <label class="qform__label" for="q-nome">Nome completo <span class="req">*</span></label>
            <input class="qform__input" type="text" id="q-nome" name="nome" placeholder="Como podemos te chamar" autocomplete="name" required />
          </div>
          <div class="qform__group">
            <label class="qform__label" for="q-whatsapp">WhatsApp <span class="req">*</span></label>
            <input class="qform__input" type="tel" id="q-whatsapp" name="whatsapp" placeholder="(48) 99999-9999" autocomplete="tel" inputmode="tel" required />
          </div>
        </div>

        <div class="qform__group">
          <label class="qform__label" for="q-busca">O que você busca? <span class="req">*</span></label>
          <div class="qform__select">
            <select id="q-busca" name="busca" required>
              <option value="" selected disabled>Selecione uma opção</option>
              <option value="Modelo pronto do catálogo">Modelo pronto do catálogo</option>
              <option value="Projeto exclusivo (planta sob medida)">Projeto exclusivo (planta sob medida)</option>
              <option value="Já tenho o projeto, quero orçar a execução">Já tenho o projeto, quero orçar a execução</option>
              <option value="Ainda estou pesquisando">Ainda estou pesquisando</option>
            </select>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
          </div>
        </div>

        <div class="qform__group qform__group--modelo" id="qGroupModelo" hidden>
          <label class="qform__label" for="q-modelo">Modelo de interesse</label>
          <div class="qform__select">
            <select id="q-modelo" name="modelo">
              <option value="">Ainda não sei</option>
<?php foreach ($modal_modelos as $m):
    $rotulo = $m['nome'] . ' · ' . str_replace(',00 ', ' ', (string) $m['area']) . ' · R$ ' . $m['preco'];
?>
              <option value="<?= e($rotulo) ?>"><?= e($rotulo) ?></option>
<?php endforeach; ?>
            </select>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
          </div>
        </div>

        <div class="qform__group">
          <label class="qform__label" for="q-cidade">Cidade / região do terreno</label>
          <input class="qform__input" type="text" id="q-cidade" name="cidade" placeholder="Ex: Tubarão / SC" />
        </div>

        <div class="qform__group">
          <label class="qform__label" for="q-mensagem">Mensagem <span class="opt">(opcional)</span></label>
          <textarea class="qform__input qform__textarea" id="q-mensagem" name="mensagem" rows="3" placeholder="Conte o tamanho que imagina, se já tem terreno, prazo desejado..."></textarea>
        </div>

        <button class="btn btn--primary btn--block btn--lg qform__submit" type="submit" id="quoteSubmit">Enviar meu pedido</button>
        <p class="qform__status" id="quoteStatus" role="status" aria-live="polite"></p>
        <p class="qform__note">Resposta em até 1 dia útil. Seus dados são usados só para responder ao seu pedido.</p>
      </form>

      <div class="qmodal__done" id="quoteDone" hidden>
        <span class="qmodal__done-ico">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m4 12 5 5L20 6"/></svg>
        </span>
        <h3>Pedido pronto pra enviar</h3>
        <p id="quoteDoneMsg">Toque no botão abaixo para mandar seu pedido no WhatsApp da Castello. É só apertar enviar.</p>
        <a class="btn btn--primary btn--lg qmodal__done-cta" id="quoteWppLink" href="https://wa.me/5548998244494" target="_blank" rel="noopener">
          <svg viewBox="0 0 24 24" class="ico-wpp" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1-.2.2-.6.8-.8 1-.1.2-.3.2-.5.1-.7-.3-1.4-.7-2-1.4-.4-.5-.8-1.1-.9-1.3-.1-.2 0-.4.1-.5l.4-.4c.1-.2.2-.3.2-.5 0-.2 0-.3-.1-.5l-.8-1.9c-.2-.5-.4-.4-.6-.4h-.5c-.2 0-.4.1-.6.3-.2.2-.8.8-.8 2 0 1.2.8 2.3 1 2.5.1.2 1.7 2.6 4 3.5 1.4.6 1.9.6 2.6.5.4 0 1.3-.5 1.5-1.1.2-.5.2-1 .1-1.1-.1-.1-.2-.1-.4-.2Z"/></svg>
          Enviar no WhatsApp
        </a>
        <button class="qmodal__done-back" type="button" id="quoteBack">Voltar e revisar os dados</button>
      </div>
    </div>
  </div>

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

  <!-- Botão flutuante: abre o formulário de orçamento -->
  <button type="button" class="wpp-float" id="wppFloat" aria-label="Pedir orçamento" data-quote-open>
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H8.6L4 20.5V5a1 1 0 0 1 1-1Zm3 5h10v1.7H7V9Zm0 3.6h6.6v1.7H7v-1.7Z"/></svg>
    <span class="wpp-float__label">Pedir orçamento</span>
  </button>
