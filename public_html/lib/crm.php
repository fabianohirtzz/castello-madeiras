<?php
declare(strict_types=1);

/**
 * Conector do Agendor.
 *
 * Manda o lead do site para o CRM da Castello em tres chamadas encadeadas:
 * procura a pessoa pelo telefone, cria se nao existir, e cria o negocio nela.
 * E o negocio, nao a pessoa, que aparece no funil de vendas.
 *
 * O formato segue o que a conta da Castello ja pratica, medido em 2026-09-10:
 * titulo "[SITE] - <prazo> - <Nome>", espelhando o "[META] - ..." que a
 * integracao do Meta ja deposita no mesmo funil.
 *
 * Depende de config_ler(), definida em lib/db.php. Este arquivo nao carrega a
 * lib: quem carrega e o ponto de entrada.
 *
 * Spec: docs/superpowers/specs/2026-09-10-castello-agendor-design.md
 */

/** Endereco da API. Sobrescrito por config.crm_base, que os testes apontam
    para o servidor falso. */
const CRM_BASE_PADRAO = 'https://api.agendor.com.br/v3';

/** Teto do tempo TOTAL das tres chamadas. O contrato fixa 10 segundos. */
const CRM_TIMEOUT_MAX = 10;

/** Abaixo disto nao vale a pena comecar mais uma chamada. */
const CRM_MINIMO_POR_CHAMADA = 2;

/** Tamanho maximo da resposta guardada, para nao inchar o banco. */
const CRM_RESPOSTA_MAX = 2000;

/** Os titulos que ja existem no funil tem de 27 a 53 caracteres. */
const CRM_TITULO_MAX = 120;

/** Identificadores dos campos customizados de pessoa, como estao na conta. */
const CRM_CAMPO_CIDADE = 'cidade_da_obra';
const CRM_CAMPO_ANUNCIO = 'anuncio_de_origem';
const CRM_CAMPO_PRAZO = 'pretende_iniciar_a_obra_em';

/**
 * Token da conta.
 *
 * O ambiente vem primeiro para o teste poder trocar sem redefinir constante,
 * mesmo padrao do CASTELLO_EMAIL_DIR em lib/email.php.
 */
function crm_token(): string
{
    $doAmbiente = getenv('CASTELLO_AGENDOR_TOKEN');
    if (is_string($doAmbiente) && trim($doAmbiente) !== '') {
        return trim($doAmbiente);
    }
    return defined('CASTELLO_AGENDOR_TOKEN') ? trim((string) CASTELLO_AGENDOR_TOKEN) : '';
}

/**
 * Telefone com DDI, para contact.whatsapp.
 *
 * E o formato que faz o botao de WhatsApp funcionar dentro do Agendor.
 */
function crm_whatsapp_ddi(string $bruto): string
{
    $digitos = preg_replace('/\D+/', '', $bruto) ?? '';
    $tamanho = strlen($digitos);

    if ($tamanho === 10 || $tamanho === 11) {
        return '55' . $digitos;
    }
    if (($tamanho === 12 || $tamanho === 13) && str_starts_with($digitos, '55')) {
        return $digitos;
    }
    return '';
}

/**
 * Telefone sem DDI, para contact.mobile e para a busca de duplicata.
 *
 * Medido na conta real em 2026-09-10: GET /people?phone= devolve vazio quando
 * a busca leva o 55 na frente. Por isso a busca vai por aqui, e por isso o
 * mobile guarda este formato: e ele que faz o site reencontrar as pessoas que
 * ele mesmo criou.
 */
function crm_whatsapp_busca(string $bruto): string
{
    $digitos = preg_replace('/\D+/', '', $bruto) ?? '';

    if ((strlen($digitos) === 12 || strlen($digitos) === 13) && str_starts_with($digitos, '55')) {
        $digitos = substr($digitos, 2);
    }
    return (strlen($digitos) === 10 || strlen($digitos) === 11) ? $digitos : '';
}

/** Texto do lead, sempre string, sempre aparado. */
function crm_campo(array $lead, string $chave): string
{
    $valor = $lead[$chave] ?? '';
    return is_scalar($valor) ? trim((string) $valor) : '';
}

