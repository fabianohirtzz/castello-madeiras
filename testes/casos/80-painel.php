<?php
declare(strict_types=1);

require_once site() . '/painel/tabelas.php';
require_once site() . '/lib/auth.php';

banco_com_conteudo();

teste('as seis telas de conteudo estao descritas', function (): void {
    igual(['modelos', 'portfolio', 'avaliacoes', 'videos', 'faq', 'passos'], array_keys(painel_tabelas()));
});

teste('toda tabela descrita existe no banco e todo campo e uma coluna real', function (): void {
    foreach (painel_tabelas() as $chave => $def) {
        $colunas = array_column(db()->query('PRAGMA table_info(' . $chave . ')')->fetchAll(), 'name');
        verdade($colunas !== [], "a tabela $chave precisa existir no banco");

        foreach (array_keys($def['campos']) as $coluna) {
            verdade(in_array($coluna, $colunas, true), "$chave.$coluna nao existe no banco");
        }
        foreach (array_keys($def['resumo']) as $coluna) {
            verdade(in_array($coluna, $colunas, true), "resumo de $chave aponta para coluna inexistente: $coluna");
        }
        verdade(in_array('ativo', $colunas, true), "$chave precisa de ativo");
        verdade(in_array('ordem', $colunas, true), "$chave precisa de ordem");
    }
});

teste('toda descricao tem rotulo, singular, resumo e campos com tipo conhecido', function (): void {
    $tipos = ['texto', 'texto_longo', 'numero', 'selecao', 'sim_nao', 'imagem', 'video', 'icone_faq'];

    foreach (painel_tabelas() as $chave => $def) {
        verdade(($def['rotulo'] ?? '') !== '', "$chave sem rotulo");
        verdade(($def['singular'] ?? '') !== '', "$chave sem singular");
        verdade(($def['resumo'] ?? []) !== [], "$chave sem resumo");

        foreach ($def['campos'] as $coluna => $campo) {
            verdade(($campo['rotulo'] ?? '') !== '', "$chave.$coluna sem rotulo");
            verdade(in_array($campo['tipo'] ?? '', $tipos, true), "$chave.$coluna com tipo desconhecido");

            if ($campo['tipo'] === 'selecao') {
                verdade(($campo['opcoes'] ?? []) !== [], "$chave.$coluna precisa de opcoes");
            }
            if (in_array($campo['tipo'], ['imagem', 'video'], true)) {
                verdade(in_array($campo['pasta'] ?? '', ['modelos', 'portfolio', 'videos', 'passos'], true),
                    "$chave.$coluna precisa apontar para uma pasta valida de upload");
            }
        }
    }
});

teste('modelos e passos filtram por modalidade e contexto', function (): void {
    igual('modalidade', painel_tabela('modelos')['filtro']['coluna']);
    igual(['pronta', 'flex'], array_keys(painel_tabela('modelos')['filtro']['opcoes']));
    igual('pronta', painel_tabela('modelos')['filtro']['padrao']);

    igual('contexto', painel_tabela('passos')['filtro']['coluna']);
    igual('contexto', painel_tabela('faq')['filtro']['coluna']);
    igual(['geral', 'flex'], array_keys(painel_tabela('faq')['filtro']['opcoes']));

    igual(null, painel_tabela('portfolio')['filtro'] ?? null, 'portfolio nao tem filtro');
});

teste('o FAQ usa o seletor visual de icone com as sete chaves fechadas', function (): void {
    igual('icone_faq', painel_tabela('faq')['campos']['icone']['tipo']);
    verdade((bool) (painel_tabela('faq')['campos']['icone']['obrigatorio'] ?? false), 'icone e obrigatorio');
    igual(7, count(CASTELLO_ICONES_FAQ));
});

teste('painel_tabela devolve null para chave inventada', function (): void {
    igual(null, painel_tabela('usuarios'));
    igual(null, painel_tabela('leads'));
    igual(null, painel_tabela('config'));
    igual(null, painel_tabela(''));
});

