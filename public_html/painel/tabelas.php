<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/conteudo.php';
require_once __DIR__ . '/../lib/upload.php';
require_once __DIR__ . '/../lib/auth.php';

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
                'parede'     => ['rotulo' => 'Selo do card', 'tipo' => 'texto', 'ajuda' => 'Aparece acima do nome. Ex.: Madeira horizontal, Com garagem coberta. Vazio mostra o prazo'],
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
                'estrelas' => ['rotulo' => 'Estrelas', 'tipo' => 'numero', 'min' => 1, 'max' => 5, 'padrao' => 5, 'ajuda' => 'De 1 a 5'],
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
            'numero'    => ($bruto === null || trim((string) $bruto) === '') && isset($campo['padrao'])
                             ? (int) $campo['padrao']
                             : (int) $bruto,
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
        if ($campo['tipo'] === 'numero' && (isset($campo['min']) || isset($campo['max']))) {
            $n = (int) ($valores[$coluna] ?? 0);
            if ((isset($campo['min']) && $n < $campo['min']) || (isset($campo['max']) && $n > $campo['max'])) {
                $erros[$coluna] = $campo['rotulo'] . ' precisa ser de ' . ($campo['min'] ?? 0) . ' a ' . ($campo['max'] ?? '') . '.';
                continue;
            }
        }

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

/** Liga ou desliga um item. Nada e apagado, so deixa de aparecer no site. */
function painel_estado(string $chave, int $id, int $ativo): void
{
    if (painel_tabela($chave) === null) {
        throw new InvalidArgumentException('tela de conteudo desconhecida: ' . $chave);
    }

    db()->prepare('UPDATE ' . $chave . ' SET ativo = ? WHERE id = ?')
        ->execute([$ativo === 1 ? 1 : 0, $id]);
}

/**
 * Grava a nova ordem: o primeiro id da lista vira ordem 1, e assim por diante.
 * Ids que nao existem sao ignorados. Devolve quantas linhas mudaram.
 */
function painel_reordenar(string $chave, array $ids): int
{
    if (painel_tabela($chave) === null) {
        throw new InvalidArgumentException('tela de conteudo desconhecida: ' . $chave);
    }

    $st = db()->prepare('UPDATE ' . $chave . ' SET ordem = ? WHERE id = ?');
    $posicao = 0;
    $mudaram = 0;

    db()->beginTransaction();
    foreach ($ids as $bruto) {
        $id = (int) $bruto;
        if ($id <= 0) {
            continue;
        }
        $posicao++;
        $st->execute([$posicao, $id]);
        $mudaram += $st->rowCount();
    }
    db()->commit();

    return $mudaram;
}

/** Telas do painel que nao sao de conteudo. */
function painel_fixas(): array
{
    return [
        'textos' => 'Textos',
        'config' => 'Configurações',
        'backup' => 'Backup',
        'senha'  => 'Trocar senha',
    ];
}

/** As telas do menu lateral, na ordem, agrupadas por assunto. */
function painel_grupos(): array
{
    return [
        ['titulo' => 'Conteúdo', 'itens' => painel_abas()],
        ['titulo' => 'Sistema',  'itens' => painel_fixas()],
    ];
}

