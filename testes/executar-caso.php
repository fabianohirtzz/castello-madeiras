<?php
declare(strict_types=1);

/**
 * Roda um unico arquivo de caso em um ambiente isolado: pasta de config e
 * pasta de uploads proprias, apagadas ao final. Chamado por smoke.php.
 */

$arquivo = $argv[1] ?? '';
if ($arquivo === '' || !is_file($arquivo)) {
    fwrite(STDERR, "uso: php testes/executar-caso.php <arquivo-do-caso>\n");
    exit(2);
}

$temp = rtrim(sys_get_temp_dir(), "/\\") . '/castello-smoke-' . getmypid() . '-' . bin2hex(random_bytes(4));
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
require $arquivo;
smoke_encerrar();
