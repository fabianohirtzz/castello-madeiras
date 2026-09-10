<?php
/**
 * Formulario generico de adicionar e editar. Espera: string $tela, array $def.
 * Os campos saem da descricao em painel/tabelas.php.
 */
declare(strict_types=1);

$id    = (int) ($_GET['editar'] ?? 0);
$linha = $id > 0 ? painel_linha($tela, $id) : null;

if ($id > 0 && $linha === null) {
    echo '<p class="p-aviso p-aviso--erro">Este item não existe mais.</p>';
    echo '<p><a class="p-btn" href="painel.php?tela=' . e($tela) . '">Voltar para a lista</a></p>';
    return;
}

auth_iniciar();
$rascunho = $_SESSION['painel_form'] ?? null;
unset($_SESSION['painel_form']);

$valores = $linha ?? [];
$erros   = [];

if (is_array($rascunho) && ($rascunho['tela'] ?? '') === $tela && (int) ($rascunho['id'] ?? -1) === $id) {
    $valores = array_merge($valores, $rascunho['valores']);
    $erros   = $rascunho['erros'];
}

$filtro = (string) ($_GET['filtro'] ?? '');
$voltar = 'painel.php?tela=' . rawurlencode($tela) . ($filtro !== '' ? '&filtro=' . rawurlencode($filtro) : '');
?>
<h1><?= $id > 0 ? 'Editar' : 'Adicionar' ?> <?= e($def['singular']) ?></h1>
<p class="p-sub"><a href="<?= e($voltar) ?>">Voltar para <?= e($def['rotulo']) ?></a></p>

<?php if ($erros !== []): ?>
<p class="p-aviso p-aviso--erro" role="alert">Faltou alguma coisa. Veja os campos marcados abaixo.</p>
<?php endif; ?>

<form class="p-form" method="post" action="acoes/salvar.php" enctype="multipart/form-data">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>" />
  <input type="hidden" name="tela" value="<?= e($tela) ?>" />
  <input type="hidden" name="id" value="<?= $id ?>" />
  <input type="hidden" name="filtro" value="<?= e($filtro) ?>" />

<?php foreach ($def['campos'] as $coluna => $campo):
    $valor = (string) ($valores[$coluna] ?? '');
    $erro  = (string) ($erros[$coluna] ?? '');
    $idc   = 'c-' . $coluna;
    $arquivo = in_array($campo['tipo'], ['imagem', 'video'], true);
?>
  <div class="p-campo<?= $erro !== '' ? ' p-campo--erro' : '' ?>">
<?php if ($campo['tipo'] === 'icone_faq'): ?>
    <strong><?= e($campo['rotulo']) ?><?= !empty($campo['obrigatorio']) ? ' *' : '' ?></strong>
<?php else: ?>
    <label for="<?= e($idc) ?>"><?= e($campo['rotulo']) ?><?= !empty($campo['obrigatorio']) ? ' *' : '' ?></label>
<?php endif; ?>

<?php if ($campo['tipo'] === 'texto'): ?>
    <input type="text" id="<?= e($idc) ?>" name="<?= e($coluna) ?>" value="<?= e($valor) ?>" />

<?php elseif ($campo['tipo'] === 'texto_longo'): ?>
    <textarea id="<?= e($idc) ?>" name="<?= e($coluna) ?>" rows="5"><?= e($valor) ?></textarea>

<?php elseif ($campo['tipo'] === 'numero'): ?>
    <input type="number" id="<?= e($idc) ?>" name="<?= e($coluna) ?>" value="<?= e($valor) ?>" min="0" step="1" />

<?php elseif ($campo['tipo'] === 'selecao'): ?>
    <select id="<?= e($idc) ?>" name="<?= e($coluna) ?>">
<?php foreach ($campo['opcoes'] as $opcao => $rotulo): ?>
      <option value="<?= e((string) $opcao) ?>"<?= (string) $opcao === $valor ? ' selected' : '' ?>><?= e($rotulo) ?></option>
<?php endforeach; ?>
    </select>

<?php elseif ($campo['tipo'] === 'sim_nao'): ?>
    <input type="hidden" name="<?= e($coluna) ?>" value="0" />
    <input type="checkbox" id="<?= e($idc) ?>" name="<?= e($coluna) ?>" value="1"<?= $valor === '1' ? ' checked' : '' ?> />

<?php elseif ($campo['tipo'] === 'icone_faq'): $marcado = $valor !== '' ? $valor : 'relogio'; ?>
    <div class="p-icones">
<?php foreach (CASTELLO_ICONES_FAQ as $chaveIcone => $svg): ?>
      <label class="p-icone" title="<?= e($chaveIcone) ?>">
        <input type="radio" name="<?= e($coluna) ?>" value="<?= e($chaveIcone) ?>"<?= $marcado === $chaveIcone ? ' checked' : '' ?> />
        <?= $svg ?>
        <span class="p-icone-nome" hidden><?= e($chaveIcone) ?></span>
      </label>
<?php endforeach; ?>
    </div>

<?php elseif ($arquivo): ?>
<?php if ($valor !== ''): ?>
    <span class="p-atual">
<?php if ($campo['tipo'] === 'video'): ?>
      <video src="../<?= e($valor) ?>" muted playsinline preload="metadata"></video>
<?php else: ?>
      <img src="../<?= e($valor) ?>" alt="" />
<?php endif; ?>
      Já enviado. Escolha outro arquivo só se quiser trocar.
    </span>
<?php endif; ?>
    <input type="file" id="<?= e($idc) ?>" name="<?= e($coluna) ?>"
           accept="<?= $campo['tipo'] === 'video' ? 'video/mp4' : 'image/jpeg,image/png,image/webp' ?>" />
<?php endif; ?>

<?php if (!empty($campo['ajuda'])): ?>
    <span class="p-ajuda"><?= e($campo['ajuda']) ?></span>
<?php endif; ?>
<?php if ($erro !== ''): ?>
    <p class="p-erro"><?= e($erro) ?></p>
<?php endif; ?>
  </div>
<?php endforeach; ?>

  <button class="p-btn p-btn--forte" type="submit">Salvar</button>
  <a class="p-btn p-btn--fraco" href="<?= e($voltar) ?>">Cancelar</a>
</form>
