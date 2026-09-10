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
 * modalidade, nome, area, parede, preco, prazo, foto de origem, alt, destaque.
 * Os tres Flex sao provisorios, com preco vazio (o site imprime Sob consulta)
 * e foto emprestada das casas prontas, ate o cliente mandar o material.
 */
const MIGRAR_MODELOS = [
    ['pronta', 'Compacta', '39,00 m²', 'Parede vertical', '69.900', '90 a 120 dias', 'fotos-casas/casa4.png', 'Casa de madeira compacta de dois pavimentos da Castello', 0],
    ['pronta', 'Conforto', '42,75 m²', 'Parede dupla', '79.988', '90 a 120 dias', 'fotos-casas/casa2.png', 'Casa de madeira Castello térrea com telhado de telhas e varanda', 0],
    ['pronta', 'Família', '51,00 m²', 'Parede dupla', '87.997', '90 a 120 dias', 'fotos-casas/casa5.png', 'Casa de madeira Castello com varanda ampla em volta e jardim', 1],
    ['pronta', 'Ampla', '59,75 m²', 'Parede dupla', '97.776', '90 a 120 dias', 'fotos-casas/casa3.png', 'Sobrado de madeira Castello à beira da água com vista para a ponte', 0],
    ['flex', 'Castelo Flex 36', '36,00 m²', '', '', '45 dias', 'fotos-casas/casa4.png', 'Casa de madeira Castello compacta de dois pavimentos', 0],
    ['flex', 'Castelo Flex 48', '48,00 m²', '', '', '45 dias', 'fotos-casas/casa2.png', 'Casa de madeira Castello térrea com telhado de telhas e varanda', 0],
    ['flex', 'Castelo Flex 60', '60,00 m²', '', '', '45 dias', 'fotos-casas/casa6.png', 'Casa de madeira Castello térrea com varanda ampla e garagem coberta', 0],
];

