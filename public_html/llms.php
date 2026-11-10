<?php
declare(strict_types=1);

/**
 * /llms.txt: resumo do negocio em texto para as ferramentas de IA. O .htaccess
 * entrega este arquivo nesse endereco. Modelos, precos e perguntas saem do
 * banco, entao acompanham o que for mudado no painel.
 */

require_once __DIR__ . '/lib/conteudo.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$u = CASTELLO_URL;

$modelos_txt = static function (array $lista, string $antes): string {
    $linhas = '';
    foreach ($lista as $m) {
        $preco = trim((string) ($m['preco'] ?? ''));
        $linhas .= '- ' . $m['nome'] . ': ' . $m['area'] . ($preco !== '' ? ", {$antes}R$ {$preco}" : ', sob consulta') . "\n";
    }

    return $linhas;
};

$faq_txt = static function (string $contexto): string {
    $linhas = '';
    foreach (faq($contexto) as $f) {
        $linhas .= '### ' . schema_texto((string) $f['pergunta']) . "\n\n" . schema_texto((string) $f['resposta']) . "\n\n";
    }

    return $linhas;
};

echo "# Castello Casas de Madeira\n\n";
echo '> Construtora de casas de madeira em Tubarão, Santa Catarina, no mercado desde 2014, com mais de 19.000 m² construídos e nota ' . CASTELLO_GOOGLE_NOTA . ' no Google em ' . CASTELLO_GOOGLE_AVALIACOES . " avaliações. Trabalha com duas modalidades: Casa Pronta e Castello Flex.\n\n";

echo "## Modalidades\n\n";
echo "- [Casa Pronta]($u/casa-pronta): casa completa, chave na mão, entrega de 90 a 120 dias. O projeto é personalizável: distribuição dos ambientes, dimensões e acabamentos. Inclui fundação e laje de piso, estrutura de madeira e cobertura, revestimentos cerâmicos, instalações elétricas e hidráulicas, portas, janelas e vidros, fossa e sumidouro, execução e acompanhamento da obra.\n";
echo "- [Castello Flex]($u/flex): casa montada, coberta e fechada em 45 dias, em cinco plantas padronizadas, sem personalização. Inclui fundação com laje aérea, estrutura, telhado, forro à nível, esquadrias, alvenaria no chapisco e limpeza de obra. Não inclui vidros, elétrica, hidráulica, revestimento cerâmico, louças e balcão do banheiro, fossa, sumidouro e pintura: esse acabamento fica por conta do cliente.\n\n";

echo "## Modelos Castello Flex\n\n" . $modelos_txt(modelos('flex'), '') . "\n";
echo "## Projetos Casa Pronta\n\nValores de referência para o projeto inicial, porque todo projeto é personalizável.\n\n" . $modelos_txt(modelos('pronta'), 'a partir de ') . "\n";

echo "## Páginas\n\n";
echo "- [Início]($u/): visão geral da Castello\n";
echo "- [Portfólio]($u/portfolio): casas entregues em Santa Catarina\n";
echo "- [Depoimentos]($u/depoimentos): vídeos de clientes\n";
echo "- [Contato e orçamento]($u/contato): formulário, endereço e mapa\n\n";

echo "## Contato\n\n";
echo '- Endereço: ' . CASTELLO_ENDERECO_RUA . ', ' . CASTELLO_ENDERECO_BAIRRO . ', Tubarão, SC, CEP ' . CASTELLO_ENDERECO_CEP . "\n";
echo "- Horário: segunda a sexta, das 8h às 12h e das 13h30 às 18h; sábado, das 8h30 às 12h\n";
echo "- Orçamento: pelo formulário em $u/contato\n";
echo "- Instagram: https://www.instagram.com/castellocasasdemadeira/\n";
echo '- Google: ' . CASTELLO_MAPA_LINK . "\n\n";

echo "## Perguntas frequentes\n\n" . $faq_txt('geral');
echo "## Perguntas frequentes da Castello Flex\n\n" . $faq_txt('flex');
