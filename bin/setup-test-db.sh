#!/usr/bin/env bash
# Crée la base de test Postgres `cyclerun_testing` de façon idempotente.
#
# psql / createdb ne sont pas installés sur l'hôte : tout passe par le
# conteneur `db` de docker-compose. Rejouable sans effet de bord : si la base
# existe déjà, le script ne fait rien.
#
# Usage :
#   bin/setup-test-db.sh            # depuis la racine du repo
#   composer -d api test:setup      # alias
set -euo pipefail
cd "$(dirname "$0")/.."

DB_NAME="${TEST_DB_NAME:-cyclerun_testing}"
DB_USER="${TEST_DB_USER:-cyclerun}"

if ! docker compose ps --status running db | grep -q db; then
  echo "Le conteneur 'db' n'est pas démarré. Lance : docker compose up -d db" >&2
  exit 1
fi

exists="$(docker compose exec -T db \
  psql -U "$DB_USER" -d postgres -tAc \
  "SELECT 1 FROM pg_database WHERE datname='${DB_NAME}'")"

if [ "$exists" = "1" ]; then
  echo "Base '${DB_NAME}' déjà présente — rien à faire."
else
  docker compose exec -T db createdb -U "$DB_USER" "$DB_NAME"
  echo "Base '${DB_NAME}' créée."
fi
