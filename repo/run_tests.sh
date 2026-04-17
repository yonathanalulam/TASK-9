#!/bin/bash
# run_tests.sh — Canonical acceptance test runner for the Meridian platform
#
# Runs the FULL test suite in a cold Docker environment:
#   1. Backend unit tests (no DB)
#   2. Backend integration tests (real DB)
#   3. Backend API tests — ALL suites (no Coverage filter)
#   4. Frontend unit/component tests (Vitest)
#   5. Playwright E2E tests (real browser → Vite dev server → Symfony API)
#
# Cold Docker requirements are handled automatically:
#   - Docker services are started and waited on
#   - Test DB is created and migrated
#   - Playwright browsers are installed inside the Docker container
#   - No manual steps required
# set -e intentionally omitted — suites run independently, failures tracked manually

PASS=0
FAIL=0
ERRORS=()

step() {
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "  $1"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
}

echo ""
echo "==========================================="
echo "  Meridian Platform — Full Acceptance Suite"
echo "==========================================="

# ---------------------------------------------------------------------------
# Step 1: Start Docker services (cold-safe: builds if needed, waits for healthy)
# ---------------------------------------------------------------------------
step "[1/9] Starting Docker services"
docker compose up -d --build --wait 2>&1 | tail -10

# ---------------------------------------------------------------------------
# Step 2: Wait for PHP-FPM to finish entrypoint (migrations + warmup)
# ---------------------------------------------------------------------------
step "[2/9] Waiting for PHP entrypoint to complete"
for i in $(seq 1 90); do
    if docker compose logs php 2>&1 | grep -q "ready to handle connections"; then
        echo "  PHP-FPM is ready."
        break
    fi
    if [ "$i" -eq 90 ]; then
        echo "  WARNING: PHP-FPM readiness log not found after 180s — continuing anyway."
        docker compose logs php 2>&1 | tail -10
    fi
    sleep 2
done

