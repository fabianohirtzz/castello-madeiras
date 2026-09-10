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

/**
 * Extrai da entrada apenas as colunas descritas, ja convertidas para o tipo
 * de cada campo. Campos de arquivo ficam de fora: eles entram por
 * painel_arquivos(). Colunas de controle (id, ativo, ordem) nunca entram.
 */
function painel_valores(array $def, array $entrada): array
{
    $valores = [];

    foreach ($def['campos'] as $coluna => $campo) {
        if (in_array($campo['tipo'], ['imagem', 'video'], true)) {
            continue;
        }

        $bruto = $entrada[$coluna] ?? null;

        $valores[$coluna] = match ($campo['tipo']) {
            'sim_nao'   => ((string) $bruto === '1') ? 1 : 0,
            'numero'    => (int) $bruto,
            'selecao'   => isset($campo['opcoes'][(string) $bruto])
                             ? (string) $bruto
                             : (string) array_key_first($campo['opcoes']),
            'icone_faq' => icone_faq((string) $bruto) !== '' ? (string) $bruto : 'relogio',
            default     => trim((string) $bruto),
        };
    }

    return $valores;
}

/**
 * Processa os campos de arquivo do formulario.
 *
 * @return array{valores: array<string,string>, erros: array<string,string>}
 */
function painel_arquivos(array $def, array $arquivos): array
{
    $valores = [];
    $erros   = [];

    foreach ($def['campos'] as $coluna => $campo) {
        if (!in_array($campo['tipo'], ['imagem', 'video'], true)) {
            continue;
        }
        if (!isset($arquivos[$coluna])) {
            continue;
        }
        if ((int) ($arquivos[$coluna]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $r = upload_receber($arquivos[$coluna], (string) $campo['pasta'], $campo['tipo']);

        if ($r['ok']) {
            $valores[$coluna] = (string) $r['caminho'];
        } else {
            $erros[$coluna] = painel_erro_upload((string) $r['erro'], $campo['tipo']);
        }
    }

    return ['valores' => $valores, 'erros' => $erros];
}

/** Traduz o codigo de erro do upload para uma frase que o cliente entende. */
function painel_erro_upload(string $erro, string $tipo): string
{
    return match ($erro) {
        'tamanho' => $tipo === 'video'
            ? 'O vídeo passa de 30 MB. Reduza o arquivo e envie de novo.'
            : 'A imagem passa de 5 MB. Reduza o arquivo e envie de novo.',
        'tipo' => $tipo === 'video'
            ? 'Este arquivo não é um vídeo MP4. Envie o vídeo em MP4.'
            : 'Este arquivo não é uma imagem. Envie em JPG, PNG ou WEBP.',
        'gravacao'    => 'Não consegui gravar o arquivo no servidor. Tente de novo.',
        'sem_arquivo' => 'Nenhum arquivo chegou.',
        default       => 'O envio do arquivo não deu certo. Tente de novo.',
    };
}

/**
 * Campos obrigatorios que chegaram vazios.
 *
 * @return array<string,string> coluna para mensagem
 */
function painel_erros(array $def, array $valores): array
{
    $erros = [];

    foreach ($def['campos'] as $coluna => $campo) {
        if (empty($campo['obrigatorio'])) {
            continue;
        }

        $valor = $valores[$coluna] ?? '';
        if ($valor === '' || $valor === null) {
            $erros[$coluna] = $campo['rotulo'] . ' precisa ser preenchido.';
        }
    }

    return $erros;
}

/**
 * Insere ou atualiza uma linha. Devolve o id gravado.
 *
 * Item novo nasce ativo e no fim da ordem. Atualizar nunca mexe em ativo nem
 * em ordem: para isso existem painel_estado() e painel_reordenar().
 */
function painel_salvar(string $chave, ?int $id, array $valores): int
{
    $def = painel_tabela($chave);
    if ($def === null) {
        throw new InvalidArgumentException('tela de conteudo desconhecida: ' . $chave);
    }

    $colunas = array_values(array_intersect(array_keys($def['campos']), array_keys($valores)));
    if ($colunas === []) {
        throw new InvalidArgumentException('nada para gravar em ' . $chave);
    }

    $parametros = array_map(static fn (string $coluna) => $valores[$coluna], $colunas);

    if ($id === null || $id <= 0) {
        $ordem = (int) db()->query('SELECT COALESCE(MAX(ordem), 0) + 1 FROM ' . $chave)->fetchColumn();

        $sql = 'INSERT INTO ' . $chave . ' (' . implode(', ', $colunas) . ', ativo, ordem) VALUES ('
             . implode(', ', array_fill(0, count($colunas), '?')) . ', 1, ?)';

        $parametros[] = $ordem;
        db()->prepare($sql)->execute($parametros);

        return (int) db()->lastInsertId();
    }

    $sql = 'UPDATE ' . $chave . ' SET '
         . implode(', ', array_map(static fn (string $coluna): string => $coluna . ' = ?', $colunas))
         . ' WHERE id = ?';

    $parametros[] = $id;
    db()->prepare($sql)->execute($parametros);

    return $id;
}
