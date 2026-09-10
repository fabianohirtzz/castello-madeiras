<?php
declare(strict_types=1);

require_once site() . '/lib/conteudo.php';

$contagens = banco_com_conteudo();

teste('a migracao insere a quantidade exata de cada tabela', function () use ($contagens): void {
    igual(4,  $contagens['modelos']);
    igual(6,  $contagens['portfolio']);
    igual(14, $contagens['avaliacoes']);
    igual(11, $contagens['videos']);
    igual(7,  $contagens['faq']);
    igual(5,  $contagens['passos']);
    igual(21, $contagens['blocos']);
});

teste('os quatro modelos Casa Pronta chegaram com preco, area e parede', function (): void {
    $lista = modelos('pronta');
    igual(4, count($lista));

    igual('Compacta', $lista[0]['nome']);
    igual('39,00 m²', $lista[0]['area']);
    igual('Parede vertical', $lista[0]['parede']);
    igual('69.900', $lista[0]['preco']);
    igual('90 a 120 dias', $lista[0]['prazo']);

    igual('Conforto', $lista[1]['nome']);
    igual('79.988', $lista[1]['preco']);

    igual('Família', $lista[2]['nome']);
    igual('87.997', $lista[2]['preco']);
    igual(1, (int) $lista[2]['destaque'], 'Familia e a mais escolhida');

    igual('Ampla', $lista[3]['nome']);
    igual('97.776', $lista[3]['preco']);

    igual(0, count(modelos('flex')), 'material da Flex ainda nao chegou do cliente');
});

teste('as fotos dos modelos foram copiadas para uploads e o caminho e relativo', function (): void {
    foreach (modelos('pronta') as $m) {
        contem('uploads/modelos/', $m['foto']);
        falso(str_starts_with($m['foto'], '/'), 'caminho nunca comeca com barra');
        verdade(is_file(CASTELLO_UPLOADS . '/modelos/' . basename($m['foto'])), 'arquivo copiado: ' . $m['foto']);
        verdade($m['foto_alt'] !== '' && $m['foto_alt'] !== null, 'todo modelo tem alt escrito');
    }
    igual('uploads/modelos/casa5.png', modelos('pronta')[2]['foto']);
});

teste('o portfolio trouxe as seis casas entregues, com categoria e alt', function (): void {
    $lista = portfolio();
    igual(6, count($lista));
    igual('Sobrado à beira da água', $lista[0]['titulo']);
    igual('Beira da água', $lista[0]['categoria']);
    igual('Sobrado de madeira à beira da água com vista para a ponte', $lista[0]['foto_alt']);
    igual('Chalé com varanda', $lista[5]['titulo']);
    igual('uploads/portfolio/casa-8.png', $lista[3]['foto']);
});

teste('as 14 avaliacoes reais do Google chegaram inteiras', function (): void {
    $lista = avaliacoes();
    igual(14, count($lista));
    igual('Joana Lazzaris', $lista[0]['nome']);
    igual('Lares do Sul', $lista[13]['nome']);

    foreach ($lista as $a) {
        igual(5, (int) $a['estrelas'], 'toda avaliacao migrada e 5 estrelas');
        verdade(mb_strlen($a['texto']) > 80, 'texto completo, nao cortado: ' . $a['nome']);
    }

    contem('Tivemos uma excelente experiência com a Castello', $lista[0]['texto']);
    contem('A arquiteta Talita foi super atenciosa', $lista[3]['texto']);
});

teste('os 11 videos migraram com poster e sem o arquivo original de 25 MB', function (): void {
    $lista = videos(0);
    igual(11, count($lista));
    igual('uploads/videos/insta-01.mp4', $lista[0]['arquivo']);
    igual('uploads/videos/insta-01.jpg', $lista[0]['poster']);
    igual('uploads/videos/insta-11.mp4', $lista[10]['arquivo']);

    foreach ($lista as $v) {
        nao_contem('original', $v['arquivo'], 'insta-06.original.mp4 nao pode migrar');
        verdade(is_file(CASTELLO_UPLOADS . '/videos/' . basename($v['arquivo'])), 'video copiado: ' . $v['arquivo']);
        verdade(is_file(CASTELLO_UPLOADS . '/videos/' . basename($v['poster'])), 'poster copiado: ' . $v['poster']);
    }
    falso(is_file(CASTELLO_UPLOADS . '/videos/insta-06.original.mp4'));
});

teste('a home mostra 8 videos por causa do limite acordado na reuniao', function (): void {
    igual('8', config_ler('videos_na_home'));
    igual(8, count(videos()));
    igual(11, count(videos(0)), 'os 11 continuam cadastrados e ativos');
});

teste('as 7 perguntas do FAQ chegaram com o icone certo em cada uma', function (): void {
    $lista = faq('geral');
    igual(7, count($lista));

    $esperado = [
        ['Quanto tempo leva pra minha casa ficar pronta?', 'relogio'],
        ['O que está incluso no chave na mão?',            'chave'],
        ['Posso personalizar a planta e os acabamentos?',  'planta'],
        ['Casa de madeira é confortável o ano todo?',      'clima'],
        ['A casa é resistente e dura com o tempo?',        'escudo'],
        ['Vocês cuidam da fundação e do terreno?',         'fundacao'],
        ['Que garantias eu tenho com a Castello?',         'garantia'],
    ];

    foreach ($esperado as $i => [$pergunta, $icone]) {
        igual($pergunta, $lista[$i]['pergunta']);
        igual($icone, $lista[$i]['icone']);
        verdade(icone_faq($lista[$i]['icone']) !== '', 'o icone precisa existir no conjunto fechado');
        verdade(mb_strlen($lista[$i]['resposta']) > 60, 'resposta completa');
    }

    igual(0, count(faq('flex')), 'FAQ da Flex ainda nao existe');
});

