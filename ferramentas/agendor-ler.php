<?php
declare(strict_types=1);

/**
 * Leitor da conta do Agendor.
 *
 * Ferramenta de diagnostico: mostra como a conta do cliente ja esta montada
 * (funis, etapas, origens, campos customizados, usuarios) para que o conector
 * do site seja escrito em cima do que existe la, sem pedir mudanca no CRM.
 *
 * SO FAZ LEITURA. As rotas estao numa lista fechada, todas GET. Este arquivo
 * nao cria, nao altera e nao apaga nada na conta.
 *
 * Uso:
 *   php ferramentas/agendor-ler.php
 *   php ferramentas/agendor-ler.php --json=caminho/para/pasta
 *
 * O token sai do Agendor em Menu > Integracoes e e lido, nesta ordem, de:
 *   1. --token=xxxx
 *   2. variavel de ambiente AGENDOR_TOKEN
 *   3. .credenciais-agendor na raiz do repo, linha AGENDOR_TOKEN=xxxx
 */

require __DIR__ . '/agendor-comum.php';

const LER_AGENTE = 'castello-site/agendor-ler';

/**
 * Rotas lidas, na ordem do relatorio.
 * rotulo => [caminho, colunas preferidas]
 * Lista de colunas vazia significa "mostre todo campo que vier".
 */
const AGENDOR_ROTAS = [
    'Conta autenticada'        => ['/users/me', []],
    'Usuarios'                 => ['/users', ['id', 'name', 'email', 'role', 'active']],
    'Funis'                    => ['/funnels', ['id', 'name', 'default']],
    'Etapas de negocio'        => ['/deal_stages', ['id', 'name', 'sequence', 'funnel']],
    'Situacoes de negocio'     => ['/deal_statuses', ['id', 'name']],
    'Motivos de perda'         => ['/loss_reasons', ['id', 'name']],
    'Origens de lead'          => ['/lead_origins', ['id', 'name']],
    'Categorias de contato'    => ['/categories', ['id', 'name']],
    'Setores'                  => ['/sectors', ['id', 'name']],
    'Campos custom de pessoa'  => ['/custom_fields/people', []],
    'Campos custom de negocio' => ['/custom_fields/deals', []],
    'Produtos'                 => ['/products', ['id', 'name', 'value', 'active']],
];

/** Um GET na API, pelo transporte compartilhado do agendor-comum.php. */
function agendor_get(string $token, string $caminho): array
{
    return agendor_requisitar($token, 'GET', $caminho, LER_AGENTE);
}

/** Transforma qualquer valor num texto curto de uma linha. */
function agendor_texto($valor): string
{
    if ($valor === null) {
        return '-';
    }
    if (is_bool($valor)) {
        return $valor ? 'sim' : 'nao';
    }
    if (is_scalar($valor)) {
        return (string) $valor;
    }
    if (is_array($valor)) {
        // Objeto aninhado com nome (ex.: funnel: {id, name}) vira "nome (id)".
        if (isset($valor['name']) && is_scalar($valor['name'])) {
            $id = isset($valor['id']) ? ' (' . $valor['id'] . ')' : '';
            return (string) $valor['name'] . $id;
        }
        $compacto = json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return mb_substr((string) $compacto, 0, 160);
    }
    return '?';
}

/** Escolhe as colunas a mostrar: as preferidas que existirem, senao todas. */
function agendor_colunas(array $itens, array $preferidas): array
{
    $presentes = [];
    foreach ($itens as $item) {
        if (is_array($item)) {
            foreach (array_keys($item) as $chave) {
                $presentes[$chave] = true;
            }
        }
    }
    $presentes = array_keys($presentes);

    if ($preferidas !== []) {
        $escolhidas = array_values(array_intersect($preferidas, $presentes));
        if ($escolhidas !== []) {
            return $escolhidas;
        }
    }
    return $presentes;
}

/** Completa com espacos ate a largura pedida, contando em caracteres. */
function agendor_preencher(string $texto, int $largura): string
{
    $falta = $largura - mb_strlen($texto);
    return $falta > 0 ? $texto . str_repeat(' ', $falta) : $texto;
}

/** Imprime uma lista de itens como tabela alinhada. */
function agendor_tabela(array $itens, array $preferidas): void
{
    if ($itens === []) {
        echo "  (nenhum registro)\n";
        return;
    }

    $colunas = agendor_colunas($itens, $preferidas);
    if ($colunas === []) {
        $colunas = ['valor'];
    }

    $linhas = [];
    foreach ($itens as $item) {
        $linha = [];
        foreach ($colunas as $coluna) {
            $linha[$coluna] = is_array($item)
                ? agendor_texto($item[$coluna] ?? null)
                : agendor_texto($item);
        }
        $linhas[] = $linha;
    }

    $larguras = [];
    foreach ($colunas as $coluna) {
        $larguras[$coluna] = mb_strlen($coluna);
        foreach ($linhas as $linha) {
            $larguras[$coluna] = max($larguras[$coluna], mb_strlen($linha[$coluna]));
        }
        $larguras[$coluna] = min($larguras[$coluna], 60);
    }

    $cabecalho = [];
    $regua = [];
    foreach ($colunas as $coluna) {
        $cabecalho[] = agendor_preencher($coluna, $larguras[$coluna]);
        $regua[] = str_repeat('-', $larguras[$coluna]);
    }
    echo '  ' . implode('  ', $cabecalho) . "\n";
    echo '  ' . implode('  ', $regua) . "\n";

    foreach ($linhas as $linha) {
        $celulas = [];
        foreach ($colunas as $coluna) {
            $celulas[] = agendor_preencher(mb_substr($linha[$coluna], 0, 60), $larguras[$coluna]);
        }
        echo '  ' . rtrim(implode('  ', $celulas)) . "\n";
    }
}

