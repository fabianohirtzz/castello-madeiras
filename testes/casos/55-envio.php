<?php
declare(strict_types=1);

/**
 * Costura do formulario: o formulario (no modal ou embutido na pagina de
 * contato) envia para enviar.php pelo js/formulario.js, e so por ele.
 *
 * O teste de ponta a ponta fala com o servidor local (php -S localhost:8000
 * -t public_html). Sem o servidor no ar, ele e pulado, nao falha: os demais
 * testes cobrem a marcacao e os scripts sem rede.
 */

require_once site() . '/lib/conteudo.php';

banco_com_conteudo();

const ENVIO_BASE = 'http://localhost:8000';

function envio_servidor_no_ar(): bool
{
    $s = @fsockopen('localhost', 8000, $n, $t, 0.5);
    if (!is_resource($s)) {
        return false;
    }
    fclose($s);
    return true;
}

/** GET ou POST com cookie jar, devolvendo [http, corpo]. */
function envio_pedir(string $caminho, string $jar, ?array $post = null): array
{
    $ch = curl_init(ENVIO_BASE . $caminho);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR      => $jar,
        CURLOPT_COOKIEFILE     => $jar,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $corpo = (string) curl_exec($ch);
    $http  = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$http, $corpo];
}

teste('as cinco paginas carregam o formulario.js depois do main.js', function (): void {
    foreach (['index.php', 'casa-pronta.php', 'flex.php', 'portfolio.php', 'contato.php'] as $arquivo) {
        $html = render(site() . '/' . $arquivo);
        contem('<script src="js/main.js?v=14"></script>', $html, $arquivo);
        contem('<script src="js/formulario.js?v=2" defer></script>', $html, $arquivo);
        verdade(strpos($html, '<script src="js/main.js') < strpos($html, '<script src="js/formulario.js'), "$arquivo: formulario.js vem depois do main.js");
        contem('id="quoteForm" method="post" action="enviar.php"', $html, $arquivo);
    }
});

teste('so uma rotina responde ao envio: a do formulario.js', function (): void {
    $main = (string) file_get_contents(site() . '/js/main.js');
    $form = (string) file_get_contents(site() . '/js/formulario.js');

    nao_contem("addEventListener('submit'", $main, 'main.js nao pode mais tratar o submit');
    nao_contem('FORM_ENDPOINT', $main);
    nao_contem('_gotcha', $main);
    nao_contem('qOpenedAt', $main, 'o time-trap saiu do main.js');
    nao_contem('qShowDone', $main, 'a tela de sucesso e do formulario.js');

    igual(1, substr_count($form, "addEventListener('submit'"), 'um submit so, no formulario.js');
    nao_contem('stopPropagation', $form, 'o guarda de captura existia so para calar o handler antigo');
    nao_contem('_gotcha', $form, 'so o honeypot empresa permanece');

    // o que continua no main.js, porque e interface e nao envio
    contem('data-quote-open', $main);
    contem('qToggleModelo', $main, 'campo condicional de modelo');
    contem("e.key === 'Escape'", $main, 'fechar com Esc');
    contem("e.key !== 'Tab'", $main, 'laco de foco');
    contem("replace(/\\D/g, '')", $main, 'mascara de WhatsApp');
    contem('quoteBack', $main, 'voltar da tela de sucesso');
});

teste('envio de lead pelo servidor local grava e responde ok', function (): void {
    if (!envio_servidor_no_ar()) {
        pular('servidor local fora do ar: php -S localhost:8000 -t public_html');
    }
    $jar = tempnam(sys_get_temp_dir(), 'castello-cookies-');

    try {
        [$http, $corpo] = envio_pedir('/csrf.php', $jar);
        igual(200, $http, 'csrf.php responde');
        $token = (string) (json_decode($corpo, true)['token'] ?? '');
        verdade(strlen($token) === 64, 'token utilizavel: ' . $corpo);

        [$http, $corpo] = envio_pedir('/enviar.php', $jar, [
            'nome'     => 'Teste Costura',
            'whatsapp' => '(48) 99824-4494',
            'busca'    => 'Ainda estou pesquisando',
            'cidade'   => 'Tubarão / SC',
            'mensagem' => 'Lead de teste da costura. Pode apagar.',
            'pagina'   => '/flex.php',
            'utm_source'   => 'teste',
            'utm_campaign' => 'costura',
            'empresa'  => '',
            'ts'       => (string) ((time() - 10) * 1000),
            'csrf'     => $token,
        ]);
        igual(200, $http, 'enviar.php responde 200: ' . $corpo);
        $dados = json_decode($corpo, true);
        verdade(is_array($dados) && ($dados['ok'] ?? null) === true, 'envio nao devolveu ok: ' . $corpo);
        verdade((int) ($dados['id'] ?? 0) > 0, 'lead nao foi gravado: ' . $corpo);

        // sem cookie de sessao o token nao vale: 419
        [$http] = envio_pedir('/enviar.php', tempnam(sys_get_temp_dir(), 'castello-cookies-'), [
            'nome' => 'Sem sessao', 'whatsapp' => '48998244494', 'busca' => 'x',
            'ts' => (string) ((time() - 10) * 1000), 'csrf' => $token, 'empresa' => '',
        ]);
        igual(419, $http, 'token sem a sessao dona dele e recusado');
    } finally {
        @unlink($jar);
    }
});
