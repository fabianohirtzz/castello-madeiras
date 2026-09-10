<?php
/**
 * Abas verticais do FAQ. Espera: string $contexto ('geral' ou 'flex').
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/conteudo.php';

$contexto  = $contexto ?? 'geral';
$lista_faq = faq($contexto);
$rotulo_faq = $contexto === 'flex' ? 'Perguntas sobre a Castelo Flex' : 'Perguntas frequentes';
?>
<div class="faq__board reveal" id="faqTabs">
        <div class="faq__tablist" role="tablist" aria-orientation="vertical" aria-label="<?= e($rotulo_faq) ?>">
<?php foreach ($lista_faq as $i => $f): $n = $i + 1; $primeiro = $i === 0; ?>
          <button class="faq__tab<?= $primeiro ? ' is-active' : '' ?>" type="button" role="tab" id="faq-tab<?= $n ?>" aria-selected="<?= $primeiro ? 'true' : 'false' ?>" aria-controls="faq-panel<?= $n ?>"<?= $primeiro ? '' : ' tabindex="-1"' ?> aria-label="<?= e($f['pergunta']) ?>" title="<?= e($f['pergunta']) ?>">
            <span class="faq__ico"><?= icone_faq((string) $f['icone']) ?></span>
          </button>
<?php endforeach; ?>
        </div>

        <div class="faq__panels">
<?php foreach ($lista_faq as $i => $f): $n = $i + 1; $primeiro = $i === 0; ?>
          <div class="faq__content<?= $primeiro ? ' is-active' : '' ?>" role="tabpanel" id="faq-panel<?= $n ?>" aria-labelledby="faq-tab<?= $n ?>" tabindex="0"<?= $primeiro ? '' : ' hidden' ?>>
            <h3 class="faq__content-title"><?= e($f['pergunta']) ?></h3>
            <p class="faq__content-body"><?= e($f['resposta']) ?></p>
          </div>
<?php endforeach; ?>
        </div>
      </div>
