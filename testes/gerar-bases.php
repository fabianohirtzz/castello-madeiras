<?php
declare(strict_types=1);

/**
 * Regenera testes/base/pagina-*.html, as referencias do 50-paginas.php.
 *
 * Monta o MESMO ambiente do runner: pasta de config temporaria e banco semeado
 * pelo migrar(). Gerar pelo servidor local em vez daqui traria as diferencas do
 * banco de desenvolvimento para dentro da referencia, e o teste passaria a
 * comparar duas coisas diferentes.
 *
 * So rode depois de conferir as paginas no navegador: esta ferramenta grava o
 * que a pagina produz, entao ela promove a bug a referencia com a mesma
 * facilidade com que registra uma melhoria.
 *
 * Uso: php testes/gerar-bases.php
 */

$temp = rtrim(sys_get_temp_dir(), "/\\") . '/castello-bases-' . getmypid();
mkdir($temp . '/config', 0775, true);
mkdir($temp . '/uploads', 0775, true);

define('CASTELLO_CONFIG', $temp . '/config');
define('CASTELLO_UPLOADS', $temp . '/uploads');

register_shutdown_function(static function () use ($temp): void {
    if (!is_dir($temp)) {
        return;
    }
    $itens = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($temp, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($itens as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($temp);
});

require __DIR__ . '/assertivas.php';

banco_com_conteudo();

/* As mesmas cinco chaves de PAGINAS_SITE, em testes/casos/50-paginas.php. */
$paginas = [
    'home'        => 'index.php',
    'casa-pronta' => 'casa-pronta.php',
    'flex'        => 'flex.php',
    'portfolio'   => 'portfolio.php',
    'contato'     => 'contato.php',
];

foreach ($paginas as $chave => $arquivo) {
    $destino = raiz() . '/testes/base/pagina-' . $chave . '.html';
    file_put_contents($destino, render(site() . '/' . $arquivo));
    echo 'regenerada: pagina-', $chave, '.html (', number_format(filesize($destino)), " bytes)\n";
}

echo "\nConfira o diff antes de commitar: git diff testes/base/\n";
