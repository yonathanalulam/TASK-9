import { useEffect } from 'react';
import { Routes, Route } from 'react-router-dom';
import { useAuthStore } from '@/stores/authStore';
import ProtectedRoute from '@/components/common/ProtectedRoute';
import LoginPage from '@/pages/LoginPage';
import DashboardPage from '@/pages/DashboardPage';
import UserListPage from '@/pages/users/UserListPage';
import UserDetailPage from '@/pages/users/UserDetailPage';
import RegionListPage from '@/pages/regions/RegionListPage';
import RegionDetailPage from '@/pages/regions/RegionDetailPage';
import StoreListPage from '@/pages/stores/StoreListPage';
import StoreDetailPage from '@/pages/stores/StoreDetailPage';
import ZoneListPage from '@/pages/delivery-zones/ZoneListPage';
import ZoneDetailPage from '@/pages/delivery-zones/ZoneDetailPage';
import BoundaryImportsPage from '@/pages/admin/BoundaryImportsPage';
import BoundaryUploadPage from '@/pages/admin/BoundaryUploadPage';
import BoundaryDetailPage from '@/pages/admin/BoundaryDetailPage';
import MutationQueueDashboard from '@/pages/admin/MutationQueueDashboard';
import JobStatusDashboard from '@/pages/admin/JobStatusDashboard';
import ContentListPage from '@/pages/content/ContentListPage';
import ContentCreatePage from '@/pages/content/ContentCreatePage';
import ContentDetailPage from '@/pages/content/ContentDetailPage';
import ContentEditPage from '@/pages/content/ContentEditPage';
import SearchPage from '@/pages/search/SearchPage';

// Phase 4 — Import & Dedup
import ImportBatchListPage from '@/pages/import/ImportBatchListPage';
import ImportUploadPage from '@/pages/import/ImportUploadPage';
import ImportBatchDetailPage from '@/pages/import/ImportBatchDetailPage';
import DedupReviewPage from '@/pages/import/DedupReviewPage';

// Phase 4 — Governance
import DataClassificationPage from '@/pages/governance/DataClassificationPage';
import ConsentDashboard from '@/pages/governance/ConsentDashboard';
import RetentionDashboard from '@/pages/governance/RetentionDashboard';

// Phase 4 — Exports & Compliance
import ExportListPage from '@/pages/exports/ExportListPage';
import ExportRequestPage from '@/pages/exports/ExportRequestPage';
import ComplianceReportsPage from '@/pages/exports/ComplianceReportsPage';

// Phase 5 — Analytics
import AnalyticsDashboard from '@/pages/analytics/AnalyticsDashboard';
import SalesByProductPage from '@/pages/analytics/SalesByProductPage';
import SalesTrendsPage from '@/pages/analytics/SalesTrendsPage';
import ContentAnalyticsPage from '@/pages/analytics/ContentAnalyticsPage';
import ComplianceMetricsPage from '@/pages/analytics/ComplianceMetricsPage';

// Phase 5 — Warehouse
import WarehouseLoadsPage from '@/pages/admin/WarehouseLoadsPage';

// Phase 5 — Scraping
import SourceListPage from '@/pages/scraping/SourceListPage';
import SourceDetailPage from '@/pages/scraping/SourceDetailPage';
import SourceHealthDashboard from '@/pages/scraping/SourceHealthDashboard';
import ScrapeRunDetailPage from '@/pages/scraping/ScrapeRunDetailPage';

export default function App() {
  const initialize = useAuthStore((s) => s.initialize);

  useEffect(() => {
    initialize();
  }, [initialize]);

  return (
    <Routes>
      {/* Public route */}
      <Route path="/login" element={<LoginPage />} />

      {/* Protected routes — wrapped in AppShell via ProtectedRoute */}
      <Route element={<ProtectedRoute />}>
        <Route path="/" element={<DashboardPage />} />
        <Route path="/users" element={<UserListPage />} />
        <Route path="/users/:id" element={<UserDetailPage />} />
        <Route path="/regions" element={<RegionListPage />} />
        <Route path="/regions/:id" element={<RegionDetailPage />} />
        <Route path="/stores" element={<StoreListPage />} />
        <Route path="/stores/:id" element={<StoreDetailPage />} />
        <Route path="/stores/:storeId/zones" element={<ZoneListPage />} />
        <Route path="/zones/:id" element={<ZoneDetailPage />} />

        {/* Content — Phase 3 */}
        <Route path="/content" element={<ContentListPage />} />
        <Route path="/content/new" element={<ContentCreatePage />} />
        <Route path="/content/:id" element={<ContentDetailPage />} />
        <Route path="/content/:id/edit" element={<ContentEditPage />} />
        <Route path="/search" element={<SearchPage />} />

        {/* Admin — Phase 2 */}
        <Route path="/admin/boundaries" element={<BoundaryImportsPage />} />
        <Route path="/admin/boundaries/upload" element={<BoundaryUploadPage />} />
        <Route path="/admin/boundaries/:id" element={<BoundaryDetailPage />} />
        <Route path="/admin/mutations" element={<MutationQueueDashboard />} />
        <Route path="/admin/jobs" element={<JobStatusDashboard />} />

        {/* Import & Dedup — Phase 4 */}
        <Route path="/imports" element={<ImportBatchListPage />} />
        <Route path="/imports/upload" element={<ImportUploadPage />} />
        <Route path="/imports/:id" element={<ImportBatchDetailPage />} />
        <Route path="/dedup/review" element={<DedupReviewPage />} />

        {/* Governance — Phase 4 */}
        <Route path="/governance/classifications" element={<DataClassificationPage />} />
        <Route path="/governance/consent" element={<ConsentDashboard />} />
        <Route path="/governance/retention" element={<RetentionDashboard />} />

        {/* Exports & Compliance — Phase 4 */}
        <Route path="/exports" element={<ExportListPage />} />
        <Route path="/exports/new" element={<ExportRequestPage />} />
        <Route path="/compliance-reports" element={<ComplianceReportsPage />} />

        {/* Analytics — Phase 5 */}
        <Route path="/analytics" element={<AnalyticsDashboard />} />
        <Route path="/analytics/sales" element={<SalesByProductPage />} />
        <Route path="/analytics/trends" element={<SalesTrendsPage />} />
        <Route path="/analytics/content" element={<ContentAnalyticsPage />} />
        <Route path="/analytics/compliance" element={<ComplianceMetricsPage />} />

        {/* Warehouse — Phase 5 */}
        <Route path="/admin/warehouse" element={<WarehouseLoadsPage />} />

        {/* Scraping — Phase 5 */}
        <Route path="/scraping/sources" element={<SourceListPage />} />
        <Route path="/scraping/sources/:id" element={<SourceDetailPage />} />
        <Route path="/scraping/health" element={<SourceHealthDashboard />} />
        <Route path="/scraping/runs/:id" element={<ScrapeRunDetailPage />} />
      </Route>
    </Routes>
  );
}
