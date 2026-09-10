<?php
/**
 * O formulario de orcamento e a tela de sucesso. Um so no site inteiro:
 * dentro do modal (partials/modal.php) nas paginas em geral, e embutido na
 * pagina de contato. Os ids sao os mesmos nos dois lugares, por isso uma
 * pagina nunca inclui os dois.
 *
 * Espera: string $form_modalidade ('pronta' ou 'flex'), que escolhe a lista de
 * modelos do select; array $form_opcoes_extra (opcional), rotulos a mais no
 * fim da lista.
 *
 * Os campos ocultos do contrato 6.1 nascem vazios de proposito: quem preenche
 * e o js/formulario.js, na abertura do modal ou no carregamento da pagina de
 * contato. O token de csrf nunca e impresso no HTML, para a pagina continuar
 * cacheavel.
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/conteudo.php';

$form_modalidade   = $form_modalidade ?? 'pronta';
$form_opcoes_extra = $form_opcoes_extra ?? [];
$form_flex         = $form_modalidade === 'flex';

$form_opcoes = [];
foreach (modelos($form_flex ? 'flex' : 'pronta') as $m) {
    $area  = str_replace(',00 ', ' ', (string) $m['area']);
    $preco = trim((string) $m['preco']);
    $valor = $m['nome'] . ' · ' . $area . ' · ' . ($preco !== '' ? 'R$ ' . $preco : 'semipronta');
    $form_opcoes[] = [$valor, $preco !== '' ? $valor : $m['nome'] . ' · ' . $area];
}
if ($form_flex) {
    $form_opcoes[] = ['Quero a Casa Pronta, chave na mão', 'Quero a Casa Pronta, chave na mão'];
}
foreach ($form_opcoes_extra as $extra) {
    $form_opcoes[] = [(string) $extra, (string) $extra];
}
?>
      <form class="qform" id="quoteForm" method="post" action="enviar.php" novalidate>
        <!-- honeypot anti-spam (contrato 6.1: precisa chegar vazio). Humanos não veem. -->
        <input type="text" name="empresa" class="qform__hp" tabindex="-1" autocomplete="off" aria-hidden="true" />

        <!-- Ocultos do contrato 6.1, preenchidos pelo js/formulario.js. -->
        <input type="hidden" name="pagina" value="" />
        <input type="hidden" name="referrer" value="" />
        <input type="hidden" name="utm_source" value="" />
        <input type="hidden" name="utm_medium" value="" />
        <input type="hidden" name="utm_campaign" value="" />
        <input type="hidden" name="utm_term" value="" />
        <input type="hidden" name="utm_content" value="" />
        <input type="hidden" name="ts" value="" />
        <input type="hidden" name="csrf" value="" />

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

        <div class="qform__group">
          <label class="qform__label" for="q-prazo">Quando pretende iniciar a obra? <span class="opt">(opcional)</span></label>
          <div class="qform__select">
            <select id="q-prazo" name="prazo">
              <option value="">Prefiro não dizer agora</option>
              <option value="Imediato">Imediato</option>
              <option value="Até 3 meses">Até 3 meses</option>
              <option value="Até 6 meses">Até 6 meses</option>
              <option value="Só pesquisando">Só pesquisando</option>
            </select>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
          </div>
        </div>

        <div class="qform__group qform__group--modelo" id="qGroupModelo"<?= $form_flex ? '' : ' hidden' ?>>
          <label class="qform__label" for="q-modelo">Modelo de interesse</label>
          <div class="qform__select">
            <select id="q-modelo" name="modelo">
              <option value="">Ainda não sei</option>
<?php foreach ($form_opcoes as [$valor, $rotulo]): ?>
              <option value="<?= e($valor) ?>"><?= e($rotulo) ?></option>
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
