#!/usr/bin/env bash
# Monta o pacote de deploy para a Hostinger em ../deploy, no mesmo layout do
# servidor:
#
#   deploy/
#     lectta-app/          -> vai para  domains/lectta.online/lectta-app   (fora da web)
#     public_html/aulas/   -> conteúdo vai para  domains/lectta.online/public_html/aulas
#
# Uso:  bash scripts/build-deploy.sh [arquivo .env]   (padrão: .env.production)
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="$ROOT/../deploy"
ENV_FILE="${1:-$ROOT/.env.production}"

rm -rf "$OUT"
mkdir -p "$OUT/lectta-app" "$OUT/public_html/aulas"

# Código da aplicação (sem a pasta pública, sem dependências de dev)
for item in app bootstrap config database resources routes worker artisan composer.json composer.lock; do
    cp -r "$ROOT/$item" "$OUT/lectta-app/"
done
rm -f "$OUT/lectta-app/bootstrap/cache/"*.php "$OUT/lectta-app/worker/config.py"

mkdir -p "$OUT/lectta-app/storage/app/pipeline" \
         "$OUT/lectta-app/storage/framework/cache/data" \
         "$OUT/lectta-app/storage/framework/sessions" \
         "$OUT/lectta-app/storage/framework/views" \
         "$OUT/lectta-app/storage/logs"

cp "$ENV_FILE" "$OUT/lectta-app/.env"

(cd "$OUT/lectta-app" && composer install --no-dev --optimize-autoloader --no-interaction --quiet)

# Pasta pública
cp -r "$ROOT/public/." "$OUT/public_html/aulas/"
rm -rf "$OUT/public_html/aulas/uploads/pending/"*
cp "$ROOT/scripts/deploy-hostinger.sh" "$OUT/"

echo "Pacote pronto em: $OUT"

# Zips prontos para subir (File Manager ou SFTP). No Windows usa o tar.exe
# nativo (bsdtar), que grava caminhos com "/" -- o Compress-Archive do
# PowerShell 5.1 grava "\" e a extração no Linux da Hostinger quebra.
rm -f "$OUT/lectta-app.zip" "$OUT/aulas.zip"
if [ -x /c/Windows/System32/tar.exe ]; then
    (cd "$OUT" && /c/Windows/System32/tar.exe -a -c -f lectta-app.zip lectta-app)
    (cd "$OUT/public_html/aulas" && /c/Windows/System32/tar.exe -a -c -f ../../aulas.zip .)
else
    (cd "$OUT" && zip -qr lectta-app.zip lectta-app)
    (cd "$OUT/public_html/aulas" && zip -qr ../../aulas.zip .)
fi
echo "Zips: $OUT/lectta-app.zip  $OUT/aulas.zip"