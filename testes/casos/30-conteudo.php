<?php
declare(strict_types=1);

require_once site() . '/lib/conteudo.php';

/** Fixture propria: este caso nao depende da migracao. */
function semear(): void
{
    db()->exec("INSERT INTO modelos (modalidade, nome, area, parede, preco, foto, foto_alt, destaque, ativo, ordem)
                VALUES ('pronta','Beta','42,75 m²','Parede dupla','79.988','uploads/modelos/b.png','Foto B',0,1,2),
                       ('pronta','Alfa','39,00 m²','Parede vertical','69.900','uploads/modelos/a.png','Foto A',1,1,1),
                       ('pronta','Sumida','10,00 m²','Parede vertical','1','uploads/modelos/s.png','Foto S',0,0,3),
                       ('flex','Flexinha','20,00 m²','Parede vertical','2','uploads/modelos/f.png','Foto F',0,1,1)");

    db()->exec("INSERT INTO portfolio (titulo, categoria, foto, foto_alt, ativo, ordem)
                VALUES ('Casa dois','Categoria dois','uploads/portfolio/2.png','Alt dois',1,2),
                       ('Casa um','Categoria um','uploads/portfolio/1.png','Alt um',1,1),
                       ('Casa off','Categoria off','uploads/portfolio/3.png','Alt off',0,3)");

    db()->exec("INSERT INTO avaliacoes (nome, texto, estrelas, ativo, ordem)
                VALUES ('Beatriz','Texto da Beatriz',5,1,2),
                       ('Andre','Texto do Andre',5,1,1),
                       ('Oculto','Texto oculto',5,0,3)");

    for ($i = 1; $i <= 11; $i++) {
        db()->prepare('INSERT INTO videos (arquivo, poster, legenda, ativo, ordem) VALUES (?, ?, ?, 1, ?)')
            ->execute([
                sprintf('uploads/videos/insta-%02d.mp4', $i),
                sprintf('uploads/videos/insta-%02d.jpg', $i),
                '',
                $i,
            ]);
    }

    db()->exec("INSERT INTO faq (pergunta, resposta, icone, contexto, ativo, ordem)
                VALUES ('Geral dois?','Resposta dois','chave','geral',1,2),
                       ('Geral um?','Resposta um','relogio','geral',1,1),
                       ('Flex um?','Resposta flex','planta','flex',1,1),
                       ('Geral off?','Resposta off','clima','geral',0,3)");

    db()->exec("INSERT INTO passos (contexto, titulo, texto, imagem, imagem_alt, ativo, ordem)
                VALUES ('pronta','Passo dois','Texto dois','uploads/passos/2.jpg','Alt dois',1,2),
                       ('pronta','Passo um','Texto um','uploads/passos/1.jpg','Alt um',1,1),
                       ('flex','Flex passo','Texto flex','uploads/passos/f.jpg','Alt flex',1,1)");

    db()->exec("INSERT INTO blocos (chave, rotulo, valor, tipo)
                VALUES ('hero_titulo','Titulo do topo','A casa dos seus sonhos','texto'),
                       ('flex_texto','Texto da Flex',NULL,'texto_longo')");
}

semear();

teste('modelos filtra por modalidade, esconde inativo e respeita a ordem', function (): void {
    $lista = modelos('pronta');
    igual(2, count($lista));
    igual('Alfa', $lista[0]['nome']);
    igual('Beta', $lista[1]['nome']);
    igual(1, (int) $lista[0]['destaque']);
    igual('uploads/modelos/a.png', $lista[0]['foto']);

    igual(1, count(modelos('flex')));
    igual('Flexinha', modelos('flex')[0]['nome']);
});

teste('portfolio esconde inativo e respeita a ordem', function (): void {
    $lista = portfolio();
    igual(2, count($lista));
    igual('Casa um', $lista[0]['titulo']);
    igual('Casa dois', $lista[1]['titulo']);
});

teste('avaliacoes esconde inativo e respeita a ordem', function (): void {
    $lista = avaliacoes();
    igual(2, count($lista));
    igual('Andre', $lista[0]['nome']);
    igual(5, (int) $lista[0]['estrelas']);
});

teste('videos sem argumento usa o limite da config', function (): void {
    igual('8', config_ler('videos_na_home'));
    igual(8, count(videos()));
    igual('uploads/videos/insta-01.mp4', videos()[0]['arquivo']);
    igual('uploads/videos/insta-08.mp4', videos()[7]['arquivo']);
});

teste('videos aceita limite explicito e limite zero devolve todos', function (): void {
    igual(3, count(videos(3)));
    igual(11, count(videos(0)));
    igual(11, count(videos(99)));
});

teste('videos acompanha a mudanca do limite na config', function (): void {
    config_gravar('videos_na_home', '4');
    igual(4, count(videos()));
    config_gravar('videos_na_home', '8');
});

teste('faq separa geral de flex', function (): void {
    $geral = faq('geral');
    igual(2, count($geral));
    igual('Geral um?', $geral[0]['pergunta']);
    igual('relogio', $geral[0]['icone']);

    igual(2, count(faq()), 'o padrao de faq() e o contexto geral');
    igual(1, count(faq('flex')));
    igual('Flex um?', faq('flex')[0]['pergunta']);
});

teste('passos separa pronta de flex', function (): void {
    $lista = passos('pronta');
    igual(2, count($lista));
    igual('Passo um', $lista[0]['titulo']);
    igual('uploads/passos/1.jpg', $lista[0]['imagem']);
    igual('Alt um', $lista[0]['imagem_alt']);
    igual(1, count(passos('flex')));
});

teste('bloco devolve o valor e cai no padrao quando vazio', function (): void {
    igual('A casa dos seus sonhos', bloco('hero_titulo'));
    igual('', bloco('flex_texto'), 'valor NULL vira string vazia');
    igual('reserva', bloco('flex_texto', 'reserva'), 'valor NULL cai no padrao');
    igual('', bloco('chave_inexistente'));
    igual('reserva', bloco('chave_inexistente', 'reserva'));
});

teste('icone_faq devolve o SVG das sete chaves fechadas', function (): void {
    foreach (['relogio', 'chave', 'planta', 'clima', 'escudo', 'fundacao', 'garantia'] as $chave) {
        $svg = icone_faq($chave);
        contem('<svg viewBox="0 0 24 24" aria-hidden="true">', $svg, "icone $chave");
        contem('</svg>', $svg, "icone $chave");
    }
    igual(7, count(CASTELLO_ICONES_FAQ));
});

teste('icone_faq devolve string vazia para chave desconhecida', function (): void {
    igual('', icone_faq('inventado'));
    igual('', icone_faq(''));
});
