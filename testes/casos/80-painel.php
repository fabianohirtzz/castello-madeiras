<?php
declare(strict_types=1);

require_once site() . '/painel/tabelas.php';

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
    igual(0, count(painel_listar('modelos', 'flex')));
    igual(4, count(painel_listar('modelos')), 'sem filtro, lista tudo');
    igual(4, count(painel_listar('modelos', 'inventada')), 'filtro invalido e ignorado');
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
