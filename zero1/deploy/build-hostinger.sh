#!/usr/bin/env bash
# Gera o pacote para a Hostinger (hospedagem de sites, sem VPS), já no formato das pastas do hPanel:
#   zero1-hostinger/
#     public_html/   → conteúdo público (vai para ~/domains/SEU_DOMINIO/public_html)
#     zero1/         → sistema privado (vai para ~/domains/SEU_DOMINIO/zero1, FORA de public_html)
#     LEIA-ME.txt
# Uso: ./deploy/build-hostinger.sh [--teste]   (--teste prepara .env de homologação com dados demo)
set -euo pipefail
cd "$(dirname "$0")/.."
MODE="${1:-}"
OUT="release/zero1-hostinger"
rm -rf "$OUT" && mkdir -p "$OUT/public_html" "$OUT/zero1"

npm ci --no-audit --no-fund >/dev/null
npm run build >/dev/null

rsync -a --exclude-from=- ./ "$OUT/zero1/" <<'EXCL'
.git
/node_modules
/release
/tests
/public
/storage/logs/*
/storage/framework/cache/data/*
/storage/framework/sessions/*
/storage/framework/views/*
/storage/app/public/*
/bootstrap/cache/*.php
/.env
/.env.*
*.log
/database/database.sqlite
EXCL
mkdir -p "$OUT/zero1/storage/framework/"{cache/data,sessions,views} "$OUT/zero1/storage/logs" "$OUT/zero1/bootstrap/cache"
(cd "$OUT/zero1" && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress --no-scripts >/dev/null)
(cd "$OUT/zero1" && php artisan package:discover --ansi >/dev/null)

rsync -a --exclude 'storage' --exclude 'hot' public/ "$OUT/public_html/"
cp deploy/hostinger-index.php "$OUT/public_html/index.php"
mkdir -p "$OUT/public_html/storage"

if [ "$MODE" = "--teste" ]; then
  KEY="base64:$(head -c 32 /dev/urandom | base64)"
  sed -e "s#^APP_KEY=.*#APP_KEY=${KEY}#" deploy/env.hostinger-teste > "$OUT/zero1/.env"
  chmod 600 "$OUT/zero1/.env"
fi
cp deploy/LEIA-ME-hostinger.txt "$OUT/LEIA-ME.txt"
(cd release && rm -f zero1-hostinger.zip && zip -qr zero1-hostinger.zip zero1-hostinger)
echo "✓ release/zero1-hostinger.zip"
