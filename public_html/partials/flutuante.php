<?php
/**
 * Botao flutuante de orcamento. Aparece depois que o topo sai da tela
 * (js/main.js) e abre o modal pelo data-quote-open. Espera: nada.
 */
declare(strict_types=1);
?>
  <!-- Botão flutuante: abre o formulário de orçamento -->
  <button type="button" class="wpp-float" id="wppFloat" aria-label="Pedir orçamento" data-quote-open>
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H8.6L4 20.5V5a1 1 0 0 1 1-1Zm3 5h10v1.7H7V9Zm0 3.6h6.6v1.7H7v-1.7Z"/></svg>
    <span class="wpp-float__label">Pedir orçamento</span>
  </button>
