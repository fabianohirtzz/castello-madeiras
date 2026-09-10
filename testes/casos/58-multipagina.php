<?php
declare(strict_types=1);

/**
 * Site multipagina: cinco paginas com o mesmo nav, rodape e formulario.
 *
 * Home (resumo da Casa Pronta, sem portfolio nem passo a passo), Casa Pronta
 * (modelos, incluso, passos, FAQ), Castelo Flex, Portfolio (grade) e Contato
 * (formulario embutido, sem modal). Menu so com paginas, SEO por pagina e o
 * sitemap com as cinco.
 */

require_once site() . '/lib/conteudo.php';

banco_com_conteudo();

/** chave da nav => arquivo */
const MULTI_PAGINAS = [
    'home'      => 'index.php',
    'pronta'    => 'casa-pronta.php',
    'flex'      => 'flex.php',
    'portfolio' => 'portfolio.php',
    'contato'   => 'contato.php',
];

function multi_pagina(string $arquivo): string
{
    static $cache = [];
    return $cache[$arquivo] ??= render(site() . '/' . $arquivo);
}

function multi_head(string $html): string
{
    return substr($html, 0, (int) strpos($html, '</head>'));
}

teste('as cinco paginas renderizam sem aviso do PHP', function (): void {
    foreach (MULTI_PAGINAS as $arquivo) {
        verdade(is_file(site() . '/' . $arquivo), "$arquivo existe");
        $html = multi_pagina($arquivo);
        contem('<!DOCTYPE html>', $html, $arquivo);
        contem('</html>', $html, $arquivo);
        foreach (['Warning:', 'Notice:', 'Deprecated:', 'Fatal error', 'Parse error', '<?php', '<?='] as $ruim) {
            nao_contem($ruim, $html, "$arquivo imprime $ruim");
        }
    }
});

teste('a nav de cada pagina tem os quatro links de pagina, o CTA e nenhuma ancora', function (): void {
    foreach (MULTI_PAGINAS as $chave => $arquivo) {
        $html = multi_pagina($arquivo);
        preg_match('#<nav class="nav__links"[^>]*>(.*?)</nav>#s', $html, $m);
        verdade(isset($m[1]), "$arquivo: bloco nav__links");
        preg_match_all('#<a href="([^"]+)"#', $m[1], $links);
        igual(['index.php', 'casa-pronta.php', 'flex.php', 'portfolio.php', 'contato.php'], $links[1], "$arquivo: links da nav");
        contem('class="btn btn--primary nav__cta" data-quote-open', $html, "$arquivo: CTA da nav");

        preg_match('#<div class="drawer" id="drawer"[^>]*>(.*?)</div>#s', $html, $g);
        preg_match_all('#<a href="([^"]+)"#', $g[1], $glinks);
        igual(['index.php', 'casa-pronta.php', 'flex.php', 'portfolio.php', 'contato.php'], $glinks[1], "$arquivo: links da gaveta");
        contem('data-quote-open>Pedir orçamento</button>', $g[1], "$arquivo: CTA da gaveta");

        // nenhum href com # dentro da nav, da gaveta ou do rodape
        foreach ([$m[1], $g[1]] as $bloco) {
            nao_contem('href="#', $bloco, "$arquivo: ancora no menu");
        }
        preg_match('#<footer class="footer"(.*?)</footer>#s', $html, $f);
        nao_contem('href="#', $f[1], "$arquivo: ancora no rodape");
        contem('<a href="index.php" class="nav__logo"', $html, "$arquivo: o logo leva a home");
    }
});

teste('a pagina atual recebe aria-current="page" e as outras nao', function (): void {
    foreach (MULTI_PAGINAS as $chave => $arquivo) {
        $html = multi_pagina($arquivo);
        if ($chave === 'home') {
            contem('class="nav__logo" aria-label="Castello Casas de Madeira" aria-current="page"', $html, 'home: o logo e a pagina atual');
            contem('<a href="index.php" aria-current="page">Início</a>', $html, 'home: marcada no rodape');
            igual(4, substr_count($html, 'aria-current="page"'), 'home: logo, nav, gaveta e rodape');
            continue;
        }
        verdade(preg_match('#<a href="' . preg_quote($arquivo, '#') . '"[^>]*aria-current="page"[^>]*>#', $html) === 1, "$arquivo: link marcado");
        igual(3, substr_count($html, 'aria-current="page"'), "$arquivo: nav, gaveta e rodape");
        foreach (MULTI_PAGINAS as $outra) {
            if ($outra !== $arquivo) {
                verdade(preg_match('#<a href="' . preg_quote($outra, '#') . '"[^>]*aria-current#', $html) === 0, "$arquivo: $outra nao pode estar marcada");
            }
        }
    }
});

