#!/usr/bin/env bash
# Lance db (Postgres) + api (Laravel) en arrière-plan, puis mobile (Expo)
# au premier plan pour garder le QR code et les raccourcis clavier (r/a/i/w).
# Ctrl+C arrête api + mobile. db reste up (docker compose down pour l'arrêter).
set -euo pipefail
cd "$(dirname "$0")"

cleanup() {
  echo "Arrêt api..."
  kill 0 2>/dev/null || true
}
trap cleanup EXIT INT TERM

docker compose up -d db adminer

(cd api && php artisan serve --host=0.0.0.0 --port=8000 2>&1 | sed -u 's/^/[api] /') &

cd mobile && npx expo start
