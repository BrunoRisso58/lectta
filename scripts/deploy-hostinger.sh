#!/usr/bin/env bash
# Deploy na Hostinger via SSH, a partir dos dois zips gerados localmente:
#   lectta-app.zip  (código Laravel, vendor, worker e .env)
#   aulas.zip       (pasta pública: index.php, css, js, imagens)
#
# Suba os dois zips + este script para domains/lectta.online/ e rode lá:
#   cd ~/domains/lectta.online && bash deploy-hostinger.sh
#
# O que ele faz, nesta ordem (para no primeiro erro):
#   1. backup do banco (mysqldump) e da pasta public_html/aulas atual
#   2. instala o app em lectta-app/ (fora da web)
#   3. troca o conteúdo de public_html/aulas PRESERVANDO uploads/ (áudios que
#      ainda não foram processados)
#   4. atualiza o worker do YouTube, se encontrar ele no $HOME
# Nada do banco é alterado.
set -euo pipefail

SITE_DIR="$(cd "$(dirname "$0")" && pwd)"
AULAS="$SITE_DIR/public_html/aulas"
APP="$SITE_DIR/lectta-app"
STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="$HOME/lectta-backups/$STAMP"

[ -f "$SITE_DIR/lectta-app.zip" ] || { echo "lectta-app.zip não encontrado em $SITE_DIR"; exit 1; }
[ -f "$SITE_DIR/aulas.zip" ] || { echo "aulas.zip não encontrado em $SITE_DIR"; exit 1; }
[ -d "$AULAS" ] || { echo "Pasta $AULAS não existe -- confira o caminho do site."; exit 1; }

mkdir -p "$BACKUP"

echo "==> 1/5 Backup do banco"
TMP_APP="$(mktemp -d)"
unzip -q "$SITE_DIR/lectta-app.zip" -d "$TMP_APP"
env_get() { grep -E "^$1=" "$TMP_APP/lectta-app/.env" | head -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }
"${MYSQLDUMP:-mysqldump}" --single-transaction --routines --no-tablespaces \
    -h "$(env_get DB_HOST)" -u "$(env_get DB_USERNAME)" -p"$(env_get DB_PASSWORD)" \
    "$(env_get DB_DATABASE)" > "$BACKUP/banco.sql"
echo "    $(wc -c < "$BACKUP/banco.sql") bytes em $BACKUP/banco.sql"

echo "==> 2/5 Backup da pasta public_html/aulas"
tar -czf "$BACKUP/aulas.tar.gz" -C "$SITE_DIR/public_html" aulas
echo "    $BACKUP/aulas.tar.gz"

echo "==> 3/5 Instalando o app em $APP"
if [ -d "$APP" ]; then
    # Atualização: mantém sessões, logs e arquivos de trabalho.
    cp -r "$APP/storage" "$TMP_APP/lectta-app/" 2>/dev/null || true
    mv "$APP" "$BACKUP/lectta-app"
fi
mv "$TMP_APP/lectta-app" "$APP"
rmdir "$TMP_APP" 2>/dev/null || true
chmod -R u+rwX "$APP/storage" "$APP/bootstrap/cache"

echo "==> 4/5 Trocando os arquivos públicos (uploads/ é preservada)"
find "$AULAS" -mindepth 1 -maxdepth 1 ! -name uploads -exec rm -rf {} +
unzip -q -o "$SITE_DIR/aulas.zip" -d "$AULAS"
mkdir -p "$AULAS/uploads/pending"

echo "==> 5/5 Worker do YouTube e cron"
# O worker roda fora do app (no $HOME, com o config.py dele). Atualiza só o
# youtube_worker.py; o config.py fica como está.
WORKER="$(find "$HOME" -maxdepth 3 -name youtube_worker.py \
    -not -path "$APP/*" -not -path "$HOME/lectta-backups/*" 2>/dev/null | head -1)"
if [ -n "$WORKER" ]; then
    cp "$WORKER" "$BACKUP/youtube_worker.py"
    cp "$APP/worker/youtube_worker.py" "$WORKER"
    echo "    worker atualizado: $WORKER"
else
    echo "    worker não encontrado no \$HOME -- copie $APP/worker/youtube_worker.py para onde ele roda."
fi

echo "    Cron (hPanel > Avançado > Cron Jobs), a cada minuto:"
echo "    curl -s \"$(grep -E "^APP_URL=" "$APP/.env" | cut -d= -f2- | tr -d '"')/api/process?token=<LECTTA_API_TOKEN>\""

php "$APP/artisan" about --only=environment >/dev/null 2>&1 && echo "    Laravel OK (CLI)" \
    || echo "    Aviso: o PHP da linha de comando não rodou o artisan (versão < 8.2?). O site usa o PHP do hPanel; confira no navegador."
echo
echo "Pronto. Backups em: $BACKUP"
echo "Teste: https://lectta.online/aulas/"
