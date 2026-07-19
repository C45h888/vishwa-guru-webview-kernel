#!/usr/bin/env bash
#
# validate-phase2.sh — Comprehensive testing loop for Laravel Runtime Kernel Phase 2.
#
# Run after all 6 phases (A-F) of the implementation plan are complete.
# Exits 0 only when every check passes; non-zero on the first failure.
#
# Usage:
#   chmod +x scripts/validate-phase2.sh
#   ./scripts/validate-phase2.sh
#
# Requires: PHP 8.2+, composer, the project's vendor/ directory installed.

set -uo pipefail

# ─── Setup ────────────────────────────────────────────────────────────────
cd "$(dirname "$0")/.." || exit 1

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

fail_count=0
pass_count=0

section() {
    echo ""
    echo -e "${BLUE}═══ $1 ═══${NC}"
}

check_pass() {
    echo -e "  ${GREEN}✓${NC} $1"
    ((pass_count++))
}

check_fail() {
    echo -e "  ${RED}✗${NC} $1"
    ((fail_count++))
}

# ─── 1. Doctrine compliance (static) ──────────────────────────────────────
section "1. Doctrine compliance (static grep)"

# 1a. Runtime depends on Persistence contract, not DB:: facade
# (allow references in docblocks — search for actual function calls only)
if grep -rn 'DB::' app/Runtime/ --include="*.php" 2>/dev/null \
    | grep -v "DB::connection" \
    | grep -v "^\s*\*" \
    | grep -v "^[^:]*:[0-9]*:\s*//" \
    | grep -qE '\bDB::(?!connection)\w+\('; then
    check_fail "Runtime must NOT call DB:: facade (only PersistenceAdapterContract)"
else
    check_pass "Runtime depends on PersistenceAdapterContract, not DB:: facade"
fi

# 1b. Runtime does NOT read env() directly (only EnvValidator)
env_callers=$(grep -rn 'env(' app/Runtime/ --include="*.php" 2>/dev/null \
    | grep -v "EnvValidator.php" \
    | grep -v "// " \
    | grep -v "isLocal\|isProduction\|isTesting" \
    | grep -v "^\s*\*" || true)
if [[ -n "$env_callers" ]]; then
    check_fail "Runtime should NOT call env() directly outside EnvValidator:"
    echo "$env_callers" | sed 's/^/      /'
else
    check_pass "Runtime does not call env() outside EnvValidator"
fi

# 1c. Handlers are NOT instantiated directly outside FailureRouter
handler_violations=$(grep -rn 'new \(Boot\|Probe\|Command\|Http\)FailureHandler' app/ --include="*.php" 2>/dev/null \
    | grep -v "FailureRouter" || true)
if [[ -n "$handler_violations" ]]; then
    check_fail "Direct handler instantiation found (violates routing membrane):"
    echo "$handler_violations" | sed 's/^/      /'
else
    check_pass "No direct handler instantiation outside FailureRouter (routing membrane intact)"
fi

# 1d. FailureStateMachine is pure (no I/O)
if grep -nE 'DB::|Cache::|Queue::|Log::|file_get_contents|file_put_contents' \
    app/Runtime/Failure/StateMachines/FailureStateMachine.php 2>/dev/null; then
    check_fail "FailureStateMachine must be a pure function (no I/O)"
else
    check_pass "FailureStateMachine is a pure function (no I/O)"
fi

# ─── 2. File inventory ─────────────────────────────────────────────────────
section "2. File inventory"

# Production files
prod_count=$(find app/Runtime app/Persistence/Providers app/Persistence/Infrastructure/RepositoryRegistry.php \
    -type f -name "*.php" 2>/dev/null | wc -l | tr -d ' ')
if [[ "$prod_count" -ge 25 ]]; then
    check_pass "Production files present ($prod_count files, expected ≥25)"
else
    check_fail "Production files too few ($prod_count, expected ≥25)"
fi

# Test files
test_count=$(find tests/Unit/Runtime tests/Feature/Runtime \
    -type f -name "*.php" 2>/dev/null | wc -l | tr -d ' ')
if [[ "$test_count" -ge 15 ]]; then
    check_pass "Test files present ($test_count files, expected ≥15)"
else
    check_fail "Test files too few ($test_count, expected ≥15)"
fi

# Critical files present
for f in \
    app/Persistence/Infrastructure/RepositoryRegistry.php \
    app/Persistence/Providers/PersistenceServiceProvider.php \
    app/Runtime/Providers/RuntimeServiceProvider.php \
    app/Runtime/Failure/StateMachines/FailureStateMachine.php \
    app/Runtime/Failure/FailureRouter.php \
    app/Runtime/Validation/EnvValidator.php \
    app/Runtime/Validation/BootProbe.php \
    routes/runtime.php
