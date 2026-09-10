<?php
declare(strict_types=1);

teste('igual aceita valores identicos', function (): void {
    igual(3, 1 + 2);
    igual('abc', 'a' . 'bc');
});

teste('igual recusa tipos diferentes', function (): void {
    $pegou = false;
    try {
        igual(3, '3');
    } catch (Throwable $t) {
        $pegou = true;
    }
    verdade($pegou, 'igual deveria recusar o inteiro 3 contra a string "3"');
});

teste('contem encontra pedaco de string', function (): void {
    contem('bola', 'a bola rolou');
});

teste('nao_contem recusa quando o pedaco existe', function (): void {
    $pegou = false;
    try {
        nao_contem('bola', 'a bola rolou');
    } catch (Throwable $t) {
        $pegou = true;
    }
    verdade($pegou, 'nao_contem deveria falhar quando a agulha existe');
});

teste('norm colapsa espaco entre tags', function (): void {
    igual('<a><b>x</b></a>', norm("<a>\n   <b>x</b>\n</a>"));
});

teste('norm reescreve caminho de uploads para o caminho original', function (): void {
    igual('<img src="fotos-casas/casa1.png">', norm('<img src="uploads/modelos/casa1.png">'));
    igual('<img src="fotos-casas/casa3.png">', norm('<img src="uploads/portfolio/casa3.png">'));
    igual('<source src="videos-instagram/web/insta-01.mp4">', norm('<source src="uploads/videos/insta-01.mp4">'));
    igual('<img src="passos/passo-1.jpg">', norm('<img src="uploads/passos/passo-1.jpg">'));
});

teste('norm_sem_alt remove o atributo alt', function (): void {
    igual('<img src="a.png">', norm_sem_alt('<img src="a.png" alt="qualquer coisa">'));
});

teste('pular marca o caso como pulado sem quebrar a suite', function (): void {
    pular('exemplo proposital, so para provar o mecanismo de pular');
});

teste('o caso roda em pastas temporarias isoladas', function (): void {
    verdade(defined('CASTELLO_CONFIG'), 'CASTELLO_CONFIG precisa estar definida');
    verdade(defined('CASTELLO_UPLOADS'), 'CASTELLO_UPLOADS precisa estar definida');
    verdade(is_dir(CASTELLO_CONFIG), 'a pasta de config temporaria precisa existir');
    verdade(is_dir(CASTELLO_UPLOADS), 'a pasta de uploads temporaria precisa existir');
    nao_contem('prototipo-site-castello', CASTELLO_CONFIG, 'o caso nao pode escrever no config real do projeto');
});

teste('raiz e site apontam para as pastas certas', function (): void {
    verdade(is_file(raiz() . '/testes/smoke.php'), 'raiz() deve conter testes/smoke.php');
    verdade(is_dir(site() . '/css'), 'site() deve conter a pasta css');
});
