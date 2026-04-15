# Meridian API Specification

Base path: `/api/v1`  
Authentication: `Authorization: Bearer <token>` on all authenticated endpoints  
Content-Type: `application/json`

## Response Envelope

**Success (single object)**
```json
{ "data": { ... } }
```

**Success (paginated list)**
```json
{
  "data": [ ... ],
  "meta": { "page": 1, "per_page": 25, "total": 142 }
}
```

**Error**
```json
{ "error": { "code": "SNAKE_CASE_CODE", "message": "Human-readable message.", "details": {} } }
```

**Optimistic concurrency** — all `PUT` endpoints require `If-Match: "<version>"`. Version mismatch → `409 Conflict`.

---

## Authentication

### POST /auth/login
Authenticate and obtain a session token.

**Request**
```json
{ "username": "string", "password": "string" }
```

**Response 200**
```json
{
  "data": {
    "token": "string",
    "user": {
      "id": "uuid",
      "username": "string",
      "display_name": "string",
      "status": "string",
      "roles": [
        { "role": "ADMINISTRATOR", "scope_type": "GLOBAL", "scope_id": null }
      ]
    }
  }
}
```

**Errors**: `400 INVALID_JSON`, `401 AUTHENTICATION_FAILED`, `422 VALIDATION_ERROR`, `429 RATE_LIMITED`

---

### POST /auth/logout
Revoke the current session.

**Headers**: `Authorization: Bearer <token>`  
**Response 200**: `{ "data": { "message": "Logged out successfully." } }`  
**Errors**: `400 INVALID_TOKEN`, `401 UNAUTHENTICATED`

---

### GET /auth/me
Return the authenticated user's profile and active role assignments.

**Response 200**
```json
{
  "data": {
    "id": "uuid",
    "username": "string",
    "display_name": "string",
    "status": "string",
    "last_login_at": "ISO8601",
    "created_at": "ISO8601",
    "updated_at": "ISO8601",
    "roles": [
      {
        "id": "uuid",
        "role": "string",
        "role_display_name": "string",
        "scope_type": "GLOBAL|STORE|REGION",
        "scope_id": "uuid|null",
        "effective_from": "YYYY-MM-DD",
        "effective_until": "YYYY-MM-DD|null"
      }
    ]
  }
}
```

---

### POST /auth/change-password
Change the authenticated user's password.

**Request**
```json
{ "current_password": "string", "new_password": "string" }
```

**Response 200**: `{ "data": { "message": "Password changed successfully." } }`  
**Errors**: `400 INVALID_PASSWORD`, `422 PASSWORD_POLICY_VIOLATION`, `422 VALIDATION_ERROR`

---

## Stores

Permission required: `STORE_VIEW` (list/show), `STORE_CREATE` (create), `STORE_EDIT` (update)

### POST /stores
Create a store (ADMINISTRATOR only).

**Request**
```json
{
  "code": "string",
  "name": "string",
  "store_type": "RETAIL|DARKSTORE",
  "status": "ACTIVE|INACTIVE|CLOSED",
  "region_id": "uuid",
  "timezone": "string",
  "address_line_1": "string",
  "address_line_2": "string|null",
  "city": "string",
  "postal_code": "string",
  "latitude": "float|null",
  "longitude": "float|null"
}
```

**Response 201** — store object (see schema below)

---

### GET /stores
List stores with optional filters.

**Query params**: `page`, `per_page` (max 100), `region_id`, `type`, `status`  
**Response 200** — paginated list of store objects

**Store object**
```json
{
  "id": "uuid",
  "code": "string",
  "name": "string",
  "store_type": "RETAIL|DARKSTORE",
  "status": "ACTIVE|INACTIVE|CLOSED",
  "region_id": "uuid",
  "timezone": "string",
  "address_line_1": "string",
  "address_line_2": "string|null",
  "city": "string",
  "postal_code": "string",
  "latitude": "float|null",
  "longitude": "float|null",
  "is_active": "bool",
  "created_at": "ISO8601",
  "updated_at": "ISO8601",
  "version": "int"
}
```

---

### GET /stores/{id}
Retrieve a single store.

**Response 200** — store object  
**Errors**: `404 NOT_FOUND`, `403 FORBIDDEN`

---

### PUT /stores/{id}
Update a store. Requires `If-Match: "<version>"`.

**Response 200** — updated store object  
**Errors**: `404 NOT_FOUND`, `409 CONFLICT`, `422 VALIDATION_ERROR`, `428 MISSING_IF_MATCH`

---

### GET /stores/{id}/versions
Retrieve the version history of a store.

**Response 200**
```json
{
  "data": [
    {
      "id": "uuid",
      "version_number": "int",
      "change_type": "string",
      "snapshot": "object",
      "changed_by": "uuid",
      "changed_at": "ISO8601",
      "change_reason": "string|null"
    }
  ]
}
```

---

## Delivery Zones

Permission required: `ZONE_VIEW`, `ZONE_CREATE`, `ZONE_EDIT`

### POST /stores/{storeId}/delivery-zones
Create a delivery zone for a store (STORE_MANAGER, DISPATCHER, ADMINISTRATOR).

**Request**
```json
{
  "name": "string",
  "min_order_threshold": "decimal",
  "delivery_fee": "decimal",
  "status": "string"
}
```

**Response 201** — zone object

---

### GET /stores/{storeId}/delivery-zones
List delivery zones for a store.

**Query params**: `page`, `per_page`  
**Response 200** — paginated list of zone objects

---

### GET /delivery-zones/{id}
Retrieve a delivery zone with windows and rules.

**Response 200**
```json
{
  "data": {
    "id": "uuid",
    "store_id": "uuid",
    "name": "string",
    "status": "string",
    "min_order_threshold": "decimal",
    "delivery_fee": "decimal",
    "is_active": "bool",
    "created_at": "ISO8601",
    "updated_at": "ISO8601",
    "version": "int",
    "windows": [
      {
        "id": "uuid",
        "day_of_week": "string",
        "start_time": "HH:mm",
        "end_time": "HH:mm",
        "is_active": "bool"
      }
    ],
    "product_rules": [
      { "id": "uuid", "rule_type": "string", "rule_config": "object", "is_active": "bool" }
    ],
    "order_rules": [
      { "id": "uuid", "rule_type": "string", "rule_config": "object", "is_active": "bool" }
    ]
  }
}
```

---

### PUT /delivery-zones/{id}
Update a delivery zone. Requires `If-Match: "<version>"`.

**Response 200** — updated zone object

---

### POST /delivery-zones/{zoneId}/mappings
Attach a community grid or administrative area to a zone.

**Request**
```json
{
  "mapping_type": "administrative_area|community_grid",
  "mapped_entity_id": "uuid",
  "precedence": "int"
}
```

**Response 201**
```json
{
  "data": {
    "id": "uuid",
    "zone_id": "uuid",
    "mapping_type": "string",
    "mapped_entity_id": "uuid",
    "precedence": "int"
  }
}
```

**Errors**: `409 CONFLICT` (duplicate mapping), `422 VALIDATION_ERROR`

---

### GET /delivery-zones/{zoneId}/mappings
List all mappings for a delivery zone, ordered by `precedence` ASC.

**Response 200** — array of mapping objects

---

## Content

Permission required: `CONTENT_VIEW`, `CONTENT_CREATE`, `CONTENT_EDIT`, `CONTENT_PUBLISH`, `CONTENT_ARCHIVE`

### POST /content
Create a content item.

**Request**
```json
{
  "content_type": "JOB_POST|OPERATIONAL_NOTICE|VENDOR_BULLETIN|POLICY_NOTE",
  "title": "string",
  "body": "string",
  "author_name": "string",
  "store_id": "uuid|null",
  "region_id": "uuid|null",
  "tags": ["string"]
}
```

**Response 201** — content item object

---

### GET /content
List content items.

**Query params**: `page`, `per_page`, `content_type`, `store_id`, `region_id`, `status`  
**Response 200** — paginated list of content item objects

**Content item object**
```json
{
  "id": "uuid",
  "content_type": "string",
  "title": "string",
  "body": "string",
  "author_name": "string",
  "source_type": "string",
  "source_reference": "string|null",
  "published_at": "ISO8601|null",
  "store_id": "uuid|null",
  "region_id": "uuid|null",
  "status": "DRAFT|PUBLISHED|ARCHIVED",
  "view_count": "int",
  "reply_count": "int",
  "version": "int",
  "tags": ["string"],
  "created_at": "ISO8601",
  "updated_at": "ISO8601"
}
```

---

### GET /content/{id}
Retrieve a single content item.

**Response 200** — content item object  
**Errors**: `404 NOT_FOUND`, `403 FORBIDDEN`

---

### PUT /content/{id}
Update a content item. Requires `If-Match: "<version>"`.

**Response 200** — updated content item object

---

### POST /content/{id}/publish
Transition a content item from DRAFT to PUBLISHED.

**Response 200** — updated content item object  
**Errors**: `422 VALIDATION_ERROR` (e.g., already published)

---

### POST /content/{id}/archive
Archive a published content item.

**Response 200** — updated content item object

---

## Content Versions

### GET /content/{id}/versions
List all versions of a content item, newest first.

**Response 200**
```json
{
  "data": [
    {
      "id": "uuid",
      "version_number": "int",
      "title": "string",
      "body": "string",
      "tags": ["string"],
      "content_type": "string",
      "status_at_creation": "string",
      "change_reason": "string|null",
      "is_rollback": "bool",
      "rolled_back_to_version_id": "uuid|null",
      "created_by": "uuid",
      "created_at": "ISO8601"
    }
  ]
}
```

---

### POST /content/{id}/versions/{versionId}/rollback
Roll back a content item to a prior version (within 30 days).

**Response 200** — updated content item object  
**Errors**: `404 NOT_FOUND`, `422 VALIDATION_ERROR` (outside 30-day window)

---

### GET /content/{id}/versions/{v1Id}/diff/{v2Id}
Compare two versions field by field.

**Response 200**
```json
{
  "data": [
    { "field": "title", "before": "Old title", "after": "New title" }
  ]
}
```

---

## Search

Permission required: `SEARCH_EXECUTE`

### GET /search
Full-text search across content.

**Query params**

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `q` | string | required | Search query (MySQL Boolean Mode) |
| `page` | int | 1 | Page number |
| `per_page` | int | 25 (max 100) | Results per page |
| `sort` | string | `relevance` | `relevance`, `newest`, `most_viewed`, `highest_reply` |
| `type` | string | — | Filter by content type |
| `store` | uuid | — | Filter by store |
| `region` | uuid | — | Filter by region |
| `date_from` | date | — | Published on or after |
| `date_to` | date | — | Published on or before |

**Response 200**
```json
{
  "data": [
    {
      "id": "uuid",
      "content_type": "string",
      "title": "string",
      "author_name": "string",
      "published_at": "ISO8601|null",
      "tags": ["string"],
      "view_count": "int",
      "reply_count": "int",
      "snippet": "string",
      "highlight_title": "string (HTML with <mark> tags)",
      "relevance_score": "float"
    }
  ],
  "meta": { "page": 1, "per_page": 25, "total": 42 }
}
```

---

## Regions

Permission required: `REGION_VIEW`, `REGION_CREATE`, `REGION_EDIT` (ADMINISTRATOR only for create/edit)

### POST /regions
**Request**: `{ "code": "2-5 uppercase chars", "name": "string", "parent_id": "uuid|null" }`  
**Response 201** — region object

### GET /regions
**Query params**: `page`, `per_page`  
**Response 200** — paginated list of region objects

### GET /regions/{id}
**Response 200** — region object  
`{ "id": "uuid", "code": "string", "name": "string", "parent_id": "uuid|null", ... }`

### PUT /regions/{id}
Requires `If-Match: "<version>"`. ADMINISTRATOR only.

---

## Exports

Permission required: `EXPORT_REQUEST`, `EXPORT_AUTHORIZE`, `EXPORT_VIEW`, `EXPORT_DOWNLOAD`

### POST /exports
Request a new export job.

**Request**
```json
{ "dataset": "string", "format": "CSV", "filters": {} }
```

**Response 201** — export job object

---

### GET /exports
List export jobs. Non-admin users see only their own exports.

**Query params**: `page`, `per_page`  
**Response 200** — paginated list of export job objects

**Export job object**
```json
{
  "id": "uuid",
  "dataset": "string",
  "format": "string",
  "status": "PENDING|AUTHORIZED|PROCESSING|SUCCEEDED|FAILED|EXPIRED",
  "requested_by": "uuid",
  "authorized_by": "uuid|null",
  "filters": "object",
  "file_name": "string|null",
  "watermark_text": "string|null",
  "tamper_hash_sha256": "string|null",
  "requested_at": "ISO8601",
  "authorized_at": "ISO8601|null",
  "completed_at": "ISO8601|null",
  "expires_at": "ISO8601|null"
}
```

---

### GET /exports/{id}
Retrieve a single export job.

---

### POST /exports/{id}/authorize
Authorize an export job (roles with `EXPORT_AUTHORIZE`).

**Response 200** — updated export job object

---

### GET /exports/{id}/download
Download the export file. Verifies SHA-256 tamper hash before serving.

**Response 200** — binary file (`Content-Disposition: attachment`)  
**Errors**: `404 FILE_NOT_FOUND`, `422 TAMPER_DETECTED` (hash mismatch), `422 VALIDATION_ERROR` (not SUCCEEDED)

---

## Compliance Reports

Permission required: `COMPLIANCE_REPORT_GENERATE`, `COMPLIANCE_REPORT_VIEW`

### POST /compliance-reports
Generate a tamper-evident compliance report covering access, export, and deletion events.

### GET /compliance-reports
List compliance reports.

### GET /compliance-reports/{id}
Retrieve a single report.

---

## Users & Role Assignments

