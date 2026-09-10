<?php
/**
 * Trocar a senha do painel. Espera: nada.
 */
declare(strict_types=1);
?>
<h1>Trocar senha</h1>
<p class="p-sub">A senha precisa ter pelo menos 8 caracteres. Se você esquecer, quem redefine é a Freela In Home.</p>

<form class="p-form" method="post" action="acoes/senha.php" autocomplete="off">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>" />

  <div class="p-campo">
    <label for="s-atual">Senha atual</label>
    <input type="password" id="s-atual" name="atual" autocomplete="current-password" required />
  </div>

  <div class="p-campo">
    <label for="s-nova">Senha nova</label>
    <input type="password" id="s-nova" name="nova" autocomplete="new-password" minlength="8" required />
  </div>

  <div class="p-campo">
    <label for="s-confirma">Repita a senha nova</label>
    <input type="password" id="s-confirma" name="confirma" autocomplete="new-password" minlength="8" required />
  </div>

  <button class="p-btn p-btn--forte" type="submit">Trocar senha</button>
</form>
