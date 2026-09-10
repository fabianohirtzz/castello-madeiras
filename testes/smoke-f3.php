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

require_once __DIR__ . '/../public_html/lib/leads.php';

t_secao('lib/leads.php: gravacao e marcacao');
teste_banco_limpar();

$id = lead_gravar([
    'nome'         => '  Fabiano Hirtz  ',
    'whatsapp'     => '(48) 99824-4494',
    'busca'        => 'Modelo pronto do catalogo',
    'modelo'       => 'Compacta 39 m2',
    'cidade'       => 'Tubarao / SC',
    'mensagem'     => 'Tenho terreno em Tubarao.',
    'pagina'       => '/index.php',
    'referrer'     => 'https://www.google.com/',
    'utm_source'   => 'instagram',
    'utm_medium'   => 'social',
    'utm_campaign' => 'flex-setembro',
    'utm_term'     => 'casa de madeira',
    'utm_content'  => 'reel-03',
]);
t_ok('lead_gravar devolve um id positivo', $id > 0, 'id = ' . var_export($id, true));

$linha = db()->query('SELECT * FROM leads WHERE id = ' . (int) $id)->fetch(PDO::FETCH_ASSOC);
t_igual('nome vem sem espaco nas pontas', 'Fabiano Hirtz', $linha['nome']);
t_igual('whatsapp gravado como veio', '(48) 99824-4494', $linha['whatsapp']);
t_igual('utm_campaign gravada', 'flex-setembro', $linha['utm_campaign']);
t_igual('pagina gravada', '/index.php', $linha['pagina']);
t_igual('status inicial e pendente', 'pendente', $linha['crm_status']);
t_igual('tentativas comecam em zero', 0, (int) $linha['crm_tentativas']);
t_ok('criado_em no formato certo', (bool) preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $linha['criado_em']));
t_ok('crm_ultima_tentativa nasce nula', $linha['crm_ultima_tentativa'] === null);

$vazio = lead_gravar(['nome' => 'So o nome']);
$linhaVazia = db()->query('SELECT * FROM leads WHERE id = ' . (int) $vazio)->fetch(PDO::FETCH_ASSOC);
t_igual('campo ausente vira string vazia', '', $linhaVazia['cidade']);
t_igual('utm ausente vira string vazia', '', $linhaVazia['utm_source']);

$longo = lead_gravar(['nome' => str_repeat('a', 900), 'mensagem' => str_repeat('b', 9000)]);
$linhaLonga = db()->query('SELECT * FROM leads WHERE id = ' . (int) $longo)->fetch(PDO::FETCH_ASSOC);
t_igual('nome longo cortado em 500', 500, mb_strlen((string) $linhaLonga['nome']));
t_igual('mensagem longa cortada em 4000', 4000, mb_strlen((string) $linhaLonga['mensagem']));

$acentos = lead_gravar(['nome' => 'Joao Gonçalves da Conceição']);
$linhaAcentos = db()->query('SELECT * FROM leads WHERE id = ' . (int) $acentos)->fetch(PDO::FETCH_ASSOC);
t_igual('acento sobrevive ao banco', 'Joao Gonçalves da Conceição', $linhaAcentos['nome']);

lead_marcar($id, 'enviado', 1, '{"status":"ok"}');
$marcado = db()->query('SELECT * FROM leads WHERE id = ' . (int) $id)->fetch(PDO::FETCH_ASSOC);
t_igual('lead_marcar grava o status', 'enviado', $marcado['crm_status']);
t_igual('lead_marcar grava as tentativas', 1, (int) $marcado['crm_tentativas']);
t_igual('lead_marcar grava a resposta', '{"status":"ok"}', $marcado['crm_resposta']);
t_ok('lead_marcar carimba a hora', (bool) preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $marcado['crm_ultima_tentativa']));

lead_marcar($id, 'status_inventado', 2, null);
$corrigido = db()->query('SELECT * FROM leads WHERE id = ' . (int) $id)->fetch(PDO::FETCH_ASSOC);
t_igual('status invalido vira erro', 'erro', $corrigido['crm_status']);
t_ok('resposta nula limpa o campo', $corrigido['crm_resposta'] === null);

lead_marcar($id, 'erro', 1, str_repeat('x', 5000));
$cortado = db()->query('SELECT * FROM leads WHERE id = ' . (int) $id)->fetch(PDO::FETCH_ASSOC);
t_igual('resposta longa cortada em 2000', 2000, mb_strlen((string) $cortado['crm_resposta']));

exit(t_resumo());
