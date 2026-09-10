<?php
/**
 * Trilha do carrossel de avaliacoes do Google. Espera: nada.
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/conteudo.php';

$lista_avaliacoes = avaliacoes();
?>
<div class="reviews__track" id="reviewsTrack" tabindex="0">
<?php foreach ($lista_avaliacoes as $a):
    $estrelas = max(1, min(5, (int) $a['estrelas']));
    $inicial  = mb_strtoupper(mb_substr((string) $a['nome'], 0, 1));
?>
        <article class="review">
          <div class="review__top"><div class="review__stars"><?= str_repeat('★', $estrelas) ?></div><svg class="g-logo" aria-label="Avaliação no Google"><use href="#ico-google" /></svg></div>
          <p><?= e($a['texto']) ?></p>
          <footer><span class="review__avatar"><?= e($inicial) ?></span><span class="review__who"><strong><?= e($a['nome']) ?></strong><span>Avaliação no Google</span></span></footer>
        </article>
<?php endforeach; ?>
      </div>
