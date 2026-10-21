<?php
declare(strict_types=1);

/**
 * Carrega no banco o conteudo real que hoje esta escrito no index.html.
 *
 * Linha de comando:  php public_html/migrar.php
 * Pela web:          /migrar.php enquanto o banco estiver vazio, que e a
 *                    instalacao. Depois disso, /migrar.php?chave=SUA_CHAVE,
 *                    com a chave que este script gravou em segredos.php.
 *
 * Cada tabela so e preenchida quando esta vazia, entao rodar de novo nunca
 * apaga o que o cliente ja editou pelo painel.
 */

require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/conteudo.php';

if (!defined('CASTELLO_UPLOADS')) {
    define('CASTELLO_UPLOADS', __DIR__ . '/uploads');
}

/**
 * Os modelos, um array por linha. Casa Pronta: os 12 projetos do catálogo de
 * outubro de 2026 (foto recortada da página do catálogo, área total, preço "a
 * partir de" e os ambientes com ícone). Castello Flex: os cinco projetos do
 * drive, com render da fachada, planta baixa com cotas, áreas por cômodo e a
 * tabela de preços de setembro de 2026. Itens vão um por linha.
 */
const MIGRAR_MODELOS = [
    ['modalidade' => 'pronta', 'nome' => 'Projeto 1', 'area' => '61,25 m²', 'parede' => '', 'preco' => '108.000', 'prazo' => '',
     'foto' => 'fotos-catalogo/projeto-01.webp', 'alt' => 'Casa de madeira térrea com tábuas na vertical, varanda coberta e escada de concreto na entrada',
     'itens' => ['3 quartos', 'Sala e cozinha conjugada', 'Banheiro', 'Varanda'], 'planta' => '', 'descricao' => "Projeto de 61,25 m² com 3 quartos, sala e cozinha conjugada, banheiro e varanda.\n\nProjeto personalizável: a distribuição dos ambientes, as dimensões e os acabamentos se ajustam ao seu terreno e ao que você procura. O valor é de referência para o projeto inicial."],
    ['modalidade' => 'pronta', 'nome' => 'Projeto 2', 'area' => '163,25 m²', 'parede' => '', 'preco' => '326.000', 'prazo' => '',
     'foto' => 'fotos-catalogo/projeto-02.webp', 'alt' => 'Sobrado de madeira com sacada, varanda com pergolado e garagem coberta ao lado',
     'itens' => ['4 quartos', '2 banheiros', 'Sala e cozinha conjugada', 'Área de serviço', 'Varanda', 'Garagem'], 'planta' => '', 'descricao' => "Projeto de 163,25 m² com 4 quartos, 2 banheiros, sala e cozinha conjugada, área de serviço, varanda e garagem.\n\nProjeto personalizável: a distribuição dos ambientes, as dimensões e os acabamentos se ajustam ao seu terreno e ao que você procura. O valor é de referência para o projeto inicial."],
    ['modalidade' => 'pronta', 'nome' => 'Projeto 3', 'area' => '134,75 m²', 'parede' => '', 'preco' => '280.000', 'prazo' => '',
     'foto' => 'fotos-catalogo/projeto-03.webp', 'alt' => 'Sobrado de madeira com sacada de guarda-corpo em X e varanda na frente',
     'itens' => ['3 quartos', '1 suíte', '1 banheiro social', 'Sala e cozinha conjugadas', 'Sacada', 'Varanda', 'Garagem'], 'planta' => '', 'descricao' => "Projeto de 134,75 m² com 3 quartos, 1 suíte, 1 banheiro social, sala e cozinha conjugadas, sacada, varanda e garagem.\n\nProjeto personalizável: a distribuição dos ambientes, as dimensões e os acabamentos se ajustam ao seu terreno e ao que você procura. O valor é de referência para o projeto inicial."],
    ['modalidade' => 'pronta', 'nome' => 'Projeto 4', 'area' => '82,62 m²', 'parede' => '', 'preco' => '158.000', 'prazo' => '',
     'foto' => 'fotos-catalogo/projeto-04.webp', 'alt' => 'Casa de madeira térrea com janelas brancas, gramado na frente e garagem coberta ao lado',
     'itens' => ['1 suíte', '1 quarto', 'Banheiro social', 'Sala e cozinha conjugada', 'Varanda', 'Garagem'], 'planta' => '', 'descricao' => "Projeto de 82,62 m² com 1 suíte, 1 quarto, banheiro social, sala e cozinha conjugada, varanda e garagem.\n\nProjeto personalizável: a distribuição dos ambientes, as dimensões e os acabamentos se ajustam ao seu terreno e ao que você procura. O valor é de referência para o projeto inicial."],
    ['modalidade' => 'pronta', 'nome' => 'Projeto 5', 'area' => '94,67 m²', 'parede' => '', 'preco' => '174.000', 'prazo' => '',
     'foto' => 'fotos-catalogo/projeto-05.webp', 'alt' => 'Casa de madeira térrea com varanda em volta, telhado claro e jardim cercado de mata',
     'itens' => ['2 quartos', 'Banheiro social', 'Sala e cozinha conjugada', 'Varanda', 'Garagem'], 'planta' => '', 'descricao' => "Projeto de 94,67 m² com 2 quartos, banheiro social, sala e cozinha conjugada, varanda e garagem.\n\nProjeto personalizável: a distribuição dos ambientes, as dimensões e os acabamentos se ajustam ao seu terreno e ao que você procura. O valor é de referência para o projeto inicial."],
    ['modalidade' => 'pronta', 'nome' => 'Projeto 6', 'area' => '95,50 m²', 'parede' => '', 'preco' => '179.000', 'prazo' => '',
     'foto' => 'fotos-catalogo/projeto-06.webp', 'alt' => 'Casa de madeira térrea com telhado de telha cerâmica, detalhe em tijolinho e garagem coberta',
     'itens' => ['1 suíte', '2 quartos', 'Banheiro social', 'Sala e cozinha conjugada', 'Varanda', 'Garagem'], 'planta' => '', 'descricao' => "Projeto de 95,50 m² com 1 suíte, 2 quartos, banheiro social, sala e cozinha conjugada, varanda e garagem.\n\nProjeto personalizável: a distribuição dos ambientes, as dimensões e os acabamentos se ajustam ao seu terreno e ao que você procura. O valor é de referência para o projeto inicial."],
    ['modalidade' => 'pronta', 'nome' => 'Projeto 7', 'area' => '135,00 m²', 'parede' => '', 'preco' => '253.000', 'prazo' => '',
     'foto' => 'fotos-catalogo/projeto-07.webp', 'alt' => 'Casa de madeira térrea com varanda em volta, guarda-corpo em X e morro de mata ao fundo',
     'itens' => ['2 suítes', '1 quarto', 'Banheiro social', 'Sala e cozinha conjugada', 'Varanda'], 'planta' => '', 'descricao' => "Projeto de 135,00 m² com 2 suítes, 1 quarto, banheiro social, sala e cozinha conjugada e varanda.\n\nProjeto personalizável: a distribuição dos ambientes, as dimensões e os acabamentos se ajustam ao seu terreno e ao que você procura. O valor é de referência para o projeto inicial."],
    ['modalidade' => 'pronta', 'nome' => 'Projeto 8', 'area' => '122,83 m²', 'parede' => '', 'preco' => '224.000', 'prazo' => '',
     'foto' => 'fotos-catalogo/projeto-08.webp', 'alt' => 'Casa de madeira com telhado em duas águas, pergolado na entrada e garagem coberta',
     'itens' => ['1 suíte', '1 quarto', 'Banheiro social', 'Sala e cozinha conjugada', 'Varandas', 'Garagem'], 'planta' => '', 'descricao' => "Projeto de 122,83 m² com 1 suíte, 1 quarto, banheiro social, sala e cozinha conjugada, varandas e garagem.\n\nProjeto personalizável: a distribuição dos ambientes, as dimensões e os acabamentos se ajustam ao seu terreno e ao que você procura. O valor é de referência para o projeto inicial."],
    ['modalidade' => 'pronta', 'nome' => 'Projeto 9', 'area' => '42,75 m²', 'parede' => '', 'preco' => '113.000', 'prazo' => '',
     'foto' => 'fotos-catalogo/projeto-09.webp', 'alt' => 'Casa de madeira moderna com fachada preta, beiral de madeira iluminado e grandes janelas de vidro',
     'itens' => ['Estúdio aberto com cozinha', 'Quarto', '1 banheiro', 'Hidromassagem', 'Varanda', 'Área de serviço'], 'planta' => '', 'descricao' => "Projeto de 42,75 m² com estúdio aberto com cozinha, quarto, 1 banheiro, hidromassagem, varanda e área de serviço.\n\nProjeto personalizável: a distribuição dos ambientes, as dimensões e os acabamentos se ajustam ao seu terreno e ao que você procura. O valor é de referência para o projeto inicial."],
    ['modalidade' => 'pronta', 'nome' => 'Projeto 10', 'area' => '62,50 m²', 'parede' => '', 'preco' => '157.000', 'prazo' => '',
     'foto' => 'fotos-catalogo/projeto-10.webp', 'alt' => 'Chalé de madeira em formato A com fachada de vidro e deck na frente',
     'itens' => ['Estúdio aberto com cozinha', 'Quarto', '1 banheiro', 'Hidromassagem', 'Varanda', 'Mezanino', 'Área de serviço'], 'planta' => '', 'descricao' => "Projeto de 62,50 m² com estúdio aberto com cozinha, quarto, 1 banheiro, hidromassagem, varanda, mezanino e área de serviço.\n\nProjeto personalizável: a distribuição dos ambientes, as dimensões e os acabamentos se ajustam ao seu terreno e ao que você procura. O valor é de referência para o projeto inicial."],
    ['modalidade' => 'pronta', 'nome' => 'Projeto 11', 'area' => '36,00 m²', 'parede' => '', 'preco' => '72.000', 'prazo' => '',
     'foto' => 'fotos-catalogo/projeto-11.webp', 'alt' => 'Casa de madeira térrea pequena com varanda coberta, porta de vidro e janelas com veneziana',
     'itens' => ['2 quartos', 'Sala e cozinha conjugada', 'Banheiro', 'Varanda'], 'planta' => '', 'descricao' => "Projeto de 36,00 m² com 2 quartos, sala e cozinha conjugada, banheiro e varanda.\n\nProjeto personalizável: a distribuição dos ambientes, as dimensões e os acabamentos se ajustam ao seu terreno e ao que você procura. O valor é de referência para o projeto inicial."],
    ['modalidade' => 'pronta', 'nome' => 'Projeto 12', 'area' => '49,24 m²', 'parede' => '', 'preco' => '135.000', 'prazo' => '',
     'foto' => 'fotos-catalogo/projeto-12.webp', 'alt' => 'Dois sobrados de madeira geminados com sacada no andar de cima e tijolinho no térreo',
     'itens' => ['1 quarto', '1 banheiro', 'Sala e cozinha conjugadas', 'Sacada', 'Mezanino'], 'planta' => '', 'descricao' => "Projeto de 49,24 m² com 1 quarto, 1 banheiro, sala e cozinha conjugadas, sacada e mezanino.\n\nProjeto personalizável: a distribuição dos ambientes, as dimensões e os acabamentos se ajustam ao seu terreno e ao que você procura. O valor é de referência para o projeto inicial."],
    ['modalidade' => 'flex', 'nome' => 'Castello Flex 36', 'area' => '36,00 m²', 'parede' => '', 'preco' => '43.000', 'prazo' => '45 dias',
     'foto' => 'fotos-flex/flex-36.webp', 'alt' => 'Castello Flex de 36 m²: casa de madeira térrea com varanda coberta e janela com veneziana',
     'itens' => ['2 dormitórios', 'Sala e cozinha integradas', 'Banheiro', 'Varanda'], 'planta' => 'fotos-flex/flex-36-planta.webp', 'descricao' => "Planta com dois dormitórios de 7,94 m², sala e cozinha integradas de 10,10 m², banheiro de 3,38 m² e varanda de 3,00 m².\n\nÁrea fechada de 33,00 m² e área aberta de 3,00 m²."],
    ['modalidade' => 'flex', 'nome' => 'Castello Flex 39', 'area' => '39,00 m²', 'parede' => '', 'preco' => '47.000', 'prazo' => '45 dias',
     'foto' => 'fotos-flex/flex-39.webp', 'alt' => 'Castello Flex de 39 m²: casa de madeira térrea com varanda ampla na frente',
     'itens' => ['2 dormitórios', 'Sala e cozinha integradas', 'Banheiro', 'Varanda'], 'planta' => 'fotos-flex/flex-39-planta.webp', 'descricao' => "Planta com dois dormitórios de 7,94 m², sala e cozinha integradas de 13,00 m², banheiro de 2,64 m² e varanda de 3,50 m².\n\nÁrea fechada de 35,50 m² e área aberta de 3,50 m²."],
    ['modalidade' => 'flex', 'nome' => 'Castello Flex 42', 'area' => '42,75 m²', 'parede' => '', 'preco' => '51.000', 'prazo' => '45 dias',
     'foto' => 'fotos-flex/flex-42.webp', 'alt' => 'Castello Flex de 42,75 m²: casa de madeira térrea com telhado de telha cerâmica e varanda',
     'itens' => ['2 dormitórios', 'Sala e cozinha integradas', 'Banheiro', 'Varanda'], 'planta' => 'fotos-flex/flex-42-planta.webp', 'descricao' => "Planta com dormitórios de 7,94 m² e 6,56 m², sala e cozinha integradas de 16,58 m², circulação de 1,26 m², banheiro de 2,64 m² e varanda de 3,00 m².\n\nÁrea fechada de 39,75 m² e área aberta de 3,00 m²."],
    ['modalidade' => 'flex', 'nome' => 'Castello Flex 51', 'area' => '51,00 m²', 'parede' => 'Com garagem coberta', 'preco' => '59.000', 'prazo' => '45 dias',
     'foto' => 'fotos-flex/flex-51.webp', 'alt' => 'Castello Flex de 51 m²: casa de madeira térrea com garagem coberta ao lado da varanda',
     'itens' => ['2 dormitórios', 'Sala e cozinha integradas', 'Banheiro', 'Varanda', 'Garagem coberta'], 'planta' => 'fotos-flex/flex-51-planta.webp', 'descricao' => "A planta do Castello Flex 36 com garagem coberta de 15,00 m²: dois dormitórios de 7,94 m², sala e cozinha integradas de 10,10 m², banheiro de 3,38 m² e varanda de 3,00 m².\n\nÁrea fechada de 33,00 m² e área aberta de 18,00 m²."],
    ['modalidade' => 'flex', 'nome' => 'Castello Flex 57', 'area' => '57,75 m²', 'parede' => 'Com garagem coberta', 'preco' => '69.000', 'prazo' => '45 dias',
     'foto' => 'fotos-flex/flex-57.webp', 'alt' => 'Castello Flex de 57,75 m²: casa de madeira térrea com varanda e garagem coberta',
     'itens' => ['2 dormitórios', 'Sala e cozinha integradas', 'Banheiro', 'Varanda', 'Garagem coberta'], 'planta' => 'fotos-flex/flex-57-planta.webp', 'descricao' => "A planta do Castello Flex 42 com garagem coberta de 15,00 m²: dormitórios de 7,94 m² e 6,56 m², sala e cozinha integradas de 16,58 m², circulação de 1,26 m², banheiro de 2,64 m² e varanda de 3,00 m².\n\nÁrea fechada de 39,75 m² e área aberta de 18,00 m²."],
];

/** titulo, categoria, foto de origem, alt */
const MIGRAR_PORTFOLIO = [
    ['Sobrado à beira da água', 'Beira da água', 'fotos-casas/casa3.webp', 'Sobrado de madeira à beira da água com vista para a ponte'],
    ['Sobrado com sacada', 'Dois pavimentos', 'fotos-casas/casa7.webp', 'Sobrado de madeira com sacada e fachada de réguas'],
    ['Varanda ampla e garagem', 'Térrea', 'fotos-casas/casa6.webp', 'Casa de madeira térrea com varanda ampla e garagem coberta'],
    ['Casa de campo com varanda', 'No campo', 'fotos-casas/casa-8.webp', 'Casa de madeira de campo com varanda e cerca branca'],
    ['Varanda com pergolado', 'Área externa', 'fotos-casas/casa1.webp', 'Casa de madeira com pergolado e varanda ao sol'],
    ['Chalé com varanda', 'Na natureza', 'fotos-casas/casa5.webp', 'Casa de madeira com varanda em volta e mata ao fundo'],
];

/** nome, texto. Avaliacoes reais do Google Meu Negocio, 5,0 estrelas. */
const MIGRAR_AVALIACOES = [
    ['Joana Lazzaris', 'Tivemos uma excelente experiência com a Castello, desde a negociação da compra, modelo da casa, até a entrega dentro do prazo. Tivemos zero dor de cabeça de obra, mesmo sendo um pouco distante da cidade sede da empresa. São muito flexíveis e nos auxiliaram em todas as etapas.'],
    ['Franciely Silva', 'Estou muito satisfeita com a experiência que tive com a Castello. Desde o início, o atendimento foi excelente, sempre prestativo e transparente. A qualidade da construção superou minhas expectativas, com acabamentos bem feitos e um ótimo padrão. Recomendo para quem busca confiança e compromisso em cada detalhe.'],
    ['Luiz Flores', 'A Castello foi uma grata surpresa na nossa vida. Uma entrega com excelência, com negociação online e acompanhamento de obra em todas as etapas. Nunca imaginei ter uma obra de construção sem qualquer problema com o prestador do serviço. Parabéns ao Carlos e ao time da Castello.'],
    ['Ana Paula Elias de Oliveira', 'Entrei em contato com algumas empresas da região que constroem casas de madeira e fechei negócio com a Castello. A arquiteta Talita foi super atenciosa desde o primeiro momento, com paciência para responder às minhas infinitas perguntas e adaptar o projeto até chegarmos ao ideal. A velocidade e a qualidade do trabalho são impressionantes, e a equipe toda muito cordial. Meu pai, que resistia à casa de madeira, está super feliz. Recomendo!'],
    ['Nany Festa', 'Conhecer a Castello foi a realização de um sonho. Nossa construção ocorreu tudo certo, dentro do prazo, e entregaram a nossa até antes. Sempre com uma comunicação clara e dispostos a esclarecer as nossas dúvidas. Com certeza recomendo o trabalho deles. Além da construção ser excelente, a equipe toda é muito profissional.'],
    ['Diana Chris de Souza', 'A Castello realizou meu sonho e ficou tudo perfeito, com um atendimento muito especial de todos, em especial o Carlos. Já fazem quatro anos que nossa casa foi entregue e sempre recomendo eles.'],
    ['Antonio Marcos Decker', 'Atendimento de excelência em todas as etapas do contrato. Obra concluída dentro do prazo e sem intercorrências. Ótima experiência com a Castello Casas de Madeira. Para quem quer construir sem dores de cabeça, recomendo!'],
    ['Jussara Varela', 'Só temos que agradecer pelo profissionalismo e dedicação em todo o processo da obra. Foi um imenso prazer trabalhar com essa equipe sensacional, com comprometimento e responsabilidade incrível. O prazo de entrega foi devidamente cumprido conforme o combinado. Recomendamos a todos o trabalho dessa equipe de excelência.'],
    ['Roberto Gavioli', 'Em todas as etapas, desde o projeto até a entrega, a experiência foi ótima. A equipe da Castello trabalhou conosco para conseguirmos a casa que queríamos. Estamos satisfeitos e recomendamos, sem qualquer ressalva, a construtora.'],
    ['Vanderson Luiz', 'Desde o primeiro contato até a entrega das chaves, não tenho o que reclamar. O pessoal sempre pronto para atender e tirar as dúvidas. Super indico a construtora, parabéns pelo profissionalismo.'],
    ['Renato Goulart', 'Estava fazendo orçamento e fechei com a Castello pelo melhor preço, condições de pagamento e excelente qualidade da obra. Tudo que foi redigido no contrato foi cumprido. Eu indico para quem pensar em fazer uma casa.'],
    ['Frederico Guimarães', 'Minha primeira construção com a Castello e estou muito satisfeito. A empresa conta com profissionais competentes e dedicados, sempre à disposição de seus clientes e parceiros. Uma equipe enérgica, que trabalha muito para alcançar seus objetivos. O resultado são os charmosos chalés que vão surgindo e dando um toque especial em toda a região.'],
    ['Leslie Souza', 'Super indico a Castello. Fizeram a casa exatamente como pedimos, estavam sempre presentes na obra, atenciosos e dispostos a resolver tudo. Estamos muito contentes com a nossa casa nova. Muito obrigado!'],
    ['Lares do Sul', 'Tivemos uma ótima experiência com a Castello, entregaram dentro do prazo, serviço de qualidade. O proprietário também é uma pessoa de fácil negociação e respondia rapidamente sempre que solicitado. Recomendo.'],
];