teste('painel_abas monta o menu com as telas de conteudo', function (): void {
    $abas = painel_abas();
    igual('Modelos', $abas['modelos']);
    igual('Portfólio', $abas['portfolio']);
    igual('Avaliações', $abas['avaliacoes']);
    igual('Vídeos', $abas['videos']);
    igual('FAQ', $abas['faq']);
    igual('Passo a passo', $abas['passos']);
});

teste('painel_listar mostra tambem o que esta desativado', function (): void {
    db()->exec("UPDATE portfolio SET ativo = 0 WHERE titulo = 'Chalé com varanda'");

    igual(5, count(portfolio()), 'o site so mostra os ativos');
    igual(6, count(painel_listar('portfolio')), 'o painel mostra todos');

    db()->exec("UPDATE portfolio SET ativo = 1 WHERE titulo = 'Chalé com varanda'");
});

teste('painel_listar aplica o filtro quando a tabela tem um', function (): void {
    igual(4, count(painel_listar('modelos', 'pronta')));
    igual(3, count(painel_listar('modelos', 'flex')), 'os tres Flex provisorios');
    igual(7, count(painel_listar('modelos')), 'sem filtro, lista tudo');
    igual(7, count(painel_listar('modelos', 'inventada')), 'filtro invalido e ignorado');
});

teste('painel_listar devolve vazio para tabela fora da descricao', function (): void {
    igual([], painel_listar('usuarios'));
    igual([], painel_listar('leads'));
});

teste('painel_linha traz uma linha pelo id e null quando nao existe', function (): void {
    $id = (int) db()->query("SELECT id FROM modelos WHERE nome = 'Família'")->fetchColumn();

    igual('Família', painel_linha('modelos', $id)['nome']);
    igual(null, painel_linha('modelos', 999999));
    igual(null, painel_linha('usuarios', 1));
});

teste('painel_valores so deixa passar as colunas descritas', function (): void {
    $def = painel_tabela('portfolio');

    $v = painel_valores($def, [
        'titulo'    => '  Casa nova  ',
        'categoria' => 'Térrea',
        'foto_alt'  => 'Alt da foto',
        'id'        => '7',
        'ativo'     => '0',
        'ordem'     => '99',
        'inventada' => 'x',
    ]);

    igual(['titulo', 'categoria', 'foto_alt'], array_keys($v), 'campos de arquivo e colunas de fora nao entram');
    igual('Casa nova', $v['titulo'], 'texto e aparado');
});

teste('painel_valores converte cada tipo do jeito certo', function (): void {
    $modelos = painel_tabela('modelos');

    igual(1, painel_valores($modelos, ['destaque' => '1'])['destaque']);
    igual(0, painel_valores($modelos, ['destaque' => '0'])['destaque']);
    igual(0, painel_valores($modelos, [])['destaque'], 'checkbox ausente vira 0');

    igual('pronta', painel_valores($modelos, ['modalidade' => 'pronta'])['modalidade']);
    igual('flex', painel_valores($modelos, ['modalidade' => 'flex'])['modalidade']);
    igual('pronta', painel_valores($modelos, ['modalidade' => 'invasao'])['modalidade'], 'selecao invalida cai na primeira opcao');

    igual(5, painel_valores(painel_tabela('avaliacoes'), ['estrelas' => '5'])['estrelas']);
    igual(0, painel_valores(painel_tabela('avaliacoes'), ['estrelas' => 'texto'])['estrelas']);

    igual('chave', painel_valores(painel_tabela('faq'), ['icone' => 'chave'])['icone']);
    igual('relogio', painel_valores(painel_tabela('faq'), ['icone' => 'inventado'])['icone'], 'icone fora do conjunto cai em relogio');
});

teste('painel_erros aponta so os campos obrigatorios vazios', function (): void {
    $def = painel_tabela('avaliacoes');

    igual([], painel_erros($def, ['nome' => 'Ana', 'texto' => 'Muito bom', 'estrelas' => 5]));

    $erros = painel_erros($def, ['nome' => '', 'texto' => '', 'estrelas' => 5]);
    igual(['nome', 'texto'], array_keys($erros));
    contem('Nome do cliente', $erros['nome']);
});

