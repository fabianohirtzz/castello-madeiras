/* Gaveta do menu no celular. No desktop a coluna e fixa e nada disso roda. */
(function () {
  'use strict';

  var app = document.querySelector('.p-app');
  if (!app) { return; }

  app.classList.add('tem-js');

  var botao = document.getElementById('pAbrir');
  var lado  = document.getElementById('pLado');
  var veu   = document.getElementById('pVeu');
  if (!botao || !lado || !veu) { return; }

  function mostrar(aberto) {
    app.classList.toggle('is-menu-aberto', aberto);
    botao.setAttribute('aria-expanded', aberto ? 'true' : 'false');
    veu.hidden = !aberto;
    if (aberto) {
      var primeiro = lado.querySelector('.p-lado__link');
      if (primeiro) { primeiro.focus(); }
    } else {
      botao.focus();
    }
  }

  botao.addEventListener('click', function () {
    mostrar(!app.classList.contains('is-menu-aberto'));
  });

  veu.addEventListener('click', function () { mostrar(false); });

  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape' && app.classList.contains('is-menu-aberto')) { mostrar(false); }
  });

  lado.addEventListener('click', function (ev) {
    if (ev.target.closest('a')) { app.classList.remove('is-menu-aberto'); }
  });
}());

/* Reordenar arrastando, com ponteiro unico: funciona no mouse e no toque. */
(function () {
  'use strict';

  var lista = document.getElementById('pLista');
  if (!lista) { return; }

  var tabela = lista.getAttribute('data-tabela') || '';
  var csrf   = lista.getAttribute('data-csrf') || '';
  var aviso  = document.getElementById('pOrdemAviso');
  var item   = null;

  function itens() {
    return Array.prototype.slice.call(lista.querySelectorAll('.p-item'));
  }

  function vizinhoAbaixoDe(y) {
    var todos = itens();
    for (var i = 0; i < todos.length; i++) {
      var caixa = todos[i].getBoundingClientRect();
      if (y < caixa.top + caixa.height / 2) { return todos[i]; }
    }
    return null;
  }

  function mostrar(texto, ok) {
    if (!aviso) { return; }
    aviso.textContent = texto;
    aviso.className = ok ? 'p-aviso' : 'p-aviso p-aviso--erro';
    aviso.hidden = false;
  }

  function gravar() {
    var ids = itens().map(function (li) {
      return parseInt(li.getAttribute('data-id'), 10);
    });

    fetch('acoes/ordem.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ csrf: csrf, tela: tabela, ids: ids })
    })
      .then(function (resposta) { return resposta.json(); })
      .then(function (dados) {
        if (dados && dados.ok) {
          mostrar('Ordem salva.', true);
        } else {
          mostrar('Não consegui salvar a ordem. Recarregue a página e tente de novo.', false);
        }
      })
      .catch(function () {
        mostrar('Não consegui salvar a ordem. Recarregue a página e tente de novo.', false);
      });
  }

  lista.addEventListener('pointerdown', function (ev) {
    var pega = ev.target && ev.target.closest ? ev.target.closest('.p-pega') : null;
    if (!pega) { return; }

    item = pega.closest('.p-item');
    if (!item) { return; }

    item.classList.add('is-arrastando');
    pega.setPointerCapture(ev.pointerId);
    ev.preventDefault();
  });

  /* Lista mais alta que a tela: perto da borda de cima ou de baixo, a pagina
     rola sozinha enquanto o dedo fica parado, senao nao da para levar um item
     do fim ao topo num gesto so no celular. */
  var rolagem = 0, rolando = null;
  function rolar() {
    if (!item || rolagem === 0) { rolando = null; return; }
    window.scrollBy(0, rolagem);
    rolando = requestAnimationFrame(rolar);
  }
  function ajustarRolagem(y) {
    var borda = 70, h = window.innerHeight;
    rolagem = y < borda ? -Math.ceil((borda - y) / 5) : (y > h - borda ? Math.ceil((y - (h - borda)) / 5) : 0);
    if (rolagem !== 0 && rolando === null) { rolando = requestAnimationFrame(rolar); }
  }

  lista.addEventListener('pointermove', function (ev) {
    if (!item) { return; }
    ev.preventDefault();
    ajustarRolagem(ev.clientY);

    var vizinho = vizinhoAbaixoDe(ev.clientY);
    if (vizinho === item) { return; }

    if (vizinho) {
      lista.insertBefore(item, vizinho);
    } else {
      lista.appendChild(item);
    }
  });

  function soltar() {
    rolagem = 0;
    if (!item) { return; }
    item.classList.remove('is-arrastando');
    item = null;
    gravar();
  }

  lista.addEventListener('pointerup', soltar);
  lista.addEventListener('pointercancel', soltar);
})();
