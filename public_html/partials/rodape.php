<?php
/**
 * Rodape com a marca, contato, atendimento e o mapa. As paginas ficam so no
 * menu do topo (a lista saiu do rodape em 2026-10 para o mapa ganhar espaco).
 * Espera: string $pagina (mesma chave da nav). Na pagina de contato o mapa do
 * rodape some: ela ja traz o mapa grande.
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/conteudo.php';

$pagina = $pagina ?? 'home';
?>
  <!-- ============ FOOTER / CONTATO ============ -->
  <footer class="footer" id="contato">
    <div class="container footer__grid<?= $pagina === 'contato' ? ' footer__grid--sem-mapa' : '' ?>">
      <div class="footer__brand">
        <img src="images/logo-vertical-branco.png" alt="Castello Casas de Madeira" class="footer__logo" />
        <p>Casas de madeira em Tubarão e região desde 2014. Casa Pronta e Castello Flex, da obra ao pós-venda numa empresa só.</p>
        <div class="footer__social">
          <a href="https://www.instagram.com/castellocasasdemadeira/" class="footer__social-btn" target="_blank" rel="noopener" aria-label="Instagram da Castello">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.2c3.2 0 3.6 0 4.85.07 1.17.05 1.8.25 2.23.41.56.22.96.48 1.38.9.42.42.68.82.9 1.38.16.42.36 1.06.41 2.23.06 1.27.07 1.65.07 4.85s0 3.58-.07 4.85c-.05 1.17-.25 1.8-.41 2.23-.22.56-.48.96-.9 1.38-.42.42-.82.68-1.38.9-.42.16-1.06.36-2.23.41-1.27.06-1.65.07-4.85.07s-3.58 0-4.85-.07c-1.17-.05-1.8-.25-2.23-.41a3.7 3.7 0 0 1-1.38-.9 3.7 3.7 0 0 1-.9-1.38c-.16-.42-.36-1.06-.41-2.23C2.21 15.58 2.2 15.2 2.2 12s0-3.58.07-4.85c.05-1.17.25-1.8.41-2.23.22-.56.48-.96.9-1.38.42-.42.82-.68 1.38-.9.42-.16 1.06-.36 2.23-.41C8.42 2.21 8.8 2.2 12 2.2Zm0 4.87a4.93 4.93 0 1 0 0 9.86 4.93 4.93 0 0 0 0-9.86Zm0 1.8a3.13 3.13 0 1 1 0 6.26 3.13 3.13 0 0 1 0-6.26Zm5.13-3.24a1.15 1.15 0 1 0 0 2.3 1.15 1.15 0 0 0 0-2.3Z"/></svg>
          </a>
          <a href="https://www.facebook.com/castellomadeiras" class="footer__social-btn" target="_blank" rel="noopener" aria-label="Facebook da Castello">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 12a10 10 0 1 0-11.56 9.88v-6.99H7.9V12h2.54V9.8c0-2.5 1.49-3.89 3.78-3.89 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56V12h2.78l-.44 2.89h-2.34v6.99A10 10 0 0 0 22 12Z"/></svg>
          </a>
        </div>
      </div>

      <div class="footer__col">
        <h4>Contato</h4>
        <ul class="footer__list">
          <li>
            <span class="footer__ico"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H8.6L4 20.5V5a1 1 0 0 1 1-1Zm3 5h10v1.7H7V9Zm0 3.6h6.6v1.7H7v-1.7Z"/></svg></span>
            <button type="button" class="footer__link" data-quote-open>Pedir orçamento pelo formulário</button>
          </li>
          <li>
            <span class="footer__ico"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a7 7 0 0 0-7 7c0 5.2 7 13 7 13s7-7.8 7-13a7 7 0 0 0-7-7Zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5Z"/></svg></span>
            <a href="<?= e(CASTELLO_MAPA_LINK) ?>" target="_blank" rel="noopener"><?= e(CASTELLO_ENDERECO_RUA) ?><br><?= e(CASTELLO_ENDERECO_BAIRRO) ?><br><?= e(CASTELLO_ENDERECO_CIDADE) ?></a>
          </li>
        </ul>
      </div>

      <div class="footer__col">
        <h4>Atendimento</h4>
        <ul class="footer__list">
          <li>
            <span class="footer__ico"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10 10-4.5 10-10S17.5 2 12 2Zm0 18c-4.4 0-8-3.6-8-8s3.6-8 8-8 8 3.6 8 8-3.6 8-8 8Zm.5-13H11v6l5.2 3.2.8-1.3-4.5-2.7V7Z"/></svg></span>
            <span>Segunda a sexta<br>8h às 12h e 13h30 às 18h</span>
          </li>
          <li>
            <span class="footer__ico"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 2h2v2h6V2h2v2h1.5A2.5 2.5 0 0 1 21 6.5v12A2.5 2.5 0 0 1 18.5 21h-13A2.5 2.5 0 0 1 3 18.5v-12A2.5 2.5 0 0 1 5.5 4H7V2ZM5 9v9.5c0 .3.2.5.5.5h13c.3 0 .5-.2.5-.5V9H5Z"/></svg></span>
            <span>Sábado<br>8h30 às 12h</span>
          </li>
        </ul>
      </div>

<?php if ($pagina !== 'contato'): ?>
      <div class="footer__map">
        <iframe
          src="<?= e(mapa_embed()) ?>"
          title="Localização da Castello Casas de Madeira em Tubarão, SC"
          loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
      </div>
<?php endif; ?>
    </div>

    <div class="footer__bottom container">
      <span class="footer__copy">© 2026 Castello Casas de Madeira. <span class="nowrap">Todos os direitos reservados.</span></span>
      <span class="footer__assina">
        <span class="nowrap"><span aria-hidden="true">🏡</span> Não construímos casas, mas construímos sites!</span>
        <span class="nowrap"><span aria-hidden="true">🏗️</span> Construído por: <a href="https://www.freelainhome.com.br/" target="_blank" rel="noopener">Freela In Home</a>.</span>
      </span>
    </div>
  </footer>