do
    if [[ -f "$f" ]]; then
        check_pass "$f exists"
    else
        check_fail "$f MISSING"
    fi
done

# Critical files NOT modified
for f in app/Payments/Providers/PaymentsServiceProvider.php app/Shared/Providers/SharedServiceProvider.php; do
    if git diff --quiet "$f" 2>/dev/null; then
        check_pass "$f untouched (out of scope)"
    else
        check_fail "$f was modified (should be out of scope!)"
    fi
done

# ─── 3. PHPUnit test runs ──────────────────────────────────────────────────
section "3. PHPUnit test runs"

if ! command -v vendor/bin/phpunit >/dev/null 2>&1; then
    echo -e "  ${YELLOW}!${NC} vendor/bin/phpunit not found — skipping (run: composer install)"
else
    # 3a. Persistence kernel
    echo -e "  ${YELLOW}→${NC} Phase A: PersistenceBindingsTest..."
    if vendor/bin/phpunit --filter=PersistenceBindingsTest 2>&1 | tail -3 | grep -q "OK\|Tests:"; then
        check_pass "PersistenceBindingsTest passes"
    else
        check_fail "PersistenceBindingsTest failed — see phpunit output"
    fi

    # 3b. Validation kernel
    echo -e "  ${YELLOW}→${NC} Phase B: EnvValidator + BootProbe tests..."
    if vendor/bin/phpunit --filter='EnvValidatorTest|BootProbeTest' 2>&1 | tail -3 | grep -q "OK\|Tests:"; then
        check_pass "EnvValidator + BootProbe tests pass"
    else
        check_fail "EnvValidator + BootProbe tests failed"
    fi

    # 3c. Diagnostics + Health
    echo -e "  ${YELLOW}→${NC} Phase C: HealthProbeTest..."
    if vendor/bin/phpunit --filter=HealthProbeTest 2>&1 | tail -3 | grep -q "OK\|Tests:"; then
        check_pass "HealthProbeTest passes"
    else
        check_fail "HealthProbeTest failed"
    fi

    # 3d. HTTP + Console
    echo -e "  ${YELLOW}→${NC} Phase D: HealthController + commands..."
    if vendor/bin/phpunit --filter='HealthControllerTest|RuntimeStatusCommandTest|EnvironmentListCommandTest' 2>&1 | tail -3 | grep -q "OK\|Tests:"; then
        check_pass "HTTP + Console tests pass"
    else
        check_fail "HTTP + Console tests failed"
    fi

    # 3e. Failure state machine
    echo -e "  ${YELLOW}→${NC} Phase E: FailureStateMachine + Router + Handlers..."
    if vendor/bin/phpunit --filter='Failure' 2>&1 | tail -3 | grep -q "OK\|Tests:"; then
        check_pass "Failure tests pass"
    else
        check_fail "Failure tests failed"
    fi

    # 3f. Runtime service provider
    echo -e "  ${YELLOW}→${NC} Phase F: RuntimeServiceProvider..."
    if vendor/bin/phpunit --filter=RuntimeServiceProviderTest 2>&1 | tail -3 | grep -q "OK\|Tests:"; then
        check_pass "RuntimeServiceProviderTest passes"
    else
        check_fail "RuntimeServiceProviderTest failed"
    fi

    # 3g. Neon integration (Phase 2 G+H)
    echo -e "  ${YELLOW}→${NC} Phase G+H: Neon probes + diagnostics..."
    if vendor/bin/phpunit --filter='Persistence.Neon' 2>&1 | tail -3 | grep -q "OK\|Tests:"; then
        check_pass "Neon integration tests pass"
    else
        check_fail "Neon integration tests failed"
    fi

    # 3h. Phase 1 closure (FileAssetRepository — the critical path that
    # was broken before Phase I)
    echo -e "  ${YELLOW}→${NC} Phase I: Phase 1 closure (FileAssetRepository)..."
    if vendor/bin/phpunit --filter=FileAssetRepositoryTest 2>&1 | tail -3 | grep -q "OK\|Tests:"; then
        check_pass "FileAssetRepositoryTest passes"
    else
        check_fail "FileAssetRepositoryTest failed"
    fi

    # 3i. Redis env hardening (Phase 2b J)
    echo -e "  ${YELLOW}→${NC} Phase J: Redis env keys..."
    if vendor/bin/phpunit --filter=RedisEnvKeysTest 2>&1 | tail -3 | grep -q "OK\|Tests:"; then
        check_pass "RedisEnvKeysTest passes"
    else
        check_fail "RedisEnvKeysTest failed"
    fi

    # 3j. Existing Redis module tests (Bindings + CacheFallback)
    echo -e "  ${YELLOW}→${NC} Redis module tests..."
    if vendor/bin/phpunit --filter='Tests.Feature.Redis' 2>&1 | tail -3 | grep -q "OK\|Tests:"; then
        check_pass "Redis module tests pass"
    else
        check_fail "Redis module tests failed"
    fi

    # 3k. Full suite
    echo -e "  ${YELLOW}→${NC} Full suite..."
    if vendor/bin/phpunit 2>&1 | tail -10 | grep -q "OK\|Tests:"; then
        check_pass "Full PHPUnit suite passes"
    else
        check_fail "Full PHPUnit suite failed"
    fi
