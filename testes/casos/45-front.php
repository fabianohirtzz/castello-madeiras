<?php
declare(strict_types=1);

/**
 * Costura: os partials imprimem a marcacao nova da frente 2.
 *
 * A referencia e a marcacao estatica entregue em front/, com cada trecho
 * dinamico cercado por <!-- inicio:nome --> e <!-- fim:nome -->. Depois da
 * costura o front/ some e a referencia passa a morar em testes/base/.
 */

require_once site() . '/lib/conteudo.php';

banco_com_conteudo();

function referencia(string $pagina): string
{
    foreach ([raiz() . '/testes/base/' . $pagina . '-fase2.html', raiz() . '/front/' . $pagina . '.html'] as $caminho) {
        if (is_file($caminho)) {
            $html = (string) file_get_contents($caminho);
            // O front/ enxergava os assets por ../public_html/; o site, pela raiz.
            return str_replace('../public_html/', '', $html);
        }
    }
    throw new RuntimeException('referencia da pagina ' . $pagina . ' nao encontrada');
}

function trecho(string $pagina, string $nome): string
{
    $ok = preg_match('#<!-- inicio:' . preg_quote($nome, '#') . ' -->(.*?)<!-- fim:' . preg_quote($nome, '#') . ' -->#s', referencia($pagina), $m);
    verdade($ok === 1 && trim($m[1]) !== '', "a referencia de $pagina precisa marcar o trecho $nome");
    return $m[1];
}

function classes_de(string $html): array
{
    preg_match_all('#class="([^"]+)"#', $html, $c);
    $classes = [];
    foreach ($c[1] as $lista) {
        foreach (preg_split('/\s+/', trim($lista)) ?: [] as $classe) {
            if ($classe !== '') {
                $classes[$classe] = true;
            }
        }
    }
    return array_keys($classes);
}

function parcial_costura(string $nome, array $vars = []): string
{
    return render(site() . '/partials/' . $nome . '.php', $vars);
}

/** pagina, trecho, partial, variaveis */
const COSTURA_TRECHOS = [
    ['home', 'modelos',      'modelos',    ['modalidade' => 'pronta']],
    ['home', 'portfolio',    'portfolio',  []],
    ['home', 'passos',       'passos',     ['contexto' => 'pronta']],
    ['home', 'avaliacoes',   'avaliacoes', []],
    ['home', 'videos',       'videos',     []],
    ['home', 'faq',          'faq',        ['contexto' => 'geral']],
    ['flex', 'modelos-flex', 'modelos',    ['modalidade' => 'flex']],
    ['flex', 'passos-flex',  'passos',     ['contexto' => 'flex']],
    ['flex', 'faq-flex',     'faq',        ['contexto' => 'flex']],
];

foreach (COSTURA_TRECHOS as [$pagina, $marca, $partial, $vars]) {
    teste("partial $partial ($marca) imprime toda classe da marcacao nova", function () use ($pagina, $marca, $partial, $vars): void {
        $esperado = trecho($pagina, $marca);
        $saida = parcial_costura($partial, $vars);
        foreach (classes_de($esperado) as $classe) {
            verdade(str_contains($saida, $classe), "partial $partial nao imprime a classe $classe");
        }
    });

    teste("partial $partial ($marca) sai identico ao trecho de referencia", function () use ($pagina, $marca, $partial, $vars): void {
        if ($partial === 'videos') {
            config_gravar('videos_na_home', '11');
        }
        $saida = parcial_costura($partial, $vars);
        config_gravar('videos_na_home', '8');
        igual(norm(trecho($pagina, $marca)), norm($saida));
    });
}

teste('a nav muda os links conforme a pagina', function (): void {
    $home = parcial_costura('nav', ['pagina' => 'home']);
    contem('href="#casa-pronta"', $home);
    contem('href="flex.php" class="nav__link--flex"', $home);
    contem('<span class="nav__tag">Novo</span>', $home);
    contem('href="#topo" class="nav__logo"', $home);
    nao_contem('flex.html', $home);
    nao_contem('href="#modelos"', $home, 'o id antigo #modelos nao existe mais');

    $flex = parcial_costura('nav', ['pagina' => 'flex']);
    contem('href="#o-que-e"', $flex);
    contem('href="#passos-flex"', $flex);
    contem('href="#modelos-flex"', $flex);
    contem('href="index.php#casa-pronta"', $flex);
    contem('href="index.php" class="nav__logo"', $flex);
    nao_contem('home.html', $flex);
});

teste('o modal muda o titulo e a lista de modelos conforme a pagina', function (): void {
    $home = parcial_costura('modal', ['pagina' => 'home']);
    contem('Vamos falar da sua casa', $home);
    contem('<option value="Compacta · 39 m² · R$ 69.900">Compacta · 39 m² · R$ 69.900</option>', $home);
    contem('id="qGroupModelo" hidden', $home);
    contem('name="empresa"', $home, 'honeypot com o nome do contrato');
    nao_contem('_gotcha', $home, 'o nome antigo do honeypot sai de cena');
    contem('<input type="hidden" name="csrf" value="" />', $home);
    foreach (['pagina', 'referrer', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'ts'] as $oculto) {
        contem('<input type="hidden" name="' . $oculto . '" value="" />', $home, "campo oculto $oculto");
    }

    $flex = parcial_costura('modal', ['pagina' => 'flex']);
    contem('Vamos falar da sua Castelo Flex', $flex);
    contem('<option value="Castelo Flex 36 · 36 m² · semipronta">Castelo Flex 36 · 36 m²</option>', $flex);
    contem('<option value="Quero a Casa Pronta, chave na mão">Quero a Casa Pronta, chave na mão</option>', $flex);
    nao_contem('Compacta · 39 m²', $flex, 'na Flex a lista e dos modelos Flex');
    contem('id="qGroupModelo">', $flex, 'na Flex o campo de modelo ja aparece aberto');
});

teste('realce() transforma *texto* em destaque e escapa o resto', function (): void {
    igual('pronta pra morar, <span class="hl">chave na mão</span>', realce('pronta pra morar, *chave na mão*'));
    igual('sem destaque', realce('sem destaque'));
    igual('&lt;b&gt; e <span class="hl">&quot;aspas&quot;</span>', realce('<b> e *"aspas"*'));
    igual('um * solto', realce('um * solto'));
});