teste('painel_salvar insere ativo, no fim da ordem, e devolve o id', function (): void {
    $antes = count(painel_listar('portfolio'));

    $id = painel_salvar('portfolio', null, [
        'titulo'    => 'Casa de teste',
        'categoria' => 'Teste',
        'foto'      => 'uploads/portfolio/casa1.png',
        'foto_alt'  => 'Alt de teste',
    ]);

    verdade($id > 0);
    igual($antes + 1, count(painel_listar('portfolio')));

    $linha = painel_linha('portfolio', $id);
    igual('Casa de teste', $linha['titulo']);
    igual(1, (int) $linha['ativo'], 'item novo nasce ativo');

    $maior = (int) db()->query('SELECT MAX(ordem) FROM portfolio')->fetchColumn();
    igual($maior, (int) $linha['ordem'], 'item novo entra no fim da ordem');
});

teste('painel_salvar com id atualiza e nao mexe em ativo nem em ordem', function (): void {
    $id = painel_salvar('portfolio', null, ['titulo' => 'Antes', 'categoria' => 'A', 'foto' => '', 'foto_alt' => '']);
    db()->prepare('UPDATE portfolio SET ativo = 0, ordem = 42 WHERE id = ?')->execute([$id]);

    $mesmo = painel_salvar('portfolio', $id, ['titulo' => 'Depois', 'categoria' => 'B', 'foto' => '', 'foto_alt' => '']);
    igual($id, $mesmo, 'atualizar devolve o mesmo id');

    $linha = painel_linha('portfolio', $id);
    igual('Depois', $linha['titulo']);
    igual(0, (int) $linha['ativo'], 'salvar nao reativa sozinho');
    igual(42, (int) $linha['ordem'], 'salvar nao muda a ordem');
});

teste('painel_salvar recusa tabela que nao esta na descricao', function (): void {
    foreach (['usuarios', 'leads', 'config', 'portfolio; DROP TABLE portfolio'] as $chave) {
        $pegou = false;
        try {
            painel_salvar($chave, null, ['x' => 'y']);
        } catch (InvalidArgumentException $ex) {
            $pegou = true;
        }
        verdade($pegou, "painel_salvar tinha que recusar a chave $chave");
    }

    igual(1, (int) db()->query("SELECT COUNT(*) FROM sqlite_master WHERE name = 'portfolio'")->fetchColumn(),
        'a tabela portfolio continua de pe');
    igual(0, (int) db()->query('SELECT COUNT(*) FROM usuarios')->fetchColumn(), 'nada foi escrito em usuarios');
});

teste('painel_salvar ignora coluna que nao esta na descricao', function (): void {
    $id = painel_salvar('avaliacoes', null, [
        'nome'     => 'Cliente teste',
        'texto'    => 'Texto de teste',
        'estrelas' => 5,
        'ativo'    => 0,
        'ordem'    => 1,
    ]);

    igual(1, (int) painel_linha('avaliacoes', $id)['ativo'], 'ativo nao pode vir do formulario');

    // Limpa a linha de teste: os casos seguintes contam com as 14 avaliacoes migradas.
    db()->exec('DELETE FROM avaliacoes WHERE id = ' . $id);
});

teste('painel_arquivos grava o upload valido e devolve o caminho relativo', function (): void {
    $img = imagecreatetruecolor(40, 40);
    ob_start();
    imagejpeg($img);
    $bytes = (string) ob_get_clean();
    imagedestroy($img);

    $tmp = tempnam(sys_get_temp_dir(), 'castello-pan');
    file_put_contents($tmp, $bytes);

    $r = painel_arquivos(painel_tabela('portfolio'), [
        'foto' => ['name' => 'Casa Nova.jpg', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => strlen($bytes)],
    ]);

    igual([], $r['erros']);
    verdade(str_starts_with($r['valores']['foto'], 'uploads/portfolio/'));
});

teste('painel_arquivos devolve mensagem em portugues quando o tipo esta errado', function (): void {
    $tmp = tempnam(sys_get_temp_dir(), 'castello-pan');
    file_put_contents($tmp, "<?php echo 1; ");

    $r = painel_arquivos(painel_tabela('portfolio'), [
        'foto' => ['name' => 'shell.jpg', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => 14],
    ]);

    igual([], $r['valores']);
    contem('JPG, PNG ou WEBP', $r['erros']['foto']);
});

