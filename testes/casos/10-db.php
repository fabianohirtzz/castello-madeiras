<?php
declare(strict_types=1);

require_once site() . '/lib/db.php';

teste('db() cria o banco e aplica o schema inteiro', function (): void {
    $tabelas = db()->query(
        "SELECT name FROM sqlite_master WHERE type = 'table' ORDER BY name"
    )->fetchAll(PDO::FETCH_COLUMN);

    foreach (['avaliacoes', 'blocos', 'config', 'faq', 'leads', 'login_tentativas',
              'modelos', 'passos', 'portfolio', 'usuarios', 'videos'] as $tabela) {
        verdade(in_array($tabela, $tabelas, true), "faltou a tabela $tabela");
    }
    verdade(is_file(CASTELLO_CONFIG . '/castello.db'), 'o arquivo do banco precisa existir');
});

teste('o banco abre em modo WAL', function (): void {
    igual('wal', strtolower((string) db()->query('PRAGMA journal_mode')->fetchColumn()));
});

teste('db() devolve sempre a mesma conexao', function (): void {
    verdade(db() === db(), 'db() nao pode abrir uma conexao nova a cada chamada');
});

teste('os indices do contrato existem', function (): void {
    $indices = db()->query(
        "SELECT name FROM sqlite_master WHERE type = 'index' ORDER BY name"
    )->fetchAll(PDO::FETCH_COLUMN);

    foreach (['idx_modelos_lista', 'idx_faq_lista', 'idx_passos_lista', 'idx_leads_pendentes'] as $ix) {
        verdade(in_array($ix, $indices, true), "faltou o indice $ix");
    }
});

teste('o CHECK de modalidade recusa valor fora da lista', function (): void {
    $pegou = false;
    try {
        db()->prepare('INSERT INTO modelos (modalidade, nome) VALUES (?, ?)')
            ->execute(['inventada', 'Teste']);
    } catch (PDOException $ex) {
        $pegou = true;
    }
    verdade($pegou, 'modalidade fora de pronta/flex tinha que ser recusada');
});

teste('e() escapa aspas, sinal de maior e e comercial', function (): void {
    igual('&lt;b&gt;', e('<b>'));
    igual('&quot;a&quot;', e('"a"'));
    igual('&#039;a&#039;', e("'a'"));
    igual('Casa &amp; Cia', e('Casa & Cia'));
    igual('', e(null));
    igual('45 m²', e('45 m²'), 'acentos e simbolos UTF-8 passam intactos');
});

