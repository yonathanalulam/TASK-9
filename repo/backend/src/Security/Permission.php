<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Centralised permission constants for the Meridian platform.
 *
 * Every permission string referenced by voters, controllers, or service-layer
 * authorisation checks MUST be defined here so that typos are caught at
 * compile-time rather than at runtime.
 */
final class Permission
{
    // ---- Content ----
    public const string CONTENT_VIEW = 'CONTENT_VIEW';
    public const string CONTENT_CREATE = 'CONTENT_CREATE';
    public const string CONTENT_EDIT = 'CONTENT_EDIT';
    public const string CONTENT_PUBLISH = 'CONTENT_PUBLISH';
    public const string CONTENT_ARCHIVE = 'CONTENT_ARCHIVE';
    public const string CONTENT_ROLLBACK = 'CONTENT_ROLLBACK';

    // ---- Search ----
    public const string SEARCH_EXECUTE = 'SEARCH_EXECUTE';

    // ---- Import / Dedup ----
    public const string IMPORT_CREATE = 'IMPORT_CREATE';
    public const string IMPORT_VIEW = 'IMPORT_VIEW';
    public const string DEDUP_REVIEW = 'DEDUP_REVIEW';
    public const string DEDUP_MERGE = 'DEDUP_MERGE';
    public const string DEDUP_UNMERGE = 'DEDUP_UNMERGE';

    // ---- Export ----
    public const string EXPORT_REQUEST = 'EXPORT_REQUEST';
    public const string EXPORT_AUTHORIZE = 'EXPORT_AUTHORIZE';
    public const string EXPORT_VIEW = 'EXPORT_VIEW';
    public const string EXPORT_DOWNLOAD = 'EXPORT_DOWNLOAD';

    // ---- Compliance ----
    public const string COMPLIANCE_VIEW = 'COMPLIANCE_VIEW';
    public const string COMPLIANCE_MANAGE = 'COMPLIANCE_MANAGE';
    public const string COMPLIANCE_REPORT_GENERATE = 'COMPLIANCE_REPORT_GENERATE';

    // ---- Classification ----
    public const string CLASSIFICATION_VIEW = 'CLASSIFICATION_VIEW';
    public const string CLASSIFICATION_MANAGE = 'CLASSIFICATION_MANAGE';

    // ---- Analytics ----
    public const string ANALYTICS_VIEW = 'ANALYTICS_VIEW';

    // ---- Warehouse ----
    public const string WAREHOUSE_VIEW = 'WAREHOUSE_VIEW';
    public const string WAREHOUSE_TRIGGER = 'WAREHOUSE_TRIGGER';

    // ---- Mutation Queue ----
    public const string MUTATION_REPLAY = 'MUTATION_REPLAY';
    public const string MUTATION_VIEW_ADMIN = 'MUTATION_VIEW_ADMIN';

    // ---- Scraping ----
    public const string SCRAPING_VIEW = 'SCRAPING_VIEW';
    public const string SCRAPING_MANAGE = 'SCRAPING_MANAGE';
    public const string SCRAPING_TRIGGER = 'SCRAPING_TRIGGER';

    // ---- Store ----
    public const string STORE_VIEW = 'STORE_VIEW';
    public const string STORE_EDIT = 'STORE_EDIT';
    public const string STORE_CREATE = 'STORE_CREATE';

    // ---- Delivery Zone ----
    public const string ZONE_VIEW = 'ZONE_VIEW';
    public const string ZONE_EDIT = 'ZONE_EDIT';
    public const string ZONE_CREATE = 'ZONE_CREATE';

    // ---- Region ----
    public const string REGION_VIEW = 'REGION_VIEW';
    public const string REGION_EDIT = 'REGION_EDIT';
    public const string REGION_CREATE = 'REGION_CREATE';
    public const string REGION_CLOSE = 'REGION_CLOSE';

    // ---- User Management ----
    public const string USER_VIEW = 'USER_VIEW';
    public const string USER_CREATE = 'USER_CREATE';
    public const string USER_EDIT = 'USER_EDIT';
    public const string USER_DEACTIVATE = 'USER_DEACTIVATE';
    public const string ROLE_ASSIGN = 'ROLE_ASSIGN';
    public const string ROLE_REVOKE = 'ROLE_REVOKE';

    /** @codeCoverageIgnore */
    private function __construct()
    {
        // Static-only class -- prevent instantiation.
    }
}
