<?php
declare(strict_types=1);

require_once site() . '/lib/upload.php';

/** Cria um arquivo temporario e devolve o array no formato de $_FILES. */
function arquivo_falso(string $nome, string $conteudo): array
{
    $tmp = tempnam(sys_get_temp_dir(), 'castello-up');
    file_put_contents($tmp, $conteudo);

    return ['name' => $nome, 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => strlen($conteudo)];
}

function imagem_falsa(string $formato): string
{
    $img = imagecreatetruecolor(60, 40);
    imagefilledrectangle($img, 0, 0, 59, 39, imagecolorallocate($img, 200, 30, 40));

    ob_start();
    match ($formato) {
        'jpg'  => imagejpeg($img, null, 90),
        'png'  => imagepng($img),
        'webp' => imagewebp($img),
        'gif'  => imagegif($img),
    };
    $bytes = (string) ob_get_clean();
    imagedestroy($img);

    return $bytes;
}

teste('imagem jpeg valida e aceita, renomeada e gravada em uploads', function (): void {
    $r = upload_receber(arquivo_falso('Foto da Casa Bonita.jpg', imagem_falsa('jpg')), 'modelos', 'imagem');

    verdade($r['ok'], 'erro: ' . var_export($r['erro'], true));
    igual(null, $r['erro']);
    verdade(str_starts_with((string) $r['caminho'], 'uploads/modelos/'));
    verdade((bool) preg_match('#^uploads/modelos/foto-da-casa-bonita-[0-9a-f]{6}\.jpg$#', (string) $r['caminho']),
        'nome esperado: slug mais 6 caracteres aleatorios, obtido ' . $r['caminho']);
    verdade(is_file(CASTELLO_UPLOADS . '/modelos/' . basename((string) $r['caminho'])));
});

teste('a extensao gravada vem do tipo real, nao do nome enviado', function (): void {
    $r = upload_receber(arquivo_falso('mentira.PNG', imagem_falsa('jpg')), 'portfolio', 'imagem');
    verdade($r['ok']);
    verdade(str_ends_with((string) $r['caminho'], '.jpg'), 'conteudo jpeg tem que virar .jpg');
});

teste('png e webp sao aceitos, gif nao', function (): void {
    verdade(upload_receber(arquivo_falso('a.png', imagem_falsa('png')), 'modelos', 'imagem')['ok']);

    if ((gd_info()['WebP Support'] ?? false) === true) {
        verdade(upload_receber(arquivo_falso('a.webp', imagem_falsa('webp')), 'modelos', 'imagem')['ok']);
    }

    $gif = upload_receber(arquivo_falso('a.gif', imagem_falsa('gif')), 'modelos', 'imagem');
    falso($gif['ok']);
    igual('tipo', $gif['erro']);
});

teste('arquivo PHP renomeado para .jpg e recusado', function (): void {
    $r = upload_receber(arquivo_falso('shell.jpg', "<?php system(\$_GET['c']); "), 'modelos', 'imagem');

    falso($r['ok']);
    igual('tipo', $r['erro']);
    igual(null, $r['caminho']);
    igual([], glob(CASTELLO_UPLOADS . '/modelos/shell*') ?: [], 'nada pode ter sido gravado');
});

teste('imagem acima de 5 MB e recusada', function (): void {
    $grande = imagem_falsa('jpg') . str_repeat("\0", 6 * 1024 * 1024);
    $r = upload_receber(arquivo_falso('pesada.jpg', $grande), 'modelos', 'imagem');

    falso($r['ok']);
    igual('tamanho', $r['erro']);
});

teste('video mp4 real e aceito na pasta de videos', function (): void {
    $bytes = file_get_contents(site() . '/videos-instagram/web/insta-01.mp4');
    verdade($bytes !== false, 'o mp4 de referencia precisa existir no repositorio');

    $r = upload_receber(arquivo_falso('Reel do Instagram.mp4', (string) $bytes), 'videos', 'video');
    verdade($r['ok'], 'erro: ' . var_export($r['erro'], true));
    verdade((bool) preg_match('#^uploads/videos/reel-do-instagram-[0-9a-f]{6}\.mp4$#', (string) $r['caminho']));
});

