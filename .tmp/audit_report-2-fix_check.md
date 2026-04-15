# Fix Check for `audit_report-2.md`

Date: 2026-04-15  
Method: static code re-inspection of the previously flagged paths (no runtime execution)

## Overall Result

- **Partially fixed**
- **Fixed:** 3 of 3 previously reported issues
- **Still open:** 0 of 3
## Issue-by-Issue Verification

### 1) High - Replay path role escalation on store/zone mutations
- **Status:** **Fixed**
- **What was checked:** whether replay now enforces the same role + scope constraints as normal store/zone voters.
- **Current evidence:**
  - Replay entry still allows broad replay roles: `backend/src/Security/Voter/MutationQueueVoter.php:57`
  - Store update replay now includes explicit role check (Store Manager/Admin) before scope check: `backend/src/Service/MutationQueue/MutationReplayService.php:188`
  - Zone create replay now includes explicit role check (Store Manager/Dispatcher/Admin) before scope check: `backend/src/Service/MutationQueue/MutationReplayService.php:267`
  - Zone update replay now includes explicit role check (Store Manager/Dispatcher/Admin) before scope check: `backend/src/Service/MutationQueue/MutationReplayService.php:292`
  - Role sets align with existing voters:
    - Store edit roles: `backend/src/Security/Voter/StoreVoter.php:84`
    - Zone edit/create roles: `backend/src/Security/Voter/DeliveryZoneVoter.php:102`, `backend/src/Security/Voter/DeliveryZoneVoter.php:114`
- **Conclusion:** replay authorization parity for store update and zone create/update is now implemented.

### 2) Medium - Scoped content list excluding region-only content
- **Status:** **Fixed**
- **What was checked:** whether content list now includes region-only content (`store_id` null + authorized `region_id`) for scoped users.
- **Current evidence:**
  - List method now accepts both store and region scope inputs: `backend/src/Service/Content/ContentService.php:229`
  - Query now explicitly includes OR-condition for region-only rows when `store_id` is null: `backend/src/Service/Content/ContentService.php:258`
  - Condition includes `(c.storeId IS NULL AND c.regionId IN (:accessibleRegionIds))`: `backend/src/Service/Content/ContentService.php:263`
- **Conclusion:** the previously reported store-only scope filter pattern has been corrected in content listing.

### 3) Medium - Search scope filter excluding region-only rows
- **Status:** **Fixed**
- **What was checked:** whether search auth filter now includes region-authorized rows when `store_id` is null.
- **Current evidence:**
  - Search auth filtering now builds both store and region scope branches: `backend/src/Service/Search/SearchService.php:73`
  - Region-only branch is explicitly added: `(csi.store_id IS NULL AND csi.region_id IN (...))`: `backend/src/Service/Search/SearchService.php:101`
  - Final auth scope combines the scope parts with OR: `backend/src/Service/Search/SearchService.php:104`
- **Conclusion:** region-only search visibility gap appears resolved in current query logic.

## Final Acceptance View (for this fix-check)
- All of the exisiting open issues found within the last report have been fixed.
