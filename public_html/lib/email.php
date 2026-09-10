<?php
declare(strict_types=1);

/**
 * Aviso de lead novo para a Castello.
 *
 * O e-mail e a garantia de que o contato chega mesmo quando o CRM falha. Ele
 * sai sempre, com o resultado da tentativa de CRM escrito no corpo.
 *
 * mail() nao funciona na maquina local. Com a variavel de ambiente
 * CASTELLO_EMAIL_DIR apontando para uma pasta existente, a mensagem e gravada
 * em arquivo em vez de enviada. O envio real so se valida no servidor.
 *
 * Depende de config_ler(), definida em lib/db.php (frente 1).
 */

/** Rotulos dos campos do lead no corpo do e-mail, na ordem de leitura. */
const EMAIL_ROTULOS = [
    'nome'         => 'Nome',
    'whatsapp'     => 'WhatsApp',
    'busca'        => 'O que busca',
    'modelo'       => 'Modelo de interesse',
    'cidade'       => 'Cidade ou regiao',
    'prazo'        => 'Quer iniciar a obra',
    'mensagem'     => 'Mensagem',
    'pagina'       => 'Pagina de origem',
    'referrer'     => 'Veio de',
    'utm_source'   => 'utm_source',
    'utm_medium'   => 'utm_medium',
    'utm_campaign' => 'utm_campaign',
    'utm_term'     => 'utm_term',
    'utm_content'  => 'utm_content',
];

/**
 * Manda o aviso de lead novo. Devolve true quando a mensagem saiu (ou foi
 * gravada, no modo de arquivo).
 */
function email_lead_novo(array $lead, array $resultado_crm): bool
{
    $destino = trim((string) config_ler('email_aviso', 'contato@castellomadeiras.com.br'));
    if ($destino === '' || filter_var($destino, FILTER_VALIDATE_EMAIL) === false) {
        return false;
    }

    $nome = trim((string) ($lead['nome'] ?? ''));
    if ($nome === '') {
        $nome = 'sem nome';
    }
    $assunto = 'Lead novo no site: ' . $nome;
    $corpo = email_corpo_lead($lead, $resultado_crm);

    $cabecalhos = implode("\r\n", [
        'From: Site Castello <' . email_remetente() . '>',
        'Reply-To: ' . $destino,
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'X-Mailer: castello-site',
    ]);

    $pasta = (string) getenv('CASTELLO_EMAIL_DIR');
    if ($pasta !== '' && is_dir($pasta)) {
        $arquivo = rtrim(str_replace('\\', '/', $pasta), '/')
            . '/email-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.txt';
        $conteudo = 'Para: ' . $destino . "\r\n"
            . 'Assunto: ' . $assunto . "\r\n"
            . $cabecalhos . "\r\n\r\n"
            . $corpo;
        return file_put_contents($arquivo, $conteudo) !== false;
    }

    return @mail($destino, mb_encode_mimeheader($assunto, 'UTF-8', 'B'), $corpo, $cabecalhos);
}

/** Monta o texto do e-mail. Texto puro, sem HTML, para chegar em qualquer cliente. */
function email_corpo_lead(array $lead, array $resultado_crm): string
{
    $linhas = [];
    $linhas[] = 'Chegou um pedido de orcamento pelo site.';
    $linhas[] = '';
    $linhas[] = 'Lead numero: ' . (int) ($lead['id'] ?? 0);
    $linhas[] = 'Recebido em: ' . (string) ($lead['criado_em'] ?? '');
    $linhas[] = '';

    foreach (EMAIL_ROTULOS as $campo => $rotulo) {
        $valor = $lead[$campo] ?? '';
        $valor = is_scalar($valor) ? trim((string) $valor) : '';
        if ($valor !== '') {
            $linhas[] = $rotulo . ': ' . $valor;
        }
    }

    $digitos = preg_replace('/\D+/', '', (string) ($lead['whatsapp'] ?? '')) ?? '';
    if (strlen($digitos) >= 10) {
        $numero = strlen($digitos) <= 11 ? '55' . $digitos : $digitos;
        $linhas[] = '';
        $linhas[] = 'Responder no WhatsApp: https://wa.me/' . $numero;
    }

    $linhas[] = '';
    $linhas[] = '-----';
    $linhas[] = email_linha_crm($resultado_crm);

    $link = $resultado_crm['negocio_url'] ?? null;
    if (is_string($link) && $link !== '') {
        $linhas[] = 'Abrir no CRM: ' . $link;
    }

    $linhas[] = '';
    $linhas[] = 'Este lead esta gravado no banco do site. Se o CRM falhou, a rotina de';
    $linhas[] = 'reenvio tenta de novo sozinha. Nenhum contato se perde.';

    return implode("\r\n", $linhas);
}

/** Resume o resultado da tentativa de CRM em uma linha legivel. */
function email_linha_crm(array $resultado_crm): string
{
    $erro = $resultado_crm['erro'] ?? null;

    if (!empty($resultado_crm['ok'])) {
        return 'CRM: entregue (HTTP ' . (int) ($resultado_crm['http'] ?? 0) . ')';
    }
    if ($erro === 'crm_desativado') {
        return 'CRM: desligado nas configuracoes. O lead ficou guardado para reenvio.';
    }

    return 'CRM: falhou (' . (string) $erro . ', HTTP ' . (int) ($resultado_crm['http'] ?? 0) . '). '
        . 'Resposta: ' . mb_substr(trim((string) ($resultado_crm['resposta'] ?? '')), 0, 300);
}

/** Remetente no proprio dominio, porque hospedagem compartilhada recusa From de fora. */
function email_remetente(): string
{
    $host = (string) config_ler('email_dominio', '');
    if ($host === '') {
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    }
    $host = strtolower(preg_replace('/[^a-z0-9.\-]/i', '', $host) ?? '');
    $host = preg_replace('/^www\./', '', $host) ?? '';
    if ($host === '' || strpos($host, '.') === false) {
        $host = 'castellomadeiras.com.br';
    }

    return 'site@' . $host;
}
