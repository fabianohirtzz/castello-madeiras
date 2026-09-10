<?php
declare(strict_types=1);

require_once site() . '/lib/conteudo.php';
require_once site() . '/lib/auth.php';

banco_com_conteudo();

teste('index.php renderiza a home identica ao index.html original', function (): void {
    config_gravar('videos_na_home', '11');
    $novo = render(site() . '/index.php');
    config_gravar('videos_na_home', '8');

    $velho = file_get_contents(raiz() . '/testes/base/home-original.html');
    verdade($velho !== false, 'testes/base/home-original.html precisa existir');

    // Sem excecao de alt: a unica diferenca legitima e o caminho da midia, que
    // norm() desfaz, e o campo oculto de csrf, que tem teste proprio abaixo.
    // Todo o resto sai byte a byte igual ao prototipo.
    $novo = (string) preg_replace('#\s*<input type="hidden" name="csrf" value="" />#u', '', $novo);

    igual(norm((string) $velho), norm($novo));
});

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

teste('a home traz nav, rodape e modal pelos parciais compartilhados', function (): void {
    $html = render(site() . '/index.php');
    contem('<header class="nav" id="nav">', $html);
    contem('id="ico-google"', $html);
    contem('<footer class="footer" id="contato">', $html);
    contem('id="quoteModal"', $html);
    contem('id="reelbox"', $html);
    contem('id="wppFloat"', $html);
    contem('<script src="js/main.js?v=12"></script>', $html);
});

teste('flex.php renderiza sem erro mesmo com o conteudo da Flex ainda vazio', function (): void {
    $html = render(site() . '/flex.php');
    contem('<!DOCTYPE html>', $html);
    contem('<header class="nav" id="nav">', $html);
    contem('<footer class="footer" id="contato">', $html);
    contem('id="quoteModal"', $html);
    contem('45 dias', $html, 'o prazo da Flex vem do bloco flex_prazo');
    nao_contem('Warning:', $html);
    nao_contem('Fatal error', $html);
    nao_contem('<?php', $html);
    nao_contem('class="grid grid--models"', $html, 'sem modelo Flex cadastrado, a grade nao aparece');
    nao_contem('id="faqTabs"', $html, 'sem FAQ Flex cadastrado, o bloco nao aparece');
    nao_contem('id="flex-depois"', $html, 'sem texto escrito, a secao nao aparece');
});

teste('flex.php tem um formulario so, o modal compartilhado', function (): void {
    $html = render(site() . '/flex.php');
    igual(1, substr_count($html, '<form'), 'nenhum formulario inline alem do modal');
    contem('data-quote-open', $html);
});

teste('flex.php monta as oito secoes assim que o conteudo Flex existir', function (): void {
    db()->exec("INSERT INTO modelos (modalidade, nome, area, parede, preco, foto, foto_alt, ativo, ordem)
                VALUES ('flex','Flex 30','30,00 m²','Parede simples','39.900','uploads/modelos/casa1.png','Casa Flex',1,1)");
    db()->exec("INSERT INTO faq (pergunta, resposta, icone, contexto, ativo, ordem)
                VALUES ('O que a Castello entrega na Flex?','A estrutura montada e fechada.','chave','flex',1,1)");
    db()->exec("INSERT INTO passos (contexto, titulo, texto, imagem, ativo, ordem)
                VALUES ('flex','Entrega da estrutura','A Castello entrega e monta.','uploads/passos/passo-1.jpg',1,1)");

    $textos = [
        'flexpg_hero_titulo'   => 'Sua casa começa montada',
        'flexpg_hero_texto'    => 'A estrutura pronta em 45 dias, o acabamento no seu tempo.',
        'flexpg_oque_titulo'   => 'O que é a Castelo Flex',
        'flexpg_oque_texto'    => 'A Castello entrega a casa fechada e montada no seu terreno.',
        'flexpg_depois_titulo' => 'O que fica por sua conta',
        'flexpg_depois_texto'  => 'Acabamento interno, elétrica, hidráulica e revestimentos.',
        'flexpg_catalogo_nota' => 'Valores de referência para a estrutura montada.',
        'flexpg_cta_titulo'    => 'Quer um orçamento da Castelo Flex?',
        'flexpg_cta_texto'     => 'Conte o tamanho que você imagina e a gente volta com uma proposta.',
        'flex_video'           => 'uploads/videos/insta-01.mp4',
        'flex_video_poster'    => 'uploads/videos/insta-01.jpg',
    ];
    $st = db()->prepare('UPDATE blocos SET valor = ? WHERE chave = ?');
    foreach ($textos as $chave => $valor) {
        $st->execute([$valor, $chave]);
    }

    $html = render(site() . '/flex.php');

    foreach ($textos as $chave => $valor) {
        if (str_starts_with($chave, 'flexpg_')) {
            contem(e($valor), $html, "o bloco $chave precisa aparecer na pagina");
        }
    }

    contem('id="flex-oque"', $html);
    contem('id="flex-passos"', $html);
    contem('id="flex-depois"', $html);
    contem('id="flex-catalogo"', $html);
    contem('id="flex-prazo"', $html);
    contem('id="flex-faq"', $html);
    contem('class="cta-band"', $html);

    contem('class="grid grid--models"', $html);
    contem('data-modelo="Flex 30 · 30 m² · R$ 39.900"', $html);
    contem('id="faqTabs"', $html);
    contem('id="processSteps"', $html);
    contem('<source src="uploads/videos/insta-01.mp4" type="video/mp4" />', $html);
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
