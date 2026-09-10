<?php
declare(strict_types=1);

/**
 * Smoke da frente 3 (formulario, CRM, e-mail, reenvio).
 * Rodar: php testes/smoke-f3.php
 * Na Tarefa 9 este arquivo e fundido em testes/smoke.php.
 */

require_once __DIR__ . '/apoio-f1.php';

if (!function_exists('t_ok')) {
    $GLOBALS['t_total'] = 0;
    $GLOBALS['t_falhas'] = 0;

    function t_secao(string $titulo): void
    {
        echo PHP_EOL, '== ', $titulo, ' ==', PHP_EOL;
    }

    function t_ok(string $nome, bool $condicao, string $detalhe = ''): void
    {
        $GLOBALS['t_total']++;
        if ($condicao) {
            echo '  ok    ', $nome, PHP_EOL;
            return;
        }
        $GLOBALS['t_falhas']++;
        echo '  FALHA ', $nome, ($detalhe !== '' ? '  ->  ' . $detalhe : ''), PHP_EOL;
    }

    function t_igual(string $nome, $esperado, $obtido): void
    {
        t_ok(
            $nome,
            $esperado === $obtido,
            'esperado ' . var_export($esperado, true) . ', obtido ' . var_export($obtido, true)
        );
    }

    function t_resumo(): int
    {
        $passaram = $GLOBALS['t_total'] - $GLOBALS['t_falhas'];
        echo PHP_EOL, $passaram, '/', $GLOBALS['t_total'], ' passaram', PHP_EOL;
        return $GLOBALS['t_falhas'] > 0 ? 1 : 0;
    }
}

teste_banco_apagar();

t_secao('Apoio da frente 1');
t_ok('db() abre o banco', db() instanceof PDO);
t_ok('tabela leads existe', (bool) db()->query("SELECT name FROM sqlite_master WHERE name = 'leads'")->fetchColumn());
t_ok('tabela config existe', (bool) db()->query("SELECT name FROM sqlite_master WHERE name = 'config'")->fetchColumn());
config_gravar('crm_ativo', '1');
t_igual('config_gravar e config_ler', '1', config_ler('crm_ativo'));
t_igual('config_ler devolve o padrao', 'zero', config_ler('nao_existe', 'zero'));
t_ok('agora() no formato Y-m-d H:i:s', (bool) preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', agora()));
t_ok('csrf_validar aceita o token bom', csrf_validar(csrf_token()));
t_ok('csrf_validar recusa token ruim', !csrf_validar('outro'));
t_igual('e() escapa aspas', '&quot;', e('"'));

exit(t_resumo());
