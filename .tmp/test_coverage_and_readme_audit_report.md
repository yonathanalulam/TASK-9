# Combined Audit Report (Strict Static Inspection)

## 1) Test Coverage Audit

### Backend Endpoint Inventory

Detected from Symfony route attributes in `backend/src/Controller/Api/V1/*.php`.

Total endpoints (unique METHOD + resolved PATH): **98**

### API Test Mapping Table

| Endpoint | Covered | Test Type | Test Files | Evidence (test/function) |
|---|---:|---|---|---|
| POST `/api/v1/auth/login` | yes | true no-mock HTTP | `backend/tests/Api/Auth/LoginApiTest.php` | `testLoginWithValidCredentialsReturns200WithTokenAndUserData` |
| POST `/api/v1/auth/logout` | yes | true no-mock HTTP | `backend/tests/Api/Auth/LoginApiTest.php` | `testLogoutRevokesSession` |
| GET `/api/v1/auth/me` | yes | true no-mock HTTP | `backend/tests/Api/Auth/LoginApiTest.php` | `testGetMeWithValidTokenReturnsUserData` |
| POST `/api/v1/auth/change-password` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/HealthAndAuthCoverageTest.php` | `testChangePasswordEndpointIsRoutable` |
| GET `/api/v1/health` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/HealthAndAuthCoverageTest.php` | `testHealthEndpointReturns200` |
| GET `/api/v1/search` | yes | true no-mock HTTP | `backend/tests/Api/Search/SearchApiTest.php` | `testSearchReturnsDataArrayAndPagination` |
| POST `/api/v1/content` | yes | true no-mock HTTP | `backend/tests/Api/Behavior/ContentLifecycleBehaviorTest.php` | `testGetContentByIdReturns200WithCompleteShape` (create via API in setup flow) |
| GET `/api/v1/content` | yes | true no-mock HTTP | `backend/tests/Api/Behavior/ContentLifecycleBehaviorTest.php` | `testListContentReturnsPaginatedEnvelope` |
| GET `/api/v1/content/{id}` | yes | true no-mock HTTP | `backend/tests/Api/Behavior/ContentLifecycleBehaviorTest.php` | `testGetContentByIdReturns200WithCompleteShape` |
| PUT `/api/v1/content/{id}` | yes | true no-mock HTTP | `backend/tests/Api/Behavior/ContentLifecycleBehaviorTest.php` | `testUpdateContentWithValidIfMatchSucceeds` |
| POST `/api/v1/content/{id}/publish` | yes | true no-mock HTTP | `backend/tests/Api/Behavior/ContentLifecycleBehaviorTest.php` | `testPublishContentTransitionsStatusToDraftOrPublished` |
| POST `/api/v1/content/{id}/archive` | yes | true no-mock HTTP | `backend/tests/Api/Behavior/ContentLifecycleBehaviorTest.php` | `testArchiveContentReturns200WithArchivedStatus` |
| GET `/api/v1/content/{contentId}/versions` | yes | true no-mock HTTP | `backend/tests/Api/Content/ContentVersionDiffRollbackTest.php` | `testCreateUpdateAndListVersions` |
| GET `/api/v1/content/{contentId}/versions/{versionId}` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/ContentLifecycleCoverageTest.php` | `testGetContentVersionByIdReturns200` |
| GET `/api/v1/content/{contentId}/versions/{v1Id}/diff/{v2Id}` | yes | true no-mock HTTP | `backend/tests/Api/Content/ContentVersionDiffRollbackTest.php` | `testDiffBetweenTwoVersionsReturnsChangedFields` |
| POST `/api/v1/content/{contentId}/rollback` | yes | true no-mock HTTP | `backend/tests/Api/Content/ContentVersionDiffRollbackTest.php` | `testRollbackWithValidReasonReturns200` |
| POST `/api/v1/regions` | yes | true no-mock HTTP | `backend/tests/Api/Region/RegionCrudApiTest.php` | `testCreateRegionSuccessfully` |
| GET `/api/v1/regions` | yes | true no-mock HTTP | `backend/tests/Api/Region/RegionCrudApiTest.php` | `testListRegionsReturnsPaginatedList` |
| GET `/api/v1/regions/{id}` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/RegionDetailCoverageTest.php` | `testShowRegionReturns200` |
| PUT `/api/v1/regions/{id}` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/RegionDetailCoverageTest.php` | `testUpdateRegionReturns200` |
| POST `/api/v1/regions/{id}/close` | yes | true no-mock HTTP | `backend/tests/Api/Region/RegionCrudApiTest.php` | `testCloseRegionWithChildReassignmentsWorks` |
| GET `/api/v1/regions/{id}/versions` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/RegionDetailCoverageTest.php` | `testRegionVersionsReturns200` |
| POST `/api/v1/classifications` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/ClassificationCoverageTest.php` | `testPostClassificationRouteExists` |
| GET `/api/v1/classifications` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/ClassificationCoverageTest.php` | `testGetClassificationsListReturns200` |
| PUT `/api/v1/classifications/{id}` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/ClassificationCoverageTest.php` | `testPutClassificationReturns404ForNonExistentId` |
| POST `/api/v1/classifications/encrypted-fields/store` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/ClassificationCoverageTest.php` | `testPostEncryptedFieldsStoreReturns422WithMissingFields` |
| POST `/api/v1/classifications/encrypted-fields/retrieve` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/ClassificationCoverageTest.php` | `testPostEncryptedFieldsRetrieveReturns422WithMissingFields` |
| POST `/api/v1/exports` | yes | true no-mock HTTP | `backend/tests/Api/Behavior/ExportBehaviorTest.php` | `testCreateExportReturns201WithJobShape` |
| POST `/api/v1/exports/{id}/authorize` | yes | true no-mock HTTP | `backend/tests/Api/Behavior/ExportBehaviorTest.php` | `testAuthorizeExportReturns200WithAuthorizedShape` |
| GET `/api/v1/exports/{id}` | yes | true no-mock HTTP | `backend/tests/Api/Behavior/ExportBehaviorTest.php` | `testGetExportByIdReturns200WithCorrectData` |
| GET `/api/v1/exports/{id}/download` | yes | true no-mock HTTP | `backend/tests/Api/Behavior/ExportBehaviorTest.php` | `testDownloadNonSucceededExportReturns422` |
| GET `/api/v1/exports` | yes | true no-mock HTTP | `backend/tests/Api/Behavior/ExportBehaviorTest.php` | `testListExportsReturnsPaginatedEnvelope` |
| POST `/api/v1/stores` | yes | true no-mock HTTP | `backend/tests/Api/Store/StoreCrudApiTest.php` | `testCreateStoreSuccessfullyReturns201` |
| GET `/api/v1/stores` | yes | true no-mock HTTP | `backend/tests/Api/Store/StoreCrudApiTest.php` | `testListStoresReturnsPaginatedList` |
| GET `/api/v1/stores/{id}` | yes | true no-mock HTTP | `backend/tests/Api/Store/StoreCrudApiTest.php` | `testShowStoreReturnsStoreDetail` |
| PUT `/api/v1/stores/{id}` | yes | true no-mock HTTP | `backend/tests/Api/Store/StoreCrudApiTest.php` | `testUpdateStoreWithIfMatchHeaderSucceeds` |
| GET `/api/v1/stores/{id}/versions` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/StoreVersionsCoverageTest.php` | `testStoreVersionsReturns200` |
| POST `/api/v1/users/{userId}/role-assignments` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/RoleAssignmentCoverageTest.php` | `testCreateRoleAssignmentReturns201` |
| GET `/api/v1/users/{userId}/role-assignments` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/RoleAssignmentCoverageTest.php` | `testListRoleAssignmentsReturns200` |
| DELETE `/api/v1/users/{userId}/role-assignments/{assignmentId}` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/RoleAssignmentCoverageTest.php` | `testDeleteRoleAssignmentReturns200` |
| POST `/api/v1/boundaries/upload` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/BoundaryCoverageTest.php` | `testPostBoundariesUploadReturns403` |
| GET `/api/v1/boundaries` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/BoundaryCoverageTest.php` | `testGetBoundariesListReturns403` |
| GET `/api/v1/boundaries/{id}` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/BoundaryCoverageTest.php` | `testGetBoundaryShowReturns403` |
| POST `/api/v1/boundaries/{id}/validate` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/BoundaryCoverageTest.php` | `testPostBoundaryValidateReturns403` |
| POST `/api/v1/boundaries/{id}/apply` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/BoundaryCoverageTest.php` | `testPostBoundaryApplyReturns403` |
| POST `/api/v1/stores/{storeId}/delivery-zones` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/DeliveryZoneCoverageTest.php` | `testCreateDeliveryZoneReturns201` |
| GET `/api/v1/stores/{storeId}/delivery-zones` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/DeliveryZoneCoverageTest.php` | `testListDeliveryZonesReturns200` |
| GET `/api/v1/delivery-zones/{id}` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/DeliveryZoneCoverageTest.php` | `testShowDeliveryZoneReturns200` |
| PUT `/api/v1/delivery-zones/{id}` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/DeliveryZoneCoverageTest.php` | `testUpdateDeliveryZoneReturns200` |
| POST `/api/v1/delivery-zones/{zoneId}/mappings` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/ZoneMappingCoverageTest.php` | `testCreateZoneMappingReturns201Or422` |
| GET `/api/v1/delivery-zones/{zoneId}/mappings` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/ZoneMappingCoverageTest.php` | `testListZoneMappingsReturns200` |
| POST `/api/v1/delivery-zones/{zoneId}/windows` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/DeliveryWindowCoverageTest.php` | `testCreateDeliveryWindowReturns201` |
| GET `/api/v1/delivery-zones/{zoneId}/windows` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/DeliveryWindowCoverageTest.php` | `testListDeliveryWindowsReturns200` |
| PUT `/api/v1/delivery-windows/{id}` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/DeliveryWindowCoverageTest.php` | `testUpdateDeliveryWindowReturns200` |
| DELETE `/api/v1/delivery-windows/{id}` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/DeliveryWindowCoverageTest.php` | `testDeleteDeliveryWindowReturns200` |
| POST `/api/v1/users` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/UserCoverageTest.php` | `testCreateUserReturns201` |
| GET `/api/v1/users` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/UserCoverageTest.php` | `testListUsersReturns200` |
| GET `/api/v1/users/{id}` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/UserCoverageTest.php` | `testShowUserReturns200` |
| PUT `/api/v1/users/{id}` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/UserCoverageTest.php` | `testUpdateUserReturns200Or428` |
| PATCH `/api/v1/users/{id}/deactivate` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/UserCoverageTest.php` | `testDeactivateUserReturns200` |
| POST `/api/v1/compliance-reports` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/ComplianceShowCoverageTest.php` | `testPostComplianceReportReturns201` |
| GET `/api/v1/compliance-reports` | yes | true no-mock HTTP | `backend/tests/Api/Compliance/ComplianceEnumValidationTest.php` | `testListResponseShape` |
| GET `/api/v1/compliance-reports/{id}` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/ComplianceShowCoverageTest.php` | `testGetComplianceReportShowReturns200` |
| GET `/api/v1/compliance-reports/{id}/download` | yes | true no-mock HTTP | `backend/tests/Api/Compliance/ComplianceEnumValidationTest.php` | `testDownloadRouteExists` |
| POST `/api/v1/mutations/replay` | yes | true no-mock HTTP | `backend/tests/Api/Behavior/MutationReplayBehaviorTest.php` | `testAdministratorStoreCreateMutationIsApplied` |
| GET `/api/v1/mutations` | yes | true no-mock HTTP | `backend/tests/Api/Authorization/AuthorizationMatrixTest.php` | `testMutationListAllowedForAdministrator` |
| POST `/api/v1/sources` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/ScrapingCoverageTest.php` | source setup + authorization checks in `EndpointAuthorizationTest::testAnalystCannotCreateSources` |
| GET `/api/v1/sources` | yes | true no-mock HTTP | `backend/tests/Api/Security/EndpointAuthorizationTest.php` | `testAnalystCannotListSources` |
| GET `/api/v1/sources/{id}` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/ScrapingCoverageTest.php` | `testGetSourceShowReturns200` |
| PUT `/api/v1/sources/{id}` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/ScrapingCoverageTest.php` | `testPutSourceUpdateReturns200` |
| POST `/api/v1/sources/{id}/pause` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/ScrapingCoverageTest.php` | `testPostSourcePauseReturns200` |
| POST `/api/v1/sources/{id}/resume` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/ScrapingCoverageTest.php` | `testPostSourceResumeReturns200` |
| POST `/api/v1/sources/{id}/disable` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/ScrapingCoverageTest.php` | `testPostSourceDisableReturns200` |
| GET `/api/v1/sources/{id}/health` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/ScrapingCoverageTest.php` | `testGetSourceHealthReturns200` |
| GET `/api/v1/sources/health/dashboard` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/ScrapingCoverageTest.php` | `testGetSourcesHealthDashboardReturns200` |
| GET `/api/v1/retention/cases` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/RetentionCoverageTest.php` | `testGetRetentionCasesReturns200` |
| POST `/api/v1/retention/cases/{id}/schedule` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/RetentionCoverageTest.php` | `testPostRetentionScheduleReturns404ForNonExistentCase` |
| GET `/api/v1/retention/stats` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/RetentionCoverageTest.php` | `testGetRetentionStatsReturns200` |
| POST `/api/v1/consent` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/ConsentCoverageTest.php` | `testPostConsentReturns201` |
| GET `/api/v1/consent/user/{userId}` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/ConsentCoverageTest.php` | `testGetConsentUserHistoryReturns200` |
| GET `/api/v1/dedup/review` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/DedupCoverageTest.php` | `testGetDedupReviewListReturns200` |
| POST `/api/v1/dedup/review/{id}/merge` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/DedupCoverageTest.php` | `testPostDedupMergeReturns404ForNonExistentItem` |
| POST `/api/v1/dedup/review/{id}/reject` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/DedupCoverageTest.php` | `testPostDedupRejectReturns404ForNonExistentItem` |
| POST `/api/v1/dedup/unmerge/{id}` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/DedupCoverageTest.php` | `testPostDedupUnmergeReturns404ForNonExistentEvent` |
| POST `/api/v1/imports` | yes | true no-mock HTTP | `backend/tests/Api/Authorization/AuthorizationMatrixTest.php` | `testImportAllowedForRecruiter` |
| GET `/api/v1/imports` | yes | true no-mock HTTP | `backend/tests/Api/Behavior/ImportBehaviorTest.php` | `testListImportBatchesReturns200WithPaginatedEnvelope` |
| GET `/api/v1/imports/{id}` | yes | true no-mock HTTP | `backend/tests/Api/Behavior/ImportBehaviorTest.php` | `testGetImportBatchByIdReturns200WithCompleteShape` |
| GET `/api/v1/imports/{id}/items` | yes | true no-mock HTTP | `backend/tests/Api/Behavior/ImportBehaviorTest.php` | `testGetImportItemsReturns200WithPaginatedEnvelope` |
| GET `/api/v1/warehouse/loads` | yes | true no-mock HTTP | `backend/tests/Api/Security/EndpointAuthorizationTest.php` | `testAnalystCanAccessWarehouseLoads` |
| GET `/api/v1/warehouse/loads/{id}` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/WarehouseDetailCoverageTest.php` | `testGetWarehouseLoadShowReturns404ForNonExistentId` |
| POST `/api/v1/warehouse/loads/trigger` | yes | true no-mock HTTP | `backend/tests/Api/Security/EndpointAuthorizationTest.php` | `testAnalystCannotTriggerWarehouseLoad` |
| GET `/api/v1/scrape-runs` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/ScrapingCoverageTest.php` | `testGetScrapeRunsListReturns200` |
| GET `/api/v1/scrape-runs/{id}` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/ScrapingCoverageTest.php` | `testGetScrapeRunShowReturns404ForNonExistentId` |
| POST `/api/v1/scrape-runs/trigger/{sourceId}` | yes | true no-mock HTTP | `backend/tests/Api/Security/EndpointAuthorizationTest.php` | `testAnalystCannotTriggerScrapeRun` |
| GET `/api/v1/analytics/sales` | yes | true no-mock HTTP | `backend/tests/Api/Security/EndpointAuthorizationTest.php` | `testAnalystCanViewAnalyticsSales` |
| GET `/api/v1/analytics/sales/trends` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/AnalyticsCoverageTest.php` | `testGetAnalyticsSalesTrendsReturns200` |
| GET `/api/v1/analytics/content-volume` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/AnalyticsCoverageTest.php` | `testGetAnalyticsContentVolumeReturns200` |
| GET `/api/v1/analytics/kpi-summary` | yes | true no-mock HTTP | `backend/tests/Api/Security/EndpointAuthorizationTest.php` | `testAnalystCanViewKpiSummary` |

### API Test Classification

1. **True no-mock HTTP**
   - Backend API suite uses Symfony `WebTestCase` + `static::createClient()` with real kernel/request handling (e.g., `backend/tests/Api/Behavior/ExportBehaviorTest.php`, `backend/tests/Api/Store/StoreCrudApiTest.php`, `backend/tests/Api/Auth/LoginApiTest.php`).
   - Playwright E2E suite exercises real FE↔BE boundary (`frontend/e2e/*.spec.ts`) with real requests to `/api/v1/*`.

2. **HTTP with mocking**
   - **None found** in backend API tests.

3. **Non-HTTP tests (unit/integration)**
   - `backend/tests/Unit/**` (uses mocks heavily via PHPUnit `createMock`).
   - `backend/tests/Integration/**` (`KernelTestCase`, service-level integration without API transport).

### Mock Detection

- **API tests:** no transport/controller/service mocking detected under `backend/tests/Api/**` (no `createMock`, no mocking DSL matches).
- **Unit/component tests with mocks (expected at that level):**
  - `frontend/src/pages/__tests__/LoginPage.test.tsx` uses `vi.mock('@/hooks/useAuth', ...)`
  - `frontend/src/pages/stores/__tests__/StoreListPage.test.tsx` uses `vi.mock('@/api/stores', ...)`
  - `frontend/src/pages/exports/__tests__/ComplianceReportsPage.test.tsx` uses `vi.mock('@/api/complianceReports', ...)`
  - Backend unit examples with mocked dependencies: `backend/tests/Unit/Service/MutationQueue/ReplayZoneCreateScopeTest.php`, `backend/tests/Unit/Security/Voter/MutationQueueVoterTest.php`.

### Coverage Summary

- Total endpoints: **98**
- Endpoints with HTTP tests: **98**
- Endpoints with true no-mock HTTP coverage: **98**
- HTTP coverage: **100%** (endpoint-touch metric)
- True API coverage: **100%** (no-mock HTTP endpoint-touch metric)

### Unit Test Summary

- Unit test files present across controllers/services/security: `backend/tests/Unit/**`.
- Integration tests present (narrow scope): `backend/tests/Integration/Auth/AuthenticationFlowTest.php`, `backend/tests/Integration/Auth/RbacServiceTest.php`.
- Frontend unit/component tests present: `frontend/src/**/__tests__/*.test.*`.
- Important tested areas: auth lockout/policy, export service rules, mutation queue rules, some voter logic, search/content scope helpers.
- Important gaps:
  - No dedicated repository-level tests under `backend/tests/**/Repository/**`.
  - Integration breadth is concentrated in auth; non-auth service+DB integration is comparatively thin.
  - Multiple frontend tests are static contract/object-shape checks rather than executing real module behavior (`frontend/src/api/__tests__/*.test.ts`, `frontend/src/services/mutationQueue/__tests__/replay-contract.test.ts`).

### API Observability Check

- Strong in many behavior tests: clear method/path, concrete request payload, and response assertions (e.g., `ExportBehaviorTest`, `StoreCrudApiTest`, `SearchApiTest`, `ContentVersionDiffRollbackTest`).
- Weak in several coverage tests: route/authorization existence checks with broad status acceptance (e.g., `testPostClassificationRouteExists`, boundary coverage tests asserting 403 route accessibility, and several `assertNotSame(404)` style checks).

### run_tests.sh Check (Static)

- `run_tests.sh` exists at repo root and is primarily Docker-driven (`docker compose exec/run` for PHPUnit, Vitest, Playwright).
- Main flow does **not** rely on local Python/Node package manager state for test execution.
- Host dependencies still required: Docker + shell tooling (`curl`, `grep`, `tail`).

### Tests Check

- Relevant categories for this repo shape (fullstack) are present: **API, unit, integration, frontend component/unit, E2E**.
- Category presence is strong; depth is uneven. Several endpoints are only checked via unauthorized/not-found/routability assertions and do not validate rich success-path business outputs.
- E2E exists and is real-stack; however, portions use `page.request` shortcuts for backend calls rather than always driving full UI behavior.
- Overall: broad suite with genuine no-mock HTTP reach, but confidence is reduced by shallow assertions in part of the API coverage layer and contract-style frontend tests.

### Test Coverage Score (0–100)

**91 / 100**

### Score Rationale

- High breadth: endpoint-touch coverage is complete and API tests are real no-mock HTTP.
- Score reduced for sufficiency depth: many checks are permissive (403/404/routable) rather than asserting full business outcomes, and frontend unit tests include a significant amount of non-executing contract-shape validation.

### Key Gaps

- Several API coverage tests verify route availability/authorization only, not strong response semantics.
- Some privileged success paths are under-validated (e.g., trigger-style endpoints often only tested for denied paths).
- Frontend test mix is diluted by static contract tests that do not exercise implementation logic.
- Repository-focused test coverage is absent.

### Confidence & Assumptions

- Confidence: **medium-high**.
- Assumptions:
  - Route inventory is based strictly on static Symfony attributes in `backend/src/Controller/Api/V1`.
  - Coverage is endpoint-touch + static evidence only; runtime behavior was not executed.

---

## 2) README Audit

README inspected: `repo/README.md`

Project type declaration at top: **not explicit**. Inferred type from repository shape: **fullstack**.

### Hard Gate Failures

1. **Verification method is not sufficiently concrete for acceptance use**
   - README provides test/debug commands and static checks, but does not provide a clear end-user verification flow (e.g., explicit UI journey and/or concrete API curl examples with expected outputs).
   - Evidence: `README.md:317` onward (“Static Verification Guidance”) is command-heavy and reviewer-oriented.

### High Priority Issues

- README does not explicitly declare project type near the top as required by this audit mode (inferred fullstack instead).
- Verification section is oriented to engineering checks, not a practical product-acceptance walkthrough.

### Medium Priority Issues

- Some guidance includes developer-centric grep/router/schema commands that are useful but not concise for operator onboarding.
- Test strategy is extensive but could better separate mandatory acceptance checks vs optional diagnostic checks.

### Low Priority Issues

- Minor verbosity: the document is comprehensive but long; quick “happy path” verification could be surfaced earlier.

### README Verdict

**PARTIAL PASS**

Rationale:
- Passes key fullstack startup and access basics (`docker-compose up`, service URLs, credentials/roles present).
- Fails strict verification clarity gate for explicit acceptance validation flow.

---

## Final Verdicts

- **Test Coverage Audit Verdict:** Broad and real HTTP coverage present, but depth is uneven; sufficient but not high-confidence for all business-critical paths.
- **README Audit Verdict:** **PARTIAL PASS** under strict mode.
