<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../tabelas.php';

auth_exigir();

$tela   = (string) ($_POST['tela'] ?? '');
$id     = (int) ($_POST['id'] ?? 0);
$filtro = (string) ($_POST['filtro'] ?? '');
$def    = painel_tabela($tela);

if ($def === null) {
    header('Location: ../painel.php?erro=' . rawurlencode('Seção desconhecida.'));
    exit;
}

$lista = '../painel.php?tela=' . rawurlencode($tela) . ($filtro !== '' ? '&filtro=' . rawurlencode($filtro) : '');
$form  = '../painel.php?tela=' . rawurlencode($tela) . ($id > 0 ? '&editar=' . $id : '&novo=1');

if (!csrf_validar($_POST['csrf'] ?? null)) {
    header('Location: ' . $lista . '&erro=' . rawurlencode('A sessão expirou. Entre de novo e repita o envio.'));
    exit;
}

$arquivos = painel_arquivos($def, $_FILES);
$valores  = array_merge(painel_valores($def, $_POST), $arquivos['valores']);

// Campo de arquivo que veio vazio mantem o que ja estava gravado.
$atual = $id > 0 ? painel_linha($tela, $id) : null;
foreach ($def['campos'] as $coluna => $campo) {
    if (in_array($campo['tipo'], ['imagem', 'video'], true) && !isset($valores[$coluna])) {
        $valores[$coluna] = (string) ($atual[$coluna] ?? '');
    }
}

$erros = array_merge(painel_erros($def, $valores), $arquivos['erros']);

if ($erros !== []) {
    auth_iniciar();
    $_SESSION['painel_form'] = ['tela' => $tela, 'id' => $id, 'valores' => $valores, 'erros' => $erros];
    header('Location: ' . $form);
    exit;
}

painel_salvar($tela, $id > 0 ? $id : null, $valores);

$recado = $id > 0
    ? 'Alterações salvas.'
    : mb_strtoupper(mb_substr($def['singular'], 0, 1)) . mb_substr($def['singular'], 1) . ' adicionado.';

header('Location: ' . $lista . '&ok=' . rawurlencode($recado));
exit;
