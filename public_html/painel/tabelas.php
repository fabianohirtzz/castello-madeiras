<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/conteudo.php';
require_once __DIR__ . '/../lib/upload.php';

/**
 * Descricao declarativa das telas de conteudo do painel.
 *
 * Cada entrada gera sozinha a lista, o formulario, o salvar, o desativar,
 * o reativar e o reordenar. Os nomes de tabela e de coluna usados no SQL saem
 * daqui e nunca da requisicao, e e isso que torna seguro interpolar o nome da
 * tabela na consulta.
 *
 * Tipos de campo: texto, texto_longo, numero, selecao, sim_nao, imagem,
 * video, icone_faq.
 */
function painel_tabelas(): array
{
    return [
        'modelos' => [
            'rotulo'    => 'Modelos',
            'singular'  => 'modelo',
            'miniatura' => 'foto',
            'resumo'    => ['nome' => 'Nome', 'area' => 'Área', 'preco' => 'Preço'],
            'filtro'    => [
                'coluna' => 'modalidade',
                'rotulo' => 'Modalidade',
                'opcoes' => ['pronta' => 'Casa Pronta', 'flex' => 'Castelo Flex'],
                'padrao' => 'pronta',
            ],
            'campos' => [
                'modalidade' => ['rotulo' => 'Modalidade', 'tipo' => 'selecao', 'obrigatorio' => true,
                                 'opcoes' => ['pronta' => 'Casa Pronta', 'flex' => 'Castelo Flex']],
                'nome'       => ['rotulo' => 'Nome do modelo', 'tipo' => 'texto', 'obrigatorio' => true],
                'area'       => ['rotulo' => 'Área', 'tipo' => 'texto', 'ajuda' => 'Escreva com a unidade, como 39,00 m²'],
                'parede'     => ['rotulo' => 'Parede', 'tipo' => 'texto', 'ajuda' => 'Parede vertical ou Parede dupla'],
                'preco'      => ['rotulo' => 'Preço', 'tipo' => 'texto', 'ajuda' => 'Só o número, como 69.900. O site coloca o R$ sozinho'],
                'prazo'      => ['rotulo' => 'Prazo', 'tipo' => 'texto', 'ajuda' => 'Como 90 a 120 dias'],
                'descricao'  => ['rotulo' => 'Descrição', 'tipo' => 'texto_longo'],
                'foto'       => ['rotulo' => 'Foto', 'tipo' => 'imagem', 'pasta' => 'modelos'],
                'foto_alt'   => ['rotulo' => 'Descrição da foto', 'tipo' => 'texto',
                                 'ajuda' => 'Conte o que aparece na foto. Serve para quem usa leitor de tela e para o Google'],
                'destaque'   => ['rotulo' => 'Marcar como Mais escolhida', 'tipo' => 'sim_nao'],
            ],
        ],

        'portfolio' => [
            'rotulo'    => 'Portfólio',
            'singular'  => 'casa entregue',
            'miniatura' => 'foto',
            'resumo'    => ['titulo' => 'Título', 'categoria' => 'Categoria'],
            'campos'    => [
                'titulo'    => ['rotulo' => 'Título', 'tipo' => 'texto', 'obrigatorio' => true],
                'categoria' => ['rotulo' => 'Categoria', 'tipo' => 'texto', 'ajuda' => 'A linha pequena acima do título, como Beira da água'],
                'foto'      => ['rotulo' => 'Foto', 'tipo' => 'imagem', 'pasta' => 'portfolio'],
                'foto_alt'  => ['rotulo' => 'Descrição da foto', 'tipo' => 'texto'],
            ],
        ],

        'avaliacoes' => [
            'rotulo'   => 'Avaliações',
            'singular' => 'avaliação',
            'resumo'   => ['nome' => 'Cliente', 'estrelas' => 'Estrelas'],
            'campos'   => [
                'nome'     => ['rotulo' => 'Nome do cliente', 'tipo' => 'texto', 'obrigatorio' => true],
                'texto'    => ['rotulo' => 'Texto da avaliação', 'tipo' => 'texto_longo', 'obrigatorio' => true],
                'estrelas' => ['rotulo' => 'Estrelas', 'tipo' => 'numero', 'ajuda' => 'De 1 a 5'],
            ],
        ],

        'videos' => [
            'rotulo'    => 'Vídeos',
            'singular'  => 'vídeo',
            'miniatura' => 'poster',
            'resumo'    => ['arquivo' => 'Arquivo', 'legenda' => 'Legenda'],
            'campos'    => [
                'arquivo' => ['rotulo' => 'Vídeo em MP4', 'tipo' => 'video', 'pasta' => 'videos', 'obrigatorio' => true],
                'poster'  => ['rotulo' => 'Capa do vídeo', 'tipo' => 'imagem', 'pasta' => 'videos',
                              'ajuda' => 'A imagem que aparece antes de tocar'],
                'legenda' => ['rotulo' => 'Legenda', 'tipo' => 'texto'],
            ],
        ],

        'faq' => [
            'rotulo'   => 'FAQ',
            'singular' => 'pergunta',
            'resumo'   => ['pergunta' => 'Pergunta'],
            'filtro'   => [
                'coluna' => 'contexto',
                'rotulo' => 'Onde aparece',
                'opcoes' => ['geral' => 'Home', 'flex' => 'Página Castelo Flex'],
                'padrao' => 'geral',
            ],
            'campos' => [
                'contexto' => ['rotulo' => 'Onde aparece', 'tipo' => 'selecao', 'obrigatorio' => true,
                               'opcoes' => ['geral' => 'Home', 'flex' => 'Página Castelo Flex']],
                'pergunta' => ['rotulo' => 'Pergunta', 'tipo' => 'texto', 'obrigatorio' => true],
                'resposta' => ['rotulo' => 'Resposta', 'tipo' => 'texto_longo', 'obrigatorio' => true],
                'icone'    => ['rotulo' => 'Ícone', 'tipo' => 'icone_faq', 'obrigatorio' => true,
                               'ajuda' => 'Toque no desenho que combina com a pergunta'],
            ],
        ],

        'passos' => [
            'rotulo'    => 'Passo a passo',
            'singular'  => 'passo',
            'miniatura' => 'imagem',
            'resumo'    => ['titulo' => 'Título'],
            'filtro'    => [
                'coluna' => 'contexto',
                'rotulo' => 'Onde aparece',
                'opcoes' => ['pronta' => 'Home, Casa Pronta', 'flex' => 'Página Castelo Flex'],
                'padrao' => 'pronta',
            ],
            'campos' => [
                'contexto' => ['rotulo' => 'Onde aparece', 'tipo' => 'selecao', 'obrigatorio' => true,
                               'opcoes' => ['pronta' => 'Home, Casa Pronta', 'flex' => 'Página Castelo Flex']],
                'titulo'   => ['rotulo' => 'Título do passo', 'tipo' => 'texto', 'obrigatorio' => true],
                'texto'    => ['rotulo' => 'Texto do passo', 'tipo' => 'texto_longo'],
                'imagem'   => ['rotulo' => 'Imagem', 'tipo' => 'imagem', 'pasta' => 'passos'],
                'imagem_alt' => ['rotulo' => 'Descrição da imagem', 'tipo' => 'texto',
                                 'ajuda' => 'Conte o que aparece na imagem. Serve para quem usa leitor de tela e para o Google'],
            ],
        ],
    ];
}

