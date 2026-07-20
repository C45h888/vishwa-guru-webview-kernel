#!/usr/bin/env bash
set -euo pipefail

# Host-runnable CMS kernel probe suite. Mirrors scripts/validate-razorpay.sh's
# exit-code contract (0=PASS, 1=FAIL, 2=FATAL) but is light enough to run on
# any host that has PHP + composer install (no Docker required). Razorpay
# probes need Docker because they hit a live gateway; CMS probes are 100%
# in-process (DI resolution + a temporary in-memory SQLite for the migration
# probe).
#
# For environments without host PHP, pass RUNNER=docker to delegate to the
# project's app container (matches the Razorpay orchestrator shape):
#
#   RUNNER=docker ./scripts/validate-cms.sh
#
# Default runner is `host`.

RUNNER="${RUNNER:-host}"
CONTAINER="${CMS_TEST_CONTAINER:-temple-trust-app-1}"
PROBES_DIR="scripts/cms-probes"

declare -a PROBES=(
  "cms01-service-provider-bound.php"
  "cms02-repository-contracts-resolve.php"
  "cms03-repository-registry-has-entries.php"
  "cms04-state-machine-table-coverage.php"
  "cms05-block-renderer-registry-renders-known-blocks.php"
  "cms06-migration-tables-exist.php"
)

echo "=== CMS Kernel Probe Suite ==="
echo "Runner: $RUNNER"
[ "$RUNNER" = "docker" ] && echo "Container: $CONTAINER"
echo ""

PASS=0; FAIL=0; FATAL=0
for probe in "${PROBES[@]}"; do
  start=$(date +%s%N)
  set +e
  if [ "$RUNNER" = "docker" ]; then
    output=$(docker exec \
      -e APP_ENV=testing \
      -e "DB_CONNECTION=sqlite" -e "DB_DATABASE=:memory:" \
      "$CONTAINER" \
      php "$PROBES_DIR/$probe" 2>&1)
    rc=$?
  else
    output=$(APP_ENV=testing php "$PROBES_DIR/$probe" 2>&1)
    rc=$?
  fi
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
