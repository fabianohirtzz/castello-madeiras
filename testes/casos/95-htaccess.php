<?php
declare(strict_types=1);

function htaccess(string $pasta): string
{
    $caminho = rtrim(site() . '/' . $pasta, '/') . '/.htaccess';
    $texto   = file_get_contents($caminho);

    if ($texto === false) {
        throw new RuntimeException('nao consegui ler ' . $caminho);
    }

    return $texto;
}

teste('o htaccess da raiz forca HTTPS e nao lista pastas', function (): void {
    $t = htaccess('');
    contem('RewriteEngine On', $t);
    contem('RewriteCond %{HTTPS} !=on', $t);
    contem('R=301', $t);
    contem('Options -Indexes', $t);
    contem('DirectoryIndex index.php', $t);
});

teste('o htaccess da raiz bloqueia banco, schema e caminho-config', function (): void {
    $t = htaccess('');
    contem('caminho-config.php', $t);
    contem('schema.sql', $t);
    contem('.db', $t);
    contem('Require all denied', $t);
});

teste('o htaccess de uploads desliga a execucao de script', function (): void {
    $t = htaccess('uploads');

    contem('Options -ExecCGI', $t);
    contem('RemoveHandler', $t);
    contem('php_flag engine off', $t);
    contem('Require all denied', $t);

    foreach (['php', 'phtml', 'cgi', 'pl', 'py', 'sh'] as $extensao) {
        contem($extensao, $t, "uploads precisa bloquear .$extensao");
    }
});

teste('o htaccess do painel deixa o HTTP Basic pronto para ligar', function (): void {
    $t = htaccess('painel');

    contem('# AuthType Basic', $t);
    contem('# AuthName "Painel Castello"', $t);
    contem('# AuthUserFile', $t);
    contem('# Require valid-user', $t);
    contem('htpasswd', $t, 'a instrucao de como criar o arquivo de senhas tem que estar ali');

    falso(str_contains($t, "\nAuthType Basic"), 'o Basic vem desligado por padrao');
});

teste('nenhum htaccess vaza caminho de disco do ambiente local', function (): void {
    foreach (['', 'uploads', 'painel'] as $pasta) {
        nao_contem('prototipo-site-castello', htaccess($pasta));
        nao_contem('C:\\', htaccess($pasta));
    }
});

teste('lib e partials sao bloqueados por inteiro pelo navegador', function (): void {
    foreach (['lib', 'partials'] as $pasta) {
        $t = htaccess($pasta);
        contem('Require all denied', $t, "$pasta precisa negar tudo");
        nao_contem('Require all granted', $t);
    }
});