/** Icone de uma tela, para o menu lateral. Traco simples, herda a cor do link. */
function painel_icone(string $tela): string
{
    $desenhos = [
        'modelos'    => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M9.5 21v-6h5v6"/>',
        'portfolio'  => '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8.5" cy="10" r="1.5"/><path d="m3 16 4.5-4 4 3.5L15.5 11 21 16.5"/>',
        'avaliacoes' => '<path d="m12 3.5 2.6 5.3 5.9.9-4.2 4.1 1 5.8-5.3-2.8-5.3 2.8 1-5.8L3.5 9.7l5.9-.9z"/>',
        'videos'     => '<rect x="3" y="5" width="18" height="14" rx="3"/><path d="M10.5 9.2v5.6l4.5-2.8z"/>',
        'faq'        => '<circle cx="12" cy="12" r="9"/><path d="M9.6 9.3a2.5 2.5 0 1 1 3.3 2.4c-.6.2-.9.8-.9 1.4v.4"/><path d="M12 16.8h.01"/>',
        'passos'     => '<rect x="4" y="6" width="3.5" height="3.5" rx="1"/><rect x="4" y="14.5" width="3.5" height="3.5" rx="1"/><path d="M11 7.8h9"/><path d="M11 16.3h9"/>',
        'textos'     => '<path d="M5 4.5h14"/><path d="M12 4.5V19"/><path d="M9 19h6"/>',
        'config'     => '<path d="M4 7.5h4.5M13 7.5h7"/><path d="M4 16.5h7M15.5 16.5H20"/><circle cx="10.75" cy="7.5" r="2.25"/><circle cx="13.25" cy="16.5" r="2.25"/>',
        'backup'     => '<path d="M12 3.5v9"/><path d="m8.5 9.5 3.5 3 3.5-3"/><path d="M4 15.5v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/>',
        'senha'      => '<rect x="4.5" y="10.5" width="15" height="9.5" rx="2"/><path d="M8.5 10.5V7.8a3.5 3.5 0 0 1 7 0v2.7"/>',
    ];

    if (!isset($desenhos[$tela])) {
        return '';
    }

    return '<svg class="p-lado__icone" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
        . $desenhos[$tela] . '</svg>';
}

/** Grava os textos avulsos. So chaves que ja existem em blocos sao aceitas. */
function painel_textos_gravar(array $entrada): int
{
    $chaves = db()->query('SELECT chave FROM blocos ORDER BY rowid')->fetchAll(PDO::FETCH_COLUMN);
    $st = db()->prepare('UPDATE blocos SET valor = ? WHERE chave = ?');
    $gravadas = 0;

    foreach ($chaves as $chave) {
        if (!array_key_exists($chave, $entrada)) {
            continue;
        }
        $st->execute([trim((string) $entrada[$chave]), $chave]);
        $gravadas++;
    }

    return $gravadas;
}

/** As doze chaves de config que o cliente edita, com rotulo e tipo. */
function painel_config_campos(): array
{
    return [
        'videos_na_home' => ['rotulo' => 'Quantos vídeos aparecem na home', 'tipo' => 'numero',
                             'min' => 1, 'max' => 24,
                             'ajuda' => 'De 1 a 24. Quanto mais vídeos, mais devagar a página carrega'],
        'email_aviso'    => ['rotulo' => 'E-mail que recebe aviso de pedido novo', 'tipo' => 'texto'],
        'email_dominio'  => ['rotulo' => 'Domínio usado no remetente', 'tipo' => 'texto',
                             'ajuda' => 'Só o domínio, como castellomadeiras.com.br'],
        'crm_ativo'      => ['rotulo' => 'Enviar os pedidos para o CRM', 'tipo' => 'sim_nao',
                             'ajuda' => 'Deixe desligado enquanto o CRM não estiver configurado. O pedido continua sendo gravado e enviado por e-mail'],
        'crm_funil'      => ['rotulo' => 'Funil do Agendor', 'tipo' => 'numero',
                             'min' => 1, 'max' => 99999999,
                             'ajuda' => 'O número do funil que recebe os pedidos. O funil de vendas da Castello é o 904296'],
        'crm_etapa'      => ['rotulo' => 'Etapa onde o pedido entra', 'tipo' => 'numero',
                             'min' => 1, 'max' => 99,
                             'ajuda' => 'A posição da etapa no funil, contando da esquerda. Contato é a 1'],
        'crm_origem'     => ['rotulo' => 'Origem do contato', 'tipo' => 'numero',
                             'min' => 1, 'max' => 99999999,
                             'ajuda' => 'O número da origem no Agendor. Site é 2656389'],
        'crm_categoria'  => ['rotulo' => 'Categoria do contato', 'tipo' => 'numero',
                             'min' => 1, 'max' => 99999999,
                             'ajuda' => 'O número da categoria. Cliente em potencial é 4187395'],
        'crm_marcador'   => ['rotulo' => 'Marcador no título do negócio', 'tipo' => 'texto',
                             'ajuda' => 'Aparece na frente do título, para separar o que veio do site do que veio das redes. Escreva com os colchetes, como [SITE]'],
        'crm_responsavel' => ['rotulo' => 'Responsável pelos pedidos do site', 'tipo' => 'texto',
                              'ajuda' => 'O número ou o e-mail do vendedor no Agendor. Deixe vazio para cair na conta principal'],
        'crm_timeout'    => ['rotulo' => 'Segundos de espera pelo CRM', 'tipo' => 'numero',
                             'min' => 1, 'max' => 10,
                             'ajuda' => 'De 1 a 10. O servidor derruba a página em 60 segundos, então esperar mais que 10 pelo CRM faria o visitante esperar junto'],
        'reenvio_chave'  => ['rotulo' => 'Chave do reenvio de pendentes', 'tipo' => 'texto',
                             'ajuda' => 'Autoriza o reenvio dos pedidos que não chegaram ao CRM. Apague o campo e salve para gerar uma chave nova'],
    ];
}

