<?php
declare(strict_types=1);

/**
 * Pendencias de producao: indexavel, canonical e og:image absolutos.
 *
 * O dominio definitivo ainda nao existe. Enquanto o site mora no subdominio
 * de teste, CASTELLO_URL e https://castello.tohospedando.com.br e o teste
 * segue a constante: no deploy final basta trocar o valor em lib/conteudo.php.
 */

require_once site() . '/lib/conteudo.php';

banco_com_conteudo();

teste('CASTELLO_URL e uma origem https sem barra no fim', function (): void {
    verdade(defined('CASTELLO_URL'), 'lib/conteudo.php precisa definir CASTELLO_URL');
    verdade(str_starts_with(CASTELLO_URL, 'https://'), 'origem https');
    falso(str_ends_with(CASTELLO_URL, '/'), 'sem barra no fim, as paginas acrescentam o caminho');
    igual('https://castello.tohospedando.com.br', CASTELLO_URL, 'subdominio de teste ate o dominio final existir');
});

teste('metatags de producao estao corretas nas duas paginas', function (): void {
    foreach (['index.php' => '/', 'flex.php' => '/flex.php'] as $arquivo => $caminho) {
        $html = renderizar_pagina($arquivo);
        $head = substr($html, 0, (int) strpos($html, '</head>'));

        nao_contem('noindex', $head, "$arquivo ainda tem noindex");
        nao_contem('PROTÓTIPO', $head, "$arquivo ainda se apresenta como prototipo");
        contem('<link rel="canonical" href="' . CASTELLO_URL . $caminho . '" />', $head, "$arquivo sem canonical correto");
        contem('<meta property="og:url" content="' . CASTELLO_URL . $caminho . '" />', $head, "$arquivo sem og:url");
        verdade(preg_match('#<meta property="og:image" content="' . preg_quote(CASTELLO_URL, '#') . '/[^"]+\.(png|jpg)" />#', $head) === 1,
            "$arquivo sem og:image absoluto");
        igual(1, substr_count($head, 'rel="canonical"'), "$arquivo: um canonical so");
        igual(1, substr_count($head, '<title>'), "$arquivo: um title so");
        verdade(preg_match('#<meta name="description" content="[^"]{50,}" />#', $head) === 1, "$arquivo: description escrita");
    }
});

teste('a imagem do og:image existe no site', function (): void {
    foreach (['index.php', 'flex.php'] as $arquivo) {
        $html = renderizar_pagina($arquivo);
        preg_match('#<meta property="og:image" content="' . preg_quote(CASTELLO_URL, '#') . '/([^"]+)" />#', $html, $m);
        verdade(isset($m[1]) && is_file(site() . '/' . $m[1]), "$arquivo: og:image aponta para arquivo inexistente: " . ($m[1] ?? '?'));
    }
});

function renderizar_pagina(string $arquivo): string
{
    return render(site() . '/' . $arquivo);
}
