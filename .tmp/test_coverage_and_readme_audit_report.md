# Test Coverage Audit

## Project Type Detection
- Declared type found in `README.md:19`: **fullstack**.

## Backend Endpoint Inventory (METHOD + resolved PATH)

1. `GET /api/v1/health`
2. `POST /api/v1/auth/login`
3. `POST /api/v1/auth/logout`
4. `GET /api/v1/auth/me`
5. `POST /api/v1/auth/change-password`
6. `POST /api/v1/regions`
7. `GET /api/v1/regions`
8. `GET /api/v1/regions/{id}`
9. `PUT /api/v1/regions/{id}`
10. `POST /api/v1/regions/{id}/close`
11. `GET /api/v1/regions/{id}/versions`
12. `POST /api/v1/stores`
13. `GET /api/v1/stores`
14. `GET /api/v1/stores/{id}`
15. `PUT /api/v1/stores/{id}`
16. `GET /api/v1/stores/{id}/versions`
17. `POST /api/v1/stores/{storeId}/delivery-zones`
18. `GET /api/v1/stores/{storeId}/delivery-zones`
19. `GET /api/v1/delivery-zones/{id}`
20. `PUT /api/v1/delivery-zones/{id}`
21. `POST /api/v1/delivery-zones/{zoneId}/mappings`
22. `GET /api/v1/delivery-zones/{zoneId}/mappings`
23. `POST /api/v1/delivery-zones/{zoneId}/windows`
24. `GET /api/v1/delivery-zones/{zoneId}/windows`
25. `PUT /api/v1/delivery-windows/{id}`
26. `DELETE /api/v1/delivery-windows/{id}`
27. `POST /api/v1/users`
28. `GET /api/v1/users`
29. `GET /api/v1/users/{id}`
30. `PUT /api/v1/users/{id}`
31. `PATCH /api/v1/users/{id}/deactivate`
32. `POST /api/v1/users/{userId}/role-assignments`
33. `GET /api/v1/users/{userId}/role-assignments`
34. `DELETE /api/v1/users/{userId}/role-assignments/{assignmentId}`
35. `POST /api/v1/content`
36. `GET /api/v1/content`
37. `GET /api/v1/content/{id}`
38. `PUT /api/v1/content/{id}`
39. `POST /api/v1/content/{id}/publish`
40. `POST /api/v1/content/{id}/archive`
41. `GET /api/v1/content/{contentId}/versions`
42. `GET /api/v1/content/{contentId}/versions/{versionId}`
43. `GET /api/v1/content/{contentId}/versions/{v1Id}/diff/{v2Id}`
44. `POST /api/v1/content/{contentId}/rollback`
45. `GET /api/v1/search`
46. `POST /api/v1/imports`
47. `GET /api/v1/imports`
48. `GET /api/v1/imports/{id}`
49. `GET /api/v1/imports/{id}/items`
50. `POST /api/v1/exports`
51. `GET /api/v1/exports`
52. `GET /api/v1/exports/{id}`
53. `POST /api/v1/exports/{id}/authorize`
54. `GET /api/v1/exports/{id}/download`
55. `POST /api/v1/classifications`
56. `GET /api/v1/classifications`
57. `PUT /api/v1/classifications/{id}`
58. `POST /api/v1/classifications/encrypted-fields/store`
59. `POST /api/v1/classifications/encrypted-fields/retrieve`
60. `POST /api/v1/boundaries/upload`
61. `GET /api/v1/boundaries`
62. `GET /api/v1/boundaries/{id}`
63. `POST /api/v1/boundaries/{id}/validate`
64. `POST /api/v1/boundaries/{id}/apply`
65. `POST /api/v1/compliance-reports`
66. `GET /api/v1/compliance-reports`
67. `GET /api/v1/compliance-reports/{id}`
68. `GET /api/v1/compliance-reports/{id}/download`
69. `POST /api/v1/consent`
70. `GET /api/v1/consent/user/{userId}`
71. `GET /api/v1/dedup/review`
72. `POST /api/v1/dedup/review/{id}/merge`
73. `POST /api/v1/dedup/review/{id}/reject`
74. `POST /api/v1/dedup/unmerge/{id}`
75. `POST /api/v1/mutations/replay`
76. `GET /api/v1/mutations`
77. `GET /api/v1/retention/cases`
78. `POST /api/v1/retention/cases/{id}/schedule`
79. `GET /api/v1/retention/stats`
80. `POST /api/v1/sources`
81. `GET /api/v1/sources`
82. `GET /api/v1/sources/{id}`
83. `PUT /api/v1/sources/{id}`
84. `POST /api/v1/sources/{id}/pause`
85. `POST /api/v1/sources/{id}/resume`
86. `POST /api/v1/sources/{id}/disable`
87. `GET /api/v1/sources/{id}/health`
88. `GET /api/v1/sources/health/dashboard`
89. `GET /api/v1/scrape-runs`
90. `GET /api/v1/scrape-runs/{id}`
91. `POST /api/v1/scrape-runs/trigger/{sourceId}`
92. `GET /api/v1/warehouse/loads`
93. `GET /api/v1/warehouse/loads/{id}`
94. `POST /api/v1/warehouse/loads/trigger`
95. `GET /api/v1/analytics/sales`
96. `GET /api/v1/analytics/sales/trends`
97. `GET /api/v1/analytics/content-volume`
98. `GET /api/v1/analytics/kpi-summary`