/**
 * Valida a configuracao enviada pelo formulario.
 *
 * @return array{valores: array<string,string>, erros: array<string,string>}
 */
function painel_config_validar(array $entrada): array
{
    $valores = [];
    $erros   = [];

    foreach (painel_config_campos() as $chave => $campo) {
        $bruto = trim((string) ($entrada[$chave] ?? ''));

        if ($chave === 'videos_na_home') {
            $numero = (int) $bruto;
            if ($numero < 1 || $numero > 24) {
                $erros[$chave] = 'Escolha um número de 1 a 24.';
                continue;
            }
            $valores[$chave] = (string) $numero;
            continue;
        }

        if ($chave === 'crm_timeout') {
            $numero = (int) $bruto;
            if ($numero < 1) {
                $numero = $bruto === '' || !ctype_digit($bruto) ? 10 : 1;
            }
            if ($numero > 10) {
                $numero = 10;
            }
            $valores[$chave] = (string) $numero;
            continue;
        }

        if ($chave === 'email_aviso') {
            if ($bruto === '' || filter_var($bruto, FILTER_VALIDATE_EMAIL) === false) {
                $erros[$chave] = 'Escreva um e-mail válido, como contato@castellomadeiras.com.br';
                continue;
            }
            $valores[$chave] = $bruto;
            continue;
        }

        if ($chave === 'email_dominio') {
            if ($bruto === '' || !preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/i', $bruto)) {
                $erros[$chave] = 'Escreva só o domínio, como castellomadeiras.com.br';
                continue;
            }
            $valores[$chave] = mb_strtolower($bruto);
            continue;
        }

        if ($chave === 'crm_ativo') {
            $valores[$chave] = $bruto === '1' ? '1' : '0';
            continue;
        }

        if (in_array($chave, ['crm_funil', 'crm_etapa', 'crm_origem', 'crm_categoria'], true)) {
            $numero = (int) $bruto;
            if ($bruto === '' || !ctype_digit($bruto) || $numero < 1 || $numero > ($campo['max'] ?? PHP_INT_MAX)) {
                $erros[$chave] = 'Escreva só o número, maior que zero. Ele vem do Agendor.';
                continue;
            }
            $valores[$chave] = (string) $numero;
            continue;
        }

        if ($chave === 'crm_marcador') {
            if ($bruto === '') {
                $erros[$chave] = 'O marcador não pode ficar vazio. O padrão é [SITE].';
                continue;
            }
            $valores[$chave] = mb_substr($bruto, 0, 30);
            continue;
        }

        if ($chave === 'crm_responsavel') {
            $valores[$chave] = mb_substr($bruto, 0, 120);
            continue;
        }

        if ($chave === 'reenvio_chave') {
            $valores[$chave] = strlen($bruto) >= 32 ? $bruto : bin2hex(random_bytes(16));
            continue;
        }
    }

    return ['valores' => $valores, 'erros' => $erros];
}

