<?php
declare(strict_types=1);

/**
 * Politica de privacidade. RASCUNHO ate as constantes CASTELLO_LEGAL_* de
 * lib/conteudo.php serem preenchidas: enquanto faltar alguma, a pagina fica
 * com noindex, os campos aparecem marcados e nada no site aponta para ela.
 * Preenchidas, legal_pronta() liga o link do rodape e o do formulario.
 */

require_once __DIR__ . '/lib/conteudo.php';

$pronta = legal_pronta();

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Política de privacidade | Castello Casas de Madeira</title>
  <meta name="description" content="Como a Castello Casas de Madeira coleta, usa e protege os seus dados pessoais, e como exercer os seus direitos pela LGPD." />
<?php if (!$pronta): ?>
  <meta name="robots" content="noindex" />
<?php endif; ?>
  <link rel="canonical" href="<?= CASTELLO_URL . '/politica-de-privacidade.php' ?>" />
  <link rel="icon" type="image/png" href="images/icone-colorido.png" />

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet" />

  <link rel="stylesheet" href="css/style.css?v=48" />
<?php include __DIR__ . '/partials/medicao.php'; ?>
</head>
<body>

<?php $pagina = 'privacidade'; include __DIR__ . '/partials/nav.php'; ?>

  <main class="legal" id="topo">
    <div class="legal__corpo">
      <span class="eyebrow">Seus dados</span>
      <h1 class="legal__titulo">Política de privacidade</h1>
      <p class="legal__data">Última atualização: novembro de 2026</p>

      <p>A Castello Casas de Madeira respeita a sua privacidade. Este texto explica, de forma direta, quais dados pessoais o nosso site coleta, para que usamos, com quem compartilhamos e como você exerce os seus direitos, conforme a Lei Geral de Proteção de Dados (Lei nº 13.709/2018, a LGPD).</p>

      <h2>1. Quem cuida dos seus dados</h2>
      <p>O controlador dos dados é <?= legal_campo(CASTELLO_LEGAL_RAZAO, 'razão social') ?>, CNPJ <?= legal_campo(CASTELLO_LEGAL_CNPJ, 'CNPJ') ?>, com sede na <?= e(CASTELLO_ENDERECO_RUA) ?>, <?= e(CASTELLO_ENDERECO_BAIRRO) ?>, Tubarão, SC, CEP <?= e(CASTELLO_ENDERECO_CEP) ?>.</p>
      <p>Para qualquer assunto sobre os seus dados, fale com <?= legal_campo(CASTELLO_LEGAL_ENCARREGADO, 'nome do responsável') ?> pelo e-mail <?= legal_campo(CASTELLO_LEGAL_EMAIL, 'e-mail de contato') ?>.</p>

      <h2>2. Quais dados coletamos</h2>
      <p><strong>Os que você informa no formulário de orçamento:</strong> nome, WhatsApp, o tipo de casa e o modelo de interesse, a metragem, a garagem, o prazo, a cidade e a mensagem que escrever.</p>
      <p><strong>Os que vêm junto com o pedido:</strong> a página do site de onde ele foi enviado, o endereço de onde você chegou e os dados da campanha de divulgação, quando você veio por um anúncio ou link marcado.</p>
      <p><strong>Os de navegação:</strong> quando as ferramentas de medição descritas no item 6 estão ativas, elas registram informações como páginas visitadas, tipo de aparelho, navegador e localização aproximada.</p>
      <p>Pedimos que você não escreva na mensagem dados sensíveis, como informações de saúde, nem dados de terceiros.</p>

      <h2>3. Para que usamos</h2>
      <ul>
        <li>Responder ao seu pedido e preparar o seu orçamento.</li>
        <li>Entrar em contato com você pelo WhatsApp ou telefone informado.</li>
        <li>Organizar o atendimento da nossa equipe comercial.</li>
        <li>Entender como o site é usado e quais campanhas funcionam, para melhorar o site e a nossa comunicação.</li>
        <li>Cumprir obrigações legais e nos defender em eventuais processos.</li>
      </ul>

      <h2>4. Em que a lei nos permite usar</h2>
      <p>Tratamos os seus dados com base na execução de procedimentos preliminares ao contrato, quando você pede um orçamento; no legítimo interesse da Castello, para organizar o atendimento e melhorar os serviços; no cumprimento de obrigações legais; e no seu consentimento, quando ele for necessário.</p>

      <h2>5. Com quem compartilhamos</h2>
      <p>Não vendemos os seus dados. Eles podem passar por empresas que prestam serviços para a Castello, sempre para as finalidades acima:</p>
      <ul>
        <li><strong>Hospedagem do site:</strong> onde o site e os pedidos ficam guardados.</li>
        <li><strong>Agendor:</strong> sistema que a nossa equipe comercial usa para acompanhar os pedidos.</li>
        <li><strong>Serviço de e-mail:</strong> a nossa equipe recebe um aviso a cada pedido novo.</li>
        <li><strong>Google:</strong> mapa e fontes de texto do site e, quando ativo, o Google Analytics.</li>
        <li><strong>Meta:</strong> quando ativo, o pixel que mede os resultados dos anúncios no Facebook e no Instagram.</li>
      </ul>
      <p>Também podemos compartilhar dados quando uma lei ou uma autoridade competente exigir. Alguns desses serviços podem guardar dados fora do Brasil, seguindo as regras da LGPD para transferência internacional.</p>

      <h2>6. Cookies e medição</h2>
      <p>Cookies são pequenos arquivos guardados no seu navegador.</p>
      <ul>
        <li><strong>Necessários:</strong> um cookie de sessão protege o formulário contra envios falsos. O site também guarda, só enquanto a aba estiver aberta, os dados da campanha que trouxe você até aqui.</li>
        <li><strong>De medição:</strong> o Google Analytics mostra, de forma agregada, como o site é usado.</li>
        <li><strong>De publicidade:</strong> o pixel do Meta mede os resultados dos anúncios e pode ser usado para mostrar anúncios a quem visitou o site.</li>
        <li><strong>De terceiros:</strong> o mapa do Google, incorporado ao site, pode gravar os seus próprios cookies.</li>
      </ul>
      <p>Você pode apagar ou bloquear cookies nas configurações do seu navegador. Bloquear os necessários pode impedir o envio do formulário.</p>

      <h2>7. Por quanto tempo guardamos</h2>
      <p>Guardamos os dados do seu pedido por <?= legal_campo(CASTELLO_LEGAL_PRAZO, 'prazo de guarda') ?> depois do último contato, ou pelo tempo necessário para cumprir obrigações legais. Se você se tornar cliente, os dados do contrato seguem os prazos exigidos por lei. Depois disso, os dados são apagados ou anonimizados.</p>

      <h2>8. Como protegemos</h2>
      <p>O site usa conexão criptografada (HTTPS), o acesso aos pedidos é restrito à equipe autorizada e com senha, e os dados ficam em área do servidor que não é acessível pela internet. Nenhum sistema é totalmente imune a incidentes; se ocorrer algum que traga risco relevante, avisaremos você e a autoridade competente, como manda a lei.</p>

      <h2>9. Seus direitos</h2>
      <p>Pela LGPD, você pode pedir, a qualquer momento e sem custo:</p>
      <ul>
        <li>a confirmação de que tratamos os seus dados e o acesso a eles;</li>
        <li>a correção de dados incompletos ou desatualizados;</li>
        <li>a anonimização, o bloqueio ou a eliminação de dados desnecessários;</li>
        <li>a portabilidade dos dados;</li>
        <li>a eliminação dos dados tratados com o seu consentimento e a revogação desse consentimento;</li>
        <li>informações sobre com quem compartilhamos os seus dados;</li>
        <li>a oposição a um tratamento que descumpra a lei.</li>
      </ul>
      <p>Para isso, escreva para <?= legal_campo(CASTELLO_LEGAL_EMAIL, 'e-mail de contato') ?>. Você também pode reclamar à Autoridade Nacional de Proteção de Dados (ANPD).</p>

      <h2>10. Crianças e adolescentes</h2>
      <p>O site é destinado a adultos e não coleta dados de crianças e adolescentes de propósito.</p>

      <h2>11. Mudanças nesta política</h2>
      <p>Podemos atualizar este texto quando o site ou a lei mudarem. A data no início da página mostra a versão em vigor.</p>
    </div>
  </main>

<?php include __DIR__ . '/partials/rodape.php'; ?>

<?php include __DIR__ . '/partials/modal.php'; ?>

  <script src="js/main.js?v=25"></script>
  <script src="js/formulario.js?v=5" defer></script>
</body>
</html>
