#!/usr/bin/env bash
# Run the CCTV tests against a LOCAL test database only.
#
#   TEST_DB_PASS=... tests/run-local.sh
#
# Defaults: 127.0.0.1:3307 / mykid_test / user mykid_test. The test DB is reset by the tests.
# Real environment variables override .env inside the app (mk_env), so .env is never used here.
set -euo pipefail
cd "$(dirname "$0")/.."

export DB_HOST="${TEST_DB_HOST:-127.0.0.1}" DB_PORT="${TEST_DB_PORT:-3307}" DB_NAME="${TEST_DB_NAME:-mykid_test}"
export DB_USER="${TEST_DB_USER:-mykid_test}" DB_PASS="${TEST_DB_PASS:?set TEST_DB_PASS}"
case "$DB_HOST" in 127.0.0.1|localhost) ;; *) echo "[Test] REFUSING: DB_HOST must be local" >&2; exit 2 ;; esac
case "$DB_NAME" in *_test) ;; *) echo "[Test] REFUSING: DB_NAME must end with _test" >&2; exit 2 ;; esac

# CCTV media auth: a throwaway secret per run (never printed), teacher / parent streams on for the tests.
export MEDIA_TOKEN_SECRET="$(openssl rand -base64 48)" CCTV_VIEWERS_ENABLED=1

PORT="${TEST_PORT:-8099}"
export TEST_BASE_URL="http://127.0.0.1:$PORT"
LOG="$(mktemp -t mykid-test-server)"
export TEST_SERVER_LOG="$LOG" # tests grep it for leaked secrets
php -S "127.0.0.1:$PORT" -t public public/index.php >"$LOG" 2>&1 &
SERVER=$!
trap 'kill $SERVER 2>/dev/null || true' EXIT
sleep 1

status=0
for t in tests/*_test.php; do
  echo "=== $t"
  php "$t" || status=1
done
for t in tests/browser/*_test.mjs; do
  [ -e "$t" ] || continue
  echo "=== $t"
  node "$t" || status=1
done

echo "=== server log: PHP warnings / errors"
if grep -iE "PHP (warning|fatal|notice|deprecated|parse)" "$LOG"; then status=1; else echo "  none"; fi
echo "server log: $LOG"
exit $status
