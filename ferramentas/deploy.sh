#!/usr/bin/env bash
# Deploy do site Castello para o servidor de teste, por FTP.
#
# Sobe tudo que está versionado em public_html/ (mais lib/caminho-config.php,
# que é ignorado pelo git e só existe para o servidor). Mantém um manifesto
# local com o hash de cada arquivo enviado, para só reenviar o que mudou.
#
# Uso:
#   ferramentas/deploy.sh            envia o que mudou
#   ferramentas/deploy.sh --tudo     ignora o manifesto e reenvia tudo
#   ferramentas/deploy.sh --listar   só mostra o que seria enviado
#
# Credenciais em .credenciais-deploy (ignorado pelo git):
#   FTP_HOST, FTP_USER, FTP_PASS, FTP_RAIZ (pasta remota, ex: /castello), URL

set -euo pipefail

RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$RAIZ"

if [[ ! -f .credenciais-deploy ]]; then
  echo "erro: .credenciais-deploy nao existe na raiz do repositorio" >&2
  exit 1
fi
set -a; . ./.credenciais-deploy; set +a
: "${FTP_HOST:?}" "${FTP_USER:?}" "${FTP_PASS:?}" "${FTP_RAIZ:?}"

MANIFESTO=".deploy-manifesto"
MODO="${1:-}"
[[ "$MODO" == "--tudo" ]] && : > "$MANIFESTO"
touch "$MANIFESTO"

# Lista de arquivos: versionados em public_html/ mais o caminho-config do servidor.
mapfile -t ARQUIVOS < <(git ls-files public_html)
[[ -f public_html/lib/caminho-config.php ]] && ARQUIVOS+=("public_html/lib/caminho-config.php")

enviados=0; pulados=0; falhas=0
for arquivo in "${ARQUIVOS[@]}"; do
  [[ -f "$arquivo" ]] || continue
  hash="$(md5sum "$arquivo" | cut -d' ' -f1)"
  remoto="${arquivo#public_html/}"

  if grep -qF "$hash  $arquivo" "$MANIFESTO"; then
    pulados=$((pulados + 1)); continue
  fi

  if [[ "$MODO" == "--listar" ]]; then
    echo "enviaria  $remoto"; continue
  fi

  if curl -sS --connect-timeout 30 --max-time 600 --ftp-create-dirs \
       -T "$arquivo" "ftp://$FTP_HOST$FTP_RAIZ/${remoto// /%20}" \
       --user "$FTP_USER:$FTP_PASS"; then
    # troca a linha antiga do arquivo no manifesto pela nova
    grep -vF "  $arquivo" "$MANIFESTO" > "$MANIFESTO.tmp" || true
    echo "$hash  $arquivo" >> "$MANIFESTO.tmp"
    mv "$MANIFESTO.tmp" "$MANIFESTO"
    enviados=$((enviados + 1))
    echo "ok        $remoto"
  else
    falhas=$((falhas + 1))
    echo "FALHA     $remoto" >&2
  fi
done

echo "--"
echo "enviados: $enviados   sem mudanca: $pulados   falhas: $falhas"
[[ $falhas -eq 0 ]]
