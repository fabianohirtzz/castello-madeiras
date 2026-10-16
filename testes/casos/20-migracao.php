<?php
declare(strict_types=1);

require_once site() . '/lib/conteudo.php';

$contagens = banco_com_conteudo();

teste('a migracao insere a quantidade exata de cada tabela', function () use ($contagens): void {
    igual(17, $contagens['modelos'], '12 Casa Pronta e 5 Flex');
    igual(6,  $contagens['portfolio']);
    igual(14, $contagens['avaliacoes']);
    igual(15, $contagens['videos'], '11 reels da home e 4 videos da Flex');
    igual(12, $contagens['faq'], '7 gerais e 5 da Flex');
    igual(27, $contagens['passos'], '20 etapas da Casa Pronta e 7 da Flex');
    igual(22, $contagens['blocos']);
});

teste('os 12 projetos do catalogo da Casa Pronta chegaram com preco, area e ambientes', function (): void {
    $lista = modelos('pronta');
    igual(12, count($lista), 'catalogo de outubro de 2026');

    igual('Projeto 1', $lista[0]['nome']);
    igual('61,25 m²', $lista[0]['area']);
    igual('108.000', $lista[0]['preco']);
    igual("3 quartos\nSala e cozinha conjugada\nBanheiro\nVaranda", $lista[0]['itens']);

    igual('Projeto 11', $lista[10]['nome']);
    igual('36,00 m²', $lista[10]['area'], 'o menor projeto, o dos a partir de 36 m²');
    igual('72.000', $lista[10]['preco']);

    igual('Projeto 12', $lista[11]['nome']);
    contem('Mezanino', $lista[11]['itens']);

    foreach ($lista as $m) {
        igual('', (string) $m['parede'], 'o catalogo nao diz o sentido da madeira');
        igual(0, (int) $m['destaque'], 'nenhum projeto marcado como mais escolhido sem o cliente dizer');
        verdade(trim((string) $m['itens']) !== '', 'todo projeto tem ambientes: ' . $m['nome']);
        contem('valor é de referência', $m['descricao']);
    }
});

teste('os cinco modelos Flex chegaram com preco, area e prazo de 45 dias', function (): void {
    $lista = modelos('flex');
    igual(5, count($lista), 'os cinco projetos do drive da Castello');
    igual('Castello Flex 36', $lista[0]['nome']);
    igual('36,00 m²', $lista[0]['area']);
    igual('43.000', $lista[0]['preco'], 'tabela de setembro de 2026');
    igual('Castello Flex 57', $lista[4]['nome']);
    igual('Com garagem coberta', $lista[4]['parede'], 'o selo do card avisa da garagem');
    igual('57,75 m²', $lista[4]['area']);
    igual('69.000', $lista[4]['preco']);
    foreach ($lista as $m) {
        verdade((string) $m['preco'] !== '', 'todo Flex tem preco');
        igual('45 dias', $m['prazo']);
        contem('uploads/modelos/flex-', $m['foto'], 'render proprio da Flex, nao foto emprestada');
        contem('-planta.webp', $m['planta'], 'a planta baixa vai para o Ver mais');
        verdade(is_file(CASTELLO_UPLOADS . '/modelos/' . basename($m['planta'])), 'planta copiada: ' . $m['planta']);
        contem('Área fechada', $m['descricao']);
        verdade($m['foto_alt'] !== '', 'todo modelo tem alt');
    }
});

teste('as fotos dos modelos foram copiadas para uploads e o caminho e relativo', function (): void {
    foreach (modelos('pronta') as $m) {
        contem('uploads/modelos/', $m['foto']);
        falso(str_starts_with($m['foto'], '/'), 'caminho nunca comeca com barra');
        verdade(is_file(CASTELLO_UPLOADS . '/modelos/' . basename($m['foto'])), 'arquivo copiado: ' . $m['foto']);
        verdade($m['foto_alt'] !== '' && $m['foto_alt'] !== null, 'todo modelo tem alt escrito');
    }
    igual('uploads/modelos/projeto-03.webp', modelos('pronta')[2]['foto']);
});

teste('o portfolio trouxe as seis casas entregues, com categoria e alt', function (): void {
    $lista = portfolio();
    igual(6, count($lista));
    igual('Sobrado à beira da água', $lista[0]['titulo']);
    igual('Beira da água', $lista[0]['categoria']);
    igual('Sobrado de madeira à beira da água com vista para a ponte', $lista[0]['foto_alt']);
    igual('Chalé com varanda', $lista[5]['titulo']);
    igual('uploads/portfolio/casa-8.webp', $lista[3]['foto']);
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
        ['Posso personalizar a planta e os acabamentos da minha casa?',  'planta'],
        ['A casa é resistente e dura com o tempo?',        'escudo'],
        ['Qual a diferença entre madeira vertical e horizontal?', 'parede'],
        ['A Castello vai até o meu terreno antes de construir?', 'fundacao'],
        ['Que garantias eu tenho com a Castello?',         'garantia'],
    ];

    foreach ($esperado as $i => [$pergunta, $icone]) {
        igual($pergunta, $lista[$i]['pergunta']);
        igual($icone, $lista[$i]['icone']);
        verdade(icone_faq($lista[$i]['icone']) !== '', 'o icone precisa existir no conjunto fechado');
        verdade(mb_strlen($lista[$i]['resposta']) > 60, 'resposta completa');
    }

    $flex = faq('flex');
    igual(5, count($flex), 'FAQ provisoria da Flex');
    igual('Em quanto tempo a Castello Flex fica pronta?', $flex[0]['pergunta']);
    igual('relogio', $flex[0]['icone']);
    igual('fundacao', $flex[4]['icone']);
    igual(1, (int) $flex[0]['ordem'], 'a ordem da Flex recomeça do 1');
});

teste('as 20 etapas da linha do tempo da Casa Pronta chegaram em ordem', function (): void {
    $lista = passos('pronta');
    igual(20, count($lista), 'as etapas do carrossel Como tudo acontece');
    igual('Primeiro contato', $lista[0]['titulo']);
    igual('Estudo preliminar', $lista[2]['titulo'], 'a Castello faz o estudo preliminar, nao o projeto');
    igual('Início dos acabamentos em geral', $lista[18]['titulo']);
    igual('Checklist e entrega final da obra', $lista[19]['titulo']);
    $com_foto = array_filter($lista, static fn (array $p): bool => (string) $p['imagem'] !== '');
    igual(20, count($com_foto), 'toda etapa tem foto: 15 da obra e 5 geradas');
    foreach ($com_foto as $p) {
        contem('uploads/passos/etapa-', $p['imagem']);
        verdade(is_file(CASTELLO_UPLOADS . '/passos/' . basename($p['imagem'])), 'foto copiada: ' . $p['imagem']);
        verdade((string) $p['imagem_alt'] !== '', 'toda foto de etapa tem alt: ' . $p['titulo']);
    }
    $flex = passos('flex');
    igual(7, count($flex), 'passo a passo da Flex, do contato a entrega');
    igual('1º contato com a Castello', $flex[0]['titulo']);
    igual('Checklist e entrega da casa', $flex[6]['titulo']);
    foreach ($flex as $p) {
        verdade(is_file(CASTELLO_UPLOADS . '/passos/' . basename((string) $p['imagem'])), 'foto da etapa Flex copiada: ' . $p['titulo']);
        verdade((string) $p['imagem_alt'] !== '', 'toda etapa Flex tem alt: ' . $p['titulo']);
    }
    igual(1, (int) $flex[0]['ordem'], 'a ordem da Flex recomeça do 1');
});

teste('os 22 blocos de texto existem com rotulo e tipo', function (): void {
    $linhas = db()->query('SELECT chave, rotulo, tipo FROM blocos ORDER BY chave')->fetchAll();
    igual(22, count($linhas));

    $chaves = array_column($linhas, 'chave');
    foreach (['hero_titulo', 'hero_subtitulo', 'modalidades_titulo', 'modalidades_texto',
              'pronta_titulo', 'pronta_texto', 'pronta_prazo', 'flex_titulo', 'flex_texto',
              'flex_prazo', 'flex_video', 'flex_video_poster',
              'flexpg_hero_titulo', 'flexpg_hero_texto', 'flexpg_oque_titulo', 'flexpg_oque_texto',
              'flexpg_depois_titulo', 'flexpg_depois_texto', 'flexpg_catalogo_nota',
              'flexpg_cta_titulo', 'flexpg_cta_texto', 'pronta_home_modelo'] as $chave) {
        verdade(in_array($chave, $chaves, true), "faltou o bloco $chave");
    }

    foreach ($linhas as $linha) {
        verdade(in_array($linha['tipo'], ['texto', 'texto_longo'], true), 'tipo valido em ' . $linha['chave']);
        verdade($linha['rotulo'] !== '', 'rotulo escrito em ' . $linha['chave']);
    }

    igual('90 a 120 dias', bloco('pronta_prazo'));
    igual('45 dias', bloco('flex_prazo'));
    igual('Sua casa.', bloco('hero_titulo'));
    igual('Do seu jeito. *No seu ritmo.*', bloco('hero_subtitulo'), 'nenhum prazo fixo no topo da home');
    igual('Sua casa de madeira ganha forma em 45 dias. Você dá o toque final.', bloco('flex_titulo'));
    contem('sem possibilidade de personalização', bloco('flexpg_hero_texto'));
    igual('uploads/videos/insta-12.mp4', bloco('flex_video'));
    foreach (['modalidades_titulo', 'modalidades_texto', 'flex_texto', 'flexpg_hero_titulo', 'flexpg_hero_texto', 'flexpg_oque_titulo',
              'flexpg_oque_texto', 'flexpg_depois_titulo', 'flexpg_depois_texto', 'flexpg_catalogo_nota', 'flexpg_cta_titulo', 'flexpg_cta_texto'] as $chave) {
        verdade(bloco($chave) !== '', "o bloco $chave precisa nascer preenchido");
    }
});

teste('rodar a migracao de novo nao duplica conteudo', function (): void {
    $segunda = migrar();
    igual(0, $segunda['modelos']);
    igual(0, $segunda['avaliacoes']);
    igual(12, count(modelos('pronta')));
    igual(14, count(avaliacoes()));
});

