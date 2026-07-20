#!/usr/bin/env bash
set -euo pipefail

CONTAINER="${RAZORPAY_TEST_CONTAINER:-temple-trust-app-1}"
PROBES_DIR="scripts/razorpay-probes"

declare -a PROBES=(
  "rz01-config-and-credentials.php"
  "rz02-client-factory-resolves.php"
  "rz03-provider-metadata.php"
  "rz04-adapter-initialize-create-order.php"
  "rz05-adapter-verify-fetch-order.php"
  "rz06-error-translator.php"
  "rz07-signature-round-trip.php"
  "rz08-webhook-payload-verify.php"
  "rz09-fetch-payment-sample.php"
  "rz10-refund-sample.php"
  "rz11-full-domain-flow.php"
  "rz12-cleanup-report.php"
)

echo "=== Razorpay SDK Test Suite ==="
echo "Container: $CONTAINER"
echo ""

PASS=0; FAIL=0; FATAL=0
for probe in "${PROBES[@]}"; do
  start=$(date +%s%N)
  set +e
  output=$(docker exec \
    -e APP_ENV=testing \
    -e "DATABASE_URL=postgresql://temple_trust:dev@postgres:5432/temple_trust?sslmode=disable" \
    -e "DB_HOST=postgres" \
    -e "DB_CONNECTION=pgsql" \
    -e "DB_PORT=5432" \
    -e "DB_DATABASE=temple_trust" \
    -e "DB_USERNAME=temple_trust" \
    -e "DB_PASSWORD=dev" \
    -e "DB_SSLMODE=disable" \
    "$CONTAINER" \
    php "$PROBES_DIR/$probe" 2>&1)
  rc=$?
  set -e
  end=$(date +%s%N)
  duration_ms=$(( (end - start) / 1000000 ))

  if [ $rc -eq 0 ]; then
    echo "[PASS] $probe (${duration_ms}ms)"
    PASS=$((PASS+1))
  elif [ $rc -eq 2 ]; then
    echo "[FATAL] $probe (${duration_ms}ms) — $output"
    FATAL=$((FATAL+1))
  else
    echo "[FAIL] $probe (${duration_ms}ms) — $output"
    FAIL=$((FAIL+1))
  fi
done

echo ""
echo "=== Summary: pass=$PASS fail=$FAIL fatal=$FATAL ==="
[ $FAIL -eq 0 ] && [ $FATAL -eq 0 ]