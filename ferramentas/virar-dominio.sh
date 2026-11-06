#!/usr/bin/env bash
# Troca o endereco do site para o dominio definitivo: CASTELLO_URL (canonical,
# og, schema e llms.txt), robots.txt, sitemap.xml e o teste de producao, e
# regenera as bases de teste. Nao envia nada: confira, commite e rode o deploy.
#
# Uso: ferramentas/virar-dominio.sh [dominio]     (padrao: castellomadeiras.com.br)

set -euo pipefail

RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$RAIZ"

ANTIGO="castello.tohospedando.com.br"
NOVO="${1:-castellomadeiras.com.br}"

for arquivo in public_html/lib/conteudo.php public_html/robots.txt public_html/sitemap.xml testes/casos/57-producao.php; do
  sed -i "s/$ANTIGO/$NOVO/g" "$arquivo"
  echo "trocado: $arquivo"
done

php testes/gerar-bases.php > /dev/null
echo "bases regeneradas"

echo
echo "Sobrou o dominio antigo em:"
grep -rl "$ANTIGO" public_html testes --include=*.php --include=*.xml --include=*.txt --include=*.html --exclude-dir=uploads || echo "  nenhum arquivo"
echo
echo "Agora: php testes/smoke.php, git diff, commit e ferramentas/deploy.sh."