/**
 * Campo customizado tem estrutura propria: identifier, label, type e opcoes.
 * O identifier e o que o conector do site precisa gravar na config, e o id de
 * cada opcao e o que se manda em campo de selecao.
 */
function agendor_campos_custom(array $itens): void
{
    if ($itens === []) {
        echo "  (nenhum campo customizado nesta conta)\n";
        return;
    }

    foreach ($itens as $campo) {
        if (!is_array($campo)) {
            continue;
        }
        $identificador = $campo['identifier'] ?? $campo['name'] ?? '?';
        $rotulo = $campo['label'] ?? $campo['title'] ?? $campo['name'] ?? '?';
        $tipo = $campo['type'] ?? $campo['fieldType'] ?? '?';
        $obrigatorio = empty($campo['required']) ? '' : '   OBRIGATORIO';

        echo '  ' . agendor_texto($rotulo) . "\n";
        echo '      identifier: ' . agendor_texto($identificador)
            . '   tipo: ' . agendor_texto($tipo) . $obrigatorio . "\n";

        foreach (['options', 'customFieldOptions', 'values'] as $chaveOpcoes) {
            if (empty($campo[$chaveOpcoes]) || !is_array($campo[$chaveOpcoes])) {
                continue;
            }
            foreach ($campo[$chaveOpcoes] as $opcao) {
                if (is_array($opcao)) {
                    $id = $opcao['id'] ?? '?';
                    $nome = $opcao['name'] ?? $opcao['label'] ?? $opcao['value'] ?? '?';
                    echo '      opcao ' . agendor_texto($id) . ': ' . agendor_texto($nome) . "\n";
                } else {
                    echo '      opcao: ' . agendor_texto($opcao) . "\n";
                }
            }
            break;
        }
        echo "\n";
    }
}

// ---------------------------------------------------------------- execucao

$token = agendor_token($argv);
if ($token === '') {
    fwrite(STDERR, "Token do Agendor nao encontrado.\n\n");
    fwrite(STDERR, "Pegue em Menu > Integracoes e informe de uma destas formas:\n");
    fwrite(STDERR, "  php ferramentas/agendor-ler.php --token=SEU-TOKEN\n");
    fwrite(STDERR, "  AGENDOR_TOKEN=SEU-TOKEN php ferramentas/agendor-ler.php\n");
    fwrite(STDERR, "  gravar a linha AGENDOR_TOKEN=SEU-TOKEN em .credenciais-agendor\n");
    exit(1);
}

$pastaJson = '';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--json=')) {
        $pastaJson = rtrim(substr($arg, 7), '/\\');
    }
}
if ($pastaJson !== '' && !is_dir($pastaJson) && !mkdir($pastaJson, 0777, true) && !is_dir($pastaJson)) {
    fwrite(STDERR, "Nao consegui criar a pasta {$pastaJson}\n");
    exit(1);
}

echo 'Conta do Agendor, leitura em ' . date('d/m/Y H:i') . "\n";
echo str_repeat('=', 72) . "\n";

$falhas = 0;

foreach (AGENDOR_ROTAS as $rotulo => $rota) {
    [$caminho, $preferidas] = $rota;

    echo "\n" . $rotulo . '   ' . $caminho . "\n";
    echo str_repeat('-', 72) . "\n";

    $r = agendor_get($token, $caminho);
    usleep(AGENDOR_PAUSA_US);

    if (!$r['ok']) {
        $falhas++;
        echo '  FALHOU: ' . $r['erro'] . "\n";
        if ($r['http'] === 401 || $r['http'] === 403) {
            echo "  (token invalido ou sem permissao para esta rota)\n";
        }
        continue;
    }

    $dados = $r['dados'];
    $conteudo = array_key_exists('data', $dados) ? $dados['data'] : $dados;

    if ($pastaJson !== '') {
        $nome = trim(str_replace('/', '-', $caminho), '-') . '.json';
        file_put_contents(
            $pastaJson . '/' . $nome,
            (string) json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
        );
    }

    if (str_starts_with($caminho, '/custom_fields/')) {
        agendor_campos_custom(is_array($conteudo) ? $conteudo : []);
        continue;
    }

    // Objeto unico em vez de lista, caso do /users/me.
    if (is_array($conteudo) && $conteudo !== [] && !array_is_list($conteudo)) {
        foreach ($conteudo as $chave => $valor) {
            echo '  ' . agendor_preencher((string) $chave, 22) . agendor_texto($valor) . "\n";
        }
        continue;
    }

    agendor_tabela(is_array($conteudo) ? $conteudo : [], $preferidas);

    if (isset($dados['links']['next'])) {
        echo "  (ha mais paginas; esta leitura mostra so a primeira)\n";
    }
}

echo "\n" . str_repeat('=', 72) . "\n";
if ($pastaJson !== '') {
    echo "JSON bruto salvo em {$pastaJson}\n";
}
echo $falhas === 0
    ? "Leitura completa, nenhuma rota falhou.\n"
    : "Leitura terminou com {$falhas} rota(s) com falha.\n";

exit($falhas === 0 ? 0 : 1);