/** Corpo do POST /people. */
function crm_payload_pessoa(array $lead): array
{
    $payload = [
        'name'       => crm_campo($lead, 'nome'),
        'leadOrigin' => (int) config_ler('crm_origem', '2656389'),
        'category'   => (int) config_ler('crm_categoria', '4187395'),
    ];

    $contato = [];
    $comDdi = crm_whatsapp_ddi(crm_campo($lead, 'whatsapp'));
    if ($comDdi !== '') {
        $contato['whatsapp'] = $comDdi;
    }
    $semDdi = crm_campo($lead, 'whatsapp');
    if (crm_whatsapp_busca($semDdi) !== '') {
        $contato['mobile'] = $semDdi;
    }
    if ($contato !== []) {
        $payload['contact'] = $contato;
    }

    /* Campo vazio fica de fora: string vazia sobrescreveria dado bom. */
    $customizados = [];
    $cidade = crm_campo($lead, 'cidade');
    if ($cidade !== '') {
        $customizados[CRM_CAMPO_CIDADE] = $cidade;
    }
    $prazo = crm_campo($lead, 'prazo');
    if ($prazo !== '') {
        $customizados[CRM_CAMPO_PRAZO] = $prazo;
    }

    /* A origem e sempre Site; a campanha e que muda. Ver secao 3.5 da spec. */
    $anuncio = crm_campo($lead, 'utm_campaign');
    if ($anuncio === '') {
        $anuncio = crm_campo($lead, 'utm_source');
    }
    $customizados[CRM_CAMPO_ANUNCIO] = $anuncio !== '' ? $anuncio : 'direto';

    $payload['customFields'] = $customizados;

    $responsavel = trim((string) config_ler('crm_responsavel', ''));
    if ($responsavel !== '') {
        $payload['ownerUser'] = $responsavel;
    }

    return $payload;
}

/**
 * Titulo do negocio: "[SITE] - <prazo> - <Nome>".
 *
 * O prazo e o criterio de qualificacao da Castello e por isso ocupa o meio,
 * igual aos leads do Meta. Sem prazo, a busca entra no lugar; sem os dois, o
 * titulo perde o segmento em vez de ficar com traco solto. O corte protege
 * marcador e prazo, que e o que se le na coluna do funil.
 */
function crm_titulo_negocio(array $lead): string
{
    $marcador = trim((string) config_ler('crm_marcador', '[SITE]'));
    $meio = crm_campo($lead, 'prazo');
    if ($meio === '') {
        $meio = crm_campo($lead, 'busca');
    }
    $nome = crm_campo($lead, 'nome');

    $prefixo = $marcador . ($meio !== '' ? ' - ' . $meio : '') . ' - ';
    $sobra = CRM_TITULO_MAX - mb_strlen($prefixo);

    return $prefixo . mb_substr($nome, 0, max(1, $sobra));
}

/** Corpo em texto do negocio. A conta nao tem campo customizado de negocio. */
function crm_descricao_negocio(array $lead): string
{
    $linhas = ['Lead do site.', ''];

    $rotulos = [
        'busca'    => 'O que busca',
        'modelo'   => 'Modelo de interesse',
        'cidade'   => 'Cidade / regiao do terreno',
        'prazo'    => 'Prazo para iniciar',
    ];
    foreach ($rotulos as $campo => $rotulo) {
        $valor = crm_campo($lead, $campo);
        if ($valor !== '') {
            $linhas[] = $rotulo . ': ' . $valor;
        }
    }

    $mensagem = crm_campo($lead, 'mensagem');
    if ($mensagem !== '') {
        $linhas[] = '';
        $linhas[] = 'Mensagem:';
        $linhas[] = $mensagem;
    }

    $origem = [];
    $pagina = crm_campo($lead, 'pagina');
    if ($pagina !== '') {
        $origem[] = 'Pagina: ' . $pagina;
    }
    $campanha = [];
    foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as $utm) {
        $valor = crm_campo($lead, $utm);
        if ($valor !== '') {
            $campanha[] = $valor;
        }
    }
    if ($campanha !== []) {
        $origem[] = 'Campanha: ' . implode(' / ', $campanha);
    }
    $quando = crm_campo($lead, 'criado_em');
    if ($quando !== '') {
        $origem[] = 'Enviado em: ' . $quando;
    }
    if ($origem !== []) {
        $linhas[] = '';
        $linhas[] = 'Origem';
        foreach ($origem as $linha) {
            $linhas[] = $linha;
        }
    }

    return implode("\n", $linhas);
}

/** Corpo do POST /people/{id}/deals. */
function crm_payload_negocio(array $lead): array
{
    $payload = [
        'title'       => crm_titulo_negocio($lead),
        'description' => crm_descricao_negocio($lead),
        'funnel'      => (int) config_ler('crm_funil', '904296'),
        'dealStage'   => (int) config_ler('crm_etapa', '1'),
    ];

    $responsavel = trim((string) config_ler('crm_responsavel', ''));
    if ($responsavel !== '') {
        $payload['ownerUser'] = $responsavel;
    }

    return $payload;
}
