<?php
/**
 * Lista generica de uma tela de conteudo. Espera: string $tela, array $def.
 * Serve as seis telas descritas em painel/tabelas.php.
 */
declare(strict_types=1);

$filtro = null;
if (isset($def['filtro'])) {
    $pedido = (string) ($_GET['filtro'] ?? $def['filtro']['padrao']);
    $filtro = isset($def['filtro']['opcoes'][$pedido]) ? $pedido : $def['filtro']['padrao'];
}

$linhas = painel_listar($tela, $filtro);
$ativos = 0;
foreach ($linhas as $linha) {
    $ativos += (int) $linha['ativo'] === 1 ? 1 : 0;
}
?>
<h1><?= e($def['rotulo']) ?></h1>
<p class="p-sub">
  <?= count($linhas) ?> <?= count($linhas) === 1 ? 'item' : 'itens' ?>, <?= $ativos ?> aparecendo no site.
<?php if ($tela === 'videos'): ?>
  A home mostra os <?= e((string) config_ler('videos_na_home', '8')) ?> primeiros da ordem abaixo.
  Para trocar quais aparecem, arraste os que você quer para o topo.
<?php endif; ?>
</p>

<div class="p-barra">
  <a class="p-btn p-btn--forte" href="painel.php?tela=<?= e($tela) ?>&amp;novo=1<?= $filtro !== null ? '&amp;filtro=' . e($filtro) : '' ?>">
    Adicionar <?= e($def['singular']) ?>
  </a>

<?php if (isset($def['filtro'])): ?>
<?php foreach ($def['filtro']['opcoes'] as $valor => $rotulo): ?>
  <a class="p-btn<?= $valor === $filtro ? ' p-btn--forte' : ' p-btn--fraco' ?>"
     href="painel.php?tela=<?= e($tela) ?>&amp;filtro=<?= e((string) $valor) ?>"><?= e($rotulo) ?></a>
<?php endforeach; ?>
<?php endif; ?>
</div>

<?php if ($linhas === []): ?>
<p class="p-sub">Nada cadastrado aqui ainda. Toque em Adicionar <?= e($def['singular']) ?> para começar.</p>
<?php else: ?>
<p class="p-aviso" id="pOrdemAviso" role="status" hidden></p>
<ul class="p-lista" id="pLista" data-tabela="<?= e($tela) ?>" data-csrf="<?= e(csrf_token()) ?>">
<?php foreach ($linhas as $linha):
    $inativo = (int) $linha['ativo'] !== 1;
    $mini    = isset($def['miniatura']) ? (string) ($linha[$def['miniatura']] ?? '') : '';
?>
  <li class="p-item<?= $inativo ? ' is-inativo' : '' ?>" data-id="<?= (int) $linha['id'] ?>">
    <span class="p-pega" title="Arraste para mudar a ordem" aria-hidden="true">≡</span>

<?php if ($mini !== ''): ?>
    <img class="p-mini" src="../<?= e($mini) ?>" alt="" loading="lazy" />
<?php elseif (isset($def['miniatura'])): ?>
    <span class="p-mini" aria-hidden="true"></span>
<?php endif; ?>

    <span class="p-dados">
<?php $primeiro = true; foreach ($def['resumo'] as $coluna => $rotulo): ?>
<?php if ($primeiro): $primeiro = false; ?>
      <strong><?= e((string) ($linha[$coluna] ?? '')) ?><?= $inativo ? ' <span class="p-tag">desativado</span>' : '' ?></strong>
<?php else: ?>
      <span><?= e($rotulo) ?>: <?= e((string) ($linha[$coluna] ?? '')) ?></span>
<?php endif; ?>
<?php endforeach; ?>
    </span>

    <span class="p-acoes">
      <a class="p-btn" href="painel.php?tela=<?= e($tela) ?>&amp;editar=<?= (int) $linha['id'] ?><?= $filtro !== null ? '&amp;filtro=' . e($filtro) : '' ?>">Editar</a>
      <form method="post" action="acoes/estado.php">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>" />
        <input type="hidden" name="tela" value="<?= e($tela) ?>" />
        <input type="hidden" name="filtro" value="<?= e((string) $filtro) ?>" />
        <input type="hidden" name="id" value="<?= (int) $linha['id'] ?>" />
        <input type="hidden" name="ativo" value="<?= $inativo ? '1' : '0' ?>" />
        <button class="p-btn p-btn--fraco" type="submit"><?= $inativo ? 'Reativar' : 'Desativar' ?></button>
      </form>
    </span>
  </li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
