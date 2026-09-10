<?php
declare(strict_types=1);

require_once site() . '/lib/conteudo.php';
require_once site() . '/lib/auth.php';

banco_com_conteudo();

// A comparacao byte a byte com o index.html do prototipo saiu na costura: a home
// agora segue a marcacao da fase 2, conferida em 45-front.php e nos testes abaixo.

teste('index.php nao vaza codigo PHP nem aviso do PHP', function (): void {
    $html = render(site() . '/index.php');
    nao_contem('<?php', $html);
    nao_contem('<?=', $html);
    nao_contem('Warning:', $html);
    nao_contem('Notice:', $html);
    nao_contem('Deprecated:', $html);
    nao_contem('Fatal error', $html);
});

teste('a home respeita o limite de videos da config', function (): void {
    $html = render(site() . '/index.php');
    igual(8, substr_count($html, 'class="ivid"'));
    contem('<span id="reelboxCount">1 / 8</span>', $html);
});

teste('o select de modelo do formulario sai do banco', function (): void {
    $html = render(site() . '/index.php');
    contem('<option value="Compacta · 39 m² · R$ 69.900">Compacta · 39 m² · R$ 69.900</option>', $html);
    contem('<option value="Ampla · 59,75 m² · R$ 97.776">Ampla · 59,75 m² · R$ 97.776</option>', $html);
    contem('<option value="">Ainda não sei</option>', $html);
});

/**
 * Referencia de cada pagina: a marcacao estatica da frente 2, guardada em
 * testes/base/*-fase2.html (antes da costura, em front/). Os desvios
 * deliberados da costura sao aplicados aqui, e so eles:
 *   1. assets enxergados pela raiz do site, nao por ../public_html/;
 *   2. links .html viram .php;
 *   3. titulos que viraram blocos editaveis perdem o <br> de controle;
 *   4. o formulario ganha method e action, para funcionar tambem sem JS.
 */
function referencia_pagina(string $pagina): string
{
    foreach ([raiz() . '/testes/base/' . $pagina . '-fase2.html', raiz() . '/front/' . $pagina . '.html'] as $caminho) {
        if (is_file($caminho)) {
            $html = (string) file_get_contents($caminho);
            $html = str_replace('../public_html/', '', $html);
            $html = str_replace(['href="flex.html"', 'href="home.html', '"home.html"'], ['href="flex.php"', 'href="index.php', '"index.php"'], $html);
            $html = str_replace([
                'Escolha como a sua casa<br>sai do papel.',
                'Escolha o tamanho.<br>A gente entrega completa.',
                'A estrutura pronta.<br>O acabamento no seu tempo.',
            ], [
                'Escolha como a sua casa sai do papel.',
                'Escolha o tamanho. A gente entrega completa.',
                'A estrutura pronta. O acabamento no seu tempo.',
            ], $html);
            $html = str_replace('<form class="qform" id="quoteForm" novalidate>', '<form class="qform" id="quoteForm" method="post" action="enviar.php" novalidate>', $html);
            // Os comentarios de fronteira existem so para o teste dos partials.
            return (string) preg_replace('#<!-- (inicio|fim):[a-z-]+ -->\n?#', '', $html);
        }
    }
    throw new RuntimeException('referencia da pagina ' . $pagina . ' nao encontrada');
}

/**
 * So o miolo entre <body> e o primeiro <script>, sem comentarios HTML: head e
 * scripts tem testes proprios, e comentario nao e conteudo.
 */
function corpo(string $html): string
{
    $ini = strpos($html, '<body>');
    $fim = strpos($html, '<script', $ini === false ? 0 : $ini);
    verdade($ini !== false && $fim !== false, 'a pagina precisa ter <body> e <script>');
    return (string) preg_replace('/<!--.*?-->/s', '', substr($html, $ini, $fim - $ini));
}

teste('a home sai identica a marcacao da fase 2', function (): void {
    config_gravar('videos_na_home', '11');
    $novo = render(site() . '/index.php');
    config_gravar('videos_na_home', '8');
    igual(norm(corpo(referencia_pagina('home'))), norm(corpo($novo)));
});

teste('a pagina flex sai identica a marcacao da fase 2', function (): void {
    $novo = render(site() . '/flex.php');
    igual(norm(corpo(referencia_pagina('flex'))), norm(corpo($novo)));
});