teste('os 5 passos do Como funciona chegaram com imagem', function (): void {
    $lista = passos('pronta');
    igual(5, count($lista));
    igual('Conversa e projeto', $lista[0]['titulo']);
    igual('Fundação', $lista[1]['titulo']);
    igual('Estrutura e montagem', $lista[2]['titulo']);
    igual('Acabamento', $lista[3]['titulo']);
    igual('Chave na mão', $lista[4]['titulo']);
    igual('uploads/passos/passo-1.jpg', $lista[0]['imagem']);
    igual('uploads/passos/passo-5.png', $lista[4]['imagem']);
    contem('90 a 120 dias', $lista[4]['texto']);
    igual(0, count(passos('flex')), 'passo a passo da Flex ainda nao existe');
});

teste('os alt descritivos dos passos vieram do index.html, um a um', function (): void {
    $lista = passos('pronta');

    igual('Maquete do projeto da casa de madeira sobre a planta', $lista[0]['imagem_alt']);
    igual('Início da estrutura de madeira sobre a fundação', $lista[1]['imagem_alt']);
    igual('Estrutura e montagem da casa de madeira', $lista[2]['imagem_alt']);
    igual('Equipe no acabamento do telhado e fachada da casa', $lista[3]['imagem_alt']);
    igual('Chaves da casa de madeira pronta, chave na mão', $lista[4]['imagem_alt']);

    foreach ($lista as $p) {
        verdade($p['imagem_alt'] !== $p['titulo'], 'o alt e descritivo, nao repete o titulo: ' . $p['titulo']);
    }
});

teste('os 21 blocos de texto do contrato existem com rotulo e tipo', function (): void {
    $linhas = db()->query('SELECT chave, rotulo, tipo FROM blocos ORDER BY chave')->fetchAll();
    igual(21, count($linhas));

    $chaves = array_column($linhas, 'chave');
    foreach (['hero_titulo', 'hero_subtitulo', 'modalidades_titulo', 'modalidades_texto',
              'pronta_titulo', 'pronta_texto', 'pronta_prazo', 'flex_titulo', 'flex_texto',
              'flex_prazo', 'flex_video', 'flex_video_poster',
              'flexpg_hero_titulo', 'flexpg_hero_texto', 'flexpg_oque_titulo', 'flexpg_oque_texto',
              'flexpg_depois_titulo', 'flexpg_depois_texto', 'flexpg_catalogo_nota',
              'flexpg_cta_titulo', 'flexpg_cta_texto'] as $chave) {
        verdade(in_array($chave, $chaves, true), "faltou o bloco $chave");
    }

    foreach ($linhas as $linha) {
        verdade(in_array($linha['tipo'], ['texto', 'texto_longo'], true), 'tipo valido em ' . $linha['chave']);
        verdade($linha['rotulo'] !== '', 'rotulo escrito em ' . $linha['chave']);
    }

    igual('90 a 120 dias', bloco('pronta_prazo'));
    igual('45 dias', bloco('flex_prazo'));
    igual('A casa dos seus sonhos', bloco('hero_titulo'));
    igual('', bloco('flex_titulo'), 'texto da Flex e escrito depois, pela Frente 2');
});

teste('rodar a migracao de novo nao duplica conteudo', function (): void {
    $segunda = migrar();
    igual(0, $segunda['modelos']);
    igual(0, $segunda['avaliacoes']);
    igual(4, count(modelos('pronta')));
    igual(14, count(avaliacoes()));
});

teste('migrar_segredos cria o segredos.php uma vez so, com chave longa', function (): void {
    $arquivo = CASTELLO_CONFIG . '/segredos.php';
    falso(is_file($arquivo), 'o caso comeca sem segredos.php');

    $chave = migrar_segredos();
    verdade(strlen($chave) >= 32, 'a chave precisa ser longa, obtida: ' . $chave);
    verdade(is_file($arquivo));
    contem("define('CASTELLO_MIGRAR_CHAVE'", (string) file_get_contents($arquivo));
    contem($chave, (string) file_get_contents($arquivo));

    igual('', migrar_segredos(), 'arquivo ja existente nao e sobrescrito');
});

teste('migrar_usuario cria o acesso do painel uma vez so', function (): void {
    verdade(migrar_usuario('castello', 'senha-de-teste-123', 'Castello Casas de Madeira'));
    falso(migrar_usuario('castello', 'outra-senha', 'Castello Casas de Madeira'));

    $u = db()->query("SELECT * FROM usuarios WHERE login = 'castello'")->fetch();
    igual(1, (int) db()->query('SELECT COUNT(*) FROM usuarios')->fetchColumn());
    verdade(password_verify('senha-de-teste-123', $u['senha_hash']), 'a senha e verificavel por bcrypt');
    nao_contem('senha-de-teste-123', $u['senha_hash'], 'senha nunca em texto puro');
    verdade((bool) preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $u['criado_em']));
});
