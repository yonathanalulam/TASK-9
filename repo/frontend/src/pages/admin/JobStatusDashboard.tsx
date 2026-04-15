/* ------------------------------------------------------------------ */
/*  JobStatusDashboard — placeholder for background job monitoring     */
/* ------------------------------------------------------------------ */

import { useState } from 'react';

interface Job {
  id: string;
  type: string;
  status: 'PENDING' | 'RUNNING' | 'COMPLETED' | 'FAILED';
  createdAt: string;
  completedAt: string | null;
  error: string | null;
}

const statusBadge: Record<string, string> = {
  PENDING: 'badge badge-info',
  RUNNING: 'badge badge-warning',
  COMPLETED: 'badge badge-success',
  FAILED: 'badge badge-danger',
};

export default function JobStatusDashboard() {
  // Placeholder data — will be replaced with real API integration
  const [jobs] = useState<Job[]>([]);

  return (
    <div>
      <div className="page-header">
        <h1>Background Jobs</h1>
      </div>

      <div className="card" style={{ padding: 0, overflow: 'hidden' }}>
        <table>
          <thead>
            <tr>
              <th>Job ID</th>
              <th>Type</th>
              <th style={{ width: 110 }}>Status</th>
              <th style={{ width: 160 }}>Created</th>
              <th style={{ width: 160 }}>Completed</th>
              <th>Error</th>
            </tr>
          </thead>
          <tbody>
            {jobs.length === 0 ? (
              <tr>
                <td
                  colSpan={6}
                  style={{ textAlign: 'center', padding: 32, color: 'var(--color-text-muted)' }}
                >
                  No background jobs found. Job monitoring will be available when the
                  backend job runner is integrated.
                </td>
              </tr>
            ) : (
              jobs.map((job) => (
                <tr key={job.id}>
                  <td>
                    <code style={{ fontSize: 12 }}>{job.id.slice(0, 12)}...</code>
                  </td>
                  <td>{job.type}</td>
                  <td>
                    <span className={statusBadge[job.status] ?? 'badge'}>
                      {job.status}
                    </span>
                  </td>
                  <td>{new Date(job.createdAt).toLocaleString()}</td>
                  <td>{job.completedAt ? new Date(job.completedAt).toLocaleString() : '-'}</td>
                  <td style={{ color: 'var(--color-danger)', fontSize: 13 }}>
                    {job.error ?? '-'}
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