# ---------------------------------------------------------------------------
# Step 3: Verify API health
# ---------------------------------------------------------------------------
step "[3/9] Verifying API health"
for i in $(seq 1 20); do
    HEALTH=$(curl -s http://localhost:8080/api/v1/health 2>/dev/null || echo '')
    if echo "$HEALTH" | grep -q '"status":"healthy"' && echo "$HEALTH" | grep -q '"database":"connected"'; then
        echo "  API health: ${HEALTH}"
        break
    fi
    if [ "$i" -eq 20 ]; then
        echo "  WARNING: API health check did not reach healthy state after 40s."
        if [ -n "$HEALTH" ]; then
            echo "  Last API health payload: ${HEALTH}"
        fi
        docker compose logs php 2>&1 | tail -20
    fi
    sleep 2
done

# ---------------------------------------------------------------------------
# Step 4: Set up test database (cold Docker: create + schema update)
# ---------------------------------------------------------------------------
step "[4/9] Setting up test database"
MSYS_NO_PATHCONV=1 docker compose exec -T php bash -c '
    cd /var/www/backend
    php bin/console doctrine:database:create --env=test --if-not-exists 2>/dev/null || true
    php bin/console doctrine:migrations:migrate --env=test --no-interaction --allow-no-migration 2>&1 | tail -5
    php bin/console doctrine:schema:update --force --env=test 2>&1 | tail -3
'
echo ""

# ---------------------------------------------------------------------------
# Step 5: Backend unit tests
# ---------------------------------------------------------------------------
step "[5/9] Running backend unit tests (no DB)"
MSYS_NO_PATHCONV=1 docker compose exec -T -e APP_ENV=test php \
    php bin/phpunit --testsuite=unit --colors=always 2>&1 | tail -15
UNIT_EXIT=${PIPESTATUS[0]}
if [ "$UNIT_EXIT" -eq 0 ]; then PASS=$((PASS + 1)); echo "  ✓ Backend Unit Tests PASSED"
else FAIL=$((FAIL + 1)); ERRORS+=("Backend Unit Tests"); echo "  ✗ Backend Unit Tests FAILED"; fi

# ---------------------------------------------------------------------------
# Step 6: Backend integration tests
# ---------------------------------------------------------------------------
step "[6/9] Running backend integration tests"
MSYS_NO_PATHCONV=1 docker compose exec -T -e APP_ENV=test php \
    php bin/phpunit --testsuite=integration --colors=always 2>&1 | tail -15
INT_EXIT=${PIPESTATUS[0]}
if [ "$INT_EXIT" -eq 0 ]; then PASS=$((PASS + 1)); echo "  ✓ Backend Integration Tests PASSED"
else FAIL=$((FAIL + 1)); ERRORS+=("Backend Integration Tests"); echo "  ✗ Backend Integration Tests FAILED"; fi

# ---------------------------------------------------------------------------
# Step 7: Backend API tests — ALL suites (was wrongly --filter=Coverage before)
# ---------------------------------------------------------------------------
step "[7/9] Running backend API tests (ALL suites — no filter)"
MSYS_NO_PATHCONV=1 docker compose exec -T -e APP_ENV=test php \
    php bin/phpunit --testsuite=api --colors=always 2>&1 | tail -20
API_EXIT=${PIPESTATUS[0]}
if [ "$API_EXIT" -eq 0 ]; then PASS=$((PASS + 1)); echo "  ✓ Backend API Tests PASSED"
else FAIL=$((FAIL + 1)); ERRORS+=("Backend API Tests"); echo "  ✗ Backend API Tests FAILED"; fi

# ---------------------------------------------------------------------------
# Step 8: Frontend unit / component tests (Vitest)
# ---------------------------------------------------------------------------
step "[8/9] Running frontend unit and component tests (Vitest)"
# Dependencies are installed at image build time via docker/node/Dockerfile.
# No runtime install is performed in the test path — see README "Cold Docker execution".
MSYS_NO_PATHCONV=1 docker compose exec -T node \
    sh -c 'cd /var/www/frontend && npx vitest run 2>&1' | tail -20
FE_EXIT=${PIPESTATUS[0]}
if [ "$FE_EXIT" -eq 0 ]; then PASS=$((PASS + 1)); echo "  ✓ Frontend Tests (Vitest) PASSED"
else FAIL=$((FAIL + 1)); ERRORS+=("Frontend Tests (Vitest)"); echo "  ✗ Frontend Tests (Vitest) FAILED"; fi

# ---------------------------------------------------------------------------
# Step 9: Playwright E2E tests (real browser → Vite dev server → Symfony API)
#
# Requires:
#   - node service running Vite dev server (started in step 1)
#   - nginx+php+mysql serving the API
#   - playwright Docker service (profile: e2e) with browser automation dependencies
#
# Waits for the frontend dev server before running.
# ---------------------------------------------------------------------------
step "[9/9] Running Playwright E2E tests (no-mock fullstack)"

# Wait for Vite dev server to be ready (cold start: dev server boot)
echo "  Waiting for Vite dev server to be ready..."
for i in $(seq 1 90); do
    if curl -sf http://localhost:5173 > /dev/null 2>&1; then
        echo "  Frontend dev server is ready."
        break
    fi
    if [ "$i" -eq 90 ]; then
        echo "  WARNING: Vite dev server not ready after 180s — E2E tests may fail."
    fi
    sleep 2
done

MSYS_NO_PATHCONV=1 docker compose --profile e2e run --rm playwright 2>&1 | tail -40
E2E_EXIT=${PIPESTATUS[0]}
if [ "$E2E_EXIT" -eq 0 ]; then PASS=$((PASS + 1)); echo "  ✓ Playwright E2E Tests PASSED"
else FAIL=$((FAIL + 1)); ERRORS+=("Playwright E2E Tests"); echo "  ✗ Playwright E2E Tests FAILED"; fi

# ---------------------------------------------------------------------------
# Summary
# ---------------------------------------------------------------------------
echo ""
echo "==========================================="
echo "  Test Results Summary"
echo "==========================================="
echo "  Passed: ${PASS}/5"
echo "  Failed: ${FAIL}/5"
if [ ${#ERRORS[@]} -gt 0 ]; then
    echo ""
    echo "  Failed suites:"
    for err in "${ERRORS[@]}"; do
        echo "    - ${err}"
    done
fi
echo ""
echo "  Suites:"
echo "    Backend unit tests     (tests/Unit/)"
echo "    Backend integration    (tests/Integration/)"
echo "    Backend API tests      (tests/Api/ — ALL suites, no filter)"
echo "    Frontend tests         (Vitest — src/**/__tests__/*.test.*)"
echo "    Playwright E2E         (e2e/ — real browser, no mocks)"
echo "==========================================="
echo ""

if [ "$FAIL" -gt 0 ]; then
    echo "FAILURE: ${FAIL} suite(s) failed. Exit code 1."
    exit 1
fi

echo "SUCCESS: All test suites passed."
exit 0