teste('a atualizacao de outubro de 2026 leva um banco antigo ao conteudo novo, uma vez so', function (): void {
    /* Monta o banco como o servidor estava: os quatro modelos antigos, o nome
       Castelo Flex, os cinco passos com imagem e os textos de antes. */
    db()->exec("UPDATE modelos SET ativo = 0 WHERE modalidade = 'pronta'");
    db()->exec("INSERT INTO modelos (modalidade, nome, area, preco, foto, foto_alt, ativo, ordem) VALUES ('pronta', 'Compacta', '39,00 m²', '69.900', 'uploads/modelos/casa4.webp', 'Casa', 1, 1)");
    db()->exec("DELETE FROM modelos WHERE modalidade = 'pronta' AND nome LIKE 'Projeto %'");
    db()->exec("UPDATE modelos SET nome = REPLACE(nome, 'Castello', 'Castelo'), itens = NULL, planta = NULL WHERE modalidade = 'flex'");
    db()->exec("DELETE FROM passos WHERE contexto = 'pronta'");
    db()->exec("INSERT INTO passos (contexto, titulo, texto, imagem, imagem_alt, ativo, ordem) VALUES ('pronta', 'Fundação', 'x', 'uploads/passos/passo-2.jpg', 'Base', 1, 1)");
    db()->exec("UPDATE blocos SET valor = 'A casa dos seus sonhos' WHERE chave = 'hero_titulo'");
    db()->exec("UPDATE blocos SET valor = 'texto que o cliente escreveu' WHERE chave = 'flex_texto'");
    db()->exec("UPDATE faq SET pergunta = 'Posso mudar a planta da Castelo Flex?', resposta = 'Sim.' WHERE contexto = 'flex' AND ordem = 3");

    $log = migrar_atualizar_2026_10();
    verdade(count($log) > 10, 'a rodada fez o trabalho');

    $pronta = modelos('pronta');
    igual(12, count($pronta), 'so os projetos do catalogo ficam ativos');
    igual('Projeto 1', $pronta[0]['nome']);
    igual(1, (int) db()->query("SELECT COUNT(*) FROM modelos WHERE nome = 'Compacta' AND ativo = 0")->fetchColumn(), 'o antigo so foi desativado');

    $flex = modelos('flex');
    igual('Castello Flex 36', $flex[0]['nome']);
    contem('-planta.webp', $flex[0]['planta']);
    contem('Banheiro', $flex[0]['itens']);

    igual(20, count(passos('pronta')));

    db()->exec("UPDATE passos SET ativo = 0 WHERE contexto = 'flex'");
    db()->exec("INSERT INTO passos (contexto, titulo, texto, imagem, imagem_alt, ativo, ordem) VALUES ('flex', 'Escolha do modelo', 'x', '', '', 1, 1)");
    db()->exec("DELETE FROM passos WHERE contexto = 'flex' AND titulo <> 'Escolha do modelo'");
    migrar_atualizar_2026_10_passos_flex();
    igual(7, count(passos('flex')), 'as sete etapas com foto substituem os cartoes da Flex');
    igual(1, (int) db()->query("SELECT COUNT(*) FROM passos WHERE titulo = 'Escolha do modelo' AND ativo = 0")->fetchColumn(), 'o cartao antigo so foi desativado');
    igual(['passos da Flex: ja aplicado antes'], migrar_atualizar_2026_10_passos_flex(), 'rodar de novo nao duplica');
    igual('Sua casa.', bloco('hero_titulo'));
    igual('texto que o cliente escreveu', bloco('flex_texto'), 'bloco fora da rodada fica como o cliente deixou');
    $planta = db()->query("SELECT resposta FROM faq WHERE pergunta = 'Posso mudar a planta da Castello Flex?'")->fetchColumn();
    contem('padronizadas', (string) $planta);

    igual(['ja aplicado antes, nada mudou'], migrar_atualizar_2026_10(), 'a segunda chamada nao mexe em nada');
    igual(12, count(modelos('pronta')));
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

teste('conteudo real migrou por inteiro, sem item de portfolio sem alt', function (): void {
    $esperado = ['modelos' => 17, 'portfolio' => 6, 'avaliacoes' => 14, 'videos' => 11, 'faq' => 7, 'passos' => 25];
    foreach ($esperado as $tabela => $quantos) {
        $tem = (int) db()->query("SELECT COUNT(*) FROM {$tabela} WHERE ativo = 1")->fetchColumn();
        verdade($tem >= $quantos, "{$tabela}: esperado ao menos {$quantos}, tem {$tem}");
    }
    $sem_alt = (int) db()->query("SELECT COUNT(*) FROM portfolio WHERE foto_alt IS NULL OR foto_alt = ''")->fetchColumn();
    igual(0, $sem_alt, 'ha item de portfolio sem texto alternativo');
    $sem_alt = (int) db()->query("SELECT COUNT(*) FROM modelos WHERE foto_alt IS NULL OR foto_alt = ''")->fetchColumn();
    igual(0, $sem_alt, 'ha modelo sem texto alternativo');
});

teste('a FAQ revisada em 2026-10-16 chega a um banco antigo, uma vez so', function (): void {
    db()->exec("INSERT INTO faq (pergunta, resposta, icone, contexto, ativo, ordem) VALUES ('Casa de madeira é confortável o ano todo?', 'x', 'clima', 'geral', 1, 4)");
    db()->exec("UPDATE faq SET pergunta = 'Vocês cuidam da fundação e do terreno?', resposta = 'antiga' WHERE contexto = 'geral' AND icone = 'fundacao'");
    db()->exec("UPDATE faq SET resposta = 'antiga' WHERE contexto = 'geral' AND icone IN ('relogio', 'garantia')");

    migrar_atualizar_2026_10_faq();
    $geral = faq('geral');
    igual(7, count($geral), 'a pergunta do conforto saiu do site');
    igual(1, (int) db()->query("SELECT COUNT(*) FROM faq WHERE icone = 'clima' AND ativo = 0")->fetchColumn(), 'so foi desativada');
    $por = array_column($geral, 'resposta', 'pergunta');
    contem('visita técnica', $por['A Castello vai até o meu terreno antes de construir?']);
    contem('**Castello Flex:** até 45 dias.', $por['Quanto tempo leva pra minha casa ficar pronta?']);
    contem('8.078/1990', $por['Que garantias eu tenho com a Castello?']);
    nao_contem('Vocês cuidam da fundação', implode(' ', array_keys($por)));
    migrar_atualizar_2026_10_faq();
    igual(7, count(faq('geral')), 'rodar de novo nao muda nada');
});