teste('painel_arquivos ignora campo de arquivo que veio vazio', function (): void {
    $r = painel_arquivos(painel_tabela('portfolio'), [
        'foto' => ['name' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0],
    ]);

    igual([], $r['valores'], 'campo vazio nao apaga a foto que ja estava la');
    igual([], $r['erros']);
});

teste('painel_erro_upload fala a lingua do cliente', function (): void {
    contem('30 MB', painel_erro_upload('tamanho', 'video'));
    contem('5 MB', painel_erro_upload('tamanho', 'imagem'));
    contem('MP4', painel_erro_upload('tipo', 'video'));
    nao_contem('finfo', painel_erro_upload('tipo', 'imagem'));
});

teste('painel_estado desativa e reativa sem apagar nada', function (): void {
    $id = (int) db()->query("SELECT id FROM avaliacoes WHERE nome = 'Nany Festa'")->fetchColumn();

    painel_estado('avaliacoes', $id, 0);
    igual(0, (int) painel_linha('avaliacoes', $id)['ativo']);
    igual(13, count(avaliacoes()), 'o site deixa de mostrar');
    verdade(painel_linha('avaliacoes', $id) !== null, 'a linha continua no banco');

    painel_estado('avaliacoes', $id, 1);
    igual(1, (int) painel_linha('avaliacoes', $id)['ativo']);
    igual(14, count(avaliacoes()));
});

teste('painel_estado recusa tabela fora da descricao', function (): void {
    foreach (['usuarios', 'leads', 'config'] as $chave) {
        $pegou = false;
        try {
            painel_estado($chave, 1, 0);
        } catch (InvalidArgumentException $ex) {
            $pegou = true;
        }
        verdade($pegou, "painel_estado tinha que recusar $chave");
    }
});

teste('painel_reordenar renumera de 1 ate N na ordem recebida', function (): void {
    $ids = array_map('intval', array_column(painel_listar('passos', 'pronta'), 'id'));
    igual(5, count($ids));

    $invertido = array_reverse($ids);
    igual(5, painel_reordenar('passos', $invertido));

    $agora = array_map('intval', array_column(painel_listar('passos', 'pronta'), 'id'));
    igual($invertido, $agora, 'a lista do painel segue a nova ordem');
    igual('Chave na mão', passos('pronta')[0]['titulo'], 'o site tambem segue');

    igual(1, (int) painel_linha('passos', $invertido[0])['ordem']);
    igual(5, (int) painel_linha('passos', $invertido[4])['ordem']);

    painel_reordenar('passos', $ids);
    igual('Conversa e projeto', passos('pronta')[0]['titulo'], 'voltou ao normal');
});

teste('reordenar troca quais videos aparecem na home', function (): void {
    igual('8', config_ler('videos_na_home'));
    nao_contem('insta-11', videos()[0]['arquivo'] . videos()[1]['arquivo']);

    $ids = array_map('intval', array_column(painel_listar('videos'), 'id'));
    $ultimo = array_pop($ids);
    array_unshift($ids, $ultimo);
    painel_reordenar('videos', $ids);

    contem('insta-11', videos()[0]['arquivo'], 'o promovido virou o primeiro da home');
    igual(8, count(videos()), 'o limite continua valendo');
    igual(11, count(videos(0)), 'nenhum video sumiu');
});

teste('painel_reordenar ignora id invalido e recusa tabela desconhecida', function (): void {
    $ids = array_map('intval', array_column(painel_listar('portfolio'), 'id'));
    igual(count($ids), painel_reordenar('portfolio', array_merge($ids, [0, -3, 999999])));

    $pegou = false;
    try {
        painel_reordenar('usuarios', [1]);
    } catch (InvalidArgumentException $ex) {
        $pegou = true;
    }
    verdade($pegou);
});

