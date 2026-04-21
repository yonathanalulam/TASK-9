# Delivery Acceptance and Project Architecture Audit (Static-Only)

## 1. Verdict
- **Overall conclusion: Partial Pass**

## 2. Scope and Static Verification Boundary
- **Reviewed:** core docs/config, backend authz/voters/controllers/services for store/region/zone/content/search/mutation/export/governance, frontend search/offline modules, and selected backend/frontend tests.
- **Not executed (by rule):** project runtime, Docker, tests, workers, browser interactions, external services.
- **Not fully exhaustive:** entire repository line-by-line.
- **Manual verification required:** runtime scheduling cadence, end-to-end UX, production deployment hardening, and performance/locking behaviors.

## 3. Repository / Requirement Mapping Summary
- Prompt requires: offline-capable operations + scoped RBAC + delivery coverage mapping + content search (title/body/author/tags, filters incl. store/region/date/type, required sort modes) + versioning/rollback + compliance/governance.
- Current code shows significant progress and closes multiple prior defects (search store/region UI params, content region scope check, region list scope filtering, mapping UUID validation/format).
- Remaining material risks are concentrated in mutation replay authorization parity and scoped-content listing semantics.

## 4. Section-by-section Review

### 1. Hard Gates

#### 1.1 Documentation and static verifiability
- **Conclusion: Partial Pass**
- **Rationale:** setup/test docs and structure are present; runtime claims remain unverifiable statically.
- **Evidence:** `README.md:54`, `README.md:99`, `RUNBOOK.md:48`, `backend/phpunit.xml.dist:21`, `frontend/vitest.config.ts:12`.

#### 1.2 Material deviation from prompt
- **Conclusion: Partial Pass**
- **Rationale:** key prompt flows are now represented in code (including search store/region filters and zone mapping endpoint fixes), but replay permission parity still deviates from expected role boundaries.
- **Evidence:** fixed mapping/filter paths at `frontend/src/components/search/SearchFilters.tsx:93`, `frontend/src/hooks/useSearch.ts:69`, `backend/src/Controller/Api/V1/DeliveryZoneController.php:250`; replay gap at `backend/src/Service/MutationQueue/MutationReplayService.php:181`, `backend/src/Security/Voter/StoreVoter.php:84`.

### 2. Delivery Completeness

#### 2.1 Core requirements coverage
- **Conclusion: Partial Pass**
- **Rationale:** broad coverage exists and several previously reported issues are fixed, but replay authorization semantics can still violate role-based constraints.
- **Evidence:** replay endpoint+service `backend/src/Controller/Api/V1/MutationQueueController.php:29`, `backend/src/Service/MutationQueue/MutationReplayService.php:164`; expected edit/create role constraints in `backend/src/Security/Voter/StoreVoter.php:84`, `backend/src/Security/Voter/DeliveryZoneVoter.php:114`.

#### 2.2 End-to-end 0->1 deliverable
- **Conclusion: Partial Pass**
- **Rationale:** full-stack product structure is present with substantial functionality, but unresolved security-critical logic prevents acceptance.
- **Evidence:** `README.md:26`, `README.md:35`, `README.md:44`, replay risk evidence above.

### 3. Engineering and Architecture Quality

#### 3.1 Structure and module decomposition
- **Conclusion: Pass**
- **Rationale:** clear controller/service/voter/entity separation; no monolithic implementation pattern.
- **Evidence:** `README.md:35`, `README.md:37`, `README.md:39`, `README.md:43`.

#### 3.2 Maintainability/extensibility
- **Conclusion: Partial Pass**
- **Rationale:** centralized permissions and scope resolver are good, but replay service duplicates authorization logic incompletely relative to voters/controllers.
- **Evidence:** centralized constants `backend/src/Security/Permission.php:14`; duplicated/incomplete checks `backend/src/Service/MutationQueue/MutationReplayService.php:164`, `backend/src/Service/MutationQueue/MutationReplayService.php:243`.

### 4. Engineering Details and Professionalism

#### 4.1 Error handling, logging, validation, API design
- **Conclusion: Partial Pass**
- **Rationale:** robust envelopes and validation patterns are present, including new UUID validation on mapping create; critical replay auth parity gap remains.
- **Evidence:** `backend/src/Dto/Response/ApiEnvelope.php:15`, `backend/src/Controller/Api/V1/DeliveryZoneController.php:250`, `backend/src/EventListener/ApiExceptionListener.php:45`, replay gap at `backend/src/Service/MutationQueue/MutationReplayService.php:181`.

#### 4.2 Real product/service shape vs demo
- **Conclusion: Partial Pass**
- **Rationale:** codebase is product-like; however, several new tests remain source/reflection checks rather than behavioral API authorization coverage.
- **Evidence:** source-check style tests `backend/tests/Unit/Service/MutationQueue/ReplayPermissionTest.php:49`, `backend/tests/Unit/Controller/ContentRegionScopeTest.php:17`.

### 5. Prompt Understanding and Requirement Fit

#### 5.1 Business goal and constraint fit
- **Conclusion: Partial Pass**
- **Rationale:** prompt-fit improved (search filter UI, zone mapping contracts), but role-based enforcement consistency is still weaker than required for operational integrity.
- **Evidence:** improved search filter wiring `frontend/src/hooks/useSearch.ts:69`; unresolved replay permission mismatch `backend/src/Service/MutationQueue/MutationReplayService.php:181`, `backend/src/Security/Voter/StoreVoter.php:84`.

### 6. Aesthetics (frontend)

#### 6.1 Visual and interaction quality
- **Conclusion: Partial Pass**
- **Rationale:** usable structure and interaction feedback present; visual language remains functional but generic.
- **Evidence:** `frontend/src/pages/search/SearchPage.tsx:49`, `frontend/src/components/search/SearchFilters.tsx:67`, `frontend/src/global.css:120`.

## 5. Issues / Suggestions (Severity-Rated)

### High

1) **Replay path still permits role escalation on store/zone mutations**
- **Severity:** High
- **Conclusion:** Replay endpoint allows broad roles, while replay service applies mostly scope checks for store/zone update/create without enforcing same role constraints as normal endpoints.
- **Evidence:**
  - Replay access includes broad roles: `backend/src/Security/Voter/MutationQueueVoter.php:57`
  - Store update in replay has scope-only check: `backend/src/Service/MutationQueue/MutationReplayService.php:193`
  - Zone create/update in replay has scope-only checks: `backend/src/Service/MutationQueue/MutationReplayService.php:261`, `backend/src/Service/MutationQueue/MutationReplayService.php:282`
  - Normal role rules are stricter: store edit `backend/src/Security/Voter/StoreVoter.php:84`; zone edit/create `backend/src/Security/Voter/DeliveryZoneVoter.php:102`, `backend/src/Security/Voter/DeliveryZoneVoter.php:114`
- **Impact:** roles like recruiter/analyst/compliance (allowed to replay) may mutate store/zone entities outside intended role policy.
- **Minimum actionable fix:** enforce explicit role+scope parity in replay service per entity/operation (or centralize policy check reuse so replay and API paths share same gate).

### Medium

2) **Scoped content list likely excludes region-only content for non-global users**
- **Severity:** Medium
- **Conclusion:** `ContentService::list` filters by accessible store IDs only; records with `region_id` and null `store_id` are likely omitted for scoped users.
- **Evidence:**
  - Region-only content is permitted on create/update: `backend/src/Service/Content/ContentService.php:58`, `backend/src/Service/Content/ContentService.php:121`
  - List scope filter is store-only: `backend/src/Service/Content/ContentService.php:250`, `backend/src/Service/Content/ContentService.php:254`
- **Impact:** region-scoped content may be inaccessible in list views despite region authorization.
- **Minimum actionable fix:** include region-based scope condition in list query when `store_id` is null and `region_id` is present.

3) **Search scope filter likely has same region-only exclusion pattern**
- **Severity:** Medium
- **Conclusion:** search query applies `store_id IN (...)` auth filter; region-only indexed rows with null `store_id` are likely excluded for scoped users.
- **Evidence:** `backend/src/Service/Search/SearchService.php:70`, `backend/src/Service/Search/SearchService.php:83`.
- **Impact:** authorized users may miss relevant region-scoped search results.
- **Minimum actionable fix:** extend search auth where-clause to allow region-authorized rows when `store_id` is null.

## 6. Security Review Summary

- **authentication entry points: Pass**
  - Public health/login and authenticated API boundary remain clear.
  - Evidence: `backend/config/packages/security.yaml:16`, `backend/config/packages/security.yaml:19`, `backend/config/packages/security.yaml:31`.

- **route-level authorization: Partial Pass**
  - Controllers are consistently using permission checks, including subject-aware mapping checks.
  - Evidence: `backend/src/Controller/Api/V1/DeliveryZoneController.php:226`, `backend/src/Controller/Api/V1/DeliveryZoneController.php:290`.

- **object-level authorization: Partial Pass**
  - Many show/update flows are subject-aware; replay operation-level parity remains incomplete for store/zone.
  - Evidence: `backend/src/Controller/Api/V1/StoreController.php:105`, replay gaps `backend/src/Service/MutationQueue/MutationReplayService.php:193`, `backend/src/Service/MutationQueue/MutationReplayService.php:282`.

- **function-level authorization: Partial Pass**
  - Replay now includes some explicit role checks (store/region create, region update), but not all relevant operations.
  - Evidence: enforced checks `backend/src/Service/MutationQueue/MutationReplayService.php:167`, `backend/src/Service/MutationQueue/MutationReplayService.php:225`; missing parity for store/zone update/create as above.

- **tenant/user data isolation: Partial Pass**
  - Scope resolver and controller checks improved, including region-aware content show checks; list/search region-only paths remain questionable.
  - Evidence: `backend/src/Controller/Api/V1/ContentController.php:131`, `backend/src/Service/Content/ContentService.php:250`, `backend/src/Service/Search/SearchService.php:70`.

- **admin/internal/debug protection: Partial Pass**
  - Admin mutation log remains protected and debug trace exposure is kernel-debug gated.
  - Evidence: `backend/src/Controller/Api/V1/MutationQueueController.php:63`, `backend/src/EventListener/ApiExceptionListener.php:68`.

## 7. Tests and Logging Review

- **Unit tests: Partial Pass**
  - New tests exist for recent fixes, but many are reflection/source-string checks and do not validate runtime behavior.
  - Evidence: `backend/tests/Unit/Security/Voter/SubjectlessVoterTest.php:43`, `backend/tests/Unit/Service/MutationQueue/ReplayPermissionTest.php:49`, `backend/tests/Unit/Controller/ContentRegionScopeTest.php:17`.

- **API/integration tests: Partial Pass**
  - Existing API tests remain broad; targeted behavioral tests for replay role parity and region-only list/search visibility are still insufficient.
  - Evidence: `backend/tests/Api/Authorization/AuthorizationMatrixTest.php:146`, `backend/tests/Api/Search/SearchApiTest.php:38`.

- **Logging categories / observability: Pass**
  - Structured authn/authz/error logging remains in place.
  - Evidence: `backend/src/EventListener/ApiExceptionListener.php:55`, `backend/src/EventListener/ApiExceptionListener.php:57`, `backend/src/EventListener/ApiExceptionListener.php:59`.

- **Sensitive-data leakage risk in logs/responses: Partial Pass**
  - Debug responses may still include trace/class when debug enabled.
  - Evidence: `backend/src/EventListener/ApiExceptionListener.php:68`, `backend/src/EventListener/ApiExceptionListener.php:70`.

## 8. Test Coverage Assessment (Static Audit)

### 8.1 Test Overview
- PHPUnit suites (`unit`, `integration`, `api`) and Vitest are configured.
  - Evidence: `backend/phpunit.xml.dist:21`, `backend/phpunit.xml.dist:25`, `backend/phpunit.xml.dist:28`, `frontend/vitest.config.ts:12`.
- Test commands are documented.
  - Evidence: `README.md:99`, `README.md:115`.

### 8.2 Coverage Mapping Table

| Requirement / Risk Point | Mapped Test Case(s) | Key Assertion / Fixture / Mock | Coverage Assessment | Gap | Minimum Test Addition |
|---|---|---|---|---|---|
| Subjectless list voter support | `backend/tests/Unit/Security/Voter/SubjectlessVoterTest.php:43` | null-subject grant/deny checks | basically covered | no end-to-end route behavior check | add API tests for list routes across roles/scopes |
| Search store/region filter UI wiring | `frontend/src/components/search/__tests__/SearchFilters.test.tsx:28`, `frontend/src/hooks/__tests__/useSearch.test.ts:35` | verifies filter fields and param propagation | basically covered | hook test is simulated mapping, not hook runtime with mocked API | add hook integration test invoking `useSearch` with mocked `search()` |
| Content region scope check in show | `backend/tests/Unit/Controller/ContentRegionScopeTest.php:15` | source-string assertion on controller code | insufficient | not behavior-tested with scoped users | add API tests with region-scoped users and region-only content IDs |
| Region list scope support | `backend/tests/Unit/Service/Region/RegionListScopeTest.php:15` | reflection signature checks | insufficient | no behavioral verification of filtered results | add service/integration test with seeded regions and scoped assignments |
| Replay permission hardening | `backend/tests/Unit/Service/MutationQueue/ReplayPermissionTest.php:49` | source-string assertions for checks | insufficient | does not execute replay path for unauthorized roles | add API/integration tests for replay create/update denied/allowed by role+scope |
| Mapping UUID validation | `backend/tests/Unit/Controller/ZoneMappingUuidValidationTest.php:35` | source-string checks for try/catch message | insufficient | no HTTP-level 422 assertion on invalid UUID payload | add API test for invalid `mapped_entity_id` returns 422 |

### 8.3 Security Coverage Audit
- **authentication:** basically covered.
- **route authorization:** partially covered; mostly unit-style checks.
- **object-level authorization:** insufficient for replay operation parity.
- **tenant/data isolation:** insufficient for region-only list/search behavior.
- **admin/internal protection:** partly covered.

### 8.4 Final Coverage Judgment
- **Partial Pass**
- Reason: tests still leave defects possible (especially replay role escalation and scoped list/search region-only behavior) while suites could pass.

## 9. Final Notes
- This report is static-only and evidence-based.
- Acceptance is still blocked by remaining High security risk in replay authorization parity and unresolved scope/list behavior gaps.