teste('agora() devolve data no formato do contrato', function (): void {
    verdade((bool) preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', agora()));
    igual('America/Sao_Paulo', date_default_timezone_get());
});

teste('as dez chaves iniciais de config nascem com os valores do contrato', function (): void {
    igual('8', config_ler('videos_na_home'));
    igual('contato@castellomadeiras.com.br', config_ler('email_aviso'));
    igual('0', config_ler('crm_ativo'));
    igual('', config_ler('crm_endpoint'));
    igual('POST', config_ler('crm_metodo'));
    igual('{}', config_ler('crm_cabecalhos'));
    igual('10', config_ler('crm_timeout'));
    igual('castellomadeiras.com.br', config_ler('email_dominio'));

    $mapa = json_decode((string) config_ler('crm_mapa_campos'), true);
    igual('telefone', $mapa['whatsapp']);
    igual('observacao', $mapa['mensagem']);
    igual('origem', $mapa['utm_source']);

    igual(10, (int) db()->query('SELECT COUNT(*) FROM config')->fetchColumn());
});

teste('reenvio_chave e gerada na instalacao e nao muda depois', function (): void {
    $chave = (string) config_ler('reenvio_chave');
    verdade(strlen($chave) >= 32, 'a chave de reenvio precisa ser longa, obtida: ' . $chave);
    verdade((bool) preg_match('/^[0-9a-f]+$/', $chave), 'a chave e hexadecimal');
    igual($chave, (string) config_ler('reenvio_chave'), 'a chave nao pode ser regerada a cada leitura');
});

teste('o UPSERT do SQLite 3.26 funciona, que e como config e blocos gravam', function (): void {
    db()->prepare(
        'INSERT INTO config (chave, valor) VALUES (?, ?)
         ON CONFLICT(chave) DO UPDATE SET valor = excluded.valor'
    )->execute(['teste_upsert', 'um']);

    db()->prepare(
        'INSERT INTO config (chave, valor) VALUES (?, ?)
         ON CONFLICT(chave) DO UPDATE SET valor = excluded.valor'
    )->execute(['teste_upsert', 'dois']);

    igual('dois', config_ler('teste_upsert'));
    db()->exec("DELETE FROM config WHERE chave = 'teste_upsert'");
});

teste('lastInsertId funciona, porque INSERT RETURNING nao existe no SQLite 3.26', function (): void {
    db()->prepare("INSERT INTO avaliacoes (nome, texto) VALUES (?, ?)")->execute(['Teste', 'Texto']);
    $id = (int) db()->lastInsertId();

    verdade($id > 0);
    igual('Teste', (string) db()->query('SELECT nome FROM avaliacoes WHERE id = ' . $id)->fetchColumn());
    db()->exec('DELETE FROM avaliacoes WHERE id = ' . $id);
});

teste('castello_caminho_config respeita a constante ja definida pelo teste', function (): void {
    igual(CASTELLO_CONFIG, castello_caminho_config());
    nao_contem('prototipo-site-castello', castello_caminho_config());
});

teste('config_ler devolve o padrao quando a chave nao existe', function (): void {
    igual(null, config_ler('chave_que_nao_existe'));
    igual('reserva', config_ler('chave_que_nao_existe', 'reserva'));
});

teste('config_gravar cria e depois atualiza a chave', function (): void {
    config_gravar('teste_chave', 'primeiro');
    igual('primeiro', config_ler('teste_chave'));
    config_gravar('teste_chave', 'segundo');
    igual('segundo', config_ler('teste_chave'));
});

teste('db_garantir_colunas acrescenta o que falta e e idempotente', function (): void {
    /* Banco em memoria com o leads ANTIGO, sem prazo nem crm_pessoa_id. */
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('CREATE TABLE leads (id INTEGER PRIMARY KEY, nome TEXT, crm_status TEXT)');

    $acrescentadas = db_garantir_colunas($pdo);
    sort($acrescentadas);
    igual(['crm_pessoa_id', 'prazo'], $acrescentadas, 'acrescenta as duas colunas que faltavam');

    $colunas = array_column($pdo->query('PRAGMA table_info(leads)')->fetchAll(PDO::FETCH_ASSOC), 'name');
    verdade(in_array('prazo', $colunas, true), 'prazo existe agora');
    verdade(in_array('crm_pessoa_id', $colunas, true), 'crm_pessoa_id existe agora');

    /* Segunda passada nao pode falhar nem repetir: roda em toda requisicao. */
    igual([], db_garantir_colunas($pdo), 'segunda chamada nao acrescenta nada');

    /* O dado que ja estava la sobrevive. */
    $pdo->exec("INSERT INTO leads (nome, crm_status) VALUES ('Antigo', 'pendente')");
    $linha = $pdo->query('SELECT * FROM leads')->fetch(PDO::FETCH_ASSOC);
    igual('Antigo', $linha['nome']);
    verdade($linha['prazo'] === null, 'coluna nova nasce nula no registro antigo');
});

teste('o banco real do runner ja tem as colunas novas', function (): void {
    $colunas = array_column(db()->query('PRAGMA table_info(leads)')->fetchAll(PDO::FETCH_ASSOC), 'name');
    verdade(in_array('prazo', $colunas, true), 'prazo no banco do runner');
    verdade(in_array('crm_pessoa_id', $colunas, true), 'crm_pessoa_id no banco do runner');
});

teste('db_garantir_colunas tolera coluna acrescentada por outra requisicao', function (): void {
    /* Banco em memoria com o leads ANTIGO. */
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('CREATE TABLE leads (id INTEGER PRIMARY KEY, nome TEXT, crm_status TEXT)');

    /* Outra requisicao (ou thread) acrescenta prazo entre a leitura do PRAGMA e o ALTER. */
    $pdo->exec('ALTER TABLE leads ADD COLUMN prazo TEXT');

    /* Esta requisicao vai tentar acrescentar prazo e crm_pessoa_id, mas prazo ja existe. */
    $pegou_excecao = false;
    try {
        $acrescentadas = db_garantir_colunas($pdo);
    } catch (PDOException $ex) {
        $pegou_excecao = true;
    }

    falso($pegou_excecao, 'nao deve lancar excecao quando coluna duplicada');

    /* Apenas crm_pessoa_id foi acrescentado nesta chamada; prazo foi achado ja existente. */
    sort($acrescentadas);
    igual(['crm_pessoa_id'], $acrescentadas, 'so crm_pessoa_id foi acrescentado');

    /* As duas colunas existem agora. */
    $colunas = array_column($pdo->query('PRAGMA table_info(leads)')->fetchAll(PDO::FETCH_ASSOC), 'name');
    verdade(in_array('prazo', $colunas, true), 'prazo existe');
    verdade(in_array('crm_pessoa_id', $colunas, true), 'crm_pessoa_id existe');
});
