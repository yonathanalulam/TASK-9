import { NavLink } from 'react-router-dom';
import { useAuthStore } from '@/stores/authStore';

const linkStyle: React.CSSProperties = {
  display: 'block',
  padding: '10px 20px',
  color: 'var(--color-sidebar-text)',
  textDecoration: 'none',
  fontSize: '14px',
  borderLeft: '3px solid transparent',
  transition: 'background 0.15s, border-color 0.15s',
};

const activeLinkStyle: React.CSSProperties = {
  ...linkStyle,
  background: 'rgba(255,255,255,0.08)',
  borderLeftColor: 'var(--color-sidebar-active)',
  color: '#fff',
};

export default function Sidebar() {
  const roles = useAuthStore((s) => s.roles);
  const isAdmin = roles.some((r) => r.role === 'admin');

  return (
    <nav
      style={{
        width: 220,
        minHeight: '100vh',
        background: 'var(--color-sidebar)',
        display: 'flex',
        flexDirection: 'column',
        flexShrink: 0,
      }}
    >
      <div
        style={{
          padding: '20px',
          fontSize: '18px',
          fontWeight: 700,
          color: '#fff',
          borderBottom: '1px solid rgba(255,255,255,0.1)',
          letterSpacing: '0.02em',
        }}
      >
        Meridian
      </div>

      <div style={{ marginTop: 12, display: 'flex', flexDirection: 'column', gap: 2 }}>
        <NavLink to="/" end style={({ isActive }) => (isActive ? activeLinkStyle : linkStyle)}>
          Dashboard
        </NavLink>

        {isAdmin && (
          <NavLink to="/users" style={({ isActive }) => (isActive ? activeLinkStyle : linkStyle)}>
            Users
          </NavLink>
        )}

        <NavLink to="/regions" style={({ isActive }) => (isActive ? activeLinkStyle : linkStyle)}>
          Regions
        </NavLink>

        <NavLink to="/stores" style={({ isActive }) => (isActive ? activeLinkStyle : linkStyle)}>
          Stores
        </NavLink>

        <NavLink to="/content" style={({ isActive }) => (isActive ? activeLinkStyle : linkStyle)}>
          Content
        </NavLink>

        <NavLink to="/search" style={({ isActive }) => (isActive ? activeLinkStyle : linkStyle)}>
          Search
        </NavLink>
      </div>

      {/* Import section */}
      <div
        style={{
          padding: '16px 20px 6px',
          fontSize: 11,
          fontWeight: 600,
          textTransform: 'uppercase',
          letterSpacing: '0.08em',
          color: 'rgba(255,255,255,0.4)',
        }}
      >
        Import
      </div>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
        <NavLink
          to="/imports"
          end
          style={({ isActive }) => (isActive ? activeLinkStyle : linkStyle)}
        >
          Batches
        </NavLink>
        <NavLink
          to="/imports/upload"
          style={({ isActive }) => (isActive ? activeLinkStyle : linkStyle)}
        >
          Upload
        </NavLink>
        <NavLink
          to="/dedup/review"
          style={({ isActive }) => (isActive ? activeLinkStyle : linkStyle)}
        >
          Dedup Review
        </NavLink>
      </div>

      {/* Governance section */}
      <div
        style={{
          padding: '16px 20px 6px',
          fontSize: 11,
          fontWeight: 600,
          textTransform: 'uppercase',
          letterSpacing: '0.08em',
          color: 'rgba(255,255,255,0.4)',
        }}
      >
        Governance
      </div>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
        <NavLink
          to="/governance/classifications"
          style={({ isActive }) => (isActive ? activeLinkStyle : linkStyle)}
        >
          Classifications
        </NavLink>
        <NavLink
          to="/governance/consent"
          style={({ isActive }) => (isActive ? activeLinkStyle : linkStyle)}
        >
          Consent
        </NavLink>
        <NavLink
          to="/governance/retention"
          style={({ isActive }) => (isActive ? activeLinkStyle : linkStyle)}
        >
          Retention
        </NavLink>
      </div>

      {/* Exports section */}
      <div
        style={{
          padding: '16px 20px 6px',
          fontSize: 11,
          fontWeight: 600,
          textTransform: 'uppercase',
          letterSpacing: '0.08em',
          color: 'rgba(255,255,255,0.4)',
        }}
      >
        Exports
      </div>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
        <NavLink
          to="/exports"
          end
          style={({ isActive }) => (isActive ? activeLinkStyle : linkStyle)}
        >
          Exports
        </NavLink>
        <NavLink
          to="/exports/new"
          style={({ isActive }) => (isActive ? activeLinkStyle : linkStyle)}
        >
          New Export
        </NavLink>
        <NavLink
          to="/compliance-reports"
          style={({ isActive }) => (isActive ? activeLinkStyle : linkStyle)}
        >
          Compliance Reports
        </NavLink>
      </div>

      {/* Analytics section — Phase 5 */}
      <div
        style={{
          padding: '16px 20px 6px',
          fontSize: 11,
          fontWeight: 600,
          textTransform: 'uppercase',
          letterSpacing: '0.08em',
          color: 'rgba(255,255,255,0.4)',
        }}
      >
        Analytics
      </div>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
        <NavLink
          to="/analytics"
          end
          style={({ isActive }) => (isActive ? activeLinkStyle : linkStyle)}
        >
          Dashboard
        </NavLink>
        <NavLink
          to="/analytics/sales"
          style={({ isActive }) => (isActive ? activeLinkStyle : linkStyle)}
        >
          Sales
        </NavLink>
        <NavLink
          to="/analytics/trends"
          style={({ isActive }) => (isActive ? activeLinkStyle : linkStyle)}
        >
          Trends
        </NavLink>
        <NavLink
          to="/analytics/content"
          style={({ isActive }) => (isActive ? activeLinkStyle : linkStyle)}
        >
          Content
        </NavLink>
        <NavLink
          to="/analytics/compliance"
          style={({ isActive }) => (isActive ? activeLinkStyle : linkStyle)}
        >
          Compliance
        </NavLink>
      </div>

      {/* Scraping section — Phase 5 */}
      <div
        style={{
          padding: '16px 20px 6px',
          fontSize: 11,
          fontWeight: 600,
          textTransform: 'uppercase',
          letterSpacing: '0.08em',
          color: 'rgba(255,255,255,0.4)',
        }}
      >
        Scraping
      </div>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
        <NavLink
          to="/scraping/sources"
          style={({ isActive }) => (isActive ? activeLinkStyle : linkStyle)}
        >
          Sources
        </NavLink>
        <NavLink
          to="/scraping/health"
          style={({ isActive }) => (isActive ? activeLinkStyle : linkStyle)}
        >
          Health Dashboard
        </NavLink>
      </div>

      {/* Admin section */}
      {isAdmin && (
        <>
          <div
            style={{
              padding: '16px 20px 6px',
              fontSize: 11,
              fontWeight: 600,
              textTransform: 'uppercase',
              letterSpacing: '0.08em',
              color: 'rgba(255,255,255,0.4)',
            }}
          >
            Admin
          </div>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
            <NavLink
              to="/admin/boundaries"
              style={({ isActive }) => (isActive ? activeLinkStyle : linkStyle)}
            >
              Boundaries
            </NavLink>
            <NavLink
              to="/admin/mutations"
              style={({ isActive }) => (isActive ? activeLinkStyle : linkStyle)}
            >
              Mutation Queue
            </NavLink>
            <NavLink
              to="/admin/jobs"
              style={({ isActive }) => (isActive ? activeLinkStyle : linkStyle)}
            >
              Jobs
            </NavLink>
            <NavLink
              to="/admin/warehouse"
              style={({ isActive }) => (isActive ? activeLinkStyle : linkStyle)}
            >
              Warehouse Loads
            </NavLink>
          </div>
        </>
      )}
    </nav>
  );
}
