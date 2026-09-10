<?php
declare(strict_types=1);

require_once site() . '/painel/tabelas.php';
require_once site() . '/migrar.php';

banco_com_conteudo();
migrar_usuario('castello', 'senha-de-teste-forte');

teste('painel_backup nunca dispara fatal quando falta o ZipArchive', function (): void {
    if (class_exists('ZipArchive')) {
        pular('ZipArchive existe neste PHP, entao o caminho de falha nao aparece aqui');
    }

    $r = painel_backup();
    falso($r['ok']);
    igual(null, $r['arquivo']);
    contem('ZipArchive', (string) $r['erro']);
    contem('zip do PHP', (string) $r['erro'], 'a mensagem tem que dizer o que pedir para a hospedagem');
});

teste('painel_backup gera um zip com o banco e a pasta uploads', function (): void {
    if (!class_exists('ZipArchive')) {
        pular('ZipArchive nao existe no PHP local e phar.readonly esta ligado, entao o zip so pode ser validado no servidor');
    }

    $r = painel_backup();
    verdade($r['ok'], 'erro: ' . var_export($r['erro'], true));
    verdade(is_file((string) $r['arquivo']));

    $zip = new ZipArchive();
    igual(true, $zip->open((string) $r['arquivo']));

    $dentro = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $dentro[] = (string) $zip->getNameIndex($i);
    }
    $zip->close();

    verdade(in_array('castello.db', $dentro, true), 'o banco precisa estar no zip');
    verdade(count(array_filter($dentro, static fn (string $n): bool => str_starts_with($n, 'uploads/'))) > 20,
        'as fotos e os videos migrados precisam estar no zip');

    unlink((string) $r['arquivo']);
});

teste('trocar senha exige a senha atual correta', function (): void {
    $id = (int) db()->query("SELECT id FROM usuarios WHERE login = 'castello'")->fetchColumn();

    $r = painel_trocar_senha($id, 'errada', 'senha-nova-123', 'senha-nova-123');
    falso($r['ok']);
    contem('senha atual', (string) $r['erro']);

    verdade(password_verify('senha-de-teste-forte',
        (string) db()->query("SELECT senha_hash FROM usuarios WHERE id = $id")->fetchColumn()),
        'a senha nao pode ter mudado');
});

teste('trocar senha exige 8 caracteres e confirmacao igual', function (): void {
    $id = (int) db()->query("SELECT id FROM usuarios WHERE login = 'castello'")->fetchColumn();

    $curta = painel_trocar_senha($id, 'senha-de-teste-forte', 'abc123', 'abc123');
    falso($curta['ok']);
    contem('8 caracteres', (string) $curta['erro']);

    $torta = painel_trocar_senha($id, 'senha-de-teste-forte', 'senha-nova-123', 'senha-nova-124');
    falso($torta['ok']);
    contem('confirmação', (string) $torta['erro']);

    $igual = painel_trocar_senha($id, 'senha-de-teste-forte', 'senha-de-teste-forte', 'senha-de-teste-forte');
    falso($igual['ok']);
    contem('diferente', (string) $igual['erro']);
});

teste('trocar senha grava o hash novo e o antigo deixa de valer', function (): void {
    $id = (int) db()->query("SELECT id FROM usuarios WHERE login = 'castello'")->fetchColumn();

    $r = painel_trocar_senha($id, 'senha-de-teste-forte', 'senha-nova-do-cliente', 'senha-nova-do-cliente');
    verdade($r['ok'], 'erro: ' . var_export($r['erro'], true));
    igual(null, $r['erro']);

    $hash = (string) db()->query("SELECT senha_hash FROM usuarios WHERE id = $id")->fetchColumn();
    verdade(str_starts_with($hash, '$2y$'), 'continua bcrypt');
    verdade(password_verify('senha-nova-do-cliente', $hash));
    falso(password_verify('senha-de-teste-forte', $hash), 'a senha antiga tem que deixar de valer');
});

teste('trocar senha de usuario inexistente devolve erro, nao fatal', function (): void {
    $r = painel_trocar_senha(999999, 'qualquer', 'senha-nova-123', 'senha-nova-123');
    falso($r['ok']);
    contem('não encontrado', (string) $r['erro']);
});
