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
    igual(3, substr_count($flex, '<article class="model'));
    igual(3, substr_count($flex, 'Sob consulta'), 'Flex sem preco imprime Sob consulta');
    contem('<span class="model__badge">Semipronta</span>', $flex);
    nao_contem('Compacta', $flex);
    nao_contem('Chave na mão', $flex);

    db()->exec("UPDATE modelos SET ativo = 0 WHERE modalidade = 'flex'");
    igual('<div class="grid grid--models"></div>', norm(parcial('modelos', ['modalidade' => 'flex'])), 'sem modelo ativo a grade sai vazia');
    db()->exec("UPDATE modelos SET ativo = 1 WHERE modalidade = 'flex'");
});

teste('o rotulo do botao de orcamento sai no formato do site atual', function (): void {
    $html = parcial('modelos', ['modalidade' => 'pronta']);
    contem('data-modelo="Compacta · 39 m² · R$ 69.900"', $html);
    contem('data-modelo="Conforto · 42,75 m² · R$ 79.988"', $html);
    contem('data-modelo="Família · 51 m² · R$ 87.997"', $html);
    contem('data-modelo="Ampla · 59,75 m² · R$ 97.776"', $html);
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

teste('partials/passos.php imprime o scrollytelling identico ao site atual', function (): void {
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