/**
 * Gera um zip com o banco e a pasta uploads.
 *
 * ZipArchive nao existe no PHP local, so no servidor. Por isso a checagem:
 * sem a classe, devolve erro claro em vez de derrubar a pagina.
 *
 * @return array{ok: bool, arquivo: ?string, erro: ?string}
 */
function painel_backup(): array
{
    if (!class_exists('ZipArchive')) {
        return [
            'ok'      => false,
            'arquivo' => null,
            'erro'    => 'A extensão ZipArchive não está instalada neste servidor, então não consigo gerar o arquivo. Peça à hospedagem para ligar a extensão zip do PHP.',
        ];
    }

    // O banco roda em WAL: sem o checkpoint, as ultimas gravacoes ficariam so
    // no arquivo .db-wal e o backup sairia desatualizado.
    db()->exec('PRAGMA wal_checkpoint(TRUNCATE)');

    $destino = rtrim(sys_get_temp_dir(), "/\\") . '/castello-backup-' . date('Y-m-d-His') . '.zip';

    $zip = new ZipArchive();
    if ($zip->open($destino, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return ['ok' => false, 'arquivo' => null, 'erro' => 'Não consegui criar o arquivo de backup em disco.'];
    }

    $banco = CASTELLO_CONFIG . '/castello.db';
    if (is_file($banco)) {
        $zip->addFile($banco, 'castello.db');
    }

    if (is_dir(CASTELLO_UPLOADS)) {
        $itens = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(CASTELLO_UPLOADS, FilesystemIterator::SKIP_DOTS)
        );
        $corte = strlen(CASTELLO_UPLOADS) + 1;

        foreach ($itens as $item) {
            if (!$item->isFile()) {
                continue;
            }
            $relativo = 'uploads/' . str_replace('\\', '/', substr($item->getPathname(), $corte));
            $zip->addFile($item->getPathname(), $relativo);
        }
    }

    if (!$zip->close()) {
        return ['ok' => false, 'arquivo' => null, 'erro' => 'Não consegui fechar o arquivo de backup.'];
    }

    return ['ok' => true, 'arquivo' => $destino, 'erro' => null];
}

/**
 * Troca a senha do unico usuario do painel.
 *
 * @return array{ok: bool, erro: ?string}
 */
function painel_trocar_senha(int $usuarioId, string $atual, string $nova, string $confirma): array
{
    $st = db()->prepare('SELECT senha_hash FROM usuarios WHERE id = ?');
    $st->execute([$usuarioId]);
    $hash = $st->fetchColumn();

    if ($hash === false) {
        return ['ok' => false, 'erro' => 'Usuário não encontrado.'];
    }
    if (!password_verify($atual, (string) $hash)) {
        return ['ok' => false, 'erro' => 'A senha atual está errada.'];
    }
    if (mb_strlen($nova) < 8) {
        return ['ok' => false, 'erro' => 'A senha nova precisa ter pelo menos 8 caracteres.'];
    }
    if ($nova !== $confirma) {
        return ['ok' => false, 'erro' => 'A confirmação não bate com a senha nova.'];
    }
    if ($nova === $atual) {
        return ['ok' => false, 'erro' => 'A senha nova precisa ser diferente da atual.'];
    }

    db()->prepare('UPDATE usuarios SET senha_hash = ? WHERE id = ?')
        ->execute([password_hash($nova, PASSWORD_BCRYPT), $usuarioId]);

    // Senha nova expulsa todo aparelho que estava lembrado, inclusive este.
    auth_lembrar_derrubar($usuarioId);

    return ['ok' => true, 'erro' => null];
}

/** Converte um valor de php.ini como 8M ou 64M para bytes. Zero quando ilimitado. */
function painel_bytes(string $valor): int
{
    $valor = trim($valor);
    if ($valor === '' || $valor === '-1') {
        return 0;
    }
    $unidade = strtolower(substr($valor, -1));
    $numero  = (int) $valor;
    return match ($unidade) {
        'g' => $numero * 1073741824,
        'm' => $numero * 1048576,
        'k' => $numero * 1024,
        default => $numero,
    };
}
