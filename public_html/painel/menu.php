<?php
/**
 * Menu lateral do painel. Espera $tela, a chave da tela aberta.
 * No celular vira gaveta: o botao em painel.php aponta para o id pLado.
 */
declare(strict_types=1);

require_once __DIR__ . '/tabelas.php';

$telaAtual = (string) ($tela ?? '');
?>
<aside class="p-lado" id="pLado">
  <a class="p-lado__marca" href="painel.php">
    <img src="../images/logo-horizontal-branco.png" alt="Castello Casas de Madeira" />
    <span>Painel</span>
  </a>

  <nav class="p-lado__nav" aria-label="Seções do painel">
<?php foreach (painel_grupos() as $grupo): ?>
    <p class="p-lado__grupo"><?= e($grupo['titulo']) ?></p>
<?php foreach ($grupo['itens'] as $chave => $rotulo): ?>
    <a class="p-lado__link<?= $chave === $telaAtual ? ' is-ativo' : '' ?>" href="painel.php?tela=<?= e($chave) ?>"<?= $chave === $telaAtual ? ' aria-current="page"' : '' ?>><?= painel_icone($chave) ?><span><?= e($rotulo) ?></span></a>
<?php endforeach; ?>
<?php endforeach; ?>
  </nav>

  <div class="p-lado__pe">
    <a class="p-lado__link" href="sair.php">
      <svg class="p-lado__icone" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M10 20H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h4"/><path d="M15.5 8.5 19 12l-3.5 3.5"/><path d="M19 12H9.5"/></svg>
      <span>Sair</span>
    </a>
  </div>
</aside>
