<?php
declare(strict_types=1);

/**
 * Site multipagina: cinco paginas com o mesmo nav, rodape e formulario.
 *
 * Home (resumo da Casa Pronta, sem portfolio nem passo a passo), Casa Pronta
 * (modelos, incluso, passos, FAQ), Castello Flex, Portfolio (grade) e Contato
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
            igual(3, substr_count($html, 'aria-current="page"'), 'home: logo, nav e gaveta');
            continue;
        }
        verdade(preg_match('#<a href="' . preg_quote($arquivo, '#') . '"[^>]*aria-current="page"[^>]*>#', $html) === 1, "$arquivo: link marcado");
        igual(2, substr_count($html, 'aria-current="page"'), "$arquivo: nav e gaveta");
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
    foreach (['id="prova"', 'id="modalidades"', 'id="castello-flex"', 'id="vantagens"', 'id="depoimentos"', 'id="instagram"', 'id="faq"', 'class="cta-band"'] as $marca) {
        contem($marca, $html, "home sem $marca");
    }
    contem('<span class="hero__linha" data-hero-etapa="3"><span class="hl">no seu ritmo.</span></span>', $html, 'o subtitulo do hero com o realce');
    contem('<a href="casa-pronta.php" class="btn btn--primary btn--lg">Casa Pronta</a>', $html, 'o hero leva as duas modalidades');
    contem('<a href="flex.php" class="btn btn--ghost-light btn--lg">Casa Flex</a>', $html);
    contem('data-count="71"', $html, '71 avaliacoes');
    contem('data-count="19000"', $html, 'mais de 19.000 m2');
    contem('Desde</span> 2014', $html);
    nao_contem('emipront', $html, 'a palavra semipronta saiu do site');
});

teste('casa-pronta.php traz os 12 projetos com preco, o incluso, a linha do tempo e o FAQ', function (): void {
    $html = multi_pagina('casa-pronta.php');
    contem('class="pagehero"', $html);
    contem('id="modelos"', $html);
    contem('class="grid grid--models"', $html);
    igual(12, substr_count($html, '<article class="model'));
    foreach (['108.000', '326.000', '72.000', '135.000'] as $preco) {
        contem('<span class="price__cur">R$</span> ' . $preco, $html, "preco $preco");
    }
    contem('id="incluso"', $html);
    foreach (['Laje aérea', 'Instalação elétrica', 'Instalação hidráulica', 'Cerâmica', 'Fossa', 'Sumidouro', 'Vidros e aberturas', 'Prego galvanizado', 'Personalizável', 'Estudo preliminar'] as $item) {
        contem($item, $html, "incluso sem $item");
    }
    contem('id="como-funciona"', $html);
    igual(20, substr_count($html, '<li class="hpasso'), 'as 20 etapas, todas com foto');
    igual(0, substr_count($html, 'hpasso--ico'), 'nenhuma etapa sem foto');
    contem('data-hscroll', $html, 'a secao trava e rola na horizontal');
    contem('<div class="hscroll__track">', $html);
    contem('Pronta para morar de <strong>90 a 120 dias</strong>', $html);
    nao_contem('Pedir orçamento</button>' . "\n" . '        <a href="#modelos"', $html, 'o hero ficou so com Ver modelos e precos');
    contem('<a href="#modelos" class="btn btn--primary btn--lg">Ver modelos e preços</a>', $html);
    contem('id="modelbox"', $html, 'o Ver mais dos projetos');
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
    contem('class="cta-band cta-band--bone cta-band--largo"', $html);

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
    igual(1, substr_count($html, 'https://wa.me/'), 'nenhum botao de WhatsApp: so o plano B escondido da tela de sucesso');
    contem('id="quoteWppLink" href="https://wa.me/', $html);
    nao_contem('Chamar no WhatsApp', $html);
    nao_contem('tel:', $html, 'o telefone fixo saiu por enquanto');
    nao_contem('3632-8743', $html);
    contem('Segunda a sexta, 8h às 12h e 13h30 às 18h', $html);
    contem('Sábado, 8h30 às 12h', $html);
    contem('Av. Severiano Albino Corrêa, 729', $html);
    nao_contem('Patrício Lima', $html, 'o endereco antigo saiu');
    contem('id="mapa"', $html);
    verdade(preg_match('#<iframe\s+src="https://www\.google\.com/maps\?q=[^"]+&amp;output=embed"\s+title="[^"]+"\s+loading="lazy"#s', $html) === 1, 'iframe do mapa com title e loading=lazy');
    nao_contem('footer__map', $html, 'o rodape da pagina de contato nao repete o mapa');
    contem('<option value="Quero a Castello Flex">', $html, 'quem chega pelo contato pode pedir a Flex');
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

teste('o hero mantem o titulo e entra o subtitulo em duas etapas, dividido na virgula', function (): void {
    $html = render(site() . '/index.php');
    contem('<h1 class="hero__headline">Sua casa', $html);
    contem('<span class="hero__linha" data-hero-etapa="2">do seu jeito,</span>', $html);
    contem('<span class="hero__linha" data-hero-etapa="3"><span class="hl">no seu ritmo.</span></span>', $html);
    contem('<div class="hero__actions" data-hero-etapa="3">', $html, 'os botoes entram com a terceira linha');
    nao_contem('heroBeatTwo', $html, 'nao existe mais troca de bloco');

    db()->prepare('UPDATE blocos SET valor = ? WHERE chave = ?')->execute(['sem virgula nenhuma', 'hero_subtitulo']);
    $sem = render(site() . '/index.php');
    nao_contem('data-hero-etapa="2"', $sem, 'sem virgula nao ha segunda etapa');
    contem('<span class="hero__linha" data-hero-etapa="3">sem virgula nenhuma</span>', $sem);
    db()->prepare('UPDATE blocos SET valor = ? WHERE chave = ?')->execute(['do seu jeito, *no seu ritmo.*', 'hero_subtitulo']);

    $main = (string) file_get_contents(site() . '/js/main.js');
    contem("var ENTRA = { 2: [0.36, 0.48], 3: [0.78, 0.9] };", $main);
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

teste('a pagina /link abre o formulario em popup e fica fora do Google', function (): void {
    $html = render(site() . '/link.php');
    contem('<meta name="robots" content="noindex, follow" />', $html);
    contem('<meta property="og:image" content="' . CASTELLO_URL . '/images/og-link.jpg" />', $html, 'miniatura propria no WhatsApp');
    verdade(is_file(site() . '/images/og-link.jpg'), 'a imagem da miniatura existe');
    contem('class="lk-cta lk-entra" style="--i:1" data-quote-open', $html, 'o orcamento e o primeiro botao e abre o popup');
    contem('id="quoteModal"', $html, 'o formulario vem junto, na propria pagina');
    nao_contem('id="wppFloat"', $html, 'sem botao flutuante: o orcamento ja esta no topo');
    contem('href="casa-pronta.php"', $html);
    contem('href="flex.php"', $html);
    contem('A partir de R$ 72 mil', $html, 'o menor preco da Casa Pronta, do banco');
    contem('A partir de R$ 43 mil', $html, 'o menor preco da Flex, do banco');
    contem('instagram.com/castellocasasdemadeira', $html);
    contem('facebook.com/castellomadeiras', $html);
    contem('mailto:', $html);
    contem('href="https://www.freelainhome.com.br/"', $html);
    nao_contem('wa.me/5548998244494" class', $html);
    $ht = (string) file_get_contents(site() . '/.htaccess');
    contem('RewriteRule ^link/$ /link [R=301,L]', $ht, 'com barra, redireciona: os caminhos sao da raiz');
    contem('RewriteRule ^link$ link.php [L]', $ht);
    falso(is_dir(site() . '/link'), 'a pasta antiga /link saiu, senao ela ganha da regra');
    $sitemap = (string) file_get_contents(site() . '/sitemap.xml');
    nao_contem('/link', $sitemap);
});
