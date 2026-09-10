<?php
declare(strict_types=1);

require_once site() . '/lib/conteudo.php';
require_once site() . '/lib/auth.php';

banco_com_conteudo();

/** chave => arquivo. As cinco paginas do site. */
const PAGINAS_SITE = [
    'home'        => 'index.php',
    'casa-pronta' => 'casa-pronta.php',
    'flex'        => 'flex.php',
    'portfolio'   => 'portfolio.php',
    'contato'     => 'contato.php',
];

/**
 * Referencia de cada pagina: testes/base/pagina-*.html, gerado a partir da
 * propria pagina depois da conferencia no navegador (2026-09-10, site
 * multipagina). Qualquer mudanca de marcacao tem que vir acompanhada de uma
 * base nova, conferida de novo.
 */
function referencia_pagina(string $pagina): string
{
    $caminho = raiz() . '/testes/base/pagina-' . $pagina . '.html';
    if (!is_file($caminho)) {
        throw new RuntimeException('referencia da pagina ' . $pagina . ' nao encontrada em ' . $caminho);
    }
    return (string) file_get_contents($caminho);
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

teste('nenhuma pagina vaza codigo PHP nem aviso do PHP', function (): void {
    foreach (PAGINAS_SITE as $arquivo) {
        $html = render(site() . '/' . $arquivo);
        nao_contem('<?php', $html, $arquivo);
        nao_contem('<?=', $html, $arquivo);
        nao_contem('Warning:', $html, $arquivo);
        nao_contem('Notice:', $html, $arquivo);
        nao_contem('Deprecated:', $html, $arquivo);
        nao_contem('Fatal error', $html, $arquivo);
    }
});

foreach (PAGINAS_SITE as $chave => $arquivo) {
    teste("$arquivo sai identico a base conferida no navegador", function () use ($chave, $arquivo): void {
        igual(norm(corpo(referencia_pagina($chave))), norm(corpo(render(site() . '/' . $arquivo))));
    });
}

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

teste('home renderiza com as secoes esperadas e sem prazo fixo no topo', function (): void {
    $html = render(site() . '/index.php');
    foreach (['id="topo"', 'id="prova"', 'id="modalidades"', 'id="casa-pronta"', 'id="castelo-flex"', 'id="vantagens"',
              'id="depoimentos"', 'id="instagram"', 'id="faq"', 'id="contato"', 'class="cta-band"'] as $marca) {
        contem($marca, $html, "home nao tem $marca");
    }
    nao_contem('em até 120 dias', $html, 'prazo fixo continua no topo da home');
    nao_contem('id="modelos"', $html, 'o id antigo #modelos deu lugar a #casa-pronta');
    contem('Casa Pronta e Castelo Flex em Tubarão SC</title>', $html);
    contem('href="flex.php"', $html);
    contem('href="casa-pronta.php"', $html, 'a home leva a pagina da Casa Pronta');
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

teste('o resumo da Casa Pronta na home usa a foto do modelo em destaque', function (): void {
    $html = render(site() . '/index.php');
    contem('class="prontahome__media reveal reveal--mask"', $html);
    contem('uploads/modelos/casa5.webp', $html, 'foto do modelo Familia, o destaque');
    contem('Modelo Família · 51 m²', $html);
    contem('<strong>R$ 87.997</strong>', $html);
    contem('href="casa-pronta.php" class="btn btn--primary btn--lg">Ver a Casa Pronta</a>', $html);

    // sem destaque, vale o primeiro modelo; sem modelo nenhum, a figura some
    db()->exec("UPDATE modelos SET destaque = 0 WHERE modalidade = 'pronta'");
    contem('uploads/modelos/casa4.webp', render(site() . '/index.php'), 'sem destaque, o primeiro modelo');
    db()->exec("UPDATE modelos SET ativo = 0 WHERE modalidade = 'pronta'");
    nao_contem('prontahome__media', render(site() . '/index.php'), 'sem modelo ativo, sem figura');
    db()->exec("UPDATE modelos SET ativo = 1 WHERE modalidade = 'pronta'");
    db()->exec("UPDATE modelos SET destaque = 1 WHERE modalidade = 'pronta' AND nome = 'Família'");
});

teste('toda pagina traz nav, rodape e os scripts pelos parciais compartilhados', function (): void {
    foreach (PAGINAS_SITE as $arquivo) {
        $html = render(site() . '/' . $arquivo);
        contem('<header class="nav" id="nav">', $html, $arquivo);
        contem('id="ico-google"', $html, $arquivo);
        contem('<footer class="footer" id="contato">', $html, $arquivo);
        contem('<script src="js/main.js?v=14"></script>', $html, $arquivo);
        contem('<script src="js/formulario.js?v=2" defer></script>', $html, $arquivo);
    }
    $home = render(site() . '/index.php');
    contem('id="quoteModal"', $home);
    contem('id="reelbox"', $home);
    contem('id="wppFloat"', $home);
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
    contem('href="casa-pronta.php"', $html, 'a Flex aponta para a pagina da Casa Pronta');
    nao_contem('index.php#casa-pronta', $html, 'a ancora antiga da home saiu');
    nao_contem('.html', $html);
    nao_contem('id="reelbox"', $html, 'a Flex nao tem trilha do Instagram, entao nao carrega o lightbox');
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

teste('cada pagina tem um formulario so: o modal, ou o embutido na pagina de contato', function (): void {
    foreach (PAGINAS_SITE as $chave => $arquivo) {
        $html = render(site() . '/' . $arquivo);
        igual(1, substr_count($html, '<form'), "$arquivo: um formulario so");
        contem('<form class="qform" id="quoteForm" method="post" action="enviar.php" novalidate>', $html, $arquivo);
        verdade(substr_count($html, 'data-quote-open') >= 3, "$arquivo: os CTAs levam ao formulario");
        nao_contem('_gotcha', $html, "$arquivo: honeypot antigo");
        contem('name="empresa"', $html, "$arquivo: honeypot do contrato");
        if ($chave === 'contato') {
            nao_contem('id="quoteModal"', $html, 'a pagina de contato nao carrega o modal');
            nao_contem('id="wppFloat"', $html, 'nem o botao flutuante, que abriria o modal');
            contem('<div class="contato__card reveal">', $html, 'o formulario mora no cartao da pagina');
        } else {
            contem('id="quoteModal"', $html, "$arquivo: modal compartilhado");
            contem('id="wppFloat"', $html, "$arquivo: botao flutuante");
        }
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
    foreach (PAGINAS_SITE as $arquivo) {
        $html = render(site() . '/' . $arquivo);

        nao_contem('csrf-token', $html, 'metatag de token em ' . $arquivo);
        nao_contem('Cache-Control', (string) file_get_contents(site() . '/' . $arquivo),
            $arquivo . ' precisa continuar cacheavel por inteiro');
    }
});

teste('o formulario tem o campo oculto de csrf, vazio para o JS preencher', function (): void {
    foreach (PAGINAS_SITE as $arquivo) {
        $html = render(site() . '/' . $arquivo);
        contem('<input type="hidden" name="csrf" value="" />', $html, $arquivo);
        igual(1, substr_count($html, 'name="csrf"'), 'um campo csrf so, em ' . $arquivo);
    }
});
