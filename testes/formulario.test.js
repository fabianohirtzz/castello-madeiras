/* Testes das funcoes puras de public_html/js/formulario.js.
   Rodar: node testes/formulario.test.js
   Chamado tambem pelo caso testes/casos/85-crm.php do smoke. */

'use strict';

var assert = require('node:assert/strict');
var caminho = require('node:path').join(__dirname, '..', 'public_html', 'js', 'formulario.js');
var api = require(caminho);

var total = 0;
var falhas = 0;

function teste(nome, corpo) {
  total++;
  try {
    corpo();
    console.log('  ok    ' + nome);
  } catch (erro) {
    falhas++;
    console.log('  FALHA ' + nome + '  ->  ' + erro.message);
  }
}

function guardaFalsa(inicial) {
  var dados = Object.assign({}, inicial || {});
  return {
    getItem: function (chave) { return Object.prototype.hasOwnProperty.call(dados, chave) ? dados[chave] : null; },
    setItem: function (chave, valor) { dados[chave] = String(valor); },
    bruto: function () { return dados; }
  };
}

console.log('== js/formulario.js ==');

teste('utmDaUrl le os cinco parametros', function () {
  var r = api.utmDaUrl('?utm_source=instagram&utm_medium=social&utm_campaign=flex&utm_term=casa&utm_content=reel3');
  assert.equal(r.utm_source, 'instagram');
  assert.equal(r.utm_medium, 'social');
  assert.equal(r.utm_campaign, 'flex');
  assert.equal(r.utm_term, 'casa');
  assert.equal(r.utm_content, 'reel3');
});

teste('utmDaUrl ignora o que nao e utm', function () {
  var r = api.utmDaUrl('?pagina=2&utm_source=google&gclid=xyz');
  assert.deepEqual(Object.keys(r), ['utm_source']);
});

teste('utmDaUrl aceita url sem interrogacao e decodifica', function () {
  var r = api.utmDaUrl('utm_campaign=casa%20de%20madeira&utm_source=meta+ads');
  assert.equal(r.utm_campaign, 'casa de madeira');
  assert.equal(r.utm_source, 'meta ads');
});

teste('utmDaUrl devolve objeto vazio sem parametro', function () {
  assert.deepEqual(api.utmDaUrl(''), {});
  assert.deepEqual(api.utmDaUrl(undefined), {});
});

teste('utmLer devolve o que estava guardado', function () {
  var store = guardaFalsa({ castello_utm: '{"utm_source":"instagram"}' });
  assert.equal(api.utmLer(store).utm_source, 'instagram');
});

teste('utmLer aguenta lixo no sessionStorage', function () {
  assert.deepEqual(api.utmLer(guardaFalsa({ castello_utm: 'nao e json' })), {});
  assert.deepEqual(api.utmLer(guardaFalsa({})), {});
  assert.deepEqual(api.utmLer(null), {});
});

teste('utmResolver guarda o que veio na URL', function () {
  var store = guardaFalsa({});
  var r = api.utmResolver('?utm_source=instagram&utm_campaign=flex', store);
  assert.equal(r.utm_source, 'instagram');
  assert.equal(JSON.parse(store.bruto().castello_utm).utm_campaign, 'flex');
});

teste('utmResolver mantem a utm guardada quando a URL nao traz nenhuma', function () {
  var store = guardaFalsa({ castello_utm: '{"utm_source":"instagram","utm_campaign":"flex"}' });
  var r = api.utmResolver('', store);
  assert.equal(r.utm_source, 'instagram');
  assert.equal(r.utm_campaign, 'flex');
});

teste('parametro na URL sobrescreve o guardado', function () {
  var store = guardaFalsa({ castello_utm: '{"utm_source":"instagram","utm_campaign":"antiga"}' });
  var r = api.utmResolver('?utm_source=google', store);
  assert.equal(r.utm_source, 'google');
  assert.equal(r.utm_campaign, 'antiga');
  assert.equal(JSON.parse(store.bruto().castello_utm).utm_source, 'google');
});

