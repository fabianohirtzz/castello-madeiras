<?php
declare(strict_types=1);

/**
 * Partials compartilhados pelas paginas: nav, rodape, modal e formulario.
 *
 * A saida das listas (modelos, portfolio, passos, avaliacoes, videos, faq) e
 * comparada byte a byte com testes/base/frag-*.html no caso 40. Aqui ficam os
 * partials que mudam conforme a pagina.
 */

require_once site() . '/lib/conteudo.php';

banco_com_conteudo();

function parcial_costura(string $nome, array $vars = []): string
{
    return render(site() . '/partials/' . $nome . '.php', $vars);
}

/** chave da pagina => arquivo */
const PAGINAS_NAV = [
    'home'      => 'index.php',
    'pronta'    => 'casa-pronta.php',
    'flex'      => 'flex.php',
    'portfolio' => 'portfolio.php',
    'contato'   => 'contato.php',
];

teste('a nav lista so paginas, sem ancora, e o logo leva a home', function (): void {
    foreach (PAGINAS_NAV as $chave => $arquivo) {
        $html = parcial_costura('nav', ['pagina' => $chave]);

        preg_match_all('#<nav class="nav__links"[^>]*>(.*?)</nav>#s', $html, $m);
        verdade(isset($m[1][0]), "$chave: bloco de links");
        preg_match_all('#href="([^"]+)"#', $m[1][0], $hrefs);
        igual(['index.php', 'casa-pronta.php', 'flex.php', 'portfolio.php', 'contato.php'], $hrefs[1], "$chave: os cinco links de pagina, nesta ordem");

        preg_match_all('#<div class="drawer"[^>]*>(.*?)</div>#s', $html, $g);
        preg_match_all('#href="([^"]+)"#', $g[1][0], $ghrefs);
        igual(['index.php', 'casa-pronta.php', 'flex.php', 'portfolio.php', 'contato.php'], $ghrefs[1], "$chave: a gaveta tem os mesmos cinco links");

        nao_contem('href="#', $html, "$chave: nenhuma ancora no menu");
        contem('href="index.php" class="nav__logo"', $html, "$chave: o logo leva a home");
        contem('class="btn btn--primary nav__cta" data-quote-open', $html, "$chave: CTA de orcamento na nav");
        contem('<span class="nav__tag">Novo</span>', $html, "$chave: etiqueta da Flex");
        nao_contem('.html', $html);
    }
});

teste('a pagina atual recebe aria-current="page" na nav e na gaveta', function (): void {
    foreach (PAGINAS_NAV as $chave => $arquivo) {
        $html = parcial_costura('nav', ['pagina' => $chave]);
        igual($chave === 'home' ? 3 : 2, substr_count($html, 'aria-current="page"'), "$chave: um na nav e um na gaveta (na home, tambem o logo)");
        if ($chave === 'home') {
            contem('href="index.php" class="nav__logo" aria-label="Castello Casas de Madeira" aria-current="page"', $html);
        } else {
            verdade(preg_match('#<a href="' . preg_quote($arquivo, '#') . '"[^>]*aria-current="page"#', $html) === 1, "$chave: link da nav marcado");
        }
    }
});

teste('o rodape lista as cinco paginas e marca a atual', function (): void {
    foreach (PAGINAS_NAV as $chave => $arquivo) {
        $html = parcial_costura('rodape', ['pagina' => $chave]);
        preg_match_all('#<ul class="footer__pages">(.*?)</ul>#s', $html, $m);
        preg_match_all('#href="([^"]+)"#', $m[1][0], $hrefs);
        igual(['index.php', 'casa-pronta.php', 'flex.php', 'portfolio.php', 'contato.php'], $hrefs[1], "$chave: paginas do rodape");
        igual(1, substr_count($html, 'aria-current="page"'), "$chave: uma pagina atual so");
        verdade(preg_match('#<a href="' . preg_quote($arquivo, '#') . '" aria-current="page"#', $html) === 1, "$chave: link do rodape marcado");
        contem('<footer class="footer" id="contato">', $html);
        contem('wa.me/5548998244494', $html);
    }
    contem('footer__map', parcial_costura('rodape', ['pagina' => 'home']), 'a home tem o mapa no rodape');
    nao_contem('footer__map', parcial_costura('rodape', ['pagina' => 'contato']), 'a pagina de contato ja tem o mapa grande, o rodape nao repete');
    contem('footer__grid footer__grid--sem-mapa', parcial_costura('rodape', ['pagina' => 'contato']));
});

teste('o formulario e um partial so, com os campos do contrato 6.1', function (): void {
    $html = parcial_costura('formulario', ['form_modalidade' => 'pronta']);
    contem('<form class="qform" id="quoteForm" method="post" action="enviar.php" novalidate>', $html);
    foreach (['nome', 'whatsapp', 'busca', 'modelo', 'cidade', 'mensagem'] as $campo) {
        contem('name="' . $campo . '"', $html, "campo $campo");
    }
    contem('name="empresa"', $html, 'honeypot com o nome do contrato');
    nao_contem('_gotcha', $html, 'o nome antigo do honeypot sai de cena');
    contem('<input type="hidden" name="csrf" value="" />', $html);
    foreach (['pagina', 'referrer', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'ts'] as $oculto) {
        contem('<input type="hidden" name="' . $oculto . '" value="" />', $html, "campo oculto $oculto");
    }
    contem('id="quoteDone" hidden', $html, 'a tela de sucesso acompanha o formulario');
    contem('id="quoteBack"', $html);
    contem('<option value="Compacta · 39 m² · R$ 69.900">Compacta · 39 m² · R$ 69.900</option>', $html);
    contem('id="qGroupModelo" hidden', $html);

    $extra = parcial_costura('formulario', ['form_modalidade' => 'pronta', 'form_opcoes_extra' => ['Quero a Castelo Flex, semipronta']]);
    contem('<option value="Quero a Castelo Flex, semipronta">Quero a Castelo Flex, semipronta</option>', $extra);
});

teste('o modal muda o titulo e a lista de modelos conforme a pagina', function (): void {
    $home = parcial_costura('modal', ['pagina' => 'home']);
    contem('Vamos falar da sua casa', $home);
    contem('<option value="Compacta · 39 m² · R$ 69.900">Compacta · 39 m² · R$ 69.900</option>', $home);
    contem('id="qGroupModelo" hidden', $home);
    contem('id="quoteForm"', $home);
    contem('id="reelbox"', $home, 'a home tem o lightbox dos reels');
    contem('id="wppFloat"', $home, 'o botao flutuante vem junto');

    $flex = parcial_costura('modal', ['pagina' => 'flex']);
    contem('Vamos falar da sua Castelo Flex', $flex);
    contem('<option value="Castelo Flex 36 · 36 m² · R$ 43.000">Castelo Flex 36 · 36 m² · R$ 43.000</option>', $flex);
    contem('<option value="Quero a Casa Pronta, chave na mão">Quero a Casa Pronta, chave na mão</option>', $flex);
    nao_contem('Compacta · 39 m²', $flex, 'na Flex a lista e dos modelos Flex');
    contem('id="qGroupModelo">', $flex, 'na Flex o campo de modelo ja aparece aberto');
    nao_contem('id="reelbox"', $flex);

    foreach (['pronta', 'portfolio'] as $outra) {
        $html = parcial_costura('modal', ['pagina' => $outra]);
        contem('Vamos falar da sua casa', $html, $outra);
        contem('Compacta · 39 m²', $html, $outra);
        nao_contem('id="reelbox"', $html, "$outra: so a home tem trilha do Instagram");
    }
});

teste('realce() transforma *texto* em destaque e escapa o resto', function (): void {
    igual('pronta pra morar, <span class="hl">chave na mão</span>', realce('pronta pra morar, *chave na mão*'));
    igual('sem destaque', realce('sem destaque'));
    igual('&lt;b&gt; e <span class="hl">&quot;aspas&quot;</span>', realce('<b> e *"aspas"*'));
    igual('um * solto', realce('um * solto'));
});