teste('home renderiza com as secoes esperadas e sem prazo fixo no topo', function (): void {
    $html = render(site() . '/index.php');
    foreach (['id="topo"', 'id="prova"', 'id="modalidades"', 'id="casa-pronta"', 'id="castelo-flex"', 'id="vantagens"',
              'id="portfolio"', 'id="como-funciona"', 'id="depoimentos"', 'id="instagram"', 'id="faq"', 'id="contato"',
              'class="cta-band"'] as $marca) {
        contem($marca, $html, "home nao tem $marca");
    }
    nao_contem('em até 120 dias', $html, 'prazo fixo continua no topo da home');
    nao_contem('id="modelos"', $html, 'o id antigo #modelos deu lugar a #casa-pronta');
    contem('Casa Pronta e Castelo Flex em Tubarão SC</title>', $html);
    contem('href="flex.php"', $html);
    nao_contem('.html', $html, 'nenhum link para .html sobra na home');
});

teste('a home imprime os textos editaveis do banco', function (): void {
    db()->prepare('UPDATE blocos SET valor = ? WHERE chave = ?')->execute(['Titulo *editado* pelo painel', 'hero_subtitulo']);
    db()->prepare('UPDATE blocos SET valor = ? WHERE chave = ?')->execute(['Texto novo de modalidades', 'modalidades_texto']);
    db()->prepare('UPDATE blocos SET valor = ? WHERE chave = ?')->execute(['60 dias', 'flex_prazo']);
    $html = render(site() . '/index.php');
    contem('Titulo <span class="hl">editado</span> pelo painel', $html);
    contem('Texto novo de modalidades', $html);
    contem('<strong>60 dias</strong>', $html, 'o prazo da Flex no bloco de modalidades vem do banco');
    nao_contem('<strong>45 dias</strong>', $html, 'nenhum prazo da Flex fica fixo na home (o titulo flex_titulo e outro bloco, editavel)');
    nao_contem('Entrega em 45 dias', $html);
    contem('<strong>90 a 120 dias</strong>', $html, 'o prazo da Casa Pronta vem do bloco pronta_prazo');

    // o video da Flex some quando o bloco esta vazio
    db()->prepare("UPDATE blocos SET valor = '' WHERE chave = 'flex_video'")->execute();
    nao_contem('class="vexp vexp--reel', render(site() . '/index.php'), 'sem video cadastrado, a figura nao aparece');

    db()->prepare("UPDATE blocos SET valor = 'uploads/videos/insta-04.mp4' WHERE chave = 'flex_video'")->execute();
    db()->prepare("UPDATE blocos SET valor = '45 dias' WHERE chave = 'flex_prazo'")->execute();
    db()->prepare("UPDATE blocos SET valor = 'pronta pra morar, *chave na mão*' WHERE chave = 'hero_subtitulo'")->execute();
    db()->prepare("UPDATE blocos SET valor = ? WHERE chave = 'modalidades_texto'")->execute([
        'A Castello entrega a casa completa, pronta pra morar, e agora entrega também a casa semipronta, para quem quer a estrutura no terreno e o acabamento no próprio ritmo.',
    ]);
});

teste('a home traz nav, rodape e modal pelos parciais compartilhados', function (): void {
    $html = render(site() . '/index.php');
    contem('<header class="nav" id="nav">', $html);
    contem('id="ico-google"', $html);
    contem('<footer class="footer" id="contato">', $html);
    contem('id="quoteModal"', $html);
    contem('id="reelbox"', $html);
    contem('id="wppFloat"', $html);
    contem('<script src="js/main.js?v=13"></script>', $html);
});

teste('pagina flex renderiza com as secoes esperadas e o prazo', function (): void {
    $html = render(site() . '/flex.php');
    foreach (['id="topo"', 'id="o-que-e"', 'id="passos-flex"', 'id="modelos-flex"', 'id="diferenciais"', 'id="faq"',
              'id="orcamento"', 'id="contato"', 'class="pagehero"', 'class="epasso__grid"', 'id="faqTabs"',
              'Sob consulta'] as $marca) {
        contem($marca, $html, "flex nao tem $marca");
    }
    contem('45 dias', $html, 'pagina flex nao mostra o prazo');
    contem('<title>Castelo Flex | Casa de madeira semipronta em 45 dias | Castello</title>', $html);
    contem('href="index.php#casa-pronta"', $html);
    nao_contem('.html', $html);
    nao_contem('id="reelbox"', $html, 'a Flex nao tem trilha do Instagram, entao nao carrega o lightbox');
    nao_contem('Warning:', $html);
    nao_contem('Fatal error', $html);
    nao_contem('<?php', $html);
});

