# -*- coding: utf-8 -*-
"""Empurra o conteudo da Castelo Flex do banco local para um site Castello.

Usa o proprio painel, por HTTP, com o login do painel: nao sobe nenhum script
de manutencao para o servidor e faz exatamente o que o cliente faria a mao.
Pode rodar quantas vezes quiser, so mexe no que esta diferente.

  python ferramentas/sincronizar-flex.py --seco               mostra o que mudaria (local)
  python ferramentas/sincronizar-flex.py                      aplica no local
  python ferramentas/sincronizar-flex.py --servidor --seco    mostra o que mudaria la
  python ferramentas/sincronizar-flex.py --servidor           aplica no servidor

O que vai para o site sai do banco local: os cinco modelos Flex, a FAQ e os
passos da Flex e os tres textos que a Flex usa. Deploy manda o codigo; este
script manda o conteudo, que mora no banco e o deploy nao alcanca.

Local:    http://localhost:8000, login castello.
Servidor: le URL e PAINEL_SENHA de .credenciais-deploy. Enquanto o DNS de
castello.tohospedando.com.br apontar para o CDN errado, a conexao vai no IP
200.11.120.114 com o nome preservado no SNI (o mesmo que o --resolve do curl)
e o certificado autoassinado de la nao e verificado.
"""

import html as H
import http.client
import http.cookiejar
import json
import mimetypes
import os
import re
import socket
import ssl
import subprocess
import sys
import urllib.parse
import urllib.request
import uuid

RAIZ = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
IP_SERVIDOR = '200.11.120.114'


class ConexaoResolvida(http.client.HTTPSConnection):
    """HTTPS que conecta num IP fixo mantendo o nome no SNI e no Host."""

    resolve = {}

    def connect(self):
        destino = self.resolve.get(self.host, self.host)
        self.sock = socket.create_connection((destino, self.port), self.timeout)
        self.sock = self._context.wrap_socket(self.sock, server_hostname=self.host)


class HandlerResolvido(urllib.request.HTTPSHandler):
    def https_open(self, req):
        return self.do_open(ConexaoResolvida, req, context=self._context)


class Painel:
    """Cliente do painel: login, leitura das telas e envio dos formularios."""

    def __init__(self, base, resolve=None, inseguro=False):
        self.base = base.rstrip('/')
        contexto = ssl.create_default_context()
        if inseguro:
            contexto.check_hostname = False
            contexto.verify_mode = ssl.CERT_NONE
        if resolve:
            ConexaoResolvida.resolve.update(resolve)
        self.cookies = http.cookiejar.CookieJar()
        self.op = urllib.request.build_opener(
            urllib.request.HTTPCookieProcessor(self.cookies),
            HandlerResolvido(context=contexto))
        self.op.addheaders = [('User-Agent', 'castello-sincronizar/1.0')]

    def get(self, caminho):
        with self.op.open(self.base + caminho, timeout=90) as r:
            return r.read().decode('utf-8', 'replace'), r.geturl()

    def post(self, caminho, campos, arquivos=None):
        limite = '----castello' + uuid.uuid4().hex
        corpo = b''
        for chave, valor in campos.items():
            corpo += ('--%s\r\nContent-Disposition: form-data; name="%s"\r\n\r\n'
                      % (limite, chave)).encode() + str(valor).encode('utf-8') + b'\r\n'
        for chave, caminho_arquivo in (arquivos or {}).items():
            nome = os.path.basename(caminho_arquivo)
            tipo = mimetypes.guess_type(nome)[0] or 'application/octet-stream'
            corpo += ('--%s\r\nContent-Disposition: form-data; name="%s"; filename="%s"\r\n'
                      'Content-Type: %s\r\n\r\n' % (limite, chave, nome, tipo)).encode()
            with open(caminho_arquivo, 'rb') as f:
                corpo += f.read()
            corpo += b'\r\n'
        corpo += ('--%s--\r\n' % limite).encode()
        req = urllib.request.Request(
            self.base + caminho, data=corpo,
            headers={'Content-Type': 'multipart/form-data; boundary=' + limite})
        with self.op.open(req, timeout=300) as r:
            return r.read().decode('utf-8', 'replace'), r.geturl()

    @staticmethod
    def csrf(pagina):
        achado = re.search(r'name="csrf" value="([^"]+)"', pagina)
        if not achado:
            raise RuntimeError('csrf nao encontrado na pagina')
        return achado.group(1)

    def entrar(self, login, senha):
        pagina, _ = self.get('/painel/')
        if 'p-login' not in pagina:
            return
        pagina, _ = self.post('/painel/index.php',
                              {'csrf': self.csrf(pagina), 'login': login, 'senha': senha})
        if 'p-login' in pagina:
            aviso = re.search(r'p-aviso--erro">([^<]+)', pagina)
            raise RuntimeError('login recusado: ' + (aviso.group(1) if aviso else '?'))

    def lista(self, tela, filtro=''):
        pagina, _ = self.get('/painel/painel.php?tela=' + tela +
                             ('&filtro=' + filtro if filtro else ''))
        itens = []
        for bloco in pagina.split('<li class="p-item')[1:]:
            ident = re.search(r'data-id="(\d+)"', bloco)
            titulo = re.search(r'<strong[^>]*>(.*?)</strong>', bloco, re.S)
            if ident:
                itens.append({'id': int(ident.group(1)),
                              'titulo': re.sub(r'<[^>]+>', '', titulo.group(1)).strip() if titulo else ''})
        return itens, self.csrf(pagina)

    def linha(self, tela, ident, filtro=''):
        """Valores do formulario de edicao, do jeito que o painel os mostra."""
        pagina, _ = self.get('/painel/painel.php?tela=%s&editar=%d%s'
                             % (tela, ident, '&filtro=' + filtro if filtro else ''))
        valores = {}
        for achado in re.finditer(r'<input type="text" id="c-[^"]+" name="([^"]+)" value="([^"]*)"', pagina):
            valores[achado.group(1)] = H.unescape(achado.group(2))
        for achado in re.finditer(r'<textarea id="c-[^"]+" name="([^"]+)"[^>]*>(.*?)</textarea>', pagina, re.S):
            valores[achado.group(1)] = H.unescape(achado.group(2))
        selecao = re.search(r'name="modalidade">(.*?)</select>', pagina, re.S)
        if selecao:
            opcao = re.search(r'<option value="([^"]*)"[^>]*selected', selecao.group(1))
            valores['modalidade'] = opcao.group(1) if opcao else ''
        valores['destaque'] = '1' if re.search(r'name="destaque" value="1" checked', pagina) else '0'
        icone = re.search(r'name="icone" value="([^"]+)" checked', pagina)
        if icone:
            valores['icone'] = icone.group(1)
        imagem = re.search(r'<img src="\.\./(uploads/[^"]+)" alt="" />', pagina)
        valores['__arquivo'] = imagem.group(1) if imagem else ''
        return valores, self.csrf(pagina)


def exportar_desejado():
    """Le do banco local o conteudo da Flex que o site deve mostrar."""
    php = ("require '%s/public_html/lib/conteudo.php';"
           "$saida = ["
           " 'modelos' => array_map(fn($m) => ['nome'=>$m['nome'],'area'=>$m['area'],"
           "   'parede'=>$m['parede'],'preco'=>$m['preco'],'prazo'=>$m['prazo'],"
           "   'descricao'=>$m['descricao'],'foto_alt'=>$m['foto_alt'],"
           "   'destaque'=>(string)(int)$m['destaque'],'foto'=>$m['foto']], modelos('flex')),"
           " 'faq' => array_map(fn($f) => ['pergunta'=>$f['pergunta'],'resposta'=>$f['resposta'],"
           "   'icone'=>$f['icone']], faq('flex')),"
           " 'passos' => array_map(fn($p) => ['titulo'=>$p['titulo'],'texto'=>$p['texto'],"
           "   'imagem_alt'=>$p['imagem_alt']], passos('flex')),"
           " 'blocos' => ['flex_texto'=>bloco('flex_texto'),"
           "   'flexpg_catalogo_nota'=>bloco('flexpg_catalogo_nota'),"
           "   'flexpg_depois_texto'=>bloco('flexpg_depois_texto')],"
           "]; echo json_encode($saida, JSON_UNESCAPED_UNICODE);") % RAIZ.replace('\\', '/')
    saida = subprocess.run(['php', '-r', php], capture_output=True, timeout=120)
    if saida.returncode != 0:
        raise RuntimeError('php falhou: ' + saida.stderr.decode('utf-8', 'replace'))
    return json.loads(saida.stdout.decode('utf-8'))


