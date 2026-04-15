# Meridian Offline Commerce & Compliance Intelligence — Design

## Overview

Meridian is a fullstack offline-first platform for multi-site retail operations. It unifies local community delivery management, internal content search, data governance, and compliance reporting into a single interface that functions reliably in disconnected environments.

---

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                        Browser Client                        │
│  React 18 + Vite + TypeScript                                │
│  Zustand (auth/connectivity) · TanStack Query · Axios        │
│  IndexedDB Mutation Queue (offline write buffer)             │
└────────────────────────┬────────────────────────────────────┘
                         │ HTTP (local network)
                         ▼
┌─────────────────────────────────────────────────────────────┐
│                     Nginx Reverse Proxy                      │
│  Port 8080 — routes /api/* → PHP-FPM, /* → Vite bundle      │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────┐
│                   Symfony 7.4 REST API                       │
│  PHP 8.3-FPM · Doctrine ORM · 25+ controllers               │
│  14 Voters · 34 permissions · Session-based auth            │
│  48 domain services organized by business capability         │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────┐
│                       MySQL 8.0                              │
│  Transactional schema (stores, users, content, orders)       │
│  Warehouse schema (star-schema, wh_ prefix)                  │
│  FULLTEXT indexes for content search                         │
└─────────────────────────────────────────────────────────────┘
```

### Container Stack

Five Docker Compose services:

| Service | Image | Port | Role |
|---------|-------|------|------|
| `nginx` | nginx:1.25-alpine | 8080 | Reverse proxy / static bundle |
| `php` | php:8.3-fpm-alpine | — | Symfony application |
| `mysql` | mysql:8.0 | 3306 | Primary database |
| `node` | node:20-alpine | 5173 | Vite dev server |
| `worker` | php:8.3-fpm-alpine | — | Background console commands |

---

## Tech Stack

### Backend

| Component | Choice | Rationale |
|-----------|--------|-----------|
| Framework | Symfony 7.4 | Mature, deterministic, no magic; native Voter/Event system |
| ORM | Doctrine 3.6 | Native MySQL FULLTEXT support, migrations, optimistic locking |
| Auth | Session tokens | Immediate revocation without token blacklist; stateful on-LAN |
| Search | MySQL FULLTEXT (Boolean mode) | No external dependency; weighted scoring across title, body, tags, author |
| Async | Symfony Messenger + Doctrine transport | No Redis required; jobs persist through restarts |
| Encryption | AES-256-GCM + locally managed keys | Environment-variable master key; per-field key IDs tracked in DB |
| Serialization | Symfony Serializer + manual shape serializers | Stable contract shapes; no hidden field leakage |

### Frontend

| Component | Choice |
|-----------|--------|
| Framework | React 18.3 + TypeScript 5.6 |
| Build | Vite 5.4 |
| Routing | React Router DOM v6 |
| State | Zustand 4.5 (auth, connectivity) |
| Data fetching | TanStack React Query 5 + Axios |
| Forms | React Hook Form + Zod 3.24 |
| Offline queue | Custom IndexedDB-backed mutation queue |
| Unit tests | Vitest + @testing-library/react |
| E2E tests | Playwright 1.44 |

---

## Domain Model

### Core Entities

```
User ──┬── UserRoleAssignment ── Role
       └── Session

Store ──┬── DeliveryZone ──┬── DeliveryWindow
        │                  ├── ZoneProductRule
        │                  ├── ZoneOrderRule
        │                  └── ZoneMapping ── (AdministrativeArea | CommunityGrid)
        └── BoundaryImport

MdmRegion ──── Store (many-to-one)

ContentItem ──┬── ContentVersion
              ├── ContentSearchIndex
              └── ContentTag

ImportBatch ──── ImportItem ──── ContentFingerprint (dedup)
DuplicateResolutionEvent

ExportJob
ComplianceReport

AuditEvent ──── AuditEventHash (SHA-256 chain)
ConsentRecord
RetentionCase
DataClassification ──── EncryptedFieldValue ──── EncryptionKey

Scraping:
  SourceDefinition ──┬── ScrapeRun ──── ScrapeRunItem
                     └── SourceHealthEvent

Warehouse (wh_ prefix):
  wh_dim_product, wh_dim_customer, wh_dim_channel, wh_dim_region, wh_dim_time
  wh_fact_sales
  wh_mdm_region, wh_mdm_product, wh_mdm_customer
```

### Two Logical Database Schemas

**Transactional** — Operational entities accessed at runtime (stores, users, content, orders metadata, audit log).

**Warehouse** — Analytics star-schema tables with `wh_` prefix. Populated nightly by `RunWarehouseLoadCommand` via ETL. Read-only from the API (`AnalyticsController`, `WarehouseLoadController`). Dimension tables follow master-data coding rules (e.g., region codes 2–5 uppercase characters, uniqueness, referential integrity, effective dates).

---

## Security & Authorization

### Authentication

Session-based with `Authorization: Bearer <token>` header. `SessionAuthenticator` validates tokens against the `sessions` table. Sessions are revocable immediately. `AccountLockoutService` applies per-IP and per-username brute-force protection.

### RBAC

Six roles defined in `RoleName` enum:

| Role | Typical capabilities |
|------|---------------------|
| `ADMINISTRATOR` | Full access; manages users/roles; creates stores and regions |
| `STORE_MANAGER` | Reads/edits stores and delivery zones within their scope |
| `DISPATCHER` | Creates and edits delivery zones within their store scope |
| `ANALYST` | Runs searches, views analytics and content |
| `RECRUITER` | Views and searches job content |
| `COMPLIANCE_OFFICER` | Generates compliance reports, manages retention cases |

Role assignments carry a `scope_type` (`GLOBAL`, `STORE`, `REGION`) and an optional `scope_id`. `ScopeResolver` translates a user's assignments into lists of accessible store and region UUIDs, which are injected as SQL filters in every list and search query.

### Voters

All authorization decisions go through 14 `AbstractVoter` classes against 34 `Permission` constants. Controllers call `denyAccessUnlessGranted(Permission::CONSTANT, $subject)` — no raw strings, no ad-hoc checks.

### Response Masking

`ResponseMaskingSubscriber` applies field-level masking on the way out. `FieldMaskingService` converts full SSNs to `***-**-NNNN` when the requesting user does not hold the `CLASSIFICATION_VIEW_UNMASKED` permission. The masking layer is transparent to controllers.

---

## Content Lifecycle & Versioning

Content items (`job_post`, `operational_notice`, `vendor_bulletin`, `policy_note`) progress through `DRAFT → PUBLISHED → ARCHIVED`.

Every create, update, publish, archive, and rollback action creates an immutable `ContentVersion` snapshot via `ContentVersionService.createVersion()`. The `VersionTimeline` front-end component renders the full history. `ContentVersionService.diff()` does field-by-field comparison for side-by-side diffs. Rollback is available for any version created within the past 30 days; rolling back writes a new version with `isRollback = true` and a `rolledBackToVersionId` reference.

---

## Search

Search is powered by MySQL FULLTEXT indexes on the `content_search_index` table. `SearchIndexService.runIncrementalIndex()` finds all `ContentVersion` rows newer than the last indexed version and upserts them via `REPLACE INTO`. This command runs every 5 minutes via the scheduler.

Relevance is computed as a weighted sum:

```
title × 5  +  tags_text × 4  +  author_name × 2  +  body_text × 1
```

Sort modes: `relevance` (default), `newest`, `most_viewed`, `highest_reply`.

Filters: `content_type`, `store_id`, `region_id`, `date_from`, `date_to`.

`OrphanCleanupService` deletes index entries whose `content_item_id` no longer exists in `content_items` after 14 days. `IndexCompactionCommand` runs `OPTIMIZE TABLE content_search_index` weekly, outside peak hours, to reclaim fragmented space without ever blocking reads (MySQL FULLTEXT compaction uses concurrent reads).

---

## Offline-First Mutation Queue

When the React client detects the backend is unreachable (`connectivityStore`), write operations are routed through the `MutationQueue` service and persisted in IndexedDB. Each mutation gets a client-generated UUID (`mutation_id`) and a payload.

When connectivity is restored, `MutationReplay` sends the queued batch to `POST /api/v1/mutations/replay`. The backend `MutationReplayService` processes each item idempotently — if a `mutation_id` already exists in `mutation_queue_log`, the previously recorded result is returned without re-applying. Successful replays are marked `APPLIED`; version conflicts are `CONFLICT`; permission/validation failures are `REJECTED`.

---

## Data Governance

### Encryption

`EncryptionService` uses AES-256-GCM with a two-tier key structure: a master key (32 bytes, derived via SHA-256 from the `APP_ENCRYPTION_MASTER_KEY` environment variable) wraps per-field data encryption keys (DEKs). DEKs are stored as binary blobs in the `encryption_keys` table alongside their `iv` and `auth_tag`. `KeyRotationService` generates a new DEK every 90 days; existing encrypted field values retain references to the key used at encryption time and are re-encrypted lazily or via a batch command.

### Sensitive Data Masking

SSNs and similarly classified fields are masked to `***-**-NNNN` in API responses unless the caller holds `CLASSIFICATION_VIEW_UNMASKED`. The `ResponseMaskingSubscriber` applies this transparently post-serialization.

### Retention

`RetentionService` enforces 365-day retention for candidate data. `RetentionScanCommand` runs daily, identifying eligible records and either deleting them or applying irreversible anonymization. Consent records are append-only with immutable timestamps; the `AuditImmutabilityListener` prevents updates or deletes of `AuditEvent` and `ConsentRecord` entities at the Doctrine lifecycle level.

### Audit Trail

Every sensitive action (create, update, delete, export, mask view, rollback) produces an `AuditEvent`. `HashChainService` computes a SHA-256 hash of each event's canonical JSON and chains it to the previous hash, producing a tamper-evident linked log. `VerifyAuditChainCommand` recomputes and verifies the chain on demand.

---

## Import & Deduplication

Imported job content goes through a fingerprint pipeline: `NormalizationService` lowercases and strips punctuation from title, company, location, and the first 200 characters of the body. `FingerprintService` hashes the normalized composite into a stable digest stored in `content_fingerprints`.

`DedupService` checks new items against existing fingerprints using trigram-based Jaccard similarity:

- **≥ 0.92** — `AUTO_MERGE`: the new item is automatically merged into the existing record
- **0.80–0.91** — `REVIEW_NEEDED`: queued for Recruiter/Analyst review via `DedupReviewController`
- **< 0.80** — `NO_MATCH`: item is imported as a new record

`MergeService` applies the merge by updating the canonical record and preserving the full version history of changed fields with timestamps.

---

## Scraping

`ScrapeOrchestratorService` runs all `ACTIVE` sources. For each URL:

1. `RateLimiterService` checks the per-source counter (max 30 req/min); excess triggers `SelfHealingService`
2. `JitterService` adds randomized wait before each request
3. `ProxyRotationService` assigns the next available local proxy
4. `HttpScrapeClient` fetches the URL
5. HTTP 403 → `BAN_DETECTED`; HTTP 429 → `RATE_LIMITED`; CAPTCHA markers in body → `CAPTCHA_DETECTED`
6. `SelfHealingService` applies a response: degrade to metadata-only, switch source, or pause 60 minutes
7. If HTML + selectors are configured, `ContentExtractorService` parses the DOM

---

## Exports

`ExportService.requestExport()` creates a job in `PENDING` state. An authorized role (`EXPORT_AUTHORIZE`) must call `POST /exports/{id}/authorize` before the file is generated. The file name embeds a watermark (`username · MM/DD/YYYY h:mm AM/PM`). `TamperDetectionService` computes a SHA-256 hash of the file at generation; `ExportController.download()` recomputes and compares before serving the file. Non-admin users only see exports they requested.

---

## Background Jobs Schedule

| Command | Interval | Purpose |
|---------|----------|---------|
| `app:search:index` | Every 5 min | Incremental FULLTEXT index update |
| `app:search:orphan-cleanup` | Daily | Remove orphaned index entries (14-day threshold) |
| `app:search:compact` | Weekly | `OPTIMIZE TABLE` on search index |
| `app:retention:scan` | Daily | Identify and process 365-day retention cases |
| `app:governance:key-rotation-check` | Daily | Rotate encryption keys older than 90 days |
| `app:exports:expire` | Hourly | Mark expired export jobs |
| `app:warehouse:load` | Nightly | ETL dimensions and facts into warehouse |
| `app:scrape:run` | Configurable | Execute approved source scraping |
| `app:audit:verify-chain` | On-demand | Verify SHA-256 audit hash chain |
| `app:backup:verify` | Weekly | Verify backup checksums |

---

## API Conventions

- All endpoints are under `/api/v1/`
- Success responses are wrapped: `{ "data": { ... } }` (`ApiEnvelope`)
- Paginated list responses: `{ "data": [...], "meta": { "page", "per_page", "total" } }` (`PaginatedEnvelope`)
- Errors: `{ "error": { "code": "SNAKE_CASE", "message": "...", "details": {} } }` (`ErrorEnvelope`)
- Mutable resources require `If-Match: "<version>"` header; version mismatch returns `409 Conflict`
- Authentication via `Authorization: Bearer <token>`
- All IDs are UUIDv7 in RFC 4122 format
