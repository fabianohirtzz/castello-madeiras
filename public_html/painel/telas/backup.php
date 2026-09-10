<?php
/**
 * Tela de backup. Espera: nada.
 */
declare(strict_types=1);

$temZip = class_exists('ZipArchive');

$tamanhoBanco = is_file(CASTELLO_CONFIG . '/castello.db') ? (int) filesize(CASTELLO_CONFIG . '/castello.db') : 0;

$arquivosMidia = 0;
$tamanhoMidia  = 0;
if (is_dir(CASTELLO_UPLOADS)) {
    $itens = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(CASTELLO_UPLOADS, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($itens as $item) {
        if ($item->isFile()) {
            $arquivosMidia++;
            $tamanhoMidia += (int) $item->getSize();
        }
    }
}

$emMega = static fn (int $bytes): string => number_format($bytes / 1048576, 1, ',', '.') . ' MB';
?>
<h1>Backup</h1>
<p class="p-sub">
  Baixa um arquivo zip com tudo que é seu: o banco com os textos e o cadastro,
  mais <?= $arquivosMidia ?> arquivos de foto e vídeo.
  Banco: <?= e($emMega($tamanhoBanco)) ?>. Mídia: <?= e($emMega($tamanhoMidia)) ?>.
</p>

<?php if (!$temZip): ?>
<p class="p-aviso p-aviso--erro" role="alert">
  A extensão ZipArchive não está instalada neste servidor, então não consigo gerar o arquivo.
  Peça à hospedagem para ligar a extensão zip do PHP.
</p>
<?php else: ?>
<form class="p-form" method="post" action="acoes/backup.php">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>" />
  <p class="p-sub">Guarde o arquivo em um lugar seguro. Faça um backup antes de mudanças grandes.</p>
  <button class="p-btn p-btn--forte" type="submit">Baixar backup agora</button>
</form>
<?php endif; ?>