teste('a pagina flex esconde o que o cliente ainda nao cadastrou, sem quebrar', function (): void {
    db()->exec("UPDATE modelos SET ativo = 0 WHERE modalidade = 'flex'");
    db()->exec("UPDATE faq SET ativo = 0 WHERE contexto = 'flex'");
    db()->exec("UPDATE passos SET ativo = 0 WHERE contexto = 'flex'");
    db()->exec("UPDATE blocos SET valor = '' WHERE chave IN ('flex_video', 'flexpg_hero_texto', 'flexpg_oque_texto', 'flexpg_catalogo_nota')");

    $html = render(site() . '/flex.php');
    nao_contem('class="grid grid--models"', $html, 'sem modelo Flex ativo, a grade nao aparece');
    nao_contem('id="faqTabs"', $html, 'sem FAQ Flex ativa, o bloco nao aparece');
    nao_contem('class="epasso__grid"', $html, 'sem passo Flex ativo, a grade nao aparece');
    nao_contem('class="vexp', $html, 'sem video, a figura nao aparece');
    nao_contem('class="modelos__note', $html, 'sem nota, o paragrafo nao aparece');
    contem('<header class="nav" id="nav">', $html);
    contem('<footer class="footer" id="contato">', $html);
    contem('id="quoteModal"', $html);
    contem('45 dias', $html, 'o prazo do bloco continua');
    nao_contem('Warning:', $html);
    nao_contem('<?php', $html);
    igual(1, substr_count($html, '<form'), 'nenhum formulario inline alem do modal');

    db()->exec("UPDATE modelos SET ativo = 1 WHERE modalidade = 'flex'");
    db()->exec("UPDATE faq SET ativo = 1 WHERE contexto = 'flex'");
    db()->exec("UPDATE passos SET ativo = 1 WHERE contexto = 'flex'");
});

teste('as duas paginas tem um formulario so, o modal compartilhado, e todo CTA abre ele', function (): void {
    foreach (['index.php', 'flex.php'] as $arquivo) {
        $html = render(site() . '/' . $arquivo);
        igual(1, substr_count($html, '<form'), "$arquivo: nenhum formulario inline alem do modal");
        contem('<form class="qform" id="quoteForm" method="post" action="enviar.php" novalidate>', $html, $arquivo);
        verdade(substr_count($html, 'data-quote-open') >= 6, "$arquivo: os CTAs abrem o modal");
        nao_contem('_gotcha', $html, "$arquivo: honeypot antigo");
        contem('name="empresa"', $html, "$arquivo: honeypot do contrato");
    }
});

teste('csrf.php responde JSON com um token utilizavel', function (): void {
    $saida = render(site() . '/csrf.php');

    $dados = json_decode($saida, true);
    verdade(is_array($dados), 'a resposta precisa ser JSON, obtida: ' . mb_substr($saida, 0, 120));
    verdade(isset($dados['token']), 'o JSON precisa ter a chave token');
    verdade((bool) preg_match('/^[0-9a-f]{64}$/', (string) $dados['token']),
        'token esperado: 64 caracteres hexadecimais, obtido ' . var_export($dados['token'], true));

    verdade(csrf_validar((string) $dados['token']), 'o token entregue precisa passar em csrf_validar');
    igual(['token'], array_keys($dados), 'a resposta nao devolve mais nada alem do token');
});

teste('o token e o mesmo dentro da sessao e muda quando a sessao muda', function (): void {
    $primeiro = json_decode(render(site() . '/csrf.php'), true)['token'];
    $segundo  = json_decode(render(site() . '/csrf.php'), true)['token'];
    igual($primeiro, $segundo, 'na mesma sessao o token nao pode mudar a cada chamada');

    // Simula outro visitante: a sessao e zerada e o token e sorteado de novo.
    $_SESSION = [];
    $outro = json_decode(render(site() . '/csrf.php'), true)['token'];

    verdade($outro !== $primeiro, 'visitantes diferentes precisam receber tokens diferentes');
    verdade(csrf_validar($outro));
    falso(csrf_validar($primeiro), 'o token da sessao antiga deixa de valer');
});

teste('csrf.php manda os cabecalhos certos', function (): void {
    $fonte = (string) file_get_contents(site() . '/csrf.php');

    contem("Content-Type: application/json; charset=utf-8", $fonte);
    contem("Cache-Control: private, no-store", $fonte);
    contem('auth_iniciar()', $fonte);
    contem('csrf_token()', $fonte);
});

teste('nenhuma pagina do site imprime o token no HTML', function (): void {
    foreach ([site() . '/index.php', site() . '/flex.php'] as $pagina) {
        $html = render($pagina);

        nao_contem('csrf-token', $html, 'metatag de token em ' . basename($pagina));
        nao_contem('Cache-Control', (string) file_get_contents($pagina),
            basename($pagina) . ' precisa continuar cacheavel por inteiro');
    }
});

teste('o formulario do modal tem o campo oculto de csrf, vazio para o JS preencher', function (): void {
    foreach ([site() . '/index.php', site() . '/flex.php'] as $pagina) {
        $html = render($pagina);
        contem('<input type="hidden" name="csrf" value="" />', $html, basename($pagina));
        igual(1, substr_count($html, 'name="csrf"'), 'um campo csrf so, em ' . basename($pagina));
    }
});