def credenciais_servidor():
    """URL e senha do painel do servidor, de .credenciais-deploy."""
    dados = {}
    with open(os.path.join(RAIZ, '.credenciais-deploy'), encoding='utf-8') as f:
        for linha in f:
            if '=' in linha and not linha.strip().startswith('#'):
                chave, valor = linha.split('=', 1)
                dados[chave.strip()] = valor.strip().strip('"').strip("'")
    return dados.get('URL', '').rstrip('/'), dados.get('PAINEL_SENHA', '')


def foto_local(caminho_no_banco):
    """uploads/modelos/flex-36.webp -> public_html/fotos-flex/flex-36.webp"""
    return os.path.join(RAIZ, 'public_html', 'fotos-flex', os.path.basename(caminho_no_banco))


def sincronizar(desejado, base, login, senha, seco=False, inseguro=False, resolve=None):
    painel = Painel(base, inseguro=inseguro, resolve=resolve)
    painel.entrar(login, senha)
    print('entrou no painel de', base)
    mudou = []

    itens, _ = painel.lista('modelos', 'flex')
    print('modelos Flex no destino:', ', '.join(i['titulo'] for i in itens) or '(nenhum)')
    for posicao, alvo in enumerate(desejado['modelos']):
        ident = itens[posicao]['id'] if posicao < len(itens) else 0
        atual, csrf = (painel.linha('modelos', ident, 'flex') if ident
                       else ({}, painel.lista('modelos', 'flex')[1]))
        campos = {'csrf': csrf, 'tela': 'modelos', 'id': ident, 'filtro': 'flex',
                  'modalidade': 'flex', 'nome': alvo['nome'], 'area': alvo['area'],
                  'parede': alvo['parede'], 'preco': alvo['preco'], 'prazo': alvo['prazo'],
                  'descricao': alvo['descricao'], 'foto_alt': alvo['foto_alt'],
                  'destaque': alvo['destaque']}
        texto_igual = all(str(atual.get(chave, '')) == str(valor) for chave, valor in campos.items()
                          if chave not in ('csrf', 'tela', 'id', 'filtro'))
        # o upload do painel acrescenta um sufixo: flex-36.webp vira
        # flex-36-159e2c.webp. Compara so o prefixo.
        arquivo = foto_local(alvo['foto'])
        prefixo = os.path.splitext(os.path.basename(arquivo))[0]
        remoto = os.path.splitext(os.path.basename(atual.get('__arquivo', '')))[0]
        foto_igual = bool(ident) and remoto.startswith(prefixo)
        if texto_igual and foto_igual:
            print('  = modelo', alvo['nome'], 'ja esta certo')
            continue
        print(('  ~ atualiza ' if ident else '  + cria ') + alvo['nome'])
        mudou.append(alvo['nome'])
        if seco:
            continue
        _, url = painel.post('/painel/acoes/salvar.php', campos, {'foto': arquivo})
        if 'erro=' in url:
            print('    ERRO:', urllib.parse.unquote(url.split('erro=')[1]))

    itens, _ = painel.lista('faq', 'flex')
    for posicao, alvo in enumerate(desejado['faq']):
        if posicao >= len(itens):
            break
        ident = itens[posicao]['id']
        atual, csrf = painel.linha('faq', ident, 'flex')
        if atual.get('pergunta') == alvo['pergunta'] and atual.get('resposta') == alvo['resposta']:
            print('  = faq', posicao + 1, 'ja esta certa')
            continue
        print('  ~ atualiza faq', posicao + 1, alvo['pergunta'][:45])
        mudou.append('faq %d' % (posicao + 1))
        if seco:
            continue
        _, url = painel.post('/painel/acoes/salvar.php',
                             {'csrf': csrf, 'tela': 'faq', 'id': ident, 'filtro': 'flex',
                              'contexto': 'flex', 'pergunta': alvo['pergunta'],
                              'resposta': alvo['resposta'], 'icone': alvo['icone']})
        if 'erro=' in url:
            print('    ERRO:', urllib.parse.unquote(url.split('erro=')[1]))

    itens, _ = painel.lista('passos', 'flex')
    for posicao, alvo in enumerate(desejado['passos']):
        if posicao >= len(itens):
            break
        ident = itens[posicao]['id']
        atual, csrf = painel.linha('passos', ident, 'flex')
        if atual.get('titulo') == alvo['titulo'] and atual.get('texto') == alvo['texto']:
            print('  = passo', posicao + 1, 'ja esta certo')
            continue
        print('  ~ atualiza passo', posicao + 1, alvo['titulo'])
        mudou.append('passo %d' % (posicao + 1))
        if seco:
            continue
        _, url = painel.post('/painel/acoes/salvar.php',
                             {'csrf': csrf, 'tela': 'passos', 'id': ident, 'filtro': 'flex',
                              'contexto': 'flex', 'titulo': alvo['titulo'],
                              'texto': alvo['texto'], 'imagem_alt': alvo['imagem_alt']})
        if 'erro=' in url:
            print('    ERRO:', urllib.parse.unquote(url.split('erro=')[1]))

    pagina, _ = painel.get('/painel/painel.php?tela=textos')
    campos = {'csrf': painel.csrf(pagina)}
    diferentes = []
    for chave, valor in desejado['blocos'].items():
        achado = re.search(r'<textarea[^>]*name="valores\[' + re.escape(chave) + r'\]"[^>]*>(.*?)</textarea>',
                           pagina, re.S)
        if achado is None:
            achado = re.search(r'<input[^>]*name="valores\[' + re.escape(chave) + r'\]" value="([^"]*)"', pagina)
        atual = H.unescape(achado.group(1)) if achado else ''
        if atual.strip() != valor.strip():
            diferentes.append(chave)
        campos['valores[' + chave + ']'] = valor
    if diferentes:
        print('  ~ atualiza textos:', ', '.join(diferentes))
        mudou += diferentes
        if not seco:
            # a tela grava so as chaves enviadas; os outros textos ficam intactos
            _, url = painel.post('/painel/acoes/textos.php', campos)
            if 'erro=' in url:
                print('    ERRO:', urllib.parse.unquote(url.split('erro=')[1]))
    else:
        print('  = textos ja estao certos')

    print('resumo:', ('%d itens' % len(mudou)) if mudou else 'nada a fazer')
    return mudou


if __name__ == '__main__':
    modo_seco = '--seco' in sys.argv
    conteudo = exportar_desejado()
    if '--servidor' in sys.argv:
        endereco, senha_painel = credenciais_servidor()
        if not endereco or not senha_painel:
            sys.exit('faltou URL ou PAINEL_SENHA em .credenciais-deploy')
        nome = urllib.parse.urlparse(endereco).hostname or ''
        sincronizar(conteudo, endereco, 'castello', senha_painel, modo_seco,
                    inseguro=True, resolve={nome: IP_SERVIDOR})
    else:
        argumentos = [a for a in sys.argv[1:] if not a.startswith('--')]
        sincronizar(conteudo, 'http://localhost:8000', 'castello',
                    argumentos[0] if argumentos else '754ba1256654', modo_seco)
