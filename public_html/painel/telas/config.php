<?php
/**
 * Configuracoes do site e do conector do CRM (tabela config). Espera: nada.
 */
declare(strict_types=1);

auth_iniciar();
$rascunho = $_SESSION['painel_config'] ?? null;
unset($_SESSION['painel_config']);

$erros = is_array($rascunho) ? (array) ($rascunho['erros'] ?? []) : [];
$sujos = is_array($rascunho) ? (array) ($rascunho['valores'] ?? []) : [];

$ativos = (int) db()->query('SELECT COUNT(*) FROM videos WHERE ativo = 1')->fetchColumn();
?>
<h1>Configurações</h1>
<p class="p-sub">Você tem <?= $ativos ?> vídeos ativos. A home mostra os primeiros da ordem definida na tela Vídeos.</p>

<form class="p-form" method="post" action="acoes/config.php">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>" />

<?php foreach (painel_config_campos() as $chave => $campo):
    $valor = array_key_exists($chave, $sujos) ? (string) $sujos[$chave] : (string) config_ler($chave, '');
    $erro  = (string) ($erros[$chave] ?? '');
    $idc   = 'k-' . $chave;
?>
  <div class="p-campo<?= $erro !== '' ? ' p-campo--erro' : '' ?>">
    <label for="<?= e($idc) ?>"><?= e($campo['rotulo']) ?></label>

<?php if ($campo['tipo'] === 'numero'): ?>
    <input type="number" id="<?= e($idc) ?>" name="<?= e($chave) ?>" value="<?= e($valor) ?>"
           min="<?= (int) ($campo['min'] ?? 1) ?>" max="<?= (int) ($campo['max'] ?? 24) ?>" step="1" />

<?php elseif ($campo['tipo'] === 'sim_nao'): ?>
    <input type="hidden" name="<?= e($chave) ?>" value="0" />
    <input type="checkbox" id="<?= e($idc) ?>" name="<?= e($chave) ?>" value="1"<?= $valor === '1' ? ' checked' : '' ?> />

<?php elseif ($campo['tipo'] === 'selecao'): ?>
    <select id="<?= e($idc) ?>" name="<?= e($chave) ?>">
<?php foreach ($campo['opcoes'] as $opcao => $rotulo): ?>
      <option value="<?= e((string) $opcao) ?>"<?= (string) $opcao === $valor ? ' selected' : '' ?>><?= e($rotulo) ?></option>
<?php endforeach; ?>
    </select>

<?php else: ?>
    <input type="text" id="<?= e($idc) ?>" name="<?= e($chave) ?>" value="<?= e($valor) ?>" />
<?php endif; ?>

<?php if (!empty($campo['ajuda'])): ?>
    <span class="p-ajuda"><?= e($campo['ajuda']) ?></span>
<?php endif; ?>
<?php if ($erro !== ''): ?>
    <p class="p-erro"><?= e($erro) ?></p>
<?php endif; ?>
  </div>
<?php endforeach; ?>

  <button class="p-btn p-btn--forte" type="submit">Salvar configurações</button>
</form>
