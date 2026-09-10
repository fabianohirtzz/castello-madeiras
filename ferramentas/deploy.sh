#!/usr/bin/env bash
# Deploy do site Castello para o servidor de teste, por FTP.
#
# Sobe tudo que está versionado em public_html/, menos o migrar.php (que só
# pode existir no servidor durante a instalação e é apagado em seguida), mais
# o ferramentas/caminho-config.servidor.php, que vai para lib/caminho-config.php
# no servidor e diz onde fica o config/ lá. Mantém um manifesto local com o
# hash de cada arquivo enviado, para só reenviar o que mudou.
#
# Uso:
#   ferramentas/deploy.sh                envia o que mudou
#   ferramentas/deploy.sh --tudo         ignora o manifesto e reenvia tudo
#   ferramentas/deploy.sh --listar       só mostra o que seria enviado
#   ferramentas/deploy.sh --com-migrar   inclui o migrar.php (só na instalação)
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

# Cada entrada é "local|remoto". O remoto é relativo à raiz do site.
ENTRADAS=()
while IFS= read -r arquivo; do
  [[ "$arquivo" == "public_html/migrar.php" && "$MODO" != "--com-migrar" ]] && continue
  ENTRADAS+=("$arquivo|${arquivo#public_html/}")
done < <(git ls-files public_html)
[[ -f ferramentas/caminho-config.servidor.php ]] \
  && ENTRADAS+=("ferramentas/caminho-config.servidor.php|lib/caminho-config.php")

enviados=0; pulados=0; falhas=0
for entrada in "${ENTRADAS[@]}"; do
  arquivo="${entrada%%|*}"
  remoto="${entrada#*|}"
  [[ -f "$arquivo" ]] || continue
  hash="$(md5sum "$arquivo" | cut -d' ' -f1)"

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
