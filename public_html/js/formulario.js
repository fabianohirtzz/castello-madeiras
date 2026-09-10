/* Castello - formulario de orcamento.
   Carregado depois de js/main.js, em arquivo proprio.

   Aqui vive tudo que e envio: token de CSRF, campos ocultos, utm, time-trap,
   validacao, POST em enviar.php e a tela de sucesso. Abrir e fechar o modal,
   foco preso, mascara do WhatsApp e o campo condicional de modelo ficam com o
   main.js, que e so interface.

   As funcoes puras ficam em window.CastelloFormulario (e em module.exports,
   quando rodando no Node) para poderem ser testadas sem navegador. */
(function () {
  'use strict';

  var ENDPOINT = 'enviar.php';
  var CSRF_ENDPOINT = 'csrf.php';
  var CHAVE_UTM = 'castello_utm';
  var CAMPOS_UTM = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
  var TEMPO_MINIMO_MS = 3000;
  var WA_PHONE = '5548998244494';
  var ROTULOS = [
    ['nome', 'Nome'],
    ['whatsapp', 'WhatsApp'],
    ['busca', 'O que busca'],
    ['modelo', 'Modelo de interesse'],
    ['cidade', 'Cidade/regiao'],
    ['mensagem', 'Mensagem']
  ];

  var api = {};

  /* ---------- funcoes puras (testadas por testes/formulario.test.js) ---------- */

  api.utmDaUrl = function (busca) {
    var achados = {};
    String(busca == null ? '' : busca).replace(/^\?/, '').split('&').forEach(function (par) {
      if (!par) return;
      var corte = par.indexOf('=');
      var nome = corte < 0 ? par : par.slice(0, corte);
      var valor = corte < 0 ? '' : par.slice(corte + 1);
      try {
        nome = decodeURIComponent(nome.replace(/\+/g, ' '));
        valor = decodeURIComponent(valor.replace(/\+/g, ' '));
      } catch (e) { return; }
      if (CAMPOS_UTM.indexOf(nome) >= 0 && valor) achados[nome] = valor.slice(0, 200);
    });
    return achados;
  };

  api.utmLer = function (store) {
    try {
      var cru = store && store.getItem(CHAVE_UTM);
      var obj = cru ? JSON.parse(cru) : null;
      if (!obj || typeof obj !== 'object') return {};
      var limpo = {};
      CAMPOS_UTM.forEach(function (c) {
        if (typeof obj[c] === 'string' && obj[c]) limpo[c] = obj[c];
      });
      return limpo;
    } catch (e) {
      return {};
    }
  };

  api.utmGravar = function (obj, store) {
    try {
      store.setItem(CHAVE_UTM, JSON.stringify(obj));
      return true;
    } catch (e) {
      return false;
    }
  };

  /* Parametro na URL sempre sobrescreve o que estava guardado. */
  api.utmResolver = function (busca, store) {
    var guardadas = api.utmLer(store);
    var daUrl = api.utmDaUrl(busca);
    var fim = {};
    CAMPOS_UTM.forEach(function (c) { if (guardadas[c]) fim[c] = guardadas[c]; });
    CAMPOS_UTM.forEach(function (c) { if (daUrl[c]) fim[c] = daUrl[c]; });
    if (Object.keys(daUrl).length) api.utmGravar(fim, store);
    return fim;
  };

  /* Mesma mascara que o js/main.js aplica hoje: (48) 99824-4494. */
  api.mascaraWhatsapp = function (valor) {
    var d = String(valor == null ? '' : valor).replace(/\D/g, '').slice(0, 11);
    if (d.length <= 2) return d;
    if (d.length <= 7) return '(' + d.slice(0, 2) + ') ' + d.slice(2);
    var corte = d.length > 10 ? 7 : 6;
    return '(' + d.slice(0, 2) + ') ' + d.slice(2, corte) + '-' + d.slice(corte);
  };

  /* Mesmas regras do enviar.php, para o erro aparecer antes da viagem. */
  api.validar = function (dados) {
    var ruins = [];
    var nome = String((dados && dados.nome) || '').trim();
    var digitos = String((dados && dados.whatsapp) || '').replace(/\D/g, '');
    var busca = String((dados && dados.busca) || '').trim();
    if (nome.length < 2) ruins.push('nome');
    if (digitos.length < 10 || digitos.length > 13) ruins.push('whatsapp');
    if (!busca) ruins.push('busca');
    return ruins;
  };

  api.mensagemDeErro = function (campos) {
    var lista = campos || [];
    if (lista.indexOf('nome') >= 0) return 'Preencha seu nome.';
    if (lista.indexOf('whatsapp') >= 0) return 'Informe um WhatsApp com DDD.';
    if (lista.indexOf('busca') >= 0) return 'Escolha o que voce busca.';
    return 'Confira os campos e tente de novo.';
  };

  api.resumoWhatsapp = function (dados) {
    return ROTULOS.map(function (par) {
      var valor = String((dados && dados[par[0]]) || '').trim();
      return valor ? par[1] + ': ' + valor : null;
    }).filter(Boolean).join('\n');
  };

  /* ---------- token de CSRF ---------- */

  var tokenGuardado = '';

  api.tokenDaResposta = function (dados) {
    if (!dados || typeof dados !== 'object') return '';
    var t = dados.token;
    return (typeof t === 'string' && t.length >= 8) ? t : '';
  };

  api.esquecerToken = function () {
    tokenGuardado = '';
  };

  /* buscar devolve uma promessa com o objeto que o csrf.php respondeu.
     O token vale pela sessao de pagina inteira: abrir, fechar e reabrir o
     modal nao busca de novo. Falha nao e guardada, para a proxima abertura
     tentar outra vez. */
  api.obterToken = function (buscar) {
    if (tokenGuardado) return Promise.resolve(tokenGuardado);
    return Promise.resolve()
      .then(function () { return buscar(); })
      .then(function (dados) {
        tokenGuardado = api.tokenDaResposta(dados);
        return tokenGuardado;
      })
      .catch(function () { return ''; });
  };

  if (typeof window !== 'undefined') window.CastelloFormulario = api;
  if (typeof module !== 'undefined' && module.exports) module.exports = api;

  /* ---------- ligacao com a pagina ---------- */

  if (typeof document === 'undefined') return;

  var form = document.getElementById('quoteForm');
  if (!form) return;

  var status = document.getElementById('quoteStatus');
  var botao = document.getElementById('quoteSubmit');
  var painelDone = document.getElementById('quoteDone');
  var doneMsg = document.getElementById('quoteDoneMsg');
  var doneTitulo = painelDone ? painelDone.querySelector('h3') : null;
  var wppLink = document.getElementById('quoteWppLink');
  var abertoEm = Date.now();

  /* Guarda a utm logo na chegada, mesmo que o visitante nunca abra o modal:
     ela precisa sobreviver a navegacao entre a home e a pagina Flex. */
  api.utmResolver(window.location.search, window.sessionStorage);

  function dizer(texto) {
    if (status) status.textContent = texto || '';
  }

  function campoOculto(nome, valor) {
    var el = form.querySelector('[name="' + nome + '"]');
    if (!el) {
      el = document.createElement('input');
      el.type = 'hidden';
      el.name = nome;
      form.appendChild(el);
    }
    el.value = valor == null ? '' : String(valor);
    return el;
  }

  function preencherOcultos() {
    var utm = api.utmResolver(window.location.search, window.sessionStorage);
    campoOculto('pagina', window.location.pathname || '/');
    campoOculto('referrer', document.referrer || '');
    CAMPOS_UTM.forEach(function (c) { campoOculto(c, utm[c] || ''); });
    campoOculto('ts', String(abertoEm));
  }

  /* credentials same-origin e obrigatorio: sem o cookie de sessao o token que
     volta nao bate com o que o enviar.php espera. */
  function buscarTokenNoServidor() {
    return fetch(CSRF_ENDPOINT, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
      cache: 'no-store'
    }).then(function (resposta) {
      if (!resposta.ok) throw new Error('http ' + resposta.status);
      return resposta.json();
    });
  }

  /* Chamada na abertura do modal, nunca no carregamento da pagina: quem so le
     o site nao faz requisicao nem recebe cookie de sessao. */
  function garantirToken() {
    return api.obterToken(buscarTokenNoServidor).then(function (token) {
      campoOculto('csrf', token);
      if (!token) {
        dizer('Nao deu pra preparar o envio agora. Tente de novo em alguns segundos ou chame no WhatsApp.');
      }
      return token;
    });
  }

  function limparErros() {
    ['q-nome', 'q-whatsapp', 'q-busca'].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.classList.remove('is-error');
    });
  }

  function marcarErros(campos) {
    var mapa = { nome: 'q-nome', whatsapp: 'q-whatsapp', busca: 'q-busca' };
    (campos || []).forEach(function (campo) {
      var el = document.getElementById(mapa[campo]);
      if (el) el.classList.add('is-error');
    });
    var primeiro = document.getElementById(mapa[(campos || [])[0]]);
    if (primeiro) primeiro.focus({ preventScroll: true });
  }

  function restaurar(rotulo) {
    if (botao) {
      botao.disabled = false;
      botao.textContent = rotulo;
    }
  }

  function mostrarDone(entregue, dados) {
    if (!painelDone) return;
    form.hidden = true;
    painelDone.hidden = false;
    if (doneTitulo) doneTitulo.textContent = entregue ? 'Pedido enviado' : 'Pedido pronto pra enviar';
    if (doneMsg) {
      doneMsg.textContent = entregue
        ? 'Recebemos seu pedido. A Castello responde em ate 1 dia util. Se preferir adiantar, chame no WhatsApp.'
        : 'Nao deu pra enviar pelo site agora. Toque no botao abaixo para mandar seu pedido no WhatsApp da Castello.';
    }
    if (wppLink) {
      wppLink.href = 'https://wa.me/' + WA_PHONE + '?text=' +
        encodeURIComponent('Ola! Quero um orcamento de casa de madeira.\n\n' + api.resumoWhatsapp(dados));
      wppLink.focus({ preventScroll: true });
    }
  }

  function dadosDoForm(fd) {
    var saida = {};
    ROTULOS.forEach(function (par) {
      saida[par[0]] = (fd.get(par[0]) || '').toString();
    });
    return saida;
  }

  function postar(fd, dados) {
    var rotulo = botao ? botao.textContent : '';
    if (botao) {
      botao.disabled = true;
      botao.textContent = 'Enviando...';
    }
    dizer('');

    fetch(ENDPOINT, {
      method: 'POST',
      body: fd,
      headers: { Accept: 'application/json' },
      credentials: 'same-origin'
    })
      .then(function (resposta) {
        return resposta.text().then(function (texto) {
          var json = null;
          try { json = JSON.parse(texto); } catch (e) { json = null; }
          return { http: resposta.status, dados: json };
        });
      })
      .then(function (r) {
        if (r.http === 200 && r.dados && r.dados.ok === true) {
          mostrarDone(true, dados);
          return;
        }
        if (r.http === 422 && r.dados && r.dados.erro === 'campos') {
          restaurar(rotulo);
          limparErros();
          marcarErros(r.dados.campos || []);
          dizer(api.mensagemDeErro(r.dados.campos || []));
          return;
        }
        if (r.http === 419) {
          /* A sessao virou. Joga fora o token guardado, busca outro e pede
             para o visitante mandar de novo. */
          restaurar(rotulo);
          api.esquecerToken();
          garantirToken();
          dizer('A pagina ficou aberta tempo demais. Toque em enviar de novo.');
          return;
        }
        restaurar(rotulo);
        mostrarDone(false, dados);
      })
      .catch(function () {
        restaurar(rotulo);
        mostrarDone(false, dados);
      });
  }

  function enviar() {
    preencherOcultos();

    var fd = new FormData(form);
    var dados = dadosDoForm(fd);
    limparErros();

    var ruins = api.validar(dados);
    if (ruins.length) {
      marcarErros(ruins);
      dizer(api.mensagemDeErro(ruins));
      return;
    }

    /* Sem token o envio voltaria 419. Mostra o erro aqui e tenta buscar de
       novo, para o visitante entender e poder repetir. */
    if (!String(fd.get('csrf') || '')) {
      dizer('Nao deu pra preparar o envio agora. Tente de novo em alguns segundos ou chame no WhatsApp.');
      garantirToken();
      return;
    }

    /* O servidor recusa envio abaixo de 3 segundos. Em vez de perder o lead de
       quem digitou rapido, espera o resto do tempo e manda. */
    var falta = TEMPO_MINIMO_MS - (Date.now() - abertoEm);
    if (falta > 0) {
      if (botao) {
        botao.disabled = true;
        botao.textContent = 'Enviando...';
      }
      setTimeout(function () {
        if (botao) botao.disabled = false;
        postar(fd, dados);
      }, falta + 150);
      return;
    }

    postar(fd, dados);
  }

  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    enviar();
  });

  /* Marca a hora de abertura para o time-trap e busca o token. O main.js
     continua abrindo o modal normalmente. */
  document.addEventListener('click', function (ev) {
    var abridor = ev.target && ev.target.closest ? ev.target.closest('[data-quote-open]') : null;
    if (!abridor) return;
    abertoEm = Date.now();
    setTimeout(function () {
      preencherOcultos();
      garantirToken();
    }, 0);
  }, true);

  preencherOcultos();
})();
