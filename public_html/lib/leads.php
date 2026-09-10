<?php
declare(strict_types=1);

/**
 * Rede de segurança dos leads.
 *
 * O lead e gravado aqui ANTES de qualquer tentativa de integracao. Se o CRM
 * cair, o contato continua no banco e a rotina de reenvio (reenviar.php) o
 * empurra depois. Nao existe tela de leads no painel, por decisao da spec.
 *
 * Depende de db() e agora(), definidas em lib/db.php (frente 1). Este arquivo
 * nao carrega a lib: quem carrega e o ponto de entrada.
 */

/** Campos de conteudo do lead, na ordem do schema. */
const LEAD_CAMPOS = [
    'nome', 'whatsapp', 'busca', 'modelo', 'cidade', 'mensagem',
    'pagina', 'referrer',
    'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
];

/** Depois de 5 tentativas malsucedidas o lead para de ser reenviado. */
const LEAD_TENTATIVAS_MAX = 5;

/** Tamanho maximo guardado em crm_resposta, para nao inchar o banco. */
const LEAD_RESPOSTA_MAX = 2000;

/** Status aceitos pela coluna crm_status (CHECK do schema). */
const LEAD_STATUS = ['pendente', 'enviado', 'erro', 'desativado'];

/**
 * Grava o lead com status pendente e devolve o id.
 *
 * Campo ausente vira string vazia. Valores muito longos sao cortados, porque
 * o formulario e publico e o banco e a rede de segurança, nao um deposito.
 */
function lead_gravar(array $dados): int
{
    $valores = [];
    foreach (LEAD_CAMPOS as $campo) {
        $bruto = $dados[$campo] ?? '';
        $texto = is_scalar($bruto) ? trim((string) $bruto) : '';
        $limite = $campo === 'mensagem' ? 4000 : 500;
        $valores[$campo] = mb_substr($texto, 0, $limite);
    }
    $valores['criado_em'] = agora();

    $colunas = implode(', ', LEAD_CAMPOS);
    $marcas  = ':' . implode(', :', LEAD_CAMPOS);

    $st = db()->prepare(
        "INSERT INTO leads ({$colunas}, criado_em, crm_status, crm_tentativas)
         VALUES ({$marcas}, :criado_em, 'pendente', 0)"
    );
    $st->execute($valores);

    return (int) db()->lastInsertId();
}

/**
 * Registra o resultado de uma tentativa de envio ao CRM.
 *
 * Status fora da lista vira 'erro', para nunca esbarrar no CHECK do schema e
 * derrubar o enviar.php por causa de um valor inesperado.
 */
function lead_marcar(int $id, string $status, int $tentativas, ?string $resposta): void
{
    if (!in_array($status, LEAD_STATUS, true)) {
        $status = 'erro';
    }
    if ($resposta !== null) {
        $resposta = mb_substr(trim($resposta), 0, LEAD_RESPOSTA_MAX);
        if ($resposta === '') {
            $resposta = null;
        }
    }

    $st = db()->prepare(
        'UPDATE leads
            SET crm_status = :status,
                crm_tentativas = :tentativas,
                crm_ultima_tentativa = :quando,
                crm_resposta = :resposta
          WHERE id = :id'
    );
    $st->execute([
        ':status'     => $status,
        ':tentativas' => max(0, $tentativas),
        ':quando'     => agora(),
        ':resposta'   => $resposta,
        ':id'         => $id,
    ]);
}
