#!/usr/bin/env bash
# Gera um pacote pronto para a Hostinger (hospedagem de sites, sem VPS):
# dependências PHP de produção + assets compilados. Rode na sua máquina ou em CI.
#   ./deploy/build-release.sh            -> release/zero1-AAAAMMDD-HHMM.zip
set -euo pipefail
cd "$(dirname "$0")/.."

STAMP=$(date +%Y%m%d-%H%M)
OUT="release/zero1-${STAMP}"
rm -rf "$OUT" && mkdir -p "$OUT"

echo "› Compilando assets (Vite/Tailwind)…"
npm ci --no-audit --no-fund
npm run build

echo "› Copiando código…"
rsync -a --exclude-from=- ./ "$OUT/" <<'EXCL'
.git
node_modules
release
tests
storage/logs/*
storage/framework/cache/data/*
storage/framework/sessions/*
storage/framework/views/*
storage/app/public/*
public/storage
public/hot
.env
.env.*
!.env.example
*.log
EXCL

echo "› Instalando dependências PHP de produção…"
(cd "$OUT" && composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress)

(cd release && zip -qr "zero1-${STAMP}.zip" "zero1-${STAMP}")
echo "✓ Pacote: release/zero1-${STAMP}.zip"
echo "  Siga docs/DEPLOY_HOSTINGER.md (seção 'Publicar uma versão')."
