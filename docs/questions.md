# Meridian Offline Commerce & Compliance Intelligence — Clarification Questions



## 1. Offline Write Conflict Resolution: Concurrent Edits During Disconnection

**Question:** The prompt requires the UI to queue changes locally "when the backend is busy," but does not specify what happens when an offline client submits an update that conflicts with a change another user made on the server while the first user was disconnected. Should the offline version win, the server version win, or should the conflict surface for manual resolution?

**My Understanding:** Last-write-wins is dangerous when the offline period can span minutes or hours — a Dispatcher could queue a zone threshold change while a Store Manager has already raised it via a separate session. The safest approach is to detect the version mismatch explicitly and report the conflict back to the client without silently overwriting either change, so the user can see what diverged and decide.

**Solution:** The backend `MutationReplayService` processes each replayed mutation and tracks the result per `mutation_id` in `mutation_queue_log`. On UPDATE operations, `StoreService.update()`, `DeliveryZoneService.update()`, and `RegionService.update()` each check the Doctrine optimistic lock version. A version mismatch raises a `ConflictHttpException`, which the replay service catches and stores with status `CONFLICT` and a `conflict_detail` message. The response batch returned to the client includes one entry per mutation: `APPLIED`, `CONFLICT`, or `REJECTED`. The frontend `ConflictResolutionDialog` component surfaces CONFLICT results so the user can inspect and decide how to proceed. Mutations are idempotent on `mutation_id` — replaying the same batch twice returns the previously stored results without re-applying.

---

## 2. Content Rollback Window: What Does "Within 30 Days" Measure?

**Question:** The prompt says users can "roll back within 30 days if needed," but does not clarify 30 days from what reference point: from when the version was created, from when the content item was first published, or from when the rollback is requested relative to the target version's age?

**My Understanding:** Rolling back to a version that is itself six months old creates a confusing UX if the 30-day window is measured from the item's creation date — a newly archived piece could still be rolled back, while an actively edited one could not. The most coherent interpretation is that the window applies to the version being targeted: only versions whose `created_at` is within the past 30 days are eligible as rollback targets.

**Solution:** `ContentRollbackService` enforces the window by comparing the target `ContentVersion.createdAt` against `now() - 30 days`. Attempting to roll back to a version older than 30 days returns a `422 VALIDATION_ERROR`. When a rollback is applied, `ContentVersionService.createVersion()` creates a new version snapshot with `isRollback = true` and a `rolledBackToVersionId` pointing at the restored version, preserving a traceable audit trail of the reversal in the version timeline.

---

## 3. Search Index Compaction: How Is Non-Blocking Enforced?

**Question:** The prompt states that "index cleanup must never block read queries." MySQL's `OPTIMIZE TABLE` acquires a metadata lock on MyISAM tables and, on InnoDB, rebuilds the table in the background. With a FULLTEXT index on InnoDB, the behavior can vary by version. How should compaction be scheduled and implemented so that ongoing searches are not interrupted?

**My Understanding:** The safest option is to run compaction as an offline scheduled job during a low-traffic window rather than inline with cleanup. On MySQL 8.0 InnoDB, `OPTIMIZE TABLE` triggers an in-place rebuild, which allows concurrent reads throughout. As long as compaction is never triggered synchronously during an API request and the job runs against InnoDB (not MyISAM), reads are unaffected.

**Solution:** `IndexCompactionCommand` runs `OPTIMIZE TABLE content_search_index` as a standalone console command scheduled weekly, never invoked from within an HTTP request cycle. The `content_search_index` table uses the InnoDB storage engine (as all tables in this application do), so `OPTIMIZE TABLE` performs a non-blocking in-place rebuild on MySQL 8.0 that permits concurrent `SELECT` queries throughout. Orphan cleanup (`OrphanCleanupService`) runs in a separate daily command and deletes index rows with batched `DELETE` statements rather than a single large delete, minimizing lock contention further.

---

## 4. Deduplication Similarity Thresholds: Auto-Merge vs Review Queue Boundary

**Question:** The prompt requires "similarity thresholds to auto-merge near-duplicates," but does not define what "near" means numerically or how similarity is computed. Using too aggressive a threshold auto-merges distinct records; too conservative sends everything to a review queue that never gets processed.

**My Understanding:** A reasonable two-band model works: a high-confidence band where auto-merge is safe (around 90–95% similarity) and a lower-confidence band that routes to a human reviewer (around 80–90%), leaving truly different records as separate. The similarity metric should operate on a normalized fingerprint rather than raw content to avoid false negatives from minor formatting differences.

**Solution:** `FingerprintService` normalizes each item's title (lowercase, strip punctuation), company, location, and first 200 characters of body before hashing into a stable digest stored in `content_fingerprints`. `DedupService.findMatches()` computes trigram-based Jaccard similarity between the candidate's trigram set and each existing fingerprint's trigram set. The thresholds are defined as class constants: `THRESHOLD_AUTO_MERGE = 0.92` and `THRESHOLD_REVIEW = 0.80`. Exact hash matches short-circuit to `AUTO_MERGE` at 1.0 without running the trigram comparison. Items between 0.80 and 0.91 are placed in the `REVIEW_NEEDED` queue visible to Recruiters and Analysts via `DedupReviewController`. Items below 0.80 are imported as new records.

---

## 5. Scraping Self-Healing: Which Adverse Event Triggers Which Degradation Strategy?

**Question:** The prompt lists three self-healing responses — "degrade to metadata-only, switch source, or pause for 60 minutes" — but does not map each response to the triggering event (CAPTCHA, rate-limit, ban). The three strategies carry different performance costs: pausing a source for 60 minutes is expensive if triggered by a transient 429; degrading to metadata-only on every CAPTCHA would produce incomplete data.

**My Understanding:** The strategies should escalate with confidence of detection: a single rate-limit response could be transient, warranting a proxy switch first; a confirmed ban or CAPTCHA is more severe and warrants a longer pause. Pausing the entire source for 60 minutes should be a last resort triggered by definitive detection.

**Solution:** `SelfHealingService.evaluate()` maps event types to responses. `RATE_LIMITED` (HTTP 429) triggers a proxy switch via `ProxyRotationService` first; if all proxies have been cycled, it falls back to a 60-minute source pause. `BAN_DETECTED` (HTTP 403) triggers an immediate degradation to metadata-only extraction for the current run, then records a `SourceHealthEvent`. `CAPTCHA_DETECTED` (CAPTCHA markers found in the response body) triggers an immediate 60-minute pause on the source and switches the user-agent/header template for the next run. All events are persisted as `SourceHealthEvent` records so the `HealthEventTimeline` component can show operators the self-healing history for each source.

---

## 6. Encryption Key Rotation: What Happens to Existing Encrypted Data?

**Question:** The prompt requires encryption keys to be "rotated every 90 days." Key rotation can mean two different things: generating a new key and re-encrypting all existing ciphertext under it (full rotation), or simply activating a new key for future encryptions while retaining old keys to decrypt existing data (key versioning). Full rotation is safer but expensive; key versioning is practical but requires indefinite retention of old keys.

**My Understanding:** Full bulk re-encryption of all sensitive fields on every 90-day boundary would lock the database during a multi-hour job and introduce downtime risk. Key versioning is the standard pattern for envelope encryption: each `EncryptedFieldValue` record carries a `key_id` reference; decryption always fetches the correct key by ID, so old keys can remain active as "RETIRED" until all data encrypted under them has been re-encrypted lazily or via a background migration.

**Solution:** `EncryptionService` uses a two-tier envelope structure: a master key (from `APP_ENCRYPTION_MASTER_KEY` env var, hashed to 32 bytes) wraps individual data encryption keys (DEKs) stored in the `encryption_keys` table. Each `EncryptedFieldValue` row carries a `key_id` pointing to the DEK used at encryption time. `KeyRotationService` generates a new DEK every 90 days, marks it `ACTIVE`, and marks the previous key `RETIRED`. Retired keys are not deleted — they remain in the table for decryption of existing data. `KeyRotationCheckCommand` runs daily and triggers rotation when the active key's age exceeds 90 days. Bulk re-encryption of RETIRED-key data can be run as a separate maintenance command without blocking the API.

---

## 7. Export Watermark Embedding: Where and How Does the Watermark Appear?

**Question:** The prompt says exports must be "stamped with a visible watermark including username and export timestamp (MM/DD/YYYY, 12-hour time)." For a CSV export, there is no image layer — the watermark must be text embedded in the file itself. The question is whether it appears as a header row, a footer row, a comment line, or embedded in a separate metadata sheet.

**My Understanding:** For flat CSV files, a header comment block or a dedicated first/last row is the only practical approach. A header block before the data columns is most common and least likely to break automated import pipelines that read from the last row.