/**
 * Videos da galeria da Castello Flex (home e pagina Flex): arquivo, capa e
 * legenda. O primeiro e o explicativo dos 5 modelos, que ja era o video
 * unico do bloco flex_video; os outros tres sao reels de outubro de 2026,
 * comprimidos para 540x960.
 */
const MIGRAR_VIDEOS_FLEX = [
    ['videos-flex/flex-01.mp4', 'videos-flex/flex-01.jpg', 'Entrega em até 45 dias: como funciona o prazo da Flex.'],
    ['videos-instagram/web/insta-12.mp4', 'videos-instagram/web/insta-12.jpg', 'A Castello apresenta os 5 modelos da Castello Flex.'],
    ['videos-flex/flex-02.mp4', 'videos-flex/flex-02.jpg', 'O mesmo padrão de qualidade, com liberdade para você escolher.'],
    ['videos-flex/flex-03.mp4', 'videos-flex/flex-03.jpg', 'Casas de madeira montadas e fechadas em até 45 dias.'],
];

/** contexto, pergunta, resposta, icone. As cinco da Flex saem do escopo que a Castello mandou. */
const MIGRAR_FAQ = [
    ['geral', 'Quanto tempo leva pra minha casa ficar pronta?', "**Casa Pronta:** de 90 a 120 dias.

**Castello Flex:** até 45 dias.", 'relogio'],
    ['geral', 'O que está incluso no chave na mão?', 'Sua casa sai pronta pra morar: laje aérea, elétrica, hidráulica, cerâmica, fossa, sumidouro, vidros e aberturas. Você cuida da mudança, a Castello cuida da obra, dos materiais, dos prazos e do acabamento.', 'chave'],
    ['geral', 'Posso personalizar a planta e os acabamentos da minha casa?', "**Sim! Na modalidade Casa Pronta**, você pode personalizar a distribuição dos ambientes, as dimensões e os acabamentos conforme suas necessidades, preferências e características do terreno.

Já a **Castello Flex** oferece modelos predefinidos, pensados para proporcionar praticidade, agilidade e economia na construção.", 'planta'],
    ['geral', 'A casa é resistente e dura com o tempo?', 'Construímos com materiais de qualidade e mão de obra qualificada. Somando a nossa garantia ao seu cuidado, você terá a sua casa por muitos anos.', 'escudo'],
    ['geral', 'Qual a diferença entre madeira vertical e horizontal?', 'Muda o sentido das tábuas na parede. Na vertical a parede é simples e a fiação desce por perfis aparentes por dentro da casa. Na horizontal a parede é mais resistente e pode ser duplada, com a fiação embutida em conduíte. Duplar a parede é opcional, a gente combina no projeto.', 'parede'],
    ['geral', 'A Castello vai até o meu terreno antes de construir?', 'Sim. Nossa equipe faz visita técnica em todos os terrenos, porque é nessa visita que definimos os detalhes da fundação.', 'fundacao'],
    ['geral', 'Que garantias eu tenho com a Castello?', 'Você tem a garantia prevista na Lei nº 8.078/1990, o Código de Defesa do Consumidor, além do nosso compromisso com a excelência da obra e o cumprimento do prazo combinado. Do primeiro contato ao pós-venda, é tudo diretamente com a equipe Castello.', 'garantia'],
    ['flex', 'Em quanto tempo a Castello Flex fica pronta?', 'A entrega da estrutura montada, coberta e fechada é em 45 dias, contados da assinatura e da liberação do terreno. O acabamento depois disso corre no seu ritmo.', 'relogio'],
    ['flex', 'O que exatamente vem na entrega da Castello Flex?', 'Fundação com laje aérea, estrutura de madeira montada, telhado completo, forro à nível, esquadrias instaladas, a alvenaria do banheiro no chapisco e a limpeza de obra feita. Ficam por sua conta os vidros, a elétrica, a hidráulica, o revestimento cerâmico, as louças e o balcão do banheiro, a fossa, o sumidouro e a pintura.', 'chave'],
    ['flex', 'Posso mudar a planta da Castello Flex?', 'Não. As cinco plantas da Castello Flex são padronizadas, com medidas definidas, e é isso que garante o preço e a entrega em 45 dias. Se você quer uma planta personalizada, a modalidade certa é a Casa Pronta.', 'planta'],
    ['flex', 'A madeira é a mesma das casas chave na mão?', 'É a mesma. Madeira de qualidade e prego galvanizado em toda a estrutura, montados pela mesma equipe que constrói as casas Castello desde 2014. A modalidade Flex muda o escopo da entrega, nunca o padrão da construção.', 'escudo'],
    ['flex', 'A Castello vai até o meu terreno antes de construir?', 'Sim. Nossa equipe faz visita técnica em todos os terrenos, porque é nessa visita que definimos os detalhes da fundação.', 'fundacao'],
];

/**
 * contexto, titulo, texto, imagem de origem, alt da imagem.
 * Os passos da Casa Pronta sao as 20 etapas do carrossel "Como tudo
 * acontece" do Instagram da Castello, cada uma com foto: 15 da obra real,
 * mandadas pela Castello, e 5 geradas (etapas 1, 3, 5, 18 e 20, que nao tem
 * foto de obra). Os da Flex saem como cartoes numerados, sem imagem.
 */
const MIGRAR_PASSOS = [
    ['pronta', 'Primeiro contato', '', 'passos/obra/etapa-01.webp', 'Mãos segurando o celular numa conversa, com uma caneca sobre a mesa de madeira'],
    ['pronta', 'Visita ao terreno da futura obra', '', 'passos/obra/etapa-02.webp', 'Terreno limpo e nivelado, com mata ao fundo, antes do início da obra'],
    ['pronta', 'Estudo preliminar', '', 'passos/obra/etapa-03.webp', 'Esboço da planta de uma casa sobre a mesa, com lápis, régua, trena e amostras de madeira'],
    ['pronta', 'Projeto aprovado', '', 'passos/obra/etapa-04.webp', 'Render do projeto aprovado: sobrado de madeira com varanda e sacada'],
    ['pronta', 'Contrato assinado', '', 'passos/obra/etapa-05.webp', 'Mão assinando o contrato sobre a mesa de madeira, com a chave da casa ao lado'],
    ['pronta', 'Início da obra', '', 'passos/obra/etapa-06.webp', 'Equipe marcando a fundação no terreno, com gabarito de madeira e blocos'],
    ['pronta', 'Concretagem do primeiro nível de laje', '', 'passos/obra/etapa-07.webp', 'Laje de concreto pronta sobre os pilares, com tábuas empilhadas em cima'],
    ['pronta', 'Segundo nível de laje e início da alvenaria', '', 'passos/obra/etapa-08.webp', 'Parede de tijolos subindo sobre a laje, com céu de nuvens ao fundo'],
    ['pronta', 'Início da estrutura de madeira', '', 'passos/obra/etapa-09.webp', 'Primeiras paredes de madeira levantadas ao lado da torre de alvenaria'],
    ['pronta', 'Fechamento externo', '', 'passos/obra/etapa-10.webp', 'Casa de dois pavimentos com as paredes externas de madeira fechadas'],
    ['pronta', 'Início da estrutura de cobertura', '', 'passos/obra/etapa-11.webp', 'Caibros do telhado sendo montados no alto da casa de madeira'],
    ['pronta', 'Telhamento completo', '', 'passos/obra/etapa-12.webp', 'Casa de madeira com o telhado de telhas completo e a varanda coberta'],
    ['pronta', 'Aplicação dos azulejos', '', 'passos/obra/etapa-13.webp', 'Banheiro com os porcelanatos recém-aplicados nas paredes e niveladores'],
    ['pronta', 'Aplicação dos pisos internos', '', 'passos/obra/etapa-14.webp', 'Quarto com piso cerâmico amadeirado recém-assentado e janela ampla'],
    ['pronta', 'Aplicação dos pisos externos', '', 'passos/obra/etapa-15.webp', 'Varanda coberta com piso cerâmico novo e guarda-corpo de madeira em X'],
    ['pronta', 'Instalação elétrica', '', 'passos/obra/etapa-16.webp', 'Conduítes amarelos da instalação elétrica passando pelo forro de madeira'],
    ['pronta', 'Instalação das esquadrias', '', 'passos/obra/etapa-17.webp', 'Portas e janelas de madeira com venezianas instaladas na varanda'],
    ['pronta', 'Pré-checklist e vistoria', '', 'passos/obra/etapa-18-v2.webp', 'Responsável da obra anotando na prancheta enquanto vistoria um cômodo de madeira com piso novo'],
    ['pronta', 'Início dos acabamentos em geral', '', 'passos/obra/etapa-19.webp', 'Sobrado de madeira pronto por fora, com placa da Castello na obra'],
    ['pronta', 'Checklist e entrega final da obra', '', 'passos/obra/etapa-20.webp', 'Mão segurando as chaves na frente do sobrado de madeira pronto'],
    ['flex', '1º contato com a Castello', '', 'passos/obra/etapa-01.webp', 'Mãos segurando o celular numa conversa, com uma caneca sobre a mesa de madeira'],
    ['flex', 'Escolha do projeto ideal e assinatura do contrato', '', 'passos/flex/flex-etapa-02.webp', 'Mão apontando a foto de uma casa Castello Flex ao lado da planta, enquanto outra mão assina o contrato'],
    ['flex', 'Fundação com laje aérea', '', 'passos/flex/flex-etapa-03.webp', 'Laje de concreto pronta sobre os pilares, com escoras de madeira nas bordas'],
    ['flex', 'Montagem da estrutura de madeira', '', 'passos/flex/flex-etapa-04.webp', 'Estrutura de madeira da casa montada sobre a laje, com o banheiro em blocos ao centro'],
    ['flex', 'Fechamento externo e estrutura do telhado', '', 'passos/flex/flex-etapa-05.webp', 'Paredes externas de madeira fechadas e os caibros do telhado montados'],
    ['flex', 'Cobertura concluída e casa fechada', '', 'passos/flex/flex-etapa-06.webp', 'Casa de madeira coberta com telhas cerâmicas, janela com venezianas e varanda'],
    ['flex', 'Checklist e entrega da casa', '', 'passos/obra/etapa-18-v2.webp', 'Responsável da obra anotando na prancheta enquanto vistoria um cômodo de madeira com piso novo'],
];

/**
 * chave, rotulo no painel, valor inicial, tipo.
 * A copy da Flex e do bloco de modalidades e a provisoria escrita pela frente 2
 * a partir do material do Instagram; o cliente troca pelo painel. Um trecho
 * entre asteriscos, como *chave na mao*, sai em destaque vermelho (realce()).
 */
const MIGRAR_BLOCOS = [
    ['hero_titulo', 'Título do topo', 'Sua casa.', 'texto'],
    ['hero_subtitulo', 'Subtítulo do topo', 'Do seu jeito. *No seu ritmo.*', 'texto'],
    ['modalidades_titulo', 'Título do bloco de modalidades', 'Seu sonho, suas escolhas. Duas formas de construir.', 'texto'],
    ['modalidades_texto', 'Texto do bloco de modalidades', 'Com a experiência e a qualidade Castello, você escolhe a modalidade que melhor combina com seu projeto, suas necessidades e seus planos.', 'texto_longo'],
    ['pronta_titulo', 'Título da seção Casa Pronta', 'Sua casa não precisa seguir um padrão. Ela precisa combinar com você.', 'texto'],
    ['pronta_texto', 'Texto da seção Casa Pronta', 'Na Castello, cada casa pode ser personalizada conforme suas necessidades, seu estilo de vida e as características do terreno. Da fundação aos acabamentos, cuidamos da construção para você receber seu novo lar, pronto para morar.', 'texto_longo'],
    ['pronta_prazo', 'Prazo da Casa Pronta', '90 a 120 dias', 'texto'],
    ['pronta_home_modelo', 'Projeto da foto da Casa Pronta na home', 'Projeto 7', 'texto'],
    ['flex_titulo', 'Título da seção Castello Flex', 'Sua casa de madeira ganha forma em 45 dias. Você dá o toque final.', 'texto'],
    ['flex_texto', 'Texto da seção Castello Flex', 'Com a Castello Flex, você realiza o sonho da casa de madeira com mais liberdade para planejar seu investimento. A Castello executa a fundação, a montagem da estrutura, a cobertura e o fechamento da casa. Os acabamentos ficam por sua conta, conforme suas preferências e seu orçamento.', 'texto_longo'],
    ['flex_prazo', 'Prazo da Castello Flex', '45 dias', 'texto'],
    ['flex_video', 'Vídeo explicativo da Flex', 'uploads/videos/insta-12.mp4', 'texto'],
    ['flex_video_poster', 'Capa do vídeo da Flex', 'uploads/videos/insta-12.jpg', 'texto'],
    ['flexpg_hero_titulo', 'Título do topo da página Flex', 'A sua casa de madeira *montada e fechada* no seu terreno em 45 dias.', 'texto'],
    ['flexpg_hero_texto', 'Texto do topo da página Flex', 'Na Castello Flex, você escolhe entre 5 modelos, com plantas e medidas definidas, sem possibilidade de personalização. Nós entregamos a estrutura montada e fechada, e você realiza os acabamentos no seu tempo.', 'texto_longo'],
    ['flexpg_oque_titulo', 'Título de o que é a Castello Flex', 'A qualidade da Castello, com a liberdade de construir no seu ritmo.', 'texto'],
    ['flexpg_oque_texto', 'Texto de o que é a Castello Flex', 'A Castello entrega sua casa de madeira fechada: fundação com laje aérea, estrutura de madeira, cobertura, portas e janelas. Daí em diante você escolhe quando e como fazer o acabamento.', 'texto_longo'],
    ['flexpg_depois_titulo', 'Título de o que fica por sua conta', 'O que fica por sua conta', 'texto'],
    ['flexpg_depois_texto', 'Texto de o que fica por sua conta', 'Daqui pra frente a casa é sua e o ritmo é seu. Você contrata quem quiser, na ordem que quiser, conforme seu orçamento.', 'texto_longo'],
    ['flexpg_catalogo_nota', 'Nota abaixo do catálogo Flex', 'Valores de setembro de 2026, para a casa montada no seu terreno. O orçamento final considera as condições do seu terreno e a distância até Tubarão.', 'texto'],
    ['flexpg_cta_titulo', 'Título da faixa de orçamento da Flex', 'A casa que você tanto sonhou começa com uma escolha.', 'texto'],
    ['flexpg_cta_texto', 'Texto da faixa de orçamento da Flex', 'O sonho de ter seu próprio lar está mais perto do que você imagina. Conheça a Castello Flex.', 'texto_longo'],
];

/**
 * Copia um arquivo que ja esta no site para dentro de uploads/ e devolve o
 * caminho relativo ao public_html que vai ser gravado no banco.
 */
function migrar_copiar(string $origem, string $pasta): string
{
    $de = __DIR__ . '/' . $origem;
    if (!is_file($de)) {
        throw new RuntimeException('arquivo de origem nao encontrado: ' . $origem);
    }

    $destino = CASTELLO_UPLOADS . '/' . $pasta;
    if (!is_dir($destino) && !mkdir($destino, 0775, true) && !is_dir($destino)) {
        throw new RuntimeException('nao consegui criar a pasta ' . $destino);
    }

    $nome = basename($origem);
    $para = $destino . '/' . $nome;
    if (!is_file($para) && !copy($de, $para)) {
        throw new RuntimeException('nao consegui copiar ' . $origem . ' para ' . $para);
    }

    return 'uploads/' . $pasta . '/' . $nome;
}

/**
 * Insere um modelo de MIGRAR_MODELOS, copiando a foto e a planta para
 * uploads/. Os ambientes vao para o banco um por linha, como o painel edita.
 */
function migrar_inserir_modelo(array $m, int $ordem): void
{
    db()->prepare(
        'INSERT INTO modelos (modalidade, nome, area, parede, preco, prazo, descricao, foto, foto_alt, itens, planta, foto_extra, destaque, ativo, ordem)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 1, ?)'
    )->execute([
        $m['modalidade'], $m['nome'], $m['area'], $m['parede'], $m['preco'], $m['prazo'], $m['descricao'],
        migrar_copiar($m['foto'], 'modelos'), $m['alt'], implode("\n", $m['itens']),
        $m['planta'] !== '' ? migrar_copiar($m['planta'], 'modelos') : '', '', $ordem,
    ]);
}

/** Insere os videos da galeria da Flex, copiando arquivo e capa para uploads/. */
function migrar_inserir_videos_flex(): void
{
    $st = db()->prepare("INSERT INTO videos (arquivo, poster, legenda, contexto, ativo, ordem) VALUES (?, ?, ?, 'flex', 1, ?)");
    foreach (MIGRAR_VIDEOS_FLEX as $i => [$arquivo, $poster, $legenda]) {
        $st->execute([migrar_copiar($arquivo, 'videos'), migrar_copiar($poster, 'videos'), $legenda, $i + 1]);
    }
}

/** True quando a tabela ainda nao tem nenhuma linha. */
function migrar_vazia(string $tabela): bool
{
    return (int) db()->query('SELECT COUNT(*) FROM ' . $tabela)->fetchColumn() === 0;
}

/**
 * Carrega o conteudo real. Cada tabela so e preenchida quando esta vazia.
 *
 * @return array<string,int> tabela para quantidade inserida
 */
function migrar(): array
{
    $conta = ['modelos' => 0, 'portfolio' => 0, 'avaliacoes' => 0, 'videos' => 0, 'faq' => 0, 'passos' => 0, 'blocos' => 0];

    if (migrar_vazia('modelos')) {
        foreach (MIGRAR_MODELOS as $i => $m) {
            migrar_inserir_modelo($m, $i + 1);
            $conta['modelos']++;
        }
    }

    if (migrar_vazia('portfolio')) {
        $st = db()->prepare(
            'INSERT INTO portfolio (titulo, categoria, foto, foto_alt, ativo, ordem) VALUES (?, ?, ?, ?, 1, ?)'
        );
        foreach (MIGRAR_PORTFOLIO as $i => [$titulo, $categoria, $foto, $alt]) {
            $st->execute([$titulo, $categoria, migrar_copiar($foto, 'portfolio'), $alt, $i + 1]);
            $conta['portfolio']++;
        }
    }

    if (migrar_vazia('avaliacoes')) {
        $st = db()->prepare(
            'INSERT INTO avaliacoes (nome, texto, estrelas, ativo, ordem) VALUES (?, ?, 5, 1, ?)'
        );
        foreach (MIGRAR_AVALIACOES as $i => [$nome, $texto]) {
            $st->execute([$nome, $texto, $i + 1]);
            $conta['avaliacoes']++;
        }
    }

    if (migrar_vazia('videos')) {
        $st = db()->prepare(
            'INSERT INTO videos (arquivo, poster, legenda, ativo, ordem) VALUES (?, ?, ?, 1, ?)'
        );
        for ($i = 1; $i <= 11; $i++) {
            $base = sprintf('videos-instagram/web/insta-%02d', $i);
            $st->execute([
                migrar_copiar($base . '.mp4', 'videos'),
                migrar_copiar($base . '.jpg', 'videos'),
                '',
                $i,
            ]);
            $conta['videos']++;
        }
    }

    if ((int) db()->query("SELECT COUNT(*) FROM videos WHERE contexto = 'flex'")->fetchColumn() === 0) {
        migrar_inserir_videos_flex();
        $conta['videos'] += count(MIGRAR_VIDEOS_FLEX);
    }

    if (migrar_vazia('faq')) {
        $st = db()->prepare(
            'INSERT INTO faq (pergunta, resposta, icone, contexto, ativo, ordem) VALUES (?, ?, ?, ?, 1, ?)'
        );
        $ordem = [];
        foreach (MIGRAR_FAQ as [$contexto, $pergunta, $resposta, $icone]) {
            $ordem[$contexto] = ($ordem[$contexto] ?? 0) + 1;
            $st->execute([$pergunta, $resposta, $icone, $contexto, $ordem[$contexto]]);
            $conta['faq']++;
        }
    }

    if (migrar_vazia('passos')) {
        $st = db()->prepare(
            'INSERT INTO passos (contexto, titulo, texto, imagem, imagem_alt, ativo, ordem)
             VALUES (?, ?, ?, ?, ?, 1, ?)'
        );
        $ordem = [];
        foreach (MIGRAR_PASSOS as [$contexto, $titulo, $texto, $imagem, $alt]) {
            $ordem[$contexto] = ($ordem[$contexto] ?? 0) + 1;
            $st->execute([$contexto, $titulo, $texto, $imagem !== '' ? migrar_copiar($imagem, 'passos') : '', $alt, $ordem[$contexto]]);
            $conta['passos']++;
        }
    }

    if (migrar_vazia('blocos')) {
        $st = db()->prepare('INSERT INTO blocos (chave, rotulo, valor, tipo) VALUES (?, ?, ?, ?)');
        foreach (MIGRAR_BLOCOS as [$chave, $rotulo, $valor, $tipo]) {
            $st->execute([$chave, $rotulo, $valor, $tipo]);
            $conta['blocos']++;
        }
    }

    return $conta;
}

/**
 * Blocos que a rodada de outubro de 2026 reescreveu. Os demais so ganham o
 * rotulo novo (Castelo Flex virou Castello Flex), o texto fica como estava.
 */
const MIGRAR_BLOCOS_2026_10 = [
    'hero_titulo', 'hero_subtitulo', 'modalidades_titulo', 'modalidades_texto', 'pronta_titulo', 'pronta_texto',
    'flex_titulo', 'flexpg_hero_titulo', 'flexpg_hero_texto', 'flexpg_oque_titulo', 'flexpg_oque_texto',
    'flexpg_depois_texto', 'flexpg_cta_titulo', 'flexpg_cta_texto',
];

/**
 * Leva um banco que ja existe para o conteudo da rodada de outubro de 2026.
 * Roda uma vez so (marca config.conteudo_2026_10) e nunca apaga nada: o que
 * sai do site e desativado, e o painel consegue reativar.
 *
 *   - Castelo Flex vira Castello Flex em todo texto do banco;
 *   - os textos de MIGRAR_BLOCOS_2026_10 recebem o valor novo;
 *   - os quatro modelos antigos da Casa Pronta saem e entram os 12 do catalogo;
 *   - os modelos Flex ganham ambientes, planta baixa e descricao;
 *   - os cinco passos antigos da Casa Pronta saem e entram as 20 etapas;
 *   - as respostas da FAQ voltam ao texto de MIGRAR_FAQ e o primeiro passo
 *     da Flex deixa de prometer planta ajustada.
 *
 * @return array<int,string> o que foi feito, uma linha por item
 */
function migrar_atualizar_2026_10(): array
{
    if (config_ler('conteudo_2026_10') === '1') {
        return ['ja aplicado antes, nada mudou'];
    }

    $pdo = db();
    $log = [];
    $pdo->beginTransaction();
    try {
        foreach (['faq' => ['pergunta', 'resposta'], 'passos' => ['titulo', 'texto'], 'blocos' => ['rotulo', 'valor'],
                  'modelos' => ['nome', 'foto_alt', 'descricao'], 'videos' => ['legenda']] as $tabela => $colunas) {
            foreach ($colunas as $coluna) {
                $n = $pdo->exec("UPDATE {$tabela} SET {$coluna} = REPLACE({$coluna}, 'Castelo Flex', 'Castello Flex') WHERE {$coluna} LIKE '%Castelo Flex%'");
                if ($n > 0) {
                    $log[] = "{$tabela}.{$coluna}: Castelo Flex trocado em {$n}";
                }
            }
        }

        $grava = $pdo->prepare(
            'INSERT INTO blocos (chave, rotulo, valor, tipo) VALUES (?, ?, ?, ?)
             ON CONFLICT(chave) DO UPDATE SET valor = excluded.valor, rotulo = excluded.rotulo'
        );
        foreach (MIGRAR_BLOCOS as [$chave, $rotulo, $valor, $tipo]) {
            if (in_array($chave, MIGRAR_BLOCOS_2026_10, true)) {
                $grava->execute([$chave, $rotulo, $valor, $tipo]);
                $log[] = 'bloco ' . $chave;
            }
        }

        $n = $pdo->exec("UPDATE modelos SET ativo = 0 WHERE modalidade = 'pronta' AND nome NOT LIKE 'Projeto %'");
        $log[] = "modelos antigos da Casa Pronta desativados: {$n}";
        $existe = $pdo->prepare('SELECT id FROM modelos WHERE modalidade = ? AND nome = ?');
        $flex = $pdo->prepare('UPDATE modelos SET itens = ?, planta = ?, descricao = ?, foto_alt = ? WHERE id = ?');
        $ordem = 0;
        foreach (MIGRAR_MODELOS as $m) {
            $existe->execute([$m['modalidade'], $m['nome']]);
            $id = $existe->fetchColumn();
            if ($m['modalidade'] === 'pronta') {
                $ordem++;
                if ($id === false) {
                    migrar_inserir_modelo($m, $ordem);
                    $log[] = 'modelo novo ' . $m['nome'];
                }
            } elseif ($id !== false) {
                $planta = $m['planta'] !== '' ? migrar_copiar($m['planta'], 'modelos') : '';
                $flex->execute([implode("\n", $m['itens']), $planta, $m['descricao'], $m['alt'], (int) $id]);
                $log[] = 'modelo atualizado ' . $m['nome'];
            }
        }

        $n = $pdo->exec("UPDATE passos SET ativo = 0 WHERE contexto = 'pronta' AND imagem <> ''");
        $log[] = "passos antigos da Casa Pronta desativados: {$n}";
        $tem = (int) $pdo->query("SELECT COUNT(*) FROM passos WHERE contexto = 'pronta' AND ativo = 1")->fetchColumn();
        if ($tem === 0) {
            $novo = $pdo->prepare("INSERT INTO passos (contexto, titulo, texto, imagem, imagem_alt, ativo, ordem) VALUES ('pronta', ?, ?, '', '', 1, ?)");
            $ordem = 0;
            foreach (MIGRAR_PASSOS as [$contexto, $titulo, $texto]) {
                if ($contexto === 'pronta') {
                    $novo->execute([$titulo, $texto, ++$ordem]);
                }
            }
            $log[] = "etapas da linha do tempo inseridas: {$ordem}";
        }

        $faq = $pdo->prepare('UPDATE faq SET resposta = ? WHERE contexto = ? AND pergunta = ? AND resposta <> ?');
        foreach (MIGRAR_FAQ as [$contexto, $pergunta, $resposta]) {
            $faq->execute([$resposta, $contexto, $pergunta, $resposta]);
            if ($faq->rowCount() > 0) {
                $log[] = 'faq: ' . $pergunta;
            }
        }

        config_gravar('conteudo_2026_10', '1');
        $pdo->commit();
    } catch (Throwable $falha) {
        $pdo->rollBack();
        throw $falha;
    }

    return $log;
}

/**
 * Segunda parte da rodada de outubro de 2026: as fotos das etapas da obra,
 * mandadas pela Castello, e o titulo novo da etapa 19. Uma vez so (marca
 * config.conteudo_2026_10_fotos); so preenche foto onde a etapa nao tem.
 *
 * @return array<int,string>
 */
function migrar_atualizar_2026_10_fotos(): array
{
    if (config_ler('conteudo_2026_10_fotos') === '1') {
        return ['fotos das etapas: ja aplicado antes'];
    }
    $pdo = db();
    $log = [];
    $pdo->beginTransaction();
    try {
        $n = $pdo->exec("UPDATE passos SET titulo = 'Início dos acabamentos em geral' WHERE contexto = 'pronta' AND titulo = 'Finalização dos acabamentos'");
        $log[] = "etapa 19 renomeada: {$n}";
        $foto = $pdo->prepare("UPDATE passos SET imagem = ?, imagem_alt = ? WHERE contexto = 'pronta' AND titulo = ? AND ativo = 1 AND (imagem IS NULL OR imagem = '')");
        foreach (MIGRAR_PASSOS as [$contexto, $titulo, , $imagem, $alt]) {
            if ($contexto !== 'pronta' || $imagem === '') {
                continue;
            }
            $foto->execute([migrar_copiar($imagem, 'passos'), $alt, $titulo]);
            $log[] = 'foto ' . $titulo . ': ' . $foto->rowCount();
        }
        // so ficam as etapas com foto; as outras sao desativadas, nao apagadas
        $n = $pdo->exec("UPDATE passos SET ativo = 0 WHERE contexto = 'pronta' AND (imagem IS NULL OR imagem = '')");
        $log[] = "etapas sem foto desativadas: {$n}";
        config_gravar('conteudo_2026_10_fotos', '1');
        $pdo->commit();
    } catch (Throwable $falha) {
        $pdo->rollBack();
        throw $falha;
    }
    return $log;
}

/**
 * Terceira parte da rodada de outubro de 2026: os videos da galeria da Flex.
 * So entra quando ainda nao ha video com contexto flex (o cliente pode ter
 * cadastrado pelo painel).
 *
 * @return array<int,string>
 */
function migrar_atualizar_2026_10_videos(): array
{
    $tem = (int) db()->query("SELECT COUNT(*) FROM videos WHERE contexto = 'flex'")->fetchColumn();
    if ($tem > 0) {
        return ["videos da Flex: ja existem {$tem}, nada mudou"];
    }
    migrar_inserir_videos_flex();
    return ['videos da Flex inseridos: ' . count(MIGRAR_VIDEOS_FLEX)];
}

/**
 * As 5 etapas que nao tinham foto (1, 3, 5, 18 e 20) ganham as imagens
 * geradas e voltam a aparecer. Pode rodar sempre: so preenche foto vazia.
 *
 * @return array<int,string>
 */
function migrar_atualizar_2026_10_etapas(): array
{
    $log = [];
    // preenche a foto vazia ou troca uma versao gerada antiga (etapa-NN.webp)
    $st = db()->prepare("UPDATE passos SET imagem = ?, imagem_alt = ?, ativo = 1 WHERE contexto = 'pronta' AND titulo = ? AND (imagem IS NULL OR imagem = '' OR (imagem LIKE 'uploads/passos/etapa-__.webp' AND imagem <> ?))");
    foreach (MIGRAR_PASSOS as [$contexto, $titulo, , $imagem, $alt]) {
        if ($contexto !== 'pronta' || !preg_match('/etapa-(01|03|05|18|20)[.-]/', $imagem)) {
            continue;
        }
        $destino = migrar_copiar($imagem, 'passos');
        $st->execute([$destino, $alt, $titulo, $destino]);
        $log[] = 'etapa ' . $titulo . ': ' . $st->rowCount();
    }
    return $log;
}

/**
 * Textos da secao Castello Flex da home que o cliente mandou em 2026-10-15
 * (titulo e texto). Grava o valor de MIGRAR_BLOCOS por cima; rodar de novo
 * nao muda nada.
 *
 * @return array<int,string>
 */
function migrar_atualizar_2026_10_textos(): array
{
    $log = [];
    $st = db()->prepare('UPDATE blocos SET valor = ? WHERE chave = ? AND valor <> ?');
    foreach (MIGRAR_BLOCOS as [$chave, , $valor]) {
        if (in_array($chave, ['flex_titulo', 'flex_texto', 'hero_titulo', 'hero_subtitulo'], true)) {
            $st->execute([$valor, $chave, $valor]);
            $log[] = 'bloco ' . $chave . ': ' . $st->rowCount();
        }
    }
    // resposta nova da FAQ de personalizacao (2026-10-15)
    foreach (MIGRAR_FAQ as [$contexto, $pergunta, $resposta]) {
        if ($pergunta === 'Posso personalizar a planta e os acabamentos da minha casa?') {
            $faq = db()->prepare("UPDATE faq SET pergunta = ?, resposta = ? WHERE contexto = ? AND pergunta IN ('Posso personalizar a planta e os acabamentos?', ?) AND resposta <> ?");
            $faq->execute([$pergunta, $resposta, $contexto, $pergunta, $resposta]);
            $log[] = 'faq personalizacao: ' . $faq->rowCount();
        }
    }
    return $log;
}

/**
 * Passo a passo da Castello Flex com foto (2026-10-15): os cinco cartoes sem
 * imagem saem (ficam desativados) e entram as sete etapas de MIGRAR_PASSOS.
 * Roda uma vez so: se a primeira etapa nova ja existe, nada muda.
 *
 * @return array<int,string>
 */
function migrar_atualizar_2026_10_passos_flex(): array
{
    $pdo = db();
    $novos = array_values(array_filter(MIGRAR_PASSOS, fn ($p) => $p[0] === 'flex'));
    $existe = $pdo->prepare("SELECT COUNT(*) FROM passos WHERE contexto = 'flex' AND titulo = ?");
    $existe->execute([$novos[0][1]]);
    if ((int) $existe->fetchColumn() > 0) {
        return ['passos da Flex: ja aplicado antes'];
    }
    $pdo->beginTransaction();
    try {
        $n = $pdo->exec("UPDATE passos SET ativo = 0 WHERE contexto = 'flex'");
        $st = $pdo->prepare("INSERT INTO passos (contexto, titulo, texto, imagem, imagem_alt, ativo, ordem) VALUES ('flex', ?, ?, ?, ?, 1, ?)");
        foreach ($novos as $i => [, $titulo, $texto, $imagem, $alt]) {
            $st->execute([$titulo, $texto, migrar_copiar($imagem, 'passos'), $alt, $i + 1]);
        }
        $pdo->commit();
    } catch (Throwable $falha) {
        $pdo->rollBack();
        throw $falha;
    }
    return ["passos antigos da Flex desativados: {$n}", 'etapas da Flex inseridas: ' . count($novos)];
}

/**
 * FAQ geral revisada pelo cliente em 2026-10-16: prazos das duas modalidades,
 * resistencia, visita ao terreno e garantia pelo CDC; a pergunta do conforto
 * termico sai (fica desativada). Rodar de novo nao muda nada.
 *
 * @return array<int,string>
 */
function migrar_atualizar_2026_10_faq(): array
{
    $pdo = db();
    $log = [];
    $n = $pdo->exec("UPDATE faq SET ativo = 0 WHERE contexto = 'geral' AND pergunta = 'Casa de madeira é confortável o ano todo?' AND ativo = 1");
    $log[] = 'faq conforto desativada: ' . $n;
    $troca = $pdo->prepare("UPDATE faq SET pergunta = ? WHERE contexto = 'geral' AND pergunta = ?");
    $troca->execute(['A Castello vai até o meu terreno antes de construir?', 'Vocês cuidam da fundação e do terreno?']);
    $resp = $pdo->prepare("UPDATE faq SET resposta = ? WHERE contexto = 'geral' AND pergunta = ? AND resposta <> ?");
    foreach (MIGRAR_FAQ as [$contexto, $pergunta, $resposta]) {
        if ($contexto === 'geral' && in_array($pergunta, ['Quanto tempo leva pra minha casa ficar pronta?', 'A casa é resistente e dura com o tempo?',
                'A Castello vai até o meu terreno antes de construir?', 'Que garantias eu tenho com a Castello?'], true)) {
            $resp->execute([$resposta, $pergunta, $resposta]);
            $log[] = 'faq ' . $pergunta . ': ' . $resp->rowCount();
        }
    }
    return $log;
}

/**
 * Pagina Castello Flex revisada em 2026-10-16: texto de o que e a Flex, duas
 * perguntas da FAQ Flex e o video do prazo (flex-01) como o primeiro da
 * galeria. Rodar de novo nao muda nada.
 *
 * @return array<int,string>
 */
function migrar_atualizar_2026_10_flex(): array
{
    $pdo = db();
    $log = [];
    $bloco = $pdo->prepare('UPDATE blocos SET valor = ? WHERE chave = ? AND valor <> ?');
    foreach (MIGRAR_BLOCOS as [$chave, , $valor]) {
        if ($chave === 'flexpg_oque_texto') {
            $bloco->execute([$valor, $chave, $valor]);
            $log[] = 'bloco ' . $chave . ': ' . $bloco->rowCount();
        }
    }
    $pdo->prepare("UPDATE faq SET pergunta = ? WHERE contexto = 'flex' AND pergunta = ?")
        ->execute(['O que exatamente vem na entrega da Castello Flex?', 'O que exatamente vem na entrega da Flex?']);
    // 2026-10-21: a pergunta do terreno da Flex fica igual a da Casa Pronta
    $pdo->prepare("UPDATE faq SET pergunta = ? WHERE contexto = 'flex' AND pergunta = ?")
        ->execute(['A Castello vai até o meu terreno antes de construir?', 'Vocês cuidam da fundação e do terreno?']);
    $faq = $pdo->prepare("UPDATE faq SET resposta = ? WHERE contexto = 'flex' AND pergunta = ? AND resposta <> ?");
    foreach (MIGRAR_FAQ as [$contexto, $pergunta, $resposta]) {
        if ($contexto === 'flex') {
            $faq->execute([$resposta, $pergunta, $resposta]);
            if ($faq->rowCount() > 0) {
                $log[] = 'faq flex: ' . $pergunta;
            }
        }
    }
    $ordem = $pdo->prepare("UPDATE videos SET ordem = ? WHERE contexto = 'flex' AND arquivo = ?");
    foreach (MIGRAR_VIDEOS_FLEX as $i => [$arquivo]) {
        $ordem->execute([$i + 1, 'uploads/videos/' . basename($arquivo)]);
    }
    $log[] = 'videos da Flex reordenados, flex-01 primeiro';
    return $log;
}

/** Cria o unico usuario do painel. Devolve false se o login ja existia. */
function migrar_usuario(string $login, string $senha, string $nome = 'Castello Casas de Madeira'): bool
{
    $st = db()->prepare('SELECT COUNT(*) FROM usuarios WHERE login = ?');
    $st->execute([$login]);
    if ((int) $st->fetchColumn() > 0) {
        return false;
    }

    db()->prepare('INSERT INTO usuarios (login, senha_hash, nome, criado_em) VALUES (?, ?, ?, ?)')
        ->execute([$login, password_hash($senha, PASSWORD_BCRYPT), $nome, agora()]);

    return true;
}

/**
 * Garante que config/segredos.php exista e devolve a chave de migracao.
 *
 * O usuario de FTP da hospedagem esta preso ao public_html e nao alcanca a
 * pasta de configuracao, entao ninguem consegue subir esse arquivo a mao.
 * Quem o cria e o PHP, na primeira instalacao.
 */
function migrar_segredos(): string
{
    $arquivo = CASTELLO_CONFIG . '/segredos.php';

    if (is_file($arquivo)) {
        return defined('CASTELLO_MIGRAR_CHAVE') ? (string) CASTELLO_MIGRAR_CHAVE : '';
    }

    $chave = bin2hex(random_bytes(16));
    $conteudo = "<?php\n"
        . "// Gerado por migrar.php na instalacao. Nao vai para o git.\n"
        . "define('CASTELLO_MIGRAR_CHAVE', '" . $chave . "');\n";

    if (file_put_contents($arquivo, $conteudo) === false) {
        return '';
    }
    @chmod($arquivo, 0600);

    return $chave;
}

/**
 * Ponto de entrada. Roda so quando migrar.php e o script chamado.
 *
 * Pela web o acesso e liberado em dois casos: banco ainda vazio, que e a
 * instalacao, ou chave certa em ?chave=. Depois da primeira instalacao existe
 * usuario cadastrado, entao a porta fecha sozinha. O passo de deploy manda
 * apagar este arquivo do servidor assim que a migracao terminar.
 */
function migrar_entrada(): void
{
    $instalando = migrar_vazia('usuarios') && migrar_vazia('modelos');

    if (PHP_SAPI !== 'cli') {
        $esperada = defined('CASTELLO_MIGRAR_CHAVE') ? (string) CASTELLO_MIGRAR_CHAVE : '';
        $recebida = (string) ($_GET['chave'] ?? '');
        $liberado = $instalando || ($esperada !== '' && hash_equals($esperada, $recebida));

        if (!$liberado) {
            http_response_code(404);
            echo 'nao encontrado';
            return;
        }
        header('Content-Type: text/plain; charset=utf-8');
    }

    $chave = migrar_segredos();

    $conta = migrar();
    foreach ($conta as $tabela => $quantidade) {
        echo str_pad($tabela, 12) . ($quantidade > 0 ? $quantidade . ' inseridos' : 'ja tinha conteudo, nao mexi') . "\n";
    }

    $senha = bin2hex(random_bytes(6));
    if (migrar_usuario('castello', $senha)) {
        echo "\nacesso ao painel criado\n";
        echo "  login: castello\n";
        echo "  senha: $senha\n";
        echo "anote agora, esta senha nao aparece de novo. Troque em /painel na tela Trocar senha.\n";
    } else {
        echo "\nacesso ao painel ja existia, senha mantida\n";
    }

    if ($chave !== '') {
        echo "\nchave de migracao gravada em config/segredos.php\n";
        echo "  chave: $chave\n";
    }

    echo "\napague migrar.php do servidor agora que a instalacao terminou.\n";
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    migrar_entrada();
}