teste('utmGravar nao explode com sessionStorage bloqueado', function () {
  var travada = { getItem: function () { throw new Error('bloqueado'); }, setItem: function () { throw new Error('bloqueado'); } };
  assert.equal(api.utmGravar({ utm_source: 'x' }, travada), false);
  assert.deepEqual(api.utmLer(travada), {});
});

teste('mascaraWhatsapp formata celular de 11 digitos', function () {
  assert.equal(api.mascaraWhatsapp('48998244494'), '(48) 99824-4494');
});

teste('mascaraWhatsapp formata fixo de 10 digitos', function () {
  assert.equal(api.mascaraWhatsapp('4836328743'), '(48) 3632-8743');
});

teste('mascaraWhatsapp e idempotente', function () {
  assert.equal(api.mascaraWhatsapp('(48) 99824-4494'), '(48) 99824-4494');
});

teste('mascaraWhatsapp aguenta digitacao pela metade', function () {
  assert.equal(api.mascaraWhatsapp('4'), '4');
  assert.equal(api.mascaraWhatsapp('48'), '48');
  assert.equal(api.mascaraWhatsapp('489'), '(48) 9');
  assert.equal(api.mascaraWhatsapp('489982'), '(48) 9982');
});

teste('mascaraWhatsapp corta o que passa de 11 digitos', function () {
  assert.equal(api.mascaraWhatsapp('489982444949999'), '(48) 99824-4494');
});

teste('validar aceita lead completo', function () {
  assert.deepEqual(api.validar({ nome: 'Fabiano Hirtz', whatsapp: '(48) 99824-4494', busca: 'Modelo pronto do catalogo' }), []);
});

teste('validar acusa nome vazio', function () {
  assert.deepEqual(api.validar({ nome: '   ', whatsapp: '48998244494', busca: 'Ainda pesquisando' }), ['nome']);
});

teste('validar acusa whatsapp com menos de 10 digitos', function () {
  assert.deepEqual(api.validar({ nome: 'Fabiano', whatsapp: '(48) 9982', busca: 'Ainda pesquisando' }), ['whatsapp']);
});

teste('validar acusa busca vazia', function () {
  assert.deepEqual(api.validar({ nome: 'Fabiano', whatsapp: '48998244494', busca: '' }), ['busca']);
});

teste('validar acusa os tres de uma vez, na ordem do formulario', function () {
  assert.deepEqual(api.validar({ nome: '', whatsapp: '', busca: '' }), ['nome', 'whatsapp', 'busca']);
});

teste('mensagemDeErro fala do primeiro campo ruim', function () {
  assert.match(api.mensagemDeErro(['nome']), /nome/i);
  assert.match(api.mensagemDeErro(['whatsapp']), /WhatsApp/i);
  assert.match(api.mensagemDeErro(['busca']), /busca/i);
  assert.equal(typeof api.mensagemDeErro([]), 'string');
});

teste('resumoWhatsapp monta o texto so com o que foi preenchido', function () {
  var texto = api.resumoWhatsapp({ nome: 'Fabiano', whatsapp: '(48) 99824-4494', busca: 'Modelo pronto', modelo: '', cidade: 'Tubarao', mensagem: '' });
  assert.match(texto, /Nome: Fabiano/);
  assert.match(texto, /Cidade\/regiao: Tubarao/);
  assert.equal(/Modelo de interesse/.test(texto), false);
  assert.equal(/Mensagem/.test(texto), false);
});

teste('tokenDaResposta aceita o que o csrf.php devolve', function () {
  assert.equal(api.tokenDaResposta({ token: 'a1b2c3d4e5f6' }), 'a1b2c3d4e5f6');
});

teste('tokenDaResposta recusa resposta sem token util', function () {
  assert.equal(api.tokenDaResposta({}), '');
  assert.equal(api.tokenDaResposta({ token: 'curto' }), '');
  assert.equal(api.tokenDaResposta({ token: 123456789 }), '');
  assert.equal(api.tokenDaResposta(null), '');
  assert.equal(api.tokenDaResposta('a1b2c3d4e5f6'), '');
});

/* Os tres testes abaixo sao assincronos e mexem no mesmo token guardado em
   memoria, entao rodam EM FILA, um depois do outro, no fim do arquivo. Se
   forem disparados juntos, o token que um guarda vaza para o outro. */