teste('imagem enviada como video e video enviado como imagem sao recusados', function (): void {
    $comoVideo = upload_receber(arquivo_falso('a.jpg', imagem_falsa('jpg')), 'videos', 'video');
    falso($comoVideo['ok']);
    igual('tipo', $comoVideo['erro']);

    $bytes = (string) file_get_contents(site() . '/videos-instagram/web/insta-01.mp4');
    $comoImagem = upload_receber(arquivo_falso('a.mp4', $bytes), 'modelos', 'imagem');
    falso($comoImagem['ok']);
    igual('tipo', $comoImagem['erro']);
});

teste('pasta fora da lista e tipo fora da lista sao recusados', function (): void {
    $r = upload_receber(arquivo_falso('a.jpg', imagem_falsa('jpg')), 'lib', 'imagem');
    falso($r['ok']);
    igual('pasta_invalida', $r['erro']);

    $r = upload_receber(arquivo_falso('a.jpg', imagem_falsa('jpg')), '../lib', 'imagem');
    falso($r['ok']);
    igual('pasta_invalida', $r['erro']);

    $r = upload_receber(arquivo_falso('a.jpg', imagem_falsa('jpg')), 'modelos', 'documento');
    falso($r['ok']);
    igual('tipo_invalido', $r['erro']);
});

teste('campo vazio e erro do PHP viram erro claro', function (): void {
    $r = upload_receber(['name' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0], 'modelos', 'imagem');
    falso($r['ok']);
    igual('sem_arquivo', $r['erro']);

    $r = upload_receber(['name' => 'a.jpg', 'tmp_name' => '', 'error' => UPLOAD_ERR_INI_SIZE, 'size' => 0], 'modelos', 'imagem');
    igual('tamanho', $r['erro']);

    $r = upload_receber(['name' => 'a.jpg', 'tmp_name' => '', 'error' => UPLOAD_ERR_PARTIAL, 'size' => 0], 'modelos', 'imagem');
    igual('erro_upload', $r['erro']);

    $r = upload_receber([], 'modelos', 'imagem');
    igual('sem_arquivo', $r['erro']);
});

teste('dois envios com o mesmo nome geram arquivos diferentes', function (): void {
    $a = upload_receber(arquivo_falso('igual.jpg', imagem_falsa('jpg')), 'passos', 'imagem');
    $b = upload_receber(arquivo_falso('igual.jpg', imagem_falsa('jpg')), 'passos', 'imagem');

    verdade($a['ok']);
    verdade($b['ok']);
    verdade($a['caminho'] !== $b['caminho'], 'o sufixo aleatorio evita sobrescrever');
});

teste('upload_slug limpa acento, espaco e caractere de caminho', function (): void {
    igual('casa-de-madeira', upload_slug('Casa de Madeira.jpg'));
    igual('sao-jose-acucar', upload_slug('São José & Açúcar.png'));
    igual('arquivo', upload_slug('...jpg'));
    igual('passwd', upload_slug('../../etc/passwd'), 'pathinfo ja corta o diretorio');
    verdade(mb_strlen(upload_slug(str_repeat('a', 200) . '.jpg')) <= 40);
    nao_contem('/', upload_slug('../../etc/passwd'));
    nao_contem('.', upload_slug('a.b.c.jpg'));
});

teste('os limites sao os do contrato', function (): void {
    igual(5 * 1024 * 1024, UPLOAD_TIPOS['imagem']['limite']);
    igual(30 * 1024 * 1024, UPLOAD_TIPOS['video']['limite']);
    igual(['image/jpeg', 'image/png', 'image/webp'], array_keys(UPLOAD_TIPOS['imagem']['mime']));
    igual(['video/mp4'], array_keys(UPLOAD_TIPOS['video']['mime']));
    igual(['modelos', 'portfolio', 'videos', 'passos'], UPLOAD_PASTAS);
});