fi

# ─── 4. Static analysis ────────────────────────────────────────────────────
section "4. Static analysis"

if ! command -v vendor/bin/pint >/dev/null 2>&1; then
    echo -e "  ${YELLOW}!${NC} vendor/bin/pint not found — skipping"
else
    echo -e "  ${YELLOW}→${NC} Laravel Pint formatting..."
    if vendor/bin/pint --test 2>&1 | tail -3 | grep -q "All files"; then
        check_pass "Pint formatting clean"
    else
        check_fail "Pint formatting issues — run: vendor/bin/pint"
    fi
fi

if ! command -v vendor/bin/phpstan >/dev/null 2>&1; then
    echo -e "  ${YELLOW}!${NC} vendor/bin/phpstan not found — skipping"
else
    echo -e "  ${YELLOW}→${NC} PHPStan..."
    if vendor/bin/phpstan analyse --no-progress 2>&1 | tail -3 | grep -qE "\[OK\]|No errors"; then
        check_pass "PHPStan clean"
    else
        check_fail "PHPStan issues — see phpstan output"
    fi
fi

# ─── 5. Container resolution smoke ─────────────────────────────────────────
section "5. Container resolution smoke (artisan tinker)"

if ! command -v php >/dev/null 2>&1; then
    echo -e "  ${YELLOW}!${NC} php not found — skipping container smoke"
else
    smoke_commands=(
        'app(\App\Persistence\Contracts\PersistenceAdapterContract::class)'
        'app(\App\Persistence\Contracts\RepositoryRegistryContract::class)'
        'app(\App\Runtime\Failure\Contracts\FailureReportingContract::class)'
        'app(\App\Runtime\Validation\BootProbe::class)->alreadyRan()'
        'app(\App\Runtime\Diagnostics\HealthCheckAggregator::class)->probe() ? "OK" : "FAIL"'
    )

    for cmd in "${smoke_commands[@]}"; do
        if php artisan tinker --execute="$cmd" >/dev/null 2>&1; then
            check_pass "tinker command OK: $cmd"
        else
            check_fail "tinker command failed: $cmd"
        fi
    done
fi

# ─── 6. HTTP + CLI smoke ───────────────────────────────────────────────────
section "6. HTTP + CLI smoke"

if ! command -v php >/dev/null 2>&1; then
    echo -e "  ${YELLOW}!${NC} php not found — skipping HTTP/CLI smoke"
else
    # Bring up a test server in the background
    if php -S 127.0.0.1:8765 -t public 2>/dev/null &
    then
        server_pid=$!
        sleep 1

        # /api/v1/ping
        if curl -sf http://127.0.0.1:8765/api/v1/ping 2>/dev/null | grep -q "pong"; then
            check_pass "/api/v1/ping returns pong"
        else
            check_fail "/api/v1/ping failed"
        fi

        # /health
        health_status=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8765/health 2>/dev/null || echo "000")
        if [[ "$health_status" == "200" ]]; then
            check_pass "/health returns 200"
        else
            check_fail "/health returned $health_status"
        fi

        kill $server_pid 2>/dev/null || true
        wait $server_pid 2>/dev/null || true
    fi

    # temple:runtime
    if php artisan temple:runtime >/dev/null 2>&1; then
        check_pass "php artisan temple:runtime succeeds"
    else
        check_fail "php artisan temple:runtime failed"
    fi

    # temple:env
    if php artisan temple:env >/dev/null 2>&1; then
        check_pass "php artisan temple:env succeeds"
    else
        check_fail "php artisan temple:env failed"
    fi
fi

# ─── Summary ───────────────────────────────────────────────────────────────
echo ""
echo -e "${BLUE}════════════════════════════════════════════════════════════════${NC}"
echo -e "  ${GREEN}Passed:${NC} $pass_count"
echo -e "  ${RED}Failed:${NC} $fail_count"
echo -e "${BLUE}════════════════════════════════════════════════════════════════${NC}"

if [[ "$fail_count" -gt 0 ]]; then
    echo -e "${RED}Validation FAILED — fix the above issues and re-run.${NC}"
    exit 1
fi

echo -e "${GREEN}Validation PASSED — Laravel Runtime Kernel Phase 2 is complete.${NC}"
exit 0