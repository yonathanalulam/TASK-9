# Meridian Offline Commerce & Compliance Intelligence Platform

A local-network platform for multi-site retail operations, combining offline delivery management with searchable internal content, full compliance controls, and analytics — all running without internet dependencies.

## Architecture

```
┌─────────────┐     ┌──────────────┐     ┌───────────┐
│   React UI  │────▶│  Nginx :80   │────▶│ PHP-FPM   │
│  Vite :5173 │     │  (reverse    │     │ Symfony   │
│             │     │   proxy)     │     │ REST API  │
└─────────────┘     └──────────────┘     └─────┬─────┘
                                               │
                                         ┌─────▼─────┐
                                         │ MySQL 8.0 │
                                         │ :3306     │
                                         └───────────┘
```
**Project**: fullstack
**Backend**: Symfony 7.4 (PHP 8.3) — REST APIs under `/api/v1`
**Frontend**: React 18 + Vite + TypeScript
**Database**: MySQL 8.0 (transactional + warehouse schemas)
**Async**: Symfony Messenger with Doctrine transport (no Redis)
**Containers**: Docker Compose — php-fpm, nginx, mysql, node, worker (plus a `playwright` E2E profile)

## Repository Structure

```
repo/
├── docker-compose.yml          # All 5 services
├── docker/                     # Dockerfiles and configs
│   ├── php/Dockerfile          # PHP 8.3-fpm-alpine
│   ├── node/Dockerfile         # Node 18 + frontend npm deps installed at build
│   ├── playwright/Dockerfile   # Playwright + frontend deps + Chromium baked in
│   ├── nginx/conf.d/           # Nginx vhost
│   └── mysql/conf.d/           # MySQL charset/timezone
├── backend/                    # Symfony application
│   ├── src/
│   │   ├── Controller/Api/V1/  # 25 REST controllers
│   │   ├── Entity/             # Doctrine entities (50+)
│   │   ├── Security/           # Permission constants + 14 voters
│   │   ├── Service/            # Domain services
│   │   └── Command/            # Console commands
│   ├── migrations/             # Doctrine migrations
│   └── tests/                  # PHPUnit tests
├── frontend/                   # React + Vite + TypeScript
│   ├── src/
│   │   ├── api/                # Typed API client layer
│   │   ├── pages/              # Page components
│   │   └── components/         # Shared UI components
│   └── vitest.config.ts        # Frontend test config
├── ASSUMPTIONS.md
└── RUNBOOK.md                  # Supplementary operational reference
```

## Quick Start

### Prerequisites
- Docker and Docker Compose

### Setup

```bash
# Start all services — builds images, installs dependencies, runs migrations,
# and seeds demo data automatically via the PHP entrypoint
docker-compose up

# Detached mode (logs suppressed)
docker compose up -d
```

### Services

| Service | URL | Purpose |
|---------|-----|---------|
| API | http://localhost:8080/api/v1 | Symfony REST API |
| Frontend | http://localhost:5173 | React dev server |
| MySQL | localhost:3307 | Database |

### Default Users

| Username | Password | Role |
|----------|----------|------|
| admin | Demo#Password1! | Administrator (Global) |
| mgr_north | Demo#Password1! | Store Manager (NORTH region) |
| mgr_south | Demo#Password1! | Store Manager (SOUTH region) |
| dispatch1 | Demo#Password1! | Dispatcher (Global) |
| analyst1 | Demo#Password1! | Operations Analyst (Global) |
| recruit1 | Demo#Password1! | Recruiter (NORTH region) |
| comply1 | Demo#Password1! | Compliance Officer (Global) |

## Test Strategy

The Meridian platform maintains five distinct test layers with strong behavioral assertions.
`run_tests.sh` is the **canonical acceptance runner** that executes all checks
in a cold Docker environment with no manual prerequisites.

- **Backend integration tests** cover service + DB interactions for content lifecycle, store management, import processing, and export workflows.
- **Repository tests** validate query filters, scope constraints, pagination, and ordering against real data.
- **API tests** use strict status codes and envelope shape assertions (not permissive route-existence checks).
- **Frontend tests** combine behavior-first component tests with API client tests that verify request construction and response handling.
- **E2E tests** exercise full browser → Vite → nginx → PHP → MySQL flows with no mocked transport.

### Quick start — run all tests

```bash
# From the repo root — works in a fresh Docker environment
./run_tests.sh
```