var assincronos = [];

function testeAssincrono(nome, corpo) {
  total++;
  assincronos.push(function () {
    return Promise.resolve()
      .then(corpo)
      .then(function () { console.log('  ok    ' + nome); })
      .catch(function (erro) { falhas++; console.log('  FALHA ' + nome + '  ->  ' + erro.message); });
  });
}

testeAssincrono('obterToken busca uma vez e guarda em memoria', function () {
  api.esquecerToken();
  var chamadas = 0;
  var buscar = function () { chamadas++; return Promise.resolve({ token: 'a1b2c3d4e5f6' }); };
  return api.obterToken(buscar)
    .then(function (t) {
      assert.equal(t, 'a1b2c3d4e5f6');
      return api.obterToken(buscar);
    })
    .then(function (t) {
      assert.equal(t, 'a1b2c3d4e5f6');
      assert.equal(chamadas, 1, 'reabrir o modal nao pode buscar o token de novo');
    });
});

testeAssincrono('obterToken devolve vazio quando o csrf.php esta fora do ar', function () {
  api.esquecerToken();
  var buscar = function () { return Promise.reject(new Error('http 500')); };
  return api.obterToken(buscar).then(function (t) {
    assert.equal(t, '', 'falha no csrf.php nao pode virar token');
  });
});

testeAssincrono('obterToken nao guarda a falha e tenta de novo depois', function () {
  api.esquecerToken();
  var vezes = 0;
  var buscar = function () {
    vezes++;
    return vezes === 1 ? Promise.reject(new Error('caiu')) : Promise.resolve({ token: 'a1b2c3d4e5f6' });
  };
  return api.obterToken(buscar)
    .then(function (t) {
      assert.equal(t, '');
      return api.obterToken(buscar);
    })
    .then(function (t) {
      assert.equal(t, 'a1b2c3d4e5f6');
      assert.equal(vezes, 2);
    });
});

teste('sem emoji e sem travessao na copy do arquivo', function () {
  var fonte = require('node:fs').readFileSync(caminho, 'utf8');
  assert.equal(/[\u2014\u2013]/.test(fonte), false, 'achou travessao');
  assert.equal(/[\u{1F300}-\u{1FAFF}\u{2600}-\u{27BF}]/u.test(fonte), false, 'achou emoji');
});

teste('o token nunca vem de metatag e a busca vai com o cookie da sessao', function () {
  var fonte = require('node:fs').readFileSync(caminho, 'utf8');
  assert.equal(/csrf-token/.test(fonte), false, 'nao pode ler metatag de csrf');
  assert.match(fonte, /credentials: 'same-origin'/);
});

/* Dentro do modal o token so vem quando o visitante abre o formulario; na
   pagina de contato ele ja esta aberto, entao vem no carregamento. */
function formFalso(dentroDoModal) {
  return { closest: function (sel) { return sel === '#quoteModal' && dentroDoModal ? {} : null; } };
}

teste('tokenNoCarregamento e falso para o formulario dentro do modal', function () {
  assert.equal(api.tokenNoCarregamento(formFalso(true)), false);
});

teste('tokenNoCarregamento e verdadeiro para o formulario embutido na pagina', function () {
  assert.equal(api.tokenNoCarregamento(formFalso(false)), true);
});

teste('tokenNoCarregamento nao explode sem formulario', function () {
  assert.equal(api.tokenNoCarregamento(null), false);
  assert.equal(api.tokenNoCarregamento({}), false);
});

teste('o formulario embutido busca o token no carregamento e o do modal so no clique', function () {
  var fonte = require('node:fs').readFileSync(caminho, 'utf8');
  assert.match(fonte, /var embutido = api\.tokenNoCarregamento\(form\)/);
  assert.match(fonte, /if \(embutido\) \{[\s\S]*?garantirToken\(\);[\s\S]*?return;/);
  assert.match(fonte, /closest\('\[data-quote-open\]'\)/);
});

assincronos.reduce(function (fila, passo) {
  return fila.then(passo);
}, Promise.resolve()).then(function () {
  console.log('');
  console.log((total - falhas) + '/' + total + ' passaram');
  process.exit(falhas > 0 ? 1 : 0);
});
