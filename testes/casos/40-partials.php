<?php
declare(strict_types=1);

require_once site() . '/lib/conteudo.php';

banco_com_conteudo();

function base_frag(string $nome): string
{
    $caminho = raiz() . '/testes/base/frag-' . $nome . '.html';
    $html = file_get_contents($caminho);
    if ($html === false) {
        throw new RuntimeException('nao consegui ler ' . $caminho);
    }
    return $html;
}

function parcial(string $nome, array $vars = []): string
{
    return render(site() . '/partials/' . $nome . '.php', $vars);
}

teste('partials/modelos.php imprime a grade identica ao site atual', function (): void {
    igual(norm(base_frag('modelos')), norm(parcial('modelos', ['modalidade' => 'pronta'])));
});

teste('partials/modelos.php respeita a modalidade recebida', function (): void {
    $flex = parcial('modelos', ['modalidade' => 'flex']);
    igual(5, substr_count($flex, '<article class="model'));
    nao_contem('Sob consulta', $flex, 'os cinco Flex tem preco em tabela');
    contem('data-carrossel', $flex, 'na Flex a grade vira carrossel');
    contem('class="grid grid--models mcarrossel__track"', $flex);
    nao_contem('Semipronta', $flex, 'a palavra saiu do site');
    nao_contem('Projeto 1 ', $flex);
    nao_contem('Valor de referência para o projeto inicial', $flex, 'a Flex tem preço fechado de tabela');
    igual(5, substr_count($flex, '<template id="modelo-'), 'um popup de Ver mais por modelo');
    igual(5, substr_count($flex, 'modelbox__foto--planta'), 'todo Flex mostra a planta no Ver mais');

    $pronta = parcial('modelos', ['modalidade' => 'pronta']);
    nao_contem('data-carrossel', $pronta, 'a Casa Pronta continua em grade');
    igual(12, substr_count($pronta, 'class="model__ver"'));
    igual(12, substr_count($pronta, '<p class="model__ref">Valor de referência para o projeto inicial.</p>'));
    contem('<span class="eyebrow">Projeto personalizável</span>', $pronta);
    igual(1, substr_count($pronta, 'id="modelbox"'), 'um popup so por grade');

    db()->exec("UPDATE modelos SET ativo = 0 WHERE modalidade = 'flex'");
    $vazia = norm(parcial('modelos', ['modalidade' => 'flex']));
    contem('<div class="grid grid--models mcarrossel__track"></div>', $vazia, 'sem modelo ativo a grade sai vazia');
    db()->exec("UPDATE modelos SET ativo = 1 WHERE modalidade = 'flex'");
});

teste('os ambientes do modelo saem com o icone certo', function (): void {
    $amb = ambientes_modelo("2 quartos\n1 suíte\nEstúdio aberto com cozinha\nSala de estar\nBanheiro social\nÁrea de serviço\nGaragem\n\n");
    igual(7, count($amb), 'linha vazia nao vira ambiente');
    igual('2 quartos', $amb[0][1]);
    igual($amb[0][0], $amb[1][0], 'suite e quarto usam a cama');
    igual($amb[0][0], $amb[2][0], 'estudio usa a cama, nao o sofa');
    verdade($amb[3][0] !== $amb[0][0], 'sala de estar nao e quarto');
    contem('<svg', $amb[6][0]);
    igual([], ambientes_modelo(null));
});

teste('o rotulo do botao de orcamento sai no formato do site atual', function (): void {
    $html = parcial('modelos', ['modalidade' => 'pronta']);
    contem('data-modelo="Projeto 1 · 61,25 m² · R$ 108.000"', $html);
    contem('data-modelo="Projeto 11 · 36 m² · R$ 72.000"', $html, 'os centavos zerados somem do rotulo');
    contem('data-modelo="Projeto 12 · 49,24 m² · R$ 135.000"', $html);
});

teste('partials/portfolio.php imprime a grade identica a base conferida no navegador', function (): void {
    igual(norm(base_frag('portfolio')), norm(parcial('portfolio')));
});

teste('partials/avaliacoes.php imprime as 14 avaliacoes identicas ao site atual', function (): void {
    igual(norm(base_frag('avaliacoes')), norm(parcial('avaliacoes')));
});

teste('partials/videos.php com os 11 imprime a trilha identica ao site atual', function (): void {
    config_gravar('videos_na_home', '11');
    $saida = parcial('videos');
    config_gravar('videos_na_home', '8');
    igual(norm(base_frag('videos')), norm($saida));
});

teste('partials/videos.php respeita o limite da home', function (): void {
    igual(8, substr_count(parcial('videos'), 'class="ivid"'));
});

teste('partials/faq.php imprime as abas e paineis identicos ao site atual', function (): void {
    igual(norm(base_frag('faq')), norm(parcial('faq', ['contexto' => 'geral'])));
});

teste('partials/passos.php imprime a linha do tempo identica a base conferida no navegador', function (): void {
    igual(norm(base_frag('passos')), norm(parcial('passos', ['contexto' => 'pronta'])));
});

teste('toda imagem impressa pelos parciais tem alt preenchido', function (): void {
    $html = parcial('modelos', ['modalidade' => 'pronta'])
          . parcial('portfolio')
          . parcial('passos', ['contexto' => 'pronta']);

    preg_match_all('/<img\b[^>]*>/u', $html, $tags);
    verdade(count($tags[0]) > 0, 'deveria haver imagens');
    foreach ($tags[0] as $tag) {
        verdade((bool) preg_match('/ alt="[^"]+"/u', $tag), 'img sem alt: ' . $tag);
    }
});

teste('nenhum parcial imprime caminho absoluto de mídia', function (): void {
    $html = parcial('modelos', ['modalidade' => 'pronta'])
          . parcial('portfolio')
          . parcial('videos')
          . parcial('passos', ['contexto' => 'pronta']);

    nao_contem('src="/uploads', $html);
    nao_contem('poster="/uploads', $html);
    nao_contem(CASTELLO_UPLOADS, $html, 'caminho de disco nunca vai para o HTML');
});

teste('o texto do banco sai escapado', function (): void {
    db()->prepare('INSERT INTO avaliacoes (nome, texto, estrelas, ativo, ordem) VALUES (?, ?, 5, 1, 99)')
        ->execute(['Teste <script>', 'Aspas "duplas" & sinal <b>']);

    $html = parcial('avaliacoes');
    contem('Teste &lt;script&gt;', $html);
    contem('Aspas &quot;duplas&quot; &amp; sinal &lt;b&gt;', $html);
    nao_contem('<script>', $html);
});
