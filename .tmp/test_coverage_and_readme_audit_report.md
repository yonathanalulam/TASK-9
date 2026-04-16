# Combined Audit Report (Static-Only Rerun)

Date: 2026-04-17
Scope: test coverage and README audit by codebase inspection only (no runtime execution).

## 1) Test Coverage Audit

### Project Shape

- Type: fullstack (`README.md:19`)
- Backend: Symfony API controllers under `backend/src/Controller/Api/V1`
- Frontend: React/Vite with Vitest and Playwright

### Static Inventory

- Backend endpoints discovered from route attributes (`methods: [...]`): **98**
- Backend test PHP files: **83** total
  - `*Test.php` files: **82**
  - support/bootstrap-style file(s): **1**
  - API: **39**
  - Unit: **33**
  - Integration: **10**
- Frontend src test files: **44**
- Playwright E2E specs: **6**
- Frontend contract-style tests (`*contract*.test.ts[x]`): **8**

### Endpoint Coverage (Static Mapping)

- Backend API tests cover all major domains present in controllers:
  - auth/health
  - stores/regions/content lifecycle + versions
  - exports/compliance
  - delivery zones/windows/mappings
  - imports/dedup
  - scraping/source health
  - warehouse/analytics
  - consent/retention
  - mutation replay/role assignment
- API suite uses real kernel HTTP style (Symfony `WebTestCase` + client) rather than mocked transport.

### Test Type Classification

- **True no-mock HTTP:** backend `tests/Api/**`
- **Unit/integration with mocking:** backend `tests/Unit/**`, frontend unit/component suites
- **Browser E2E:** `frontend/e2e/*.spec.ts` against full stack

### Mock Detection

- Backend API tests: no `createMock` / `->expects` patterns found in `backend/tests/Api/**`.
- Frontend unit/component tests: extensive `vi.mock(...)` usage across pages and API client tests.

### Sufficiency Assessment

Strengths:
- Strong backend route breadth and dedicated API behavior suites.
- Layered strategy exists (unit/integration/api/frontend/e2e).
- E2E coverage exists for key flows (auth, stores, search, exports, permissions, content).

Gaps:
- Frontend remains contract/mock heavy in several API and component tests.
- Coverage-style API tests still include permissive/routability assertions in parts of `backend/tests/Api/Coverage/**`.

Notable improvements since prior rerun:
- Integration depth increased materially (now 10 files, including repository-focused tests).
- Repository/data-access tests now exist under `backend/tests/Integration/Repository/**`.

### Coverage Governance Note

- Coverage threshold gates are no longer part of the codebase/test runner.
- `run_tests.sh` now runs five suites (unit/integration/api/frontend/e2e) with no enforced percentage thresholds.

### Test Coverage Score

**90 / 100**

Rationale:
- High breadth and solid backend HTTP testing drive the score up.
- Score reduced by contract-heavy frontend tests and permissive API coverage assertions in parts of the coverage suite.

---

## 2) README Audit

Target: `README.md`

### Hard Gate Checks

1) Formatting/readability
- Pass: structured, comprehensive, and navigable.

2) Startup instructions
- Pass: includes Docker startup instructions (`docker-compose up`, `docker compose up -d`).

3) Access instructions
- Pass: API/frontend/database access points clearly documented.

4) Verification method
- Pass: includes UI/API verification steps and `./run_tests.sh` usage.

5) Environment rules
- Pass: Docker-first workflow, no required host package-manager setup in main path.

6) Credentials
- Pass: demo users and roles listed.

### Consistency/Quality Notes

- README test strategy and current `run_tests.sh` flow are aligned around five suites.
- README static verification guidance now avoids hardcoded route counts, reducing doc drift risk.
- Background-jobs table includes a business threshold reference (`14-day threshold`) that is not test-coverage related.

### README Verdict

**PASS**

---

## Final Verdicts

- Test coverage audit: broad and competent, but depth and confidence can improve.
- README audit: passes strict onboarding/compliance gates for a fullstack repo.

## Highest-Value Improvements

1. Update `README.md` route-count note from "93+" to current route inventory guidance.
2. Convert low-signal frontend contract tests into behavior-focused UI/service tests.
3. Tighten permissive API coverage assertions into strict state/business assertions.
4. Continue growing integration cases around persistence edge cases and failure paths.