Permission required: `USER_VIEW`, `USER_CREATE`, `USER_EDIT`, `ROLE_ASSIGN`, `ROLE_REVOKE` (ADMINISTRATOR only)

### POST /users
Create a user.

### GET /users
List users.

### GET /users/{id}
Retrieve a user profile.

### PUT /users/{id}
Update a user.

### POST /users/{id}/role-assignments
Assign a role to a user.

**Request**
```json
{
  "role": "STORE_MANAGER|DISPATCHER|ANALYST|RECRUITER|COMPLIANCE_OFFICER",
  "scope_type": "GLOBAL|STORE|REGION",
  "scope_id": "uuid|null",
  "effective_from": "YYYY-MM-DD",
  "effective_until": "YYYY-MM-DD|null"
}
```

### DELETE /users/{id}/role-assignments/{assignmentId}
Revoke a role assignment.

---

## Boundary Imports

Permission required: `IMPORT_CREATE`, `IMPORT_VIEW`

### POST /boundary-imports
Upload boundary data (CSV/GeoJSON) to create `AdministrativeArea` or `CommunityGrid` entities.

### GET /boundary-imports
List boundary import jobs.

### GET /boundary-imports/{id}
Retrieve a single import job status.

---

## Import (Job Content)

Permission required: `IMPORT_CREATE`, `IMPORT_VIEW`

### POST /imports
Create an import batch from an uploaded file or source run.

### GET /imports
List import batches.

### GET /imports/{id}
Retrieve a batch and its items.

---

## Deduplication Review

Permission required: `DEDUP_VIEW`, `DEDUP_MERGE`, `DEDUP_UNMERGE`

### GET /dedup/review-queue
List import items queued for manual dedup review (similarity 0.80–0.91).

### POST /dedup/{itemId}/merge
Merge an import item into an existing canonical record.

### POST /dedup/{itemId}/unmerge
Reject the merge suggestion and keep the item as a separate record.

---

## Data Governance

Permission required: `CLASSIFICATION_VIEW`, `CLASSIFICATION_MANAGE`, `RETENTION_VIEW`, `RETENTION_MANAGE`

### GET /classifications
List data classification rules.

### POST /classifications
Create a classification rule.

### GET /retention-cases
List retention cases approaching or past the 365-day threshold.

### POST /retention-cases/{id}/execute
Execute deletion or anonymization for a retention case.

### GET /consent-records
List consent records.

### POST /consent-records
Create a consent record (immutable; timestamp sealed at creation).

---

## Scraping

Permission required: `SCRAPING_SOURCE_VIEW`, `SCRAPING_SOURCE_MANAGE`, `SCRAPING_RUN_TRIGGER`

### GET /scraping/sources
List source definitions.

### POST /scraping/sources
Create a source definition.

### GET /scraping/sources/{id}
Retrieve a source definition with health status.

### PUT /scraping/sources/{id}
Update a source definition.

### GET /scraping/sources/{id}/health
List health events for a source.

### GET /scraping/runs
List scrape runs.

### POST /scraping/runs
Trigger a scrape run for one or all active sources.

### GET /scraping/runs/{id}
Retrieve a scrape run with item-level detail.

---

## Mutation Queue (Offline Replay)

Permission required: `MUTATION_QUEUE_REPLAY`

### POST /mutations/replay
Replay a batch of offline mutations. Idempotent on `mutation_id`.

**Request**
```json
{
  "mutations": [
    {
      "mutation_id": "uuid",
      "client_id": "string",
      "entity_type": "store|region|delivery_zone",
      "entity_id": "uuid|null",
      "operation": "CREATE|UPDATE",
      "payload": {}
    }
  ]
}
```

**Response 200**
```json
{
  "data": [
    { "mutation_id": "uuid", "status": "APPLIED|CONFLICT|REJECTED", "detail": "string|null" }
  ]
}
```

---

## Analytics

Permission required: `ANALYTICS_VIEW`

### GET /analytics/dashboard
Summary KPIs.

### GET /analytics/content
Content engagement metrics.

### GET /analytics/compliance
Compliance event metrics.

### GET /analytics/sales
Sales summary (warehouse aggregation).

### GET /analytics/sales/by-product
Sales breakdown by product dimension.

---

## Warehouse

Permission required: `WAREHOUSE_VIEW`, `WAREHOUSE_LOAD_TRIGGER`

### GET /warehouse/loads
List ETL load run history.

### POST /warehouse/loads
Trigger a manual warehouse load run.

### GET /warehouse/loads/{id}
Retrieve a load run's status and item counts.

---

## Health Check

### GET /health
No authentication required. Returns API and database connectivity status.

**Response 200**
```json
{ "data": { "status": "ok", "database": "ok" } }
```