/** Descricao de uma tela de conteudo, ou null se a chave nao for uma delas. */
function painel_tabela(string $chave): ?array
{
    return painel_tabelas()[$chave] ?? null;
}

/** Menu do painel: chave da tela para o rotulo mostrado. */
function painel_abas(): array
{
    $abas = [];
    foreach (painel_tabelas() as $chave => $def) {
        $abas[$chave] = $def['rotulo'];
    }

    return $abas;
}

/** Linhas de uma tela, inativas incluidas. O filtro so vale se a tela tiver um. */
function painel_listar(string $chave, ?string $filtro = null): array
{
    $def = painel_tabela($chave);
    if ($def === null) {
        return [];
    }

    if (isset($def['filtro']) && $filtro !== null && isset($def['filtro']['opcoes'][$filtro])) {
        $st = db()->prepare(
            'SELECT * FROM ' . $chave . ' WHERE ' . $def['filtro']['coluna'] . ' = ? ORDER BY ordem ASC, id ASC'
        );
        $st->execute([$filtro]);

        return $st->fetchAll();
    }

    return db()->query('SELECT * FROM ' . $chave . ' ORDER BY ordem ASC, id ASC')->fetchAll();
}

/** Uma linha pelo id, ou null. */
function painel_linha(string $chave, int $id): ?array
{
    if (painel_tabela($chave) === null) {
        return null;
    }

    $st = db()->prepare('SELECT * FROM ' . $chave . ' WHERE id = ?');
    $st->execute([$id]);
    $linha = $st->fetch();

    return $linha === false ? null : $linha;
}
