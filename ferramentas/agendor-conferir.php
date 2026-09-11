<?php
declare(strict_types=1);

/**
 * Confere no Agendor o que o site gravou a partir de um telefone.
 *
 * Existe para responder tres perguntas que so a conta real responde:
 *   1. o filtro GET /people?phone= acha uma pessoa cujo numero o conector
 *      gravou em contact.mobile? (se nao achar, todo visitante que voltar
 *      vira cadastro duplicado)
 *   2. o campo dealStage quer a SEQUENCIA da etapa ou o ID dela?
 *   3. titulo, funil, origem, categoria e campos customizados chegaram?
 *
 * SO FAZ LEITURA. Usa GET em /people e /people/{id}/deals, nada mais.
 *
 * Uso:
 *   php ferramentas/agendor-conferir.php --telefone="(48) 99000-0001"
 *   php ferramentas/agendor-conferir.php --telefone=48990000001 --bruto
 *
 * O token vem de --token=, da variavel AGENDOR_TOKEN ou do
 * .credenciais-agendor na raiz do repositorio.
 */

require __DIR__ . '/agendor-comum.php';

const CONFERIR_AGENTE = 'castello-site/agendor-conferir';

function conferir_arg(array $argv, string $nome): ?string
{
    foreach ($argv as $arg) {
        if (str_starts_with($arg, "--$nome=")) {
            return substr($arg, strlen($nome) + 3);
        }
    }
    return null;
}

function conferir_linha(string $rotulo, mixed $valor): void
{
    if (is_array($valor)) {
        $valor = json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    if ($valor === null || $valor === '') {
        $valor = '(vazio)';
    }
    printf("  %-24s %s\n", $rotulo . ':', is_bool($valor) ? ($valor ? 'sim' : 'nao') : (string) $valor);
}

$telefone = conferir_arg($argv, 'telefone');
if ($telefone === null || agendor_digitos($telefone) === '') {
    fwrite(STDERR, "uso: php ferramentas/agendor-conferir.php --telefone=\"(48) 99000-0001\"\n");
    exit(2);
}
$busca = agendor_digitos($telefone);

$token = agendor_token($argv);
if ($token === '') {
    fwrite(STDERR, "erro: token nao encontrado. Use --token=, AGENDOR_TOKEN ou .credenciais-agendor\n");
    exit(2);
}

$bruto = in_array('--bruto', $argv, true);

echo "Buscando pessoas por telefone: $busca\n\n";

$r = agendor_requisitar($token, 'GET', '/people?phone=' . urlencode($busca), CONFERIR_AGENTE);
if (!$r['ok']) {
    fwrite(STDERR, "erro na busca: {$r['erro']}\n");
    exit(1);
}

$pessoas = is_array($r['dados']['data'] ?? null) ? $r['dados']['data'] : [];
echo 'Pessoas encontradas: ' . count($pessoas) . "\n";
if ($pessoas === []) {
    echo "\nNENHUMA. Se o site acabou de enviar um lead com este numero, o filtro\n";
    echo "phone= nao alcanca o campo onde o conector gravou, e a deduplicacao\n";
    echo "nao funciona em producao.\n";
    exit(1);
}

foreach ($pessoas as $p) {
    $id = (int) ($p['id'] ?? 0);
    echo "\n=== Pessoa $id ===\n";
    conferir_linha('nome', $p['name'] ?? null);
    conferir_linha('mobile', $p['contact']['mobile'] ?? null);
    conferir_linha('work', $p['contact']['work'] ?? null);
    conferir_linha('whatsapp', $p['contact']['whatsapp'] ?? null);
    conferir_linha('email', $p['contact']['email'] ?? null);
    conferir_linha('origem', ($p['leadOrigin']['name'] ?? '(nenhuma)') . ' #' . ($p['leadOrigin']['id'] ?? '-'));
    conferir_linha('categoria', ($p['category']['name'] ?? '(nenhuma)') . ' #' . ($p['category']['id'] ?? '-'));
    conferir_linha('responsavel', $p['ownerUser']['name'] ?? null);
    conferir_linha('criada em', $p['createdAt'] ?? null);

    $custom = is_array($p['customFields'] ?? null) ? $p['customFields'] : [];
    echo "  campos customizados:\n";
    if ($custom === []) {
        echo "    (nenhum)\n";
    }
    foreach ($custom as $chave => $valor) {
        printf("    %-28s %s\n", $chave . ':', is_array($valor) ? json_encode($valor, JSON_UNESCAPED_UNICODE) : (string) $valor);
    }

    usleep(AGENDOR_PAUSA_US);
    $d = agendor_requisitar($token, 'GET', '/people/' . $id . '/deals', CONFERIR_AGENTE);
    if (!$d['ok']) {
        echo "  negocios: ERRO {$d['erro']}\n";
        continue;
    }
    $negocios = is_array($d['dados']['data'] ?? null) ? $d['dados']['data'] : [];
    echo '  negocios: ' . count($negocios) . "\n";

    foreach ($negocios as $n) {
        echo "\n  --- Negocio " . ($n['id'] ?? '?') . " ---\n";
        conferir_linha('  titulo', $n['title'] ?? null);
        conferir_linha('  funil', ($n['funnel']['name'] ?? '?') . ' #' . ($n['funnel']['id'] ?? '-'));
        $etapa = $n['dealStage'] ?? [];
        conferir_linha('  etapa', ($etapa['name'] ?? '?') . ' (id ' . ($etapa['id'] ?? '-') . ', sequencia ' . ($etapa['sequence'] ?? '-') . ')');
        conferir_linha('  situacao', $n['dealStatus']['name'] ?? null);
        conferir_linha('  valor', $n['value'] ?? null);
        conferir_linha('  descricao', mb_substr((string) ($n['description'] ?? ''), 0, 200));

        $marcadores = array_map(
            static fn ($m) => is_array($m) ? (string) ($m['name'] ?? '') : (string) $m,
            is_array($n['tags'] ?? null) ? $n['tags'] : []
        );
        conferir_linha('  marcadores', implode(', ', array_filter($marcadores)));
    }

    if ($bruto) {
        echo "\n  --- json da pessoa ---\n";
        echo json_encode($p, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    }
    usleep(AGENDOR_PAUSA_US);
}

echo "\n";