This script:
1. Starts all Docker services (builds if needed)
2. Creates and migrates the test database
3. Runs backend unit tests (no DB)
4. Runs backend integration tests
5. Runs **all** backend API tests (`tests/Api/` — no filter)
6. Runs frontend Vitest component/hook tests
7. Runs Playwright E2E tests (real browser, **no mocked API transport**)
8. Prints a pass/fail summary

The script exits with code 1 if any suite fails.

#### Deterministic, immutable test environment

`run_tests.sh` does **not** install dependencies at runtime. All
language-level dependencies (`composer install`, `npm ci`, Playwright
Chromium) are baked into the Docker images at build time:

- Backend PHP deps → installed in `docker/php/Dockerfile`
- Frontend Node deps → installed in `docker/node/Dockerfile`
- Playwright + Chromium → installed in `docker/playwright/Dockerfile`

The compose `node` and `playwright` services use a named-volume overlay on
`/var/www/frontend/node_modules` so the host bind mount cannot shadow the
image-installed dependencies. If you change `frontend/package.json` or
`frontend/package-lock.json`, rebuild with `docker compose build node playwright`.
If you change `backend/composer.lock`, rebuild with `docker compose build php worker`.
No inline dependency install is performed in the normal start or test path.

### Test layers

| Layer | Location | Tool | What it tests |
|-------|----------|------|---------------|
| **Unit** | `backend/tests/Unit/` | PHPUnit | Services, voters, utilities — no DB, no HTTP |
| **Integration** | `backend/tests/Integration/` | PHPUnit | DB-backed service flows (auth, RBAC) |
| **API** | `backend/tests/Api/` | PHPUnit + Symfony kernel | Real HTTP requests into Symfony — auth, CRUD, lifecycle, scope |
| **Frontend** | `frontend/src/**/__tests__/` | Vitest + RTL | Component rendering, form behavior, state transitions |
| **E2E** | `frontend/e2e/` | Playwright | Full `browser → Vite → nginx → PHP → MySQL` flow, **no mocks** |

### Backend API tests (PHPUnit)

```bash
# All API tests (Coverage + Behavior + Auth + Security + Store + Search ...)
docker compose exec -e APP_ENV=test php php bin/phpunit --testsuite=api

# Specific subdirectory
docker compose exec -e APP_ENV=test php php bin/phpunit --testsuite=api \
  --filter="App\\Tests\\Api\\Behavior"
```

The `tests/Api/` directory contains:
- `Auth/` — login contract, token revocation, change password
- `Security/` — endpoint authorization matrix, role-to-permission enforcement
- `Store/` — CRUD, optimistic concurrency, 403 for non-admin
- `Search/` — response shape, filtering, pagination, validation
- `Region/` — CRUD lifecycle
- `Coverage/` — broad endpoint accessibility
- `Behavior/` — **strengthened** exact behavior: DeliveryZone, Export, ScopedAccess, ContentLifecycle
- `Compliance/`, `Content/`, `Envelope/` — contract validation

### Backend unit tests

```bash
docker compose exec -e APP_ENV=test php php bin/phpunit --testsuite=unit
```

### Frontend component/hook tests (Vitest)

```bash
docker compose exec node sh -c 'cd /var/www/frontend && npx vitest run'
```

### Playwright E2E tests (no-mock fullstack)

The E2E suite uses real Chromium, the real Vite dev server, and the real Symfony API.
**No API calls are mocked** — every assertion exercises the complete
`browser → Vite dev server → nginx → PHP-FPM → MySQL` stack.

The critical journeys covered:

| Spec | Tests | What it validates |
|------|-------|-------------------|
| `auth.spec.ts` | A1-A5 | Login form (real `#username`/`#password` fill + click), invalid credentials show browser error, unauthenticated redirect |
| `search.spec.ts` | B1-B4 | Search page, query triggers real backend call, API envelope shape |
| `stores.spec.ts` | C1-C4 | Store list navigation, store create/update persistence |
| `exports.spec.ts` | D1-D6 | Export lifecycle, status transitions, download gating |
| `permissions.spec.ts` | E1-E9 | ADMIN vs ANALYST via API and browser UI; E9 navigates to `/exports/new` as analyst, submits form, backend returns 403, browser shows visible error |
| `content.spec.ts` | F1-F4 | **Real UI form journey**: fills `ContentCreatePage` form fields in the browser, clicks "Create Content", verifies redirect to `/content/:id`, verifies persistence via backend GET |

```bash
# Full E2E run inside Docker (dependencies and browsers installed automatically)
docker compose --profile e2e run --rm playwright
```

**E2E prerequisites** (all handled automatically by `run_tests.sh`):
- Docker services running (php, nginx, mysql, node)
- `VITE_API_URL=/api/v1` set in node service (Vite proxy routes API calls to nginx)
- Seeded demo data (admin / Demo#Password1!, analyst1 / Demo#Password1!)
- Playwright Chromium installed inside the playwright Docker image

### Cold Docker execution

`run_tests.sh` is designed for a fully cold Docker environment:
- No assumed warmed caches or pre-run state
- Builds Docker images if they don't exist (frontend deps + Chromium are
  installed during `docker compose build`, not at test runtime)
- Waits for PHP-FPM, API health, and Vite dev server readiness
- Creates and migrates the test database from scratch
- Returns exit code 1 if any suite fails

**No runtime package install is performed in any normal start or test
path.** All `npm ci` / `playwright install` work happens once during the
image build phase, which is what makes the test environment reproducible.

## Background Jobs

| Command | Schedule | Purpose |
|---------|----------|---------|
| `app:search:index` | Every 5 min | Incremental search indexing |
| `app:search:cleanup` | Daily | Remove orphaned index entries (14-day threshold) |
| `app:search:compact` | Weekly (Sun 03:00) | OPTIMIZE TABLE — non-blocking reads |
| `app:retention:scan` | Daily | Scan for retention-eligible entities |
| `app:keys:check-rotation` | Daily | Rotate encryption keys older than 90 days |
| `app:exports:expire` | Hourly | Mark expired exports |
| `app:warehouse:load` | Nightly | ETL warehouse dimensions + facts |
| `app:scrape:run` | Configurable | Run approved intranet source scraping |
| `app:audit:verify-chain` | On-demand | Verify tamper-evident audit hash chain |
| `app:backup:verify` | Weekly | Backup checksum verification |

The `worker` Docker service consumes Messenger async jobs. Restart with `docker compose restart worker`.

## Database Migrations

Two canonical Doctrine migrations manage the schema:
1. `Version20260414083521` — Core tables (users, roles, stores, regions, zones, audit)
2. `Version20260414092601` — Phase 2 tables (boundary_imports, mutation_queue_log)

Phase 3-5 tables are managed via `doctrine:schema:update`. All migrations and schema updates run automatically via the PHP entrypoint on `docker compose up` — no manual commands are needed for normal operation.

## Authorization Model

All API endpoints use centralized permission constants from `App\Security\Permission`. Every `denyAccessUnlessGranted()` call references a `Permission::` constant — no raw string permission literals exist in controller code. Each permission is mapped to roles via dedicated Voter classes in `App\Security\Voter\`. There are 14 voters covering content, search, export, compliance, import, analytics, scraping, warehouse, mutation queue, stores, regions, zones, users, and classifications. The role-to-permission mapping follows the spec's capability matrix.

## Key Compliance Features

- **Exports**: CSV format only. Watermarked with `{username} MM/DD/YYYY hh:mm AM/PM`. Datasets: `content_items`, `audit_events`
- **Compliance reports**: Tamper-evident hash chain, downloadable JSON
- **Audit trail**: Append-only with SHA-256 chain verification
- **Sensitive data**: Field-level masking (SSN → `***-**-1234`) via response subscriber
- **Encryption**: AES-256-GCM with 90-day key rotation
- **Retention**: 365-day policy with delete/anonymize enforcement
- **Scraping**: Max 30 req/min with self-healing degradation chain

## Scope Isolation

List and show endpoints enforce actor-derived scope filtering:
- Store lists are filtered by the user's accessible store IDs (via `ScopeResolver`)
- Content list and show endpoints enforce both store-level and region-level scope. Region-only content (no `store_id`, only `region_id`) is included for users with matching region access via an OR condition: `(storeId IN accessible_stores) OR (storeId IS NULL AND regionId IN accessible_regions)`
- Search results apply the same dual scope filter — region-only indexed content is visible to users with matching region access
- Region lists are filtered by the user's accessible region IDs for non-global users
- Delivery zone lists verify the user has access to the parent store
- Mutation replay enforces the same role + scope policy as the normal controller/voter path for each entity type:
  - Store update: requires STORE_MANAGER or ADMINISTRATOR role + `canAccessStore()` scope (mirrors `StoreVoter::canEdit`)
  - Zone create: requires STORE_MANAGER, DISPATCHER, or ADMINISTRATOR role + `canAccessStore()` scope on parent store (mirrors `DeliveryZoneVoter::canCreate`)
  - Zone update: requires STORE_MANAGER, DISPATCHER, or ADMINISTRATOR role + `canAccessDeliveryZone()` scope (mirrors `DeliveryZoneVoter::canEdit`)
  - Store/region create: requires ADMINISTRATOR role (mirrors `StoreVoter::canCreate` / `RegionVoter::canCreate`)
  - Region update: requires ADMINISTRATOR role + `canAccessRegion()` scope

## Zone Coverage Mapping

Boundary import creates `AdministrativeArea` and `CommunityGrid` entities. These can be linked to delivery zones via the zone mappings API:
- `POST /api/v1/delivery-zones/{zoneId}/mappings` — link an area/grid to a zone
- `GET /api/v1/delivery-zones/{zoneId}/mappings` — list mappings for a zone
- Mapping types: `administrative_area`, `community_grid`
- Duplicate mappings are rejected (409 Conflict)

## Debug Exception Policy

Exception details (class name, stack trace) are only included in error responses when `APP_DEBUG=true` (the kernel debug flag). This is `true` by default in local `dev` environment and `false` in production. Shared test environments should set `APP_DEBUG=false` to avoid leaking implementation details.

## Observability

Structured logging covers:
- Authorization denials (403) — WARNING level
- Authentication failures (401) — NOTICE level
- Server errors (500) — ERROR level
- Export operations — INFO/ERROR level
- Sensitive field masking events — INFO level

No sensitive classified values appear in log output.

## Known Assumptions

See `ASSUMPTIONS.md` for all strengthened assumptions. Key ones:
- Single MySQL database with `wh_` prefix for warehouse tables
- MySQL FULLTEXT for search (no Elasticsearch)
- Symfony Messenger with Doctrine transport (no Redis)
- Session tokens (not JWTs) for immediate revocation support
- AES-256-GCM encryption with master key from environment variable

## Acceptance Verification

After `docker-compose up`, verify the platform is working end-to-end:

### 1. UI verification (browser)

1. Open http://localhost:5173/login
2. Log in as **admin** / `Demo#Password1!` — expect redirect to dashboard
3. Navigate to **Stores** (`/stores`) — expect a paginated store list
4. Navigate to **Content** (`/content`) — expect a content list page
5. Navigate to **Search** (`/search`) — type a query, expect results or empty state (no crash)
6. Navigate to **Exports** (`/exports`) — expect an export list

### 2. API verification (curl)

```bash
# Health check — expect {"data":{"status":"healthy",...},"error":null}
curl -s http://localhost:8080/api/v1/health | head -c 100

# Login — expect 200 with token
curl -s http://localhost:8080/api/v1/auth/login \
  -X POST -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"Demo#Password1!"}' | head -c 120

# Authenticated store list — expect 200 with {"data":[...],"meta":{"pagination":...}}
TOKEN=$(curl -s http://localhost:8080/api/v1/auth/login \
  -X POST -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"Demo#Password1!"}' \
  | grep -o '"token":"[^"]*"' | cut -d'"' -f4)

curl -s http://localhost:8080/api/v1/stores \
  -H "Authorization: Bearer $TOKEN" | head -c 200
```

### 3. Role-based verification

| Role | Credential | Can access | Cannot access |
|------|-----------|------------|---------------|
| admin | `Demo#Password1!` | All endpoints | — |
| analyst1 | `Demo#Password1!` | Analytics, exports, search | Store create, region create, scraping |
| mgr_north | `Demo#Password1!` | NORTH region stores, content | SOUTH region stores |
| comply1 | `Demo#Password1!` | Compliance, exports, audit | Store management |

### 4. Full test suite

```bash
./run_tests.sh
```

Runs backend and frontend suites in Docker and exits non-zero on any failure.

## Static Verification Guidance

A reviewer can verify the implementation by checking:
1. `docker compose exec php php bin/console debug:router` — verify all registered API routes
2. `docker compose exec php php bin/console doctrine:schema:validate` — schema in sync
3. `docker compose exec -e APP_ENV=test php php bin/phpunit --testsuite=unit` — all unit tests pass
4. `docker compose exec node sh -c 'cd /var/www/frontend && npx vitest run'` — all frontend tests pass
5. `docker compose exec node sh -c 'cd /var/www/frontend && npx tsc --noEmit'` — zero TypeScript errors
6. `grep -r "Permission::" backend/src/Controller/` — all controllers use centralized permissions
7. `grep -r "ROLE_ADMIN\|ROLE_USER" backend/src/Controller/` — no raw Symfony roles in controllers