teste('a home resume a Casa Pronta e nao traz mais portfolio nem passo a passo', function (): void {
    $html = multi_pagina('index.php');
    contem('id="casa-pronta"', $html);
    contem('class="prontahome__media', $html, 'a foto do modelo em destaque');
    contem('<a href="casa-pronta.php" class="btn btn--primary btn--lg">Ver a Casa Pronta</a>', $html);
    nao_contem('id="portfolio"', $html);
    nao_contem('id="como-funciona"', $html);
    nao_contem('class="grid grid--models"', $html, 'a grade de modelos mora em casa-pronta.php');
    nao_contem('<article class="model', $html);
    nao_contem('id="galeria"', $html);
    nao_contem('id="processSteps"', $html);
    foreach (['id="prova"', 'id="modalidades"', 'id="castelo-flex"', 'id="vantagens"', 'id="depoimentos"', 'id="instagram"', 'id="faq"', 'class="cta-band"'] as $marca) {
        contem($marca, $html, "home sem $marca");
    }
    contem('<span class="hl">chave na mão</span>', $html, 'o subtitulo do hero com o realce');
});

teste('casa-pronta.php traz os quatro modelos com preco, o incluso, os cinco passos e o FAQ', function (): void {
    $html = multi_pagina('casa-pronta.php');
    contem('class="pagehero"', $html);
    contem('id="modelos"', $html);
    contem('class="grid grid--models"', $html);
    igual(4, substr_count($html, '<article class="model'));
    foreach (['69.900', '79.988', '87.997', '97.776'] as $preco) {
        contem('<span class="price__cur">R$</span> ' . $preco, $html, "preco $preco");
    }
    contem('id="incluso"', $html);
    foreach (['Laje aérea', 'Instalação elétrica', 'Instalação hidráulica', 'Cerâmica', 'Fossa', 'Sumidouro', 'Vidros e aberturas', 'Prego galvanizado', '100% personalizável'] as $item) {
        contem($item, $html, "incluso sem $item");
    }
    contem('id="como-funciona"', $html);
    contem('id="processSteps"', $html);
    igual(5, substr_count($html, '<li class="process__step'));
    igual(5, substr_count($html, 'class="process__media-fig'));
    contem('id="faq"', $html);
    contem('id="faqTabs"', $html);
    verdade(substr_count($html, 'class="faq__tab') >= 5, 'abas do FAQ geral');
    contem('class="cta-band"', $html);
    contem('90 a 120 dias', $html);
});

teste('portfolio.php traz a grade com as seis casas cadastradas', function (): void {
    $html = multi_pagina('portfolio.php');
    contem('class="pagehero"', $html);
    contem('id="galeria"', $html);
    igual(6, substr_count($html, '<button class="galeria__item"'));
    igual(6, preg_match_all('#<span class="galeria__eyebrow">[^<]+</span>#', $html));
    igual(6, preg_match_all('#<span class="galeria__title">[^<]+</span>#', $html));
    contem('data-titulo="Sobrado à beira da água"', $html);
    contem('id="fotobox"', $html, 'a foto abre em tela cheia');
    nao_contem('accordion', $html, 'o acordeao antigo saiu');
    contem('class="cta-band cta-band--bone"', $html);

    // a grade cresce com o cadastro
    db()->exec("INSERT INTO portfolio (titulo, categoria, foto, foto_alt, ativo, ordem) VALUES ('Casa nova', 'Teste', 'uploads/portfolio/x.webp', 'Alt', 1, 99)");
    igual(7, substr_count(render(site() . '/portfolio.php'), '<button class="galeria__item"'));
    db()->exec("DELETE FROM portfolio WHERE titulo = 'Casa nova'");
});

teste('contato.php embute o formulario do contrato 6.1 e nao carrega o modal', function (): void {
    $html = multi_pagina('contato.php');
    contem('class="pagehero"', $html);
    contem('id="formulario"', $html);
    contem('<div class="contato__card reveal">', $html);
    contem('<form class="qform" id="quoteForm" method="post" action="enviar.php" novalidate>', $html);
    foreach (['nome', 'whatsapp', 'busca', 'modelo', 'cidade', 'mensagem', 'pagina', 'referrer',
              'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'empresa', 'ts', 'csrf'] as $campo) {
        igual(1, substr_count($html, 'name="' . $campo . '"'), "campo $campo, uma vez");
    }
    contem('id="quoteDone"', $html);
    contem('id="quoteBack"', $html);
    nao_contem('id="quoteModal"', $html);
    nao_contem('class="qmodal"', $html);
    nao_contem('id="wppFloat"', $html);
    nao_contem('data-quote-close', $html);
    verdade(substr_count($html, 'data-quote-open') >= 3, 'os CTAs da pagina rolam ate o formulario');

    contem('id="canais"', $html);
    contem('https://wa.me/5548998244494', $html);
    contem('(48) 99824-4494', $html);
    contem('href="tel:+554836328743"', $html);
    contem('(48) 3632-8743', $html);
    contem('Segunda a sexta, 8h às 12h e 13h30 às 18h', $html);
    contem('Sábado, 8h30 às 12h', $html);
    contem('Av. Patrício Lima, 843', $html);
    contem('id="mapa"', $html);
    verdade(preg_match('#<iframe\s+src="https://www\.google\.com/maps\?q=[^"]+&output=embed"\s+title="[^"]+"\s+loading="lazy"#s', $html) === 1, 'iframe do mapa com title e loading=lazy');
    nao_contem('footer__map', $html, 'o rodape da pagina de contato nao repete o mapa');
    contem('<option value="Quero a Castelo Flex, semipronta">', $html, 'quem chega pelo contato pode pedir a Flex');
});

teste('nenhuma pagina tem id duplicado', function (): void {
    foreach (MULTI_PAGINAS as $arquivo) {
        preg_match_all('/ id="([^"]+)"/', multi_pagina($arquivo), $ids);
        $repetidos = array_filter(array_count_values($ids[1]), static fn (int $n): bool => $n > 1);
        igual([], $repetidos, "$arquivo: ids repetidos " . json_encode(array_keys($repetidos)));
    }
});

teste('cada pagina tem title, description, canonical e og proprios', function (): void {
    $titles = $canonicals = $descs = [];
    foreach (MULTI_PAGINAS as $chave => $arquivo) {
        $head = multi_head(multi_pagina($arquivo));
        $caminho = $chave === 'home' ? '/' : '/' . $arquivo;

        verdade(preg_match('#<title>([^<]+)</title>#', $head, $t) === 1, "$arquivo: title");
        verdade(preg_match('#<meta name="description" content="([^"]{50,})" />#', $head, $d) === 1, "$arquivo: description");
        contem('<link rel="canonical" href="' . CASTELLO_URL . $caminho . '" />', $head, "$arquivo: canonical");
        contem('<meta property="og:url" content="' . CASTELLO_URL . $caminho . '" />', $head, "$arquivo: og:url");
        verdade(preg_match('#<meta property="og:title" content="([^"]+)" />#', $head, $ot) === 1, "$arquivo: og:title");
        verdade(preg_match('#<meta property="og:description" content="([^"]{30,})" />#', $head) === 1, "$arquivo: og:description");
        verdade(preg_match('#<meta property="og:image" content="' . preg_quote(CASTELLO_URL, '#') . '/[^"]+" />#', $head) === 1, "$arquivo: og:image absoluto");
        igual(1, substr_count($head, '<title>'), "$arquivo: um title so");
        igual(1, substr_count($head, 'rel="canonical"'), "$arquivo: um canonical so");
        nao_contem('noindex', $head, $arquivo);

        $titles[] = $t[1];
        $descs[] = $d[1];
        $canonicals[] = CASTELLO_URL . $caminho;
        verdade(str_contains($t[1], 'Castello'), "$arquivo: o title cita a marca");
    }
    igual(5, count(array_unique($titles)), 'titles diferentes entre si');
    igual(5, count(array_unique($descs)), 'descriptions diferentes entre si');
    igual(5, count(array_unique($canonicals)), 'canonicals diferentes entre si');
});

teste('o sitemap lista as cinco paginas e o robots continua apontando para ele', function (): void {
    $xml = (string) file_get_contents(site() . '/sitemap.xml');
    foreach (['/', '/casa-pronta.php', '/flex.php', '/portfolio.php', '/contato.php'] as $caminho) {
        contem('<loc>' . CASTELLO_URL . $caminho . '</loc>', $xml, "sitemap sem $caminho");
    }
    igual(5, substr_count($xml, '<url>'));
    verdade(simplexml_load_string($xml) !== false, 'sitemap e XML valido');

    $robots = (string) file_get_contents(site() . '/robots.txt');
    contem('Sitemap: ' . CASTELLO_URL . '/sitemap.xml', $robots);
    contem('Disallow: /painel/', $robots);
});

teste('o subtitulo do hero quebra o "chave na mao" em linha propria so no desktop', function (): void {
    $css = (string) file_get_contents(site() . '/css/style.css');
    verdade(preg_match('#@media\(min-width:761px\)\{\.hero__headline-sub \.hl\{display:block\}\}#', $css) === 1,
        'a regra .hero__headline-sub .hl{display:block} precisa viver dentro da media query do desktop');
    verdade(preg_match('#^\.hero__headline-sub \.hl\{[^}]*display:block#m', $css) === 0, 'fora da media query o realce fica inline');
});

teste('o menu nao depende mais do scroll-spy e o formulario embutido busca o token no carregamento', function (): void {
    $main = (string) file_get_contents(site() . '/js/main.js');
    nao_contem('Scroll-spy', $main);
    nao_contem("a[href^=\"#\"]", $main, 'nenhum seletor de ancora sobra no menu');
    contem('scrollIntoView', $main, 'sem modal, o CTA rola ate o formulario');
    contem("getElementById('fotobox')", $main, 'a galeria abre a foto em tela cheia');
    nao_contem('accordion', $main);

    $form = (string) file_get_contents(site() . '/js/formulario.js');
    contem('api.tokenNoCarregamento', $form);
    contem("closest('#quoteModal')", $form);
    nao_contem('csrf-token', $form);
});