teste('painel_textos_gravar atualiza so as chaves que existem em blocos', function (): void {
    $n = painel_textos_gravar([
        'hero_titulo'  => '  A casa que você sempre quis  ',
        'flex_titulo'  => 'Castelo Flex',
        'chave_falsa'  => 'nao pode entrar',
    ]);

    igual(2, $n);
    igual('A casa que você sempre quis', bloco('hero_titulo'), 'o texto e aparado');
    igual('Castelo Flex', bloco('flex_titulo'));
    igual(21, (int) db()->query('SELECT COUNT(*) FROM blocos')->fetchColumn(), 'nenhuma chave nova foi criada');

    painel_textos_gravar(['hero_titulo' => 'A casa dos seus sonhos']);
});

teste('painel_config_campos cobre as dez chaves do contrato', function (): void {
    igual(
        ['videos_na_home', 'email_aviso', 'email_dominio', 'crm_ativo', 'crm_endpoint',
         'crm_metodo', 'crm_cabecalhos', 'crm_mapa_campos', 'crm_timeout', 'reenvio_chave'],
        array_keys(painel_config_campos())
    );
});

teste('painel_config_validar aceita uma configuracao boa', function (): void {
    $r = painel_config_validar([
        'videos_na_home'  => '6',
        'email_aviso'     => 'contato@castellomadeiras.com.br',
        'email_dominio'   => 'castellomadeiras.com.br',
        'crm_ativo'       => '1',
        'crm_endpoint'    => 'https://crm.exemplo.com.br/api/leads',
        'crm_metodo'      => 'POST',
        'crm_cabecalhos'  => '{"Authorization":"Bearer abc"}',
        'crm_mapa_campos' => '{"nome":"nome","whatsapp":"telefone"}',
        'crm_timeout'     => '8',
        'reenvio_chave'   => str_repeat('a1b2', 8),
    ]);

    igual([], $r['erros']);
    igual('6', $r['valores']['videos_na_home']);
    igual('1', $r['valores']['crm_ativo']);
    igual('8', $r['valores']['crm_timeout']);
    igual('{"Authorization":"Bearer abc"}', $r['valores']['crm_cabecalhos']);
});

teste('crm_timeout nunca passa de 10, porque max_execution_time e 60', function (): void {
    $base = [
        'videos_na_home' => '8', 'email_aviso' => 'a@b.com', 'email_dominio' => 'b.com',
        'crm_ativo' => '0', 'crm_endpoint' => '', 'crm_metodo' => 'POST',
        'crm_cabecalhos' => '{}', 'crm_mapa_campos' => '{}', 'reenvio_chave' => str_repeat('c', 32),
    ];

    igual('10', painel_config_validar($base + ['crm_timeout' => '30'])['valores']['crm_timeout']);
    igual('1', painel_config_validar($base + ['crm_timeout' => '0'])['valores']['crm_timeout']);
    igual('10', painel_config_validar($base + ['crm_timeout' => 'texto'])['valores']['crm_timeout']);
});

teste('reenvio_chave em branco gera uma chave nova em vez de apagar', function (): void {
    $base = [
        'videos_na_home' => '8', 'email_aviso' => 'a@b.com', 'email_dominio' => 'b.com',
        'crm_ativo' => '0', 'crm_endpoint' => '', 'crm_metodo' => 'POST',
        'crm_cabecalhos' => '{}', 'crm_mapa_campos' => '{}', 'crm_timeout' => '10',
    ];

    $nova = painel_config_validar($base + ['reenvio_chave' => ''])['valores']['reenvio_chave'];
    verdade(strlen($nova) >= 32, 'chave vazia tem que virar uma chave nova e longa');

    $mantida = str_repeat('d', 40);
    igual($mantida, painel_config_validar($base + ['reenvio_chave' => $mantida])['valores']['reenvio_chave']);
});

