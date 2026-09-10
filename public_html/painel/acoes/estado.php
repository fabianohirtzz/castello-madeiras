<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../tabelas.php';

auth_exigir();

$tela   = (string) ($_POST['tela'] ?? '');
$id     = (int) ($_POST['id'] ?? 0);
$ativo  = ((string) ($_POST['ativo'] ?? '')) === '1' ? 1 : 0;
$filtro = (string) ($_POST['filtro'] ?? '');

if (painel_tabela($tela) === null) {
    header('Location: ../painel.php?erro=' . rawurlencode('Seção desconhecida.'));
    exit;
}

$lista = '../painel.php?tela=' . rawurlencode($tela) . ($filtro !== '' ? '&filtro=' . rawurlencode($filtro) : '');

if (!csrf_validar($_POST['csrf'] ?? null)) {
    header('Location: ' . $lista . '&erro=' . rawurlencode('A sessão expirou. Entre de novo e repita.'));
    exit;
}

if ($id <= 0 || painel_linha($tela, $id) === null) {
    header('Location: ' . $lista . '&erro=' . rawurlencode('Este item não existe mais.'));
    exit;
}

painel_estado($tela, $id, $ativo);

$recado = $ativo === 1
    ? 'Item reativado. Já aparece no site.'
    : 'Item desativado. Saiu do site, mas continua guardado aqui.';

header('Location: ' . $lista . '&ok=' . rawurlencode($recado));
exit;
