<?php
declare(strict_types=1);

/**
 * Assertivas do smoke test da Castello. Sem framework: um caso e um arquivo
 * PHP que chama teste() varias vezes. O processo do caso sai com codigo 1
 * assim que qualquer teste falhar.
 */

$GLOBALS['smoke'] = ['passou' => 0, 'falhou' => 0, 'pulou' => 0];

final class SmokeFalha extends RuntimeException {}
final class SmokePulado extends RuntimeException {}

function teste(string $nome, callable $corpo): void
{
    try {
        $corpo();
        $GLOBALS['smoke']['passou']++;
        echo "  ok    $nome\n";
    } catch (SmokePulado $p) {
        $GLOBALS['smoke']['pulou']++;
        echo "  pula  $nome  (" . $p->getMessage() . ")\n";
    } catch (Throwable $t) {
        $GLOBALS['smoke']['falhou']++;
        echo "  FALHA $nome\n";
        echo '        ' . $t->getMessage() . "\n";
        echo '        ' . $t->getFile() . ':' . $t->getLine() . "\n";
    }
}

function igual($esperado, $obtido, string $msg = ''): void
{
    if ($esperado !== $obtido) {
        throw new SmokeFalha(($msg !== '' ? $msg . ' | ' : '')
            . 'esperado ' . smoke_mostrar($esperado) . ', obtido ' . smoke_mostrar($obtido));
    }
}

function verdade($valor, string $msg = 'esperava verdadeiro'): void
{
    if ($valor !== true) {
        throw new SmokeFalha($msg . ' | obtido ' . smoke_mostrar($valor));
    }
}

function falso($valor, string $msg = 'esperava falso'): void
{
    if ($valor !== false) {
        throw new SmokeFalha($msg . ' | obtido ' . smoke_mostrar($valor));
    }
}

function contem(string $agulha, string $palheiro, string $msg = ''): void
{
    if (!str_contains($palheiro, $agulha)) {
        throw new SmokeFalha(($msg !== '' ? $msg . ' | ' : '')
            . 'nao encontrou ' . smoke_mostrar($agulha)
            . ' dentro de ' . smoke_mostrar(mb_substr($palheiro, 0, 400)));
    }
}

function nao_contem(string $agulha, string $palheiro, string $msg = ''): void
{
    if (str_contains($palheiro, $agulha)) {
        throw new SmokeFalha(($msg !== '' ? $msg . ' | ' : '')
            . 'encontrou ' . smoke_mostrar($agulha) . ' e nao deveria');
    }
}

function pular(string $motivo): void
{
    throw new SmokePulado($motivo);
}

function smoke_mostrar($valor): string
{
    if (is_string($valor)) return '"' . $valor . '"';
    if (is_bool($valor)) return $valor ? 'true' : 'false';
    if ($valor === null) return 'null';
    if (is_array($valor)) return 'array(' . count($valor) . ')';
    if (is_object($valor)) return get_class($valor);
    return (string) $valor;
}

function smoke_encerrar(): void
{
    $s = $GLOBALS['smoke'];
    echo sprintf("  -- %d ok, %d falha, %d pulado\n", $s['passou'], $s['falhou'], $s['pulou']);
    exit($s['falhou'] > 0 ? 1 : 0);
}

/** Raiz do repositorio. */
function raiz(): string
{
    return dirname(__DIR__);
}

/** Raiz publica do site. */
function site(): string
{
    return raiz() . '/public_html';
}

/**
 * Roda a migracao de conteudo real no banco temporario do caso.
 * Devolve o mapa de contagens que migrar() produz.
 */
function banco_com_conteudo(): array
{
    require_once site() . '/migrar.php';
    return migrar();
}

/**
 * Normaliza HTML para comparacao: colapsa espaco em branco e traz os
 * caminhos de uploads de volta para os caminhos originais do prototipo,
 * que e o unico ponto em que a saida legitimamente difere do index.html.
 */
function norm(string $html): string
{
    $html = strtr($html, [
        'uploads/modelos/'   => 'fotos-casas/',
        'uploads/portfolio/' => 'fotos-casas/',
        'uploads/videos/'    => 'videos-instagram/web/',
        'uploads/passos/'    => 'passos/',
    ]);
    $html = (string) preg_replace('/>\s+</u', '><', $html);
    $html = (string) preg_replace('/\s+/u', ' ', $html);
    return trim($html);
}

/** Igual a norm(), mas tambem apaga os atributos alt. */
function norm_sem_alt(string $html): string
{
    return (string) preg_replace('/ alt="[^"]*"/u', '', norm($html));
}

/** Inclui um arquivo PHP com variaveis em escopo e devolve o que ele imprimiu. */
function render(string $arquivo, array $vars = []): string
{
    extract($vars, EXTR_SKIP);
    ob_start();
    include $arquivo;
    return (string) ob_get_clean();
}