**Solution:** `CsvExportRenderer` inserts the watermark as the first line of every CSV file using a `#` comment prefix: `# EXPORTED BY: username | 04/15/2026 2:47 PM`. `ExportFileNamer` embeds the same text into the file name. The `watermark_text` field is persisted on the `ExportJob` entity at job creation time and returned in the API response, so the UI can display it in the export status view before the file is downloaded. `TamperDetectionService` computes a SHA-256 hash of the complete file (including the watermark line) at generation time; any post-generation modification changes the hash and triggers a `TAMPER_DETECTED` error on download.

---

## 8. Compliance Audit Tamper Evidence: How Is the Hash Chain Structured?

**Question:** The prompt requires "tamper-evident hashing" for compliance audit reports. A single hash of a report can be recomputed after tampering; the requirement implies a chain structure. The design question is whether the chain covers individual events (making every event tamper-evident) or only the final report artifact.

**My Understanding:** Per-event chaining is more robust: tampering with any event in the middle of the log changes all subsequent hashes, making the tampering detectable at chain verification time rather than only when a specific report is downloaded. Report-level hashing alone would not catch modifications to the underlying audit log.

**Solution:** `HashChainService.computeAndStore()` is called each time an `AuditEvent` is persisted. It computes a SHA-256 hash of the event's canonical JSON representation (`action`, `entityType`, `entityId`, `actorUsername`, `occurredAt`, `oldValues`, `newValues`). The `chain_hash` for each event is `sha256(previousChainHash + eventHash)`. The first event's `chain_hash` equals its own `event_hash` (no previous). All hash records are stored in `audit_event_hashes` with a monotonic `sequence_number`. `VerifyAuditChainCommand` re-derives every `event_hash` and `chain_hash` from scratch and compares against stored values; any mismatch in the sequence indicates a tampered or deleted event. Compliance reports generated by `ComplianceReportController` include the chain head hash so the recipient can independently verify the log segment covered by the report.

---

## 9. Scope Isolation for Content Search: How Are Global vs Store-Scoped Items Handled?

**Question:** Content items can belong to a specific store (`store_id` set), a specific region (`region_id` set, no `store_id`), or be global (neither set). When a Store Manager with access to only certain stores runs a full-text search, should they see global content, regional content for their region, or only store-specific content?

**My Understanding:** The most inclusive but still safe interpretation is: a Store Manager should see (a) content scoped to their specific stores, (b) content scoped to regions those stores belong to, and (c) global content (no store or region). Restricting them to only store-specific content would hide legitimate operational notices published at the region level.

**Solution:** `SearchService.search()` builds a dual OR condition when the user has limited scope: `(csi.store_id IN (:accessible_stores)) OR (csi.store_id IS NULL AND csi.region_id IN (:accessible_regions))`. Global content (both `store_id` and `region_id` null) passes through all scope filters because it matches neither exclusion condition. `ScopeResolver.getAccessibleStoreIds()` and `getAccessibleRegionIds()` each return `null` for ADMINISTRATOR-scoped users (meaning no filter applied) and a UUID list for store- or region-scoped users. If a scoped user has zero accessible stores and zero accessible regions, the service short-circuits to return an empty result set rather than applying a vacuous IN() clause.

---

## 10. Mutation Queue Replay Authorization: Should Offline Mutations Be Re-Validated Against Current Permissions?

**Question:** A user might queue a write while they hold a certain role, then have that role revoked before the device reconnects. When the mutation is replayed, should the backend apply the permission that existed when the mutation was created (based on the queue timestamp) or the permission that exists at replay time?

**My Understanding:** Honoring stale permissions at replay time creates a privilege-escalation path: a revoked user could pre-queue mutations before revocation and replay them after. The backend should always validate the actor's current roles and scope at replay time, not the role they held offline.

**Solution:** `MutationReplayService.replayBatch()` re-validates the actor's current roles and scope for every mutation during replay, not at queue time. For store updates it checks `rbacService.hasAnyRole(actor, [STORE_MANAGER, ADMINISTRATOR])` and `scopeResolver.canAccessStore(actor, store)`. For zone mutations it checks the Dispatcher/Store Manager/Administrator set and `scopeResolver.canAccessDeliveryZone(actor, zone)`. If the actor's role was revoked between queue time and replay, the mutation is rejected with `AccessDeniedHttpException` and recorded as `REJECTED` in `mutation_queue_log`, with the denial reason returned to the client in the batch response. This mirrors the same Voter logic that protects the synchronous endpoints, so the replay path cannot be used to bypass access controls.
