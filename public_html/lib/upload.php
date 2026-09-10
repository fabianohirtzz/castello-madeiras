<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Validacao e gravacao de imagem e video enviados pelo painel.
 *
 * O tipo e conferido com finfo_file, nunca pela extensao. O arquivo e
 * renomeado para slug do nome original mais 6 caracteres aleatorios, e a
 * extensao vem do tipo real detectado.
 */

if (!defined('CASTELLO_UPLOADS')) {
    define('CASTELLO_UPLOADS', dirname(__DIR__) . '/uploads');
}

const UPLOAD_PASTAS = ['modelos', 'portfolio', 'videos', 'passos'];

const UPLOAD_TIPOS = [
    'imagem' => [
        'limite' => 5242880,
        'mime'   => ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
    ],
    'video' => [
        'limite' => 31457280,
        'mime'   => ['video/mp4' => 'mp4'],
    ],
];

/**
 * @param array  $arquivo uma entrada de $_FILES
 * @param string $pasta   modelos, portfolio, videos ou passos
 * @param string $tipo    imagem ou video
 *
 * @return array{ok: bool, caminho: ?string, erro: ?string}
 */
function upload_receber(array $arquivo, string $pasta, string $tipo): array
{
    $falha = static fn (string $erro): array => ['ok' => false, 'caminho' => null, 'erro' => $erro];

    if (!in_array($pasta, UPLOAD_PASTAS, true)) {
        return $falha('pasta_invalida');
    }
    if (!isset(UPLOAD_TIPOS[$tipo])) {
        return $falha('tipo_invalido');
    }

    $codigo = (int) ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($codigo === UPLOAD_ERR_NO_FILE) {
        return $falha('sem_arquivo');
    }
    if ($codigo === UPLOAD_ERR_INI_SIZE || $codigo === UPLOAD_ERR_FORM_SIZE) {
        return $falha('tamanho');
    }
    if ($codigo !== UPLOAD_ERR_OK) {
        return $falha('erro_upload');
    }

    $temporario = (string) ($arquivo['tmp_name'] ?? '');
    if ($temporario === '' || !is_file($temporario)) {
        return $falha('sem_arquivo');
    }

    $regra = UPLOAD_TIPOS[$tipo];
    if (filesize($temporario) > $regra['limite']) {
        return $falha('tamanho');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo === false) {
        return $falha('finfo_indisponivel');
    }
    $mime = (string) finfo_file($finfo, $temporario);
    finfo_close($finfo);

    if (!isset($regra['mime'][$mime])) {
        return $falha('tipo');
    }

    $destino = CASTELLO_UPLOADS . '/' . $pasta;
    if (!is_dir($destino) && !mkdir($destino, 0775, true) && !is_dir($destino)) {
        return $falha('gravacao');
    }

    $nome = upload_slug((string) ($arquivo['name'] ?? 'arquivo'))
          . '-' . bin2hex(random_bytes(3))
          . '.' . $regra['mime'][$mime];

    $emDisco = $destino . '/' . $nome;

    // Na linha de comando is_uploaded_file e sempre falso, entao o teste usa
    // rename. No navegador o caminho e move_uploaded_file, que so aceita
    // arquivo que veio mesmo de um POST.
    $movido = PHP_SAPI === 'cli'
        ? @rename($temporario, $emDisco)
        : @move_uploaded_file($temporario, $emDisco);

    if (!$movido) {
        return $falha('gravacao');
    }

    @chmod($emDisco, 0644);

    return ['ok' => true, 'caminho' => 'uploads/' . $pasta . '/' . $nome, 'erro' => null];
}

/** Nome de arquivo seguro: sem acento, sem espaco, sem caractere de caminho. */
function upload_slug(string $nome): string
{
    $base = pathinfo($nome, PATHINFO_FILENAME);

    $base = strtr($base, [
        'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
        'é' => 'e', 'ê' => 'e', 'è' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i',
        'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ò' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c', 'ñ' => 'n',
        'Á' => 'a', 'À' => 'a', 'Ã' => 'a', 'Â' => 'a', 'Ä' => 'a',
        'É' => 'e', 'Ê' => 'e', 'È' => 'e',
        'Í' => 'i', 'Ì' => 'i',
        'Ó' => 'o', 'Ô' => 'o', 'Õ' => 'o', 'Ò' => 'o',
        'Ú' => 'u', 'Ù' => 'u', 'Ü' => 'u',
        'Ç' => 'c', 'Ñ' => 'n',
    ]);

    $base = mb_strtolower($base);
    $base = (string) preg_replace('/[^a-z0-9]+/u', '-', $base);
    $base = trim($base, '-');

    if ($base === '') {
        $base = 'arquivo';
    }

    return mb_substr($base, 0, 40);
}
