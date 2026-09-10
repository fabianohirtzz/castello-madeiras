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

  lista.addEventListener('pointermove', function (ev) {
    if (!item) { return; }
    ev.preventDefault();

    var vizinho = vizinhoAbaixoDe(ev.clientY);
    if (vizinho === item) { return; }

    if (vizinho) {
      lista.insertBefore(item, vizinho);
    } else {
      lista.appendChild(item);
    }
  });

  function soltar() {
    if (!item) { return; }
    item.classList.remove('is-arrastando');
    item = null;
    gravar();
  }

  lista.addEventListener('pointerup', soltar);
  lista.addEventListener('pointercancel', soltar);
})();