teste('painel_config_validar recusa numero fora da faixa, e-mail torto e JSON quebrado', function (): void {
    $r = painel_config_validar([
        'videos_na_home'  => '0',
        'email_aviso'     => 'nao-e-email',
        'email_dominio'   => 'nao vale espaco',
        'crm_ativo'       => '0',
        'crm_endpoint'    => 'isso nao e url',
        'crm_metodo'      => 'DELETE',
        'crm_cabecalhos'  => '{quebrado',
        'crm_mapa_campos' => '',
        'crm_timeout'     => '10',
        'reenvio_chave'   => str_repeat('e', 32),
    ]);

    igual(
        ['videos_na_home', 'email_aviso', 'email_dominio', 'crm_endpoint', 'crm_cabecalhos'],
        array_keys($r['erros'])
    );
    contem('1 a 24', $r['erros']['videos_na_home']);
    contem('e-mail', $r['erros']['email_aviso']);
    contem('domínio', $r['erros']['email_dominio']);
    contem('https://', $r['erros']['crm_endpoint']);
    contem('JSON', $r['erros']['crm_cabecalhos']);

    igual('POST', $r['valores']['crm_metodo'], 'metodo fora da lista cai em POST');
    igual('{}', $r['valores']['crm_mapa_campos'], 'JSON vazio vira objeto vazio');
});

teste('config gravada muda o que a home mostra', function (): void {
    $r = painel_config_validar([
        'videos_na_home'  => '3',
        'email_aviso'     => 'contato@castellomadeiras.com.br',
        'email_dominio'   => 'castellomadeiras.com.br',
        'crm_ativo'       => '0',
        'crm_endpoint'    => '',
        'crm_metodo'      => 'POST',
        'crm_cabecalhos'  => '{}',
        'crm_mapa_campos' => '{"nome":"nome"}',
        'crm_timeout'     => '10',
        'reenvio_chave'   => (string) config_ler('reenvio_chave'),
    ]);
    igual([], $r['erros']);

    foreach ($r['valores'] as $chave => $valor) {
        config_gravar($chave, $valor);
    }

    igual(3, count(videos()));
    config_gravar('videos_na_home', '8');
    igual(8, count(videos()));
});

teste('painel_fixas traz as quatro telas que nao sao de conteudo', function (): void {
    igual(['textos', 'config', 'backup', 'senha'], array_keys(painel_fixas()));
    igual('Textos', painel_fixas()['textos']);
    igual('Configurações', painel_fixas()['config']);
});

teste('estrelas fora de 1 a 5 e recusado e vazio vira 5', function (): void {
    $def = painel_tabela('avaliacoes');
    $v = painel_valores($def, ['nome' => 'Teste', 'texto' => 'Texto', 'estrelas' => '9']);
    verdade(isset(painel_erros($def, $v)['estrelas']), '9 estrelas tem que dar erro');
    $v = painel_valores($def, ['nome' => 'Teste', 'texto' => 'Texto', 'estrelas' => '0']);
    verdade(isset(painel_erros($def, $v)['estrelas']), '0 estrelas tem que dar erro');
    $v = painel_valores($def, ['nome' => 'Teste', 'texto' => 'Texto', 'estrelas' => '']);
    igual(5, $v['estrelas'], 'vazio cai no padrao 5');
    igual([], painel_erros($def, $v));
    igual(3, painel_valores($def, ['estrelas' => '3'])['estrelas']);
});

teste('painel_bytes le os valores do php.ini', function (): void {
    igual(8388608, painel_bytes('8M'));
    igual(67108864, painel_bytes('64M'));
    igual(2048, painel_bytes('2K'));
    igual(1073741824, painel_bytes('1G'));
    igual(123, painel_bytes('123'));
    igual(0, painel_bytes('-1'));
    igual(0, painel_bytes(''));
});

teste('o formulario de item novo nasce com o filtro da lista', function (): void {
    $_GET = ['tela' => 'modelos', 'novo' => '1', 'filtro' => 'flex'];
    $html = render(site() . '/painel/telas/form.php', ['tela' => 'modelos', 'def' => painel_tabela('modelos'), 'id' => 0, 'linha' => null]);
    contem('<option value="flex" selected>', $html, 'novo modelo aberto do filtro Flex ja vem como Flex');
    $_GET = ['tela' => 'modelos', 'novo' => '1'];
    $html = render(site() . '/painel/telas/form.php', ['tela' => 'modelos', 'def' => painel_tabela('modelos'), 'id' => 0, 'linha' => null]);
    nao_contem('<option value="flex" selected>', $html, 'sem filtro, nada e semeado e o navegador mostra a primeira opcao');
    $_GET = [];
});