Total inventory from controller attributes: **98 endpoints**.

## API Test Mapping Table

Legend:
- test type = `true no-mock HTTP` if executed via `WebTestCase` + `createClient()` and no DI/mock override in API tests.
- evidence uses request call sites.

| Endpoint | Covered | Test type | Test files | Evidence |
|---|---|---|---|---|
| `GET /api/v1/health` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/HealthAndAuthCoverageTest.php` | `...HealthAndAuthCoverageTest.php:78` |
| `POST /api/v1/auth/login` | yes | true no-mock HTTP | `backend/tests/Api/Auth/LoginApiTest.php` | `...LoginApiTest.php:36` |
| `POST /api/v1/auth/logout` | yes | true no-mock HTTP | `backend/tests/Api/Auth/LoginApiTest.php` | `...LoginApiTest.php:149` |
| `GET /api/v1/auth/me` | yes | true no-mock HTTP | `backend/tests/Api/Auth/LoginApiTest.php` | `...LoginApiTest.php:116` |
| `POST /api/v1/auth/change-password` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/HealthAndAuthCoverageTest.php` | `...HealthAndAuthCoverageTest.php:103` |
| `POST /api/v1/regions` | yes | true no-mock HTTP | `backend/tests/Api/Region/RegionCrudApiTest.php` | `...RegionCrudApiTest.php:37` |
| `GET /api/v1/regions` | yes | true no-mock HTTP | `backend/tests/Api/Region/RegionCrudApiTest.php`, `backend/tests/Api/Envelope/EnvelopeFormatTest.php` | `...RegionCrudApiTest.php:126` |
| `GET /api/v1/regions/{id}` | yes | true no-mock HTTP | `backend/tests/Api/Region/RegionCrudApiTest.php`, `backend/tests/Api/Coverage/RegionDetailCoverageTest.php` | `...RegionCrudApiTest.php:202` |
| `PUT /api/v1/regions/{id}` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/RegionDetailCoverageTest.php` | `...RegionDetailCoverageTest.php:120` |
| `POST /api/v1/regions/{id}/close` | yes | true no-mock HTTP | `backend/tests/Api/Region/RegionCrudApiTest.php` | `...RegionCrudApiTest.php:186` |
| `GET /api/v1/regions/{id}/versions` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/RegionDetailCoverageTest.php` | `...RegionDetailCoverageTest.php:141` |
| `POST /api/v1/stores` | yes | true no-mock HTTP | `backend/tests/Api/Store/StoreCrudApiTest.php`, `backend/tests/Api/Store/StoreContractTest.php` | `...StoreCrudApiTest.php:38` |
| `GET /api/v1/stores` | yes | true no-mock HTTP | `backend/tests/Api/Store/StoreCrudApiTest.php`, `backend/tests/Api/Security/EndpointAuthorizationTest.php` | `...StoreCrudApiTest.php:112` |
| `GET /api/v1/stores/{id}` | yes | true no-mock HTTP | `backend/tests/Api/Store/StoreCrudApiTest.php`, `backend/tests/Api/Store/StoreContractTest.php` | `...StoreCrudApiTest.php:149` |
| `PUT /api/v1/stores/{id}` | yes | true no-mock HTTP | `backend/tests/Api/Store/StoreCrudApiTest.php` | `...StoreCrudApiTest.php:181` |
| `GET /api/v1/stores/{id}/versions` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/StoreVersionsCoverageTest.php` | `...StoreVersionsCoverageTest.php:118` |
| `POST /api/v1/stores/{storeId}/delivery-zones` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/DeliveryZoneCoverageTest.php` | `...DeliveryZoneCoverageTest.php:119` |
| `GET /api/v1/stores/{storeId}/delivery-zones` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/DeliveryZoneCoverageTest.php` | `...DeliveryZoneCoverageTest.php:145` |
| `GET /api/v1/delivery-zones/{id}` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/DeliveryZoneCoverageTest.php` | `...DeliveryZoneCoverageTest.php:167` |
| `PUT /api/v1/delivery-zones/{id}` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/DeliveryZoneCoverageTest.php` | `...DeliveryZoneCoverageTest.php:190` |
| `POST /api/v1/delivery-zones/{zoneId}/mappings` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/ZoneMappingCoverageTest.php` | `...ZoneMappingCoverageTest.php:138` |
| `GET /api/v1/delivery-zones/{zoneId}/mappings` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/ZoneMappingCoverageTest.php` | `...ZoneMappingCoverageTest.php:162` |
| `POST /api/v1/delivery-zones/{zoneId}/windows` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/DeliveryWindowCoverageTest.php` | `...DeliveryWindowCoverageTest.php:136` |
| `GET /api/v1/delivery-zones/{zoneId}/windows` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/DeliveryWindowCoverageTest.php` | `...DeliveryWindowCoverageTest.php:162` |
| `PUT /api/v1/delivery-windows/{id}` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/DeliveryWindowCoverageTest.php`, `backend/tests/Api/Behavior/DeliveryWindowBehaviorTest.php` | `...DeliveryWindowCoverageTest.php:188` |
| `DELETE /api/v1/delivery-windows/{id}` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/DeliveryWindowCoverageTest.php` | `...DeliveryWindowCoverageTest.php:216` |
| `POST /api/v1/users` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/UserCoverageTest.php` | `...UserCoverageTest.php:87` |
| `GET /api/v1/users` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/UserCoverageTest.php` | `...UserCoverageTest.php:106` |
| `GET /api/v1/users/{id}` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/UserCoverageTest.php` | `...UserCoverageTest.php:131` |
| `PUT /api/v1/users/{id}` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/UserCoverageTest.php` | `...UserCoverageTest.php:156` |
| `PATCH /api/v1/users/{id}/deactivate` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/UserCoverageTest.php` | `...UserCoverageTest.php:185` |
| `POST /api/v1/users/{userId}/role-assignments` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/RoleAssignmentCoverageTest.php` | `...RoleAssignmentCoverageTest.php:108` |
| `GET /api/v1/users/{userId}/role-assignments` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/RoleAssignmentCoverageTest.php` | `...RoleAssignmentCoverageTest.php:137` |
| `DELETE /api/v1/users/{userId}/role-assignments/{assignmentId}` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/RoleAssignmentCoverageTest.php` | `...RoleAssignmentCoverageTest.php:174` |
| `POST /api/v1/content` | yes | true no-mock HTTP | `backend/tests/Api/Content/ContentVersionDiffRollbackTest.php`, `backend/tests/Api/Coverage/ContentLifecycleCoverageTest.php` | `...ContentVersionDiffRollbackTest.php:40` |
| `GET /api/v1/content` | yes | true no-mock HTTP | `backend/tests/Api/Behavior/ContentScopeBehaviorTest.php`, `backend/tests/Api/Behavior/ContentLifecycleBehaviorTest.php` | `...ContentScopeBehaviorTest.php:185` |
| `GET /api/v1/content/{id}` | yes | true no-mock HTTP | `backend/tests/Api/Behavior/ContentLifecycleBehaviorTest.php`, `backend/tests/Api/Coverage/ContentLifecycleCoverageTest.php` | `...ContentLifecycleBehaviorTest.php:55` |
| `PUT /api/v1/content/{id}` | yes | true no-mock HTTP | `backend/tests/Api/Content/ContentVersionDiffRollbackTest.php`, `backend/tests/Api/Coverage/ContentLifecycleCoverageTest.php` | `...ContentVersionDiffRollbackTest.php:57` |
| `POST /api/v1/content/{id}/publish` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/ContentLifecycleCoverageTest.php`, `backend/tests/Api/Search/SearchApiTest.php` | `...ContentLifecycleCoverageTest.php:178` |
| `POST /api/v1/content/{id}/archive` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/ContentLifecycleCoverageTest.php`, `backend/tests/Api/Behavior/ContentLifecycleBehaviorTest.php` | `...ContentLifecycleCoverageTest.php:197` |
| `GET /api/v1/content/{contentId}/versions` | yes | true no-mock HTTP | `backend/tests/Api/Content/ContentVersionDiffRollbackTest.php`, `backend/tests/Api/Content/ContentDiffContractTest.php` | `...ContentVersionDiffRollbackTest.php:70` |
| `GET /api/v1/content/{contentId}/versions/{versionId}` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/ContentLifecycleCoverageTest.php` | `...ContentLifecycleCoverageTest.php:246` |
| `GET /api/v1/content/{contentId}/versions/{v1Id}/diff/{v2Id}` | yes | true no-mock HTTP | `backend/tests/Api/Content/ContentVersionDiffRollbackTest.php` | `...ContentVersionDiffRollbackTest.php:125` |
| `POST /api/v1/content/{contentId}/rollback` | yes | true no-mock HTTP | `backend/tests/Api/Content/ContentVersionDiffRollbackTest.php` | `...ContentVersionDiffRollbackTest.php:188` |
| `GET /api/v1/search` | yes | true no-mock HTTP | `backend/tests/Api/Search/SearchApiTest.php`, `backend/tests/Api/Security/EndpointAuthorizationTest.php` | `...SearchApiTest.php:43` |
| `POST /api/v1/imports` | yes | true no-mock HTTP | `backend/tests/Api/Behavior/ImportBehaviorTest.php`, `backend/tests/Api/Authorization/AuthorizationMatrixTest.php` | `...ImportBehaviorTest.php:422` |
| `GET /api/v1/imports` | yes | true no-mock HTTP | `backend/tests/Api/Behavior/ImportBehaviorTest.php` | `...ImportBehaviorTest.php:62` |
| `GET /api/v1/imports/{id}` | yes | true no-mock HTTP | `backend/tests/Api/Behavior/ImportBehaviorTest.php` | `...ImportBehaviorTest.php:166` |
| `GET /api/v1/imports/{id}/items` | yes | true no-mock HTTP | `backend/tests/Api/Behavior/ImportBehaviorTest.php` | `...ImportBehaviorTest.php:232` |
| `POST /api/v1/exports` | yes | true no-mock HTTP | `backend/tests/Api/Behavior/ExportBehaviorTest.php`, `backend/tests/Api/Authorization/AuthorizationMatrixTest.php` | `...ExportBehaviorTest.php:48` |
| `GET /api/v1/exports` | yes | true no-mock HTTP | `backend/tests/Api/Behavior/ExportBehaviorTest.php` | `...ExportBehaviorTest.php:153` |
| `GET /api/v1/exports/{id}` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/ExportLifecycleCoverageTest.php`, `backend/tests/Api/Behavior/ExportBehaviorTest.php` | `...ExportLifecycleCoverageTest.php:124` |
| `POST /api/v1/exports/{id}/authorize` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/ExportLifecycleCoverageTest.php`, `backend/tests/Api/Behavior/ExportBehaviorTest.php` | `...ExportLifecycleCoverageTest.php:141` |
| `GET /api/v1/exports/{id}/download` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/ExportLifecycleCoverageTest.php` | `...ExportLifecycleCoverageTest.php:165` |
| `POST /api/v1/classifications` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/ClassificationCoverageTest.php` | `...ClassificationCoverageTest.php:98` |
| `GET /api/v1/classifications` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/ClassificationCoverageTest.php` | `...ClassificationCoverageTest.php:119` |
| `PUT /api/v1/classifications/{id}` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/ClassificationCoverageTest.php` | `...ClassificationCoverageTest.php:127` |
| `POST /api/v1/classifications/encrypted-fields/store` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/ClassificationCoverageTest.php` | `...ClassificationCoverageTest.php:137` |
| `POST /api/v1/classifications/encrypted-fields/retrieve` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/ClassificationCoverageTest.php` | `...ClassificationCoverageTest.php:145` |
| `POST /api/v1/boundaries/upload` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/BoundaryCoverageTest.php` | `...BoundaryCoverageTest.php:119` |
| `GET /api/v1/boundaries` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/BoundaryCoverageTest.php` | `...BoundaryCoverageTest.php:103` |
| `GET /api/v1/boundaries/{id}` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/BoundaryCoverageTest.php` | `...BoundaryCoverageTest.php:139` |
| `POST /api/v1/boundaries/{id}/validate` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/BoundaryCoverageTest.php` | `...BoundaryCoverageTest.php:151` |
| `POST /api/v1/boundaries/{id}/apply` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/BoundaryCoverageTest.php` | `...BoundaryCoverageTest.php:163` |
| `POST /api/v1/compliance-reports` | yes | true no-mock HTTP | `backend/tests/Api/Compliance/ComplianceEnumValidationTest.php`, `backend/tests/Api/Coverage/ComplianceShowCoverageTest.php` | `...ComplianceEnumValidationTest.php:50` |
| `GET /api/v1/compliance-reports` | yes | true no-mock HTTP | `backend/tests/Api/Compliance/ComplianceEnumValidationTest.php` | `...ComplianceEnumValidationTest.php:121` |
| `GET /api/v1/compliance-reports/{id}` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/ComplianceShowCoverageTest.php` | `...ComplianceShowCoverageTest.php:107` |
| `GET /api/v1/compliance-reports/{id}/download` | yes | true no-mock HTTP | `backend/tests/Api/Compliance/ComplianceEnumValidationTest.php` | `...ComplianceEnumValidationTest.php:152` |
| `POST /api/v1/consent` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/ConsentCoverageTest.php` | `...ConsentCoverageTest.php:100` |
| `GET /api/v1/consent/user/{userId}` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/ConsentCoverageTest.php` | `...ConsentCoverageTest.php:126` |
| `GET /api/v1/dedup/review` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/DedupCoverageTest.php` | `...DedupCoverageTest.php:87` |
| `POST /api/v1/dedup/review/{id}/merge` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/DedupCoverageTest.php` | `...DedupCoverageTest.php:99` |
| `POST /api/v1/dedup/review/{id}/reject` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/DedupCoverageTest.php` | `...DedupCoverageTest.php:111` |
| `POST /api/v1/dedup/unmerge/{id}` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/DedupCoverageTest.php` | `...DedupCoverageTest.php:123` |
| `POST /api/v1/mutations/replay` | yes | true no-mock HTTP | `backend/tests/Api/Behavior/MutationReplayBehaviorTest.php`, `backend/tests/Api/Authorization/AuthorizationMatrixTest.php` | `...MutationReplayBehaviorTest.php:235` |
| `GET /api/v1/mutations` | yes | true no-mock HTTP | `backend/tests/Api/Authorization/AuthorizationMatrixTest.php` | `...AuthorizationMatrixTest.php:168` |
| `GET /api/v1/retention/cases` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/RetentionCoverageTest.php` | `...RetentionCoverageTest.php:87` |
| `POST /api/v1/retention/cases/{id}/schedule` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/RetentionCoverageTest.php` | `...RetentionCoverageTest.php:110` |
| `GET /api/v1/retention/stats` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/RetentionCoverageTest.php` | `...RetentionCoverageTest.php:99` |
| `POST /api/v1/sources` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/ScrapingCoverageTest.php`, `backend/tests/Api/Security/EndpointAuthorizationTest.php` | `...ScrapingCoverageTest.php:86` |
| `GET /api/v1/sources` | yes | true no-mock HTTP | `backend/tests/Api/Security/EndpointAuthorizationTest.php` | `...EndpointAuthorizationTest.php:109` |
| `GET /api/v1/sources/{id}` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/ScrapingCoverageTest.php` | `...ScrapingCoverageTest.php:101` |
| `PUT /api/v1/sources/{id}` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/ScrapingCoverageTest.php` | `...ScrapingCoverageTest.php:114` |
| `POST /api/v1/sources/{id}/pause` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/ScrapingCoverageTest.php` | `...ScrapingCoverageTest.php:129` |
| `POST /api/v1/sources/{id}/resume` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/ScrapingCoverageTest.php` | `...ScrapingCoverageTest.php:144` |
| `POST /api/v1/sources/{id}/disable` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/ScrapingCoverageTest.php` | `...ScrapingCoverageTest.php:157` |
| `GET /api/v1/sources/{id}/health` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/ScrapingCoverageTest.php` | `...ScrapingCoverageTest.php:170` |
| `GET /api/v1/sources/health/dashboard` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/ScrapingCoverageTest.php` | `...ScrapingCoverageTest.php:181` |
| `GET /api/v1/scrape-runs` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/ScrapingCoverageTest.php` | `...ScrapingCoverageTest.php:192` |
| `GET /api/v1/scrape-runs/{id}` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/ScrapingCoverageTest.php` | `...ScrapingCoverageTest.php:204` |
| `POST /api/v1/scrape-runs/trigger/{sourceId}` | yes | true no-mock HTTP | `backend/tests/Api/Security/EndpointAuthorizationTest.php` | `...EndpointAuthorizationTest.php:120` |
| `GET /api/v1/warehouse/loads` | yes | true no-mock HTTP | `backend/tests/Api/Security/EndpointAuthorizationTest.php`, `backend/tests/Api/Authorization/AuthorizationMatrixTest.php` | `...EndpointAuthorizationTest.php:69` |
| `GET /api/v1/warehouse/loads/{id}` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/WarehouseDetailCoverageTest.php` | `...WarehouseDetailCoverageTest.php:87` |
| `POST /api/v1/warehouse/loads/trigger` | yes | true no-mock HTTP | `backend/tests/Api/Security/EndpointAuthorizationTest.php` | `...EndpointAuthorizationTest.php:81` |
| `GET /api/v1/analytics/sales` | yes | true no-mock HTTP | `backend/tests/Api/Security/EndpointAuthorizationTest.php` | `...EndpointAuthorizationTest.php:136` |
| `GET /api/v1/analytics/sales/trends` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/AnalyticsCoverageTest.php` | `...AnalyticsCoverageTest.php:87` |
| `GET /api/v1/analytics/content-volume` | yes | true no-mock HTTP | `backend/tests/Api/Coverage/AnalyticsCoverageTest.php` | `...AnalyticsCoverageTest.php:98` |
| `GET /api/v1/analytics/kpi-summary` | yes | true no-mock HTTP | `backend/tests/Api/Security/EndpointAuthorizationTest.php`, `backend/tests/Api/Authorization/AuthorizationMatrixTest.php` | `...EndpointAuthorizationTest.php:148` |

## API Test Classification

1. **True No-Mock HTTP**
   - Evidence of real HTTP bootstrapping: `backend/tests/Api/Coverage/HealthAndAuthCoverageTest.php:23`, `backend/tests/Api/Auth/LoginApiTest.php:25` (`static::createClient()`).
   - No DI override/mock injection found under `backend/tests/Api/` (no `createMock()` or container `set()` replacement).

2. **HTTP with Mocking**
   - **None found in backend API tests**.

3. **Non-HTTP (unit/integration without HTTP)**
   - Unit tests: `backend/tests/Unit/**` (e.g., `backend/tests/Unit/Service/MutationQueue/ReplayZoneCreateScopeTest.php:42`).
   - Integration/kernel tests: `backend/tests/Integration/**` (e.g., `backend/tests/Integration/Content/ContentServiceIntegrationTest.php`).

## Mock Detection

- Backend API layer: no mocks detected in `backend/tests/Api/**`.
- Backend unit layer uses mocks extensively (expected for unit tests):
  - `backend/tests/Unit/Service/MutationQueue/ReplayZoneCreateScopeTest.php:42` (`EntityManagerInterface`, `StoreService`, `DeliveryZoneService`, etc.)
  - `backend/tests/Unit/Security/Voter/SearchVoterTest.php:29` (`RbacService`, `TokenInterface`)
  - `backend/tests/Unit/Service/Content/ContentRollbackWindowTest.php:30` (`ContentVersionService`, `AuditService`)
- Frontend unit layer uses `vi.mock` broadly (expected for component/hook/API-unit tests):
  - `frontend/src/pages/content/__tests__/ContentCreatePage.test.tsx:13`
  - `frontend/src/api/__tests__/auth.test.ts:7`
  - `frontend/src/hooks/__tests__/useAuth.test.ts:19`

## Coverage Summary

- Total endpoints: **98**
- Endpoints with HTTP tests: **98**
- Endpoints with true no-mock HTTP tests: **98**
- HTTP coverage: **100.0%**
- True API coverage: **100.0%**

## Unit Test Summary

### Backend Unit Tests
- Present: yes (`backend/tests/Unit/**`)
- Modules covered (evidence):
  - controllers/contracts: `backend/tests/Unit/Controller/StoreContractTest.php`, `backend/tests/Unit/Controller/ComplianceReportContractTest.php`
  - services: `backend/tests/Unit/Service/Export/ExportLifecycleTest.php`, `backend/tests/Unit/Service/Auth/PasswordPolicyServiceTest.php`
  - security/voters/authorization: `backend/tests/Unit/Security/Voter/MutationQueueVoterTest.php`, `backend/tests/Unit/Security/ListAuthorizationTest.php`
  - middleware/listeners: `backend/tests/Unit/EventListener/DebugTraceExposureTest.php`
- Important backend modules not directly unit-tested (file-level):
  - `backend/src/Service/Scraping/ScrapeOrchestratorService.php`
  - `backend/src/Service/Search/SearchIndexService.php`
  - `backend/src/Service/Warehouse/FactLoaderService.php`
  - `backend/src/Service/Governance/EncryptionService.php`
  - `backend/src/Service/Boundary/BoundaryApplyService.php`

### Frontend Unit Tests (STRICT REQUIREMENT)
- Frontend test files found: yes (e.g., `frontend/src/pages/content/__tests__/ContentCreatePage.test.tsx`, `frontend/src/hooks/__tests__/useAuth.test.ts`, `frontend/src/pages/__tests__/LoginPage.test.tsx`, `frontend/src/pages/__tests__/DashboardPage.test.tsx`)
- Framework/tooling evidence:
  - Vitest config: `frontend/vitest.config.ts:12`
  - React Testing Library imports: `frontend/src/pages/content/__tests__/ContentCreatePage.test.tsx:3`
  - Component rendering evidence: `frontend/src/pages/content/__tests__/ContentCreatePage.test.tsx:50`
- Components/modules covered (sample):
  - pages: `ContentCreatePage`, `ContentListPage`, `StoreListPage`, `LoginPage`, `ExportListPage`
  - newly added critical pages: `DashboardPage`, `ZoneListPage`, `ZoneDetailPage`, `MutationQueueDashboard`
  - layout: `AppShell`
  - hooks/stores: `useAuth`, `useSearch`, `authStore`, `connectivityStore`
  - API modules: `auth`, `content`, `stores`, `regions`, `exports`, `analytics`, `scraping`, etc.
- Important frontend components/modules not tested (file-level gaps):
  - `frontend/src/pages/governance/ConsentDashboard.tsx`
  - `frontend/src/pages/admin/JobStatusDashboard.tsx`
  - `frontend/src/components/content/RollbackDialog.tsx`
  - `frontend/src/components/layout/ConnectivityBanner.tsx`
  - `frontend/src/components/common/ProtectedRoute.tsx`

**Mandatory verdict:** **Frontend unit tests: PRESENT**

### Cross-Layer Observation
- Fullstack test distribution exists across backend API/unit/integration, frontend unit, and Playwright E2E (`frontend/e2e/*.spec.ts`).
- Balance is acceptable; frontend is not untested.

## API Observability Check

- Strong observability examples:
  - Request + response body assertions: `backend/tests/Api/Content/ContentVersionDiffRollbackTest.php:136` onward.
  - Deep auth flow assertions (state transition + re-auth): `backend/tests/Api/Coverage/HealthAndAuthCoverageTest.php:97` onward.
  - Deep download assertions (headers + attachment payload): `backend/tests/Api/Compliance/ComplianceEnumValidationTest.php:141` onward.
- Weak observability patterns present:
  - Permissive status assertions still exist in coverage suites (`assertContains` with broad accepted codes): `backend/tests/Api/Coverage/ContentLifecycleCoverageTest.php:150`, `backend/tests/Api/Coverage/RegionDetailCoverageTest.php:124`.
  - Route-existence style checks still exist in behavior tests (`not 404/405`): `backend/tests/Api/Behavior/ContentLifecycleBehaviorTest.php:174`.

## Tests Check

- `run_tests.sh` is Docker-oriented (good): starts compose, runs phpunit/vitest/playwright in containers (`run_tests.sh:38`, `run_tests.sh:112`, `run_tests.sh:155`).
- Runtime npm install concern has been removed from test/start paths (`run_tests.sh:124-126`, `docker-compose.yml:67`, `docker-compose.yml:90`).
- Runtime composer install concern has also been removed from startup paths (`docker-compose.yml:113`, `docker/php/entrypoint.sh:19` onward).

## Test Quality & Sufficiency

- Success paths: broad coverage across CRUD/lifecycle/auth/scope.
- Failure and validation paths: present (401/403/422 and invalid enum/validation checks).
- Edge cases: present but uneven; many are status-only checks.
- Auth/permissions: strong dedicated suites (`backend/tests/Api/Security/EndpointAuthorizationTest.php`, `backend/tests/Api/Authorization/AuthorizationMatrixTest.php`).
- Integration boundaries: backend integration tests exist (`backend/tests/Integration/**`), plus FE E2E flows (`frontend/e2e/**`).

## End-to-End Expectations (fullstack)

- Real FE↔BE E2E present via Playwright specs: `frontend/e2e/auth.spec.ts`, `frontend/e2e/stores.spec.ts`, `frontend/e2e/content.spec.ts`.
- Compensation check not needed (E2E exists).

## Test Coverage Score (0-100)

**Score: 93/100**

### Score Rationale
- + High endpoint-level HTTP reachability across backend controllers.
- + Strong auth/authorization matrix presence.
- + Fullstack layering exists (backend + frontend unit + E2E).
- + Notable quality improvement: change-password and compliance-download tests now validate business outcomes, not just routability (`backend/tests/Api/Coverage/HealthAndAuthCoverageTest.php:97`, `backend/tests/Api/Compliance/ComplianceEnumValidationTest.php:141`).
- + Critical previously-missing frontend modules now have unit tests (`frontend/src/pages/__tests__/DashboardPage.test.tsx`, `frontend/src/pages/delivery-zones/__tests__/ZoneListPage.test.tsx`, `frontend/src/pages/delivery-zones/__tests__/ZoneDetailPage.test.tsx`, `frontend/src/pages/admin/__tests__/MutationQueueDashboard.test.tsx`, `frontend/src/components/layout/__tests__/AppShell.test.tsx`).
- + Test/start environment no longer performs runtime dependency installation in compose commands or runner script (`docker-compose.yml:90`, `docker-compose.yml:113`, `run_tests.sh:124`).
- - Some API suites still accept wide status ranges instead of asserting single expected outcomes.

## Key Gaps

1. Residual API depth gap: several coverage tests still use permissive assertions (`201 or 500`, `200/422/428`) rather than deterministic business-state checks.
2. Frontend unit coverage improved materially, but some governance/admin/layout edge modules remain untested.
3. A few README verification snippets still use shell utilities (`grep`, `head`) that are platform-sensitive and less deterministic across environments.

## Confidence & Assumptions

- Confidence: **high** for endpoint inventory and HTTP reachability mapping (controller attributes + static request call evidence).
- Confidence: **medium** for “real business logic depth” because static inspection cannot prove all runtime branches.
- Assumption: requests made in `WebTestCase` hit real Symfony HTTP/kernel path (no API-layer mocks detected).

---

# README Audit

## README Location Check

- Required file exists: `README.md` (PASS)

## Hard Gate Evaluation

### Formatting
- Clean markdown with headings, tables, code blocks (PASS).

### Startup Instructions (backend/fullstack)
- Includes required `docker-compose up` explicitly: `README.md:64` (PASS).

### Access Method
- URL + ports provided for API/frontend/db: `README.md:72-77` (PASS).

### Verification Method
- UI flow verification provided: `README.md:315-323` (PASS).
- API verification provided (curl): `README.md:326-343` (PASS).

### Environment Rules (STRICT: no runtime installs/manual setup)
- README and test paths removed runtime npm install and explicitly document image-baked dependencies (`README.md:123-138`, `run_tests.sh:124-126`, `docker-compose.yml:67`, `docker-compose.yml:90`).
- Backend runtime install path has been removed from both worker and php entrypoint (`docker-compose.yml:113`, `docker/php/entrypoint.sh`).
- Dockerfiles now own dependency installation (`docker/php/Dockerfile:32`, `docker/node/Dockerfile:9`, `docker/playwright/Dockerfile:9`) with runtime containers using mounted source + preserved dependency volumes.

### Demo Credentials / Auth
- Auth clearly exists and credentials with roles are documented (`README.md:78-89`) (PASS).

## Engineering Quality

- Tech stack clarity: strong (`README.md:19-25`).
- Architecture explanation: strong diagram + structure (`README.md:7-52`).
- Testing instructions: extensive and specific (`README.md:90-201`).
- Security/roles/workflows: documented (`README.md:227-255`, `README.md:322-330`).
- Presentation quality: high readability.

## High Priority Issues

1. None.

## Medium Priority Issues

1. “Static Verification Guidance” includes commands that are operationally useful but not tightly tied to a minimal acceptance checklist.
2. Several API coverage tests still rely on permissive status sets instead of deterministic business assertions.

## Low Priority Issues

1. Repo structure section references files not confirmed in this audit scope (`ASSUMPTIONS.md`, `RUNBOOK.md`) without fallback note.

## Hard Gate Failures

- None.

## README Verdict

**PASS**

- Passes startup/access/verification/auth-documentation gates.
- Passes strict environment immutability gate (runtime dependency install removed from normal start/test paths).

---

## Final Verdicts

- **Test Coverage Audit Verdict:** PASS (IMPROVED; score >= 90) with residual quality gaps
- **README Audit Verdict:** PASS