/** titulo, categoria, foto de origem, alt */
const MIGRAR_PORTFOLIO = [
    ['Sobrado à beira da água', 'Beira da água', 'fotos-casas/casa3.png', 'Sobrado de madeira à beira da água com vista para a ponte'],
    ['Sobrado com sacada', 'Dois pavimentos', 'fotos-casas/casa7.png', 'Sobrado de madeira com sacada e fachada de réguas'],
    ['Varanda ampla e garagem', 'Térrea', 'fotos-casas/casa6.png', 'Casa de madeira térrea com varanda ampla e garagem coberta'],
    ['Casa de campo com varanda', 'No campo', 'fotos-casas/casa-8.png', 'Casa de madeira de campo com varanda e cerca branca'],
    ['Varanda com pergolado', 'Área externa', 'fotos-casas/casa1.png', 'Casa de madeira com pergolado e varanda ao sol'],
    ['Chalé com varanda', 'Na natureza', 'fotos-casas/casa5.png', 'Casa de madeira com varanda em volta e mata ao fundo'],
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

/** contexto, pergunta, resposta, icone. As cinco da Flex sao provisorias. */
const MIGRAR_FAQ = [
    ['geral', 'Quanto tempo leva pra minha casa ficar pronta?', 'Entre 90 e 120 dias, do projeto à chave na mão. Enquanto a obra convencional se arrasta por anos, sua casa de madeira é montada de forma rápida e organizada, com o prazo combinado em contrato.', 'relogio'],
    ['geral', 'O que está incluso no chave na mão?', 'Sua casa sai pronta pra morar: laje aérea, elétrica, hidráulica, cerâmica, fossa, sumidouro, vidros e aberturas. Você cuida da mudança, a Castello cuida de projeto, materiais, prazos e acabamento.', 'chave'],
    ['geral', 'Posso personalizar a planta e os acabamentos?', 'Sim, 100% personalizável. Planta, acabamentos, revestimentos, janelas, portas e piso são escolhidos do seu jeito. Cada projeto Castello é exclusivo e desenhado pra sua rotina e o seu gosto.', 'planta'],
    ['geral', 'Casa de madeira é confortável o ano todo?', 'É um dos maiores diferenciais. A madeira mantém o ambiente fresco no calor e aconchegante no frio, com um conforto térmico bem acima da alvenaria comum em todas as estações.', 'clima'],
    ['geral', 'A casa é resistente e dura com o tempo?', 'Construímos com madeira de qualidade e prego galvanizado em toda a estrutura, com equipe experiente na obra todo dia. Bem cuidada, a casa atravessa gerações e ainda valoriza como patrimônio.', 'escudo'],
    ['geral', 'Vocês cuidam da fundação e do terreno?', 'A fundação faz parte do processo. A gente avalia o seu terreno e prepara a base certa pra receber a estrutura, com técnica e segurança em cada etapa, do primeiro passo até a chave na mão.', 'fundacao'],
    ['geral', 'Que garantias eu tenho com a Castello?', 'Você tem a garantia da construção, o compromisso com a excelência da obra e o cumprimento do prazo combinado. Do primeiro contato ao pós-venda, é tudo com uma empresa só.', 'garantia'],
    ['flex', 'Em quanto tempo a Castelo Flex fica pronta?', 'A entrega da estrutura montada, coberta e fechada é em 45 dias, contados da assinatura e da liberação do terreno. O acabamento depois disso corre no seu ritmo, sem prazo de obra em cima de você.', 'relogio'],
    ['flex', 'O que exatamente vem na entrega da Flex?', 'Fundação preparada, estrutura de madeira montada, telhado completo, portas e janelas instaladas. A casa é entregue fechada e trancada no seu terreno. Elétrica, hidráulica, revestimento, piso e pintura ficam por sua conta.', 'chave'],
    ['flex', 'Posso mudar a planta da Castelo Flex?', 'Sim. A planta é ajustada ao seu terreno e à sua rotina antes da produção. Depois que a estrutura entra em fabricação, as mudanças passam a ser de acabamento, que é justamente a parte que fica com você.', 'planta'],
    ['flex', 'A madeira é a mesma das casas chave na mão?', 'É a mesma. Madeira de qualidade e prego galvanizado em toda a estrutura, montados pela mesma equipe que constrói as casas Castello há 12 anos. A Flex muda o escopo da entrega, nunca o padrão da construção.', 'escudo'],
    ['flex', 'Vocês cuidam da fundação e do terreno?', 'A fundação faz parte da entrega da Flex. A gente avalia o seu terreno e prepara a base certa pra receber a estrutura, com técnica e segurança, antes de a casa subir.', 'fundacao'],
];

/**
 * contexto, titulo, texto, imagem de origem, alt da imagem.
 * Os passos da Flex nao tem imagem: na pagina Flex eles saem como cartoes
 * numerados, nao como o scrollytelling da Casa Pronta.
 */
const MIGRAR_PASSOS = [
    ['pronta', 'Conversa e projeto', 'Entendemos seu sonho, seu terreno e seu orçamento, e desenhamos a planta ideal pra você.', 'passos/passo-1.jpg', 'Maquete do projeto da casa de madeira sobre a planta'],
    ['pronta', 'Fundação', 'Preparamos a base da casa com técnica e segurança, prontos para receber a estrutura.', 'passos/passo-2.jpg', 'Início da estrutura de madeira sobre a fundação'],
    ['pronta', 'Estrutura e montagem', 'Montamos a casa com madeira de qualidade e prego galvanizado, no padrão Castello.', 'passos/passo-3.jpg', 'Estrutura e montagem da casa de madeira'],
    ['pronta', 'Acabamento', 'Elétrica, hidráulica, revestimentos, vidros e os detalhes finos que fazem do seu jeito.', 'passos/passo-4.jpg', 'Equipe no acabamento do telhado e fachada da casa'],
    ['pronta', 'Chave na mão', 'Você recebe a casa pronta pra morar, completa, em 90 a 120 dias.', 'passos/passo-5.png', 'Chaves da casa de madeira pronta, chave na mão'],
    ['flex', 'Projeto e modelo', 'Você escolhe o modelo Flex e a gente ajusta a planta ao seu terreno e ao seu orçamento.', '', ''],
    ['flex', 'Fundação', 'A Castello prepara a base da casa, no padrão que a estrutura de madeira exige.', '', ''],
    ['flex', 'Estrutura montada', 'Paredes e estrutura montadas com madeira de qualidade e prego galvanizado em toda a obra.', '', ''],
    ['flex', 'Cobertura', 'Telhado completo, com a casa protegida da chuva e do sol desde o primeiro dia.', '', ''],
    ['flex', 'Portas e janelas', 'Aberturas instaladas e a casa entregue fechada e trancada no seu terreno, em 45 dias.', '', ''],
];

/**
 * chave, rotulo no painel, valor inicial, tipo.
 * A copy da Flex e do bloco de modalidades e a provisoria escrita pela frente 2
 * a partir do material do Instagram; o cliente troca pelo painel. Um trecho
 * entre asteriscos, como *chave na mao*, sai em destaque vermelho (realce()).
 */
const MIGRAR_BLOCOS = [
    ['hero_titulo', 'Título do topo', 'A casa dos seus sonhos', 'texto'],
    ['hero_subtitulo', 'Subtítulo do topo', 'pronta pra morar, *chave na mão*', 'texto'],
    ['modalidades_titulo', 'Título do bloco de modalidades', 'Escolha como a sua casa sai do papel.', 'texto'],
    ['modalidades_texto', 'Texto do bloco de modalidades', 'A Castello entrega a casa completa, pronta pra morar, e agora entrega também a casa semipronta, para quem quer a estrutura no terreno e o acabamento no próprio ritmo.', 'texto_longo'],
    ['pronta_titulo', 'Título da seção Casa Pronta', 'Escolha o tamanho. A gente entrega completa.', 'texto'],
    ['pronta_texto', 'Texto da seção Casa Pronta', 'Todos os modelos saem prontos pra morar: laje aérea, elétrica, hidráulica, cerâmica, fossa, sumidouro, vidros e aberturas.', 'texto_longo'],
    ['pronta_prazo', 'Prazo da Casa Pronta', '90 a 120 dias', 'texto'],
    ['flex_titulo', 'Título da seção Castelo Flex', 'Castelo Flex: a casa semipronta no seu terreno em 45 dias.', 'texto'],
    ['flex_texto', 'Texto da seção Castelo Flex', 'Você recebe a casa de madeira estruturada, coberta e fechada. O acabamento fica no seu ritmo e no seu bolso, com a mesma madeira e o mesmo padrão de montagem das casas chave na mão.', 'texto_longo'],
    ['flex_prazo', 'Prazo da Castelo Flex', '45 dias', 'texto'],
    ['flex_video', 'Vídeo explicativo da Flex', 'uploads/videos/insta-04.mp4', 'texto'],
    ['flex_video_poster', 'Capa do vídeo da Flex', 'uploads/videos/insta-04.jpg', 'texto'],
    ['flexpg_hero_titulo', 'Título do topo da página Flex', 'A casa de madeira *montada e fechada* no seu terreno em 45 dias.', 'texto'],
    ['flexpg_hero_texto', 'Texto do topo da página Flex', 'A Castelo Flex é a modalidade semipronta da Castello. A gente entrega a estrutura completa, coberta, com portas e janelas instaladas. Você conduz o acabamento no seu ritmo, com a economia de quem faz por etapas.', 'texto_longo'],
    ['flexpg_oque_titulo', 'Título de o que é a Castelo Flex', 'A estrutura pronta. O acabamento no seu tempo.', 'texto'],
    ['flexpg_oque_texto', 'Texto de o que é a Castelo Flex', 'A Castello monta a casa de madeira no seu terreno e entrega ela fechada: estrutura, telhado, portas e janelas. Daí em diante você escolhe quando e como fazer o acabamento, sem prazo de obra correndo atrás de você.', 'texto_longo'],
    ['flexpg_depois_titulo', 'Título de o que fica por sua conta', 'O que fica por sua conta', 'texto'],
    ['flexpg_depois_texto', 'Texto de o que fica por sua conta', 'Daqui pra frente a casa é sua e o ritmo é seu. Você contrata quem quiser, na ordem que quiser, e paga por etapa. A Castello segue disponível para orientar, mas o acabamento não está incluso na Castelo Flex.', 'texto_longo'],
    ['flexpg_catalogo_nota', 'Nota abaixo do catálogo Flex', 'Tamanhos e valores da Castelo Flex em fechamento com a fábrica. Peça o seu orçamento e receba a tabela atualizada, com o prazo para o seu terreno.', 'texto'],
    ['flexpg_cta_titulo', 'Título da faixa de orçamento da Flex', 'Quer a Castelo Flex no seu terreno?', 'texto'],
    ['flexpg_cta_texto', 'Texto da faixa de orçamento da Flex', 'Peça seu orçamento. A Castello volta com a tabela atualizada da Flex e o prazo para o seu terreno.', 'texto_longo'],
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
        $st = db()->prepare(
            'INSERT INTO modelos (modalidade, nome, area, parede, preco, prazo, descricao, foto, foto_alt, destaque, ativo, ordem)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)'
        );
        foreach (MIGRAR_MODELOS as $i => [$modalidade, $nome, $area, $parede, $preco, $prazo, $foto, $alt, $destaque]) {
            $st->execute([$modalidade, $nome, $area, $parede, $preco, $prazo, '', migrar_copiar($foto, 'modelos'), $alt, $destaque, $i + 1]);
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
