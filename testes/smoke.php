<?php
declare(strict_types=1);

/**
 * Runner do smoke test da Castello.
 *
 *   php testes/smoke.php            roda todos os casos
 *   php testes/smoke.php 40         roda so os casos cujo nome contem "40"
 *
 * Cada caso roda em um processo PHP separado, com banco e uploads proprios.
 * Sai com codigo 1 se qualquer caso falhar.
 */

$filtro = $argv[1] ?? '';
$casos  = glob(__DIR__ . '/casos/*.php') ?: [];
sort($casos);

if ($filtro !== '') {
    $casos = array_values(array_filter(
        $casos,
        static fn (string $c): bool => str_contains(basename($c), $filtro)
    ));
}

if ($casos === []) {
    fwrite(STDERR, "nenhum caso encontrado em testes/casos\n");
    exit(1);
}

$falharam = [];

foreach ($casos as $caso) {
    echo basename($caso) . "\n";

    $linha = escapeshellarg(PHP_BINARY)
        . ' ' . escapeshellarg(__DIR__ . '/executar-caso.php')
        . ' ' . escapeshellarg($caso);

    $proc = proc_open($linha, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $canos);
    if (!is_resource($proc)) {
        echo "  FALHA nao consegui abrir o processo do caso\n";
        $falharam[] = basename($caso);
        continue;
    }

    $saida = (string) stream_get_contents($canos[1]);
    $erro  = (string) stream_get_contents($canos[2]);
    fclose($canos[1]);
    fclose($canos[2]);
    $codigo = proc_close($proc);

    echo $saida;
    if (trim($erro) !== '') {
        echo '  stderr: ' . trim($erro) . "\n";
    }
    if ($codigo !== 0) {
        $falharam[] = basename($caso);
    }
}

echo "\n";
if ($falharam !== []) {
    echo 'FALHOU: ' . implode(', ', $falharam) . "\n";
    exit(1);
}
echo "todos os casos passaram\n";
exit(0);
