import { useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { PageHeader } from '@/components/layout/PageHeader';
import { StatusBadge } from '@/components/ui/Badges';
import { ExportMenu } from '@/components/ui/ExportMenu';
import { GlassCard } from '@/components/ui/GlassCard';
import { Pagination } from '@/components/ui/Pagination';
import { SegmentedControl } from '@/components/ui/SegmentedControl';
import { StatTile } from '@/components/ui/StatTile';
import { EmptyState, ErrorState, Skeleton } from '@/components/ui/States';
import { useDebounce } from '@/hooks/useDebounce';
import { formatDateTime, relative } from '@/lib/format';
import { useActivity, useDataChangeStats, useDataChanges } from './api';
import { ChangeDetailModal } from './components/ChangeDetailModal';
import { OperationBadge, SourceLabel } from './components/OperationBadge';

const TABS = [
  { value: 'changes', label: 'Database changes', icon: 'database' },
  { value: 'requests', label: 'API requests', icon: 'arrow-left-right' },
];

/**
 * Audit trail. "Database changes" lists every INSERT / UPDATE / DELETE that
 * the SQL Server triggers recorded in dbo.data_change_log; "API requests"
 * lists every action from activity_logs. Both are filterable and exportable.
 */
export default function ActivityPage() {
  const [params, setParams] = useSearchParams();
  const tab = params.get('tab') === 'requests' ? 'requests' : 'changes';

  return (
    <>
      <PageHeader
        title="Activity log"
        subtitle="Every create, update and delete is saved in the SQL Server database — browse, filter and export it here."
        actions={(
          <ExportMenu
            url={tab === 'changes' ? '/exports/data_changes' : '/exports/activity'}
            label={tab === 'changes' ? 'Export changes' : 'Export requests'}
          />
        )}
      />
      <div className="mb-3">
        <SegmentedControl options={TABS} value={tab} onChange={(value) => setParams({ tab: value })} ariaLabel="Audit view" />
      </div>
      {tab === 'changes' ? <DataChangesTab /> : <RequestsTab />}
    </>
  );
}

function DataChangesTab() {
  const [filters, setFilters] = useState({ operation: '', table: '', from: '', to: '', page: 1 });
  const [search, setSearch] = useState('');
  const [openId, setOpenId] = useState(null);
  const q = useDebounce(search, 350);
  const stats = useDataChangeStats();
  const changes = useDataChanges({ ...filters, q, per_page: 25 });
  const set = (key, value) => setFilters((f) => ({ ...f, [key]: value, page: key === 'page' ? value : 1 }));
  const s = stats.data;
  const rows = changes.data?.data ?? [];

  return (
    <>
      <div className="row g-3 mb-3">
        <div className="col-6 col-lg-3"><StatTile label="All changes" value={s?.total ?? 0} icon="database-check" /></div>
        <div className="col-6 col-lg-3"><StatTile label="Created" value={s?.by_operation.insert ?? 0} icon="plus-circle" delay={0.04} /></div>
        <div className="col-6 col-lg-3"><StatTile label="Updated" value={s?.by_operation.update ?? 0} icon="pencil" delay={0.08} /></div>
        <div className="col-6 col-lg-3"><StatTile label="Deleted" value={s?.by_operation.delete ?? 0} icon="trash3" delay={0.12} /></div>
      </div>

      <GlassCard variant="solid" className="p-3 mb-3">
        <div className="row g-2 align-items-end">
          <div className="col-12 col-xl-4">
            <label className="form-label small" htmlFor="ch-op">Operation</label>
            <SegmentedControl
              size="sm"
              ariaLabel="Operation"
              value={filters.operation}
              onChange={(value) => set('operation', value)}
              options={[
                { value: '', label: 'All' },
                { value: 'insert', label: 'Created', icon: 'plus-circle' },
                { value: 'update', label: 'Updated', icon: 'pencil' },
                { value: 'delete', label: 'Deleted', icon: 'trash3' },
              ]}
            />
          </div>
          <div className="col-6 col-md-3 col-xl-2">
            <label className="form-label small" htmlFor="ch-table">Table</label>
            <select id="ch-table" className="form-select form-select-sm" value={filters.table} onChange={(e) => set('table', e.target.value)}>
              <option value="">All tables</option>
              {(s?.by_table ?? []).map((t) => <option key={t.table} value={t.table}>{t.table} ({t.total})</option>)}
            </select>
          </div>
          <div className="col-6 col-md-3 col-xl-2">
            <label className="form-label small" htmlFor="ch-from">From</label>
            <input id="ch-from" type="date" className="form-control form-control-sm" value={filters.from} onChange={(e) => set('from', e.target.value)} />
          </div>
          <div className="col-6 col-md-3 col-xl-2">
            <label className="form-label small" htmlFor="ch-to">To</label>
            <input id="ch-to" type="date" className="form-control form-control-sm" value={filters.to} onChange={(e) => set('to', e.target.value)} />
          </div>
          <div className="col-6 col-md-3 col-xl-2">
            <label className="form-label small" htmlFor="ch-q">Search values</label>
            <input id="ch-q" type="search" className="form-control form-control-sm" placeholder="e.g. Database" value={search} onChange={(e) => { setSearch(e.target.value); set('page', 1); }} />
          </div>
        </div>
      </GlassCard>

      {changes.isError ? <ErrorState error={changes.error} onRetry={changes.refetch} /> : (
        <GlassCard variant="solid" className="p-2">
          <div className="table-responsive">
            <table className="table table-glass table-hover align-middle mb-0">
              <thead>
                <tr><th>When</th><th>Operation</th><th>Table</th><th className="num">Record</th><th>What changed</th><th>Source</th><th>Action</th></tr>
              </thead>
              <tbody>
                {changes.isLoading && Array.from({ length: 6 }, (_, i) => <tr key={i}><td colSpan={7}><Skeleton height={18} /></td></tr>)}
                {rows.map((c) => (
                  <tr key={c.id} className="cursor-pointer" tabIndex={0} onClick={() => setOpenId(c.id)} onKeyDown={(e) => e.key === 'Enter' && setOpenId(c.id)}>
                    <td className="text-nowrap" title={formatDateTime(c.changed_at)}>{relative(c.changed_at)}</td>
                    <td><OperationBadge operation={c.operation} /></td>
                    <td className="font-monospace small">{c.table}</td>
                    <td className="num">{c.record_id}</td>
                    <td className="text-truncate" style={{ maxWidth: 320 }}>{c.summary}</td>
                    <td className="small"><SourceLabel change={c} /></td>
                    <td className="small text-2 text-truncate" style={{ maxWidth: 200 }}>{c.activity?.action ?? '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {!changes.isLoading && rows.length === 0 && (
            <EmptyState icon="database" title="No changes match">Create, edit or delete anything in AcadeAlert and it appears here instantly.</EmptyState>
          )}
        </GlassCard>
      )}
      <Pagination meta={changes.data?.meta} onPage={(page) => set('page', page)} />
      <ChangeDetailModal changeId={openId} onHide={() => setOpenId(null)} />
    </>
  );
}

function RequestsTab() {
  const [filters, setFilters] = useState({ outcome: '', page: 1 });
  const [search, setSearch] = useState('');
  const q = useDebounce(search, 350);
  const activity = useActivity({ ...filters, q, per_page: 25 });
  const set = (key, value) => setFilters((f) => ({ ...f, [key]: value, page: key === 'page' ? value : 1 }));
  const rows = activity.data?.data ?? [];

  return (
    <>
      <GlassCard variant="solid" className="p-3 mb-3">
        <div className="d-flex flex-wrap gap-2 align-items-center">
          <SegmentedControl
            size="sm"
            ariaLabel="Outcome"
            value={filters.outcome}
            onChange={(value) => set('outcome', value)}
            options={[{ value: '', label: 'All' }, { value: 'success', label: 'Succeeded', icon: 'check2' }, { value: 'failed', label: 'Failed', icon: 'x-lg' }]}
          />
          <input type="search" className="form-control form-control-sm ms-auto" style={{ maxWidth: 260 }} placeholder="Search descriptions" aria-label="Search descriptions" value={search} onChange={(e) => setSearch(e.target.value)} />
        </div>
      </GlassCard>
      {activity.isError ? <ErrorState error={activity.error} onRetry={activity.refetch} /> : (
        <GlassCard variant="solid" className="p-2">
          <div className="table-responsive">
            <table className="table table-glass table-hover align-middle mb-0">
              <thead>
                <tr><th>When</th><th>Module</th><th>Action</th><th>Description</th><th>Method</th><th className="num">Status</th><th className="num">Time</th></tr>
              </thead>
              <tbody>
                {activity.isLoading && Array.from({ length: 6 }, (_, i) => <tr key={i}><td colSpan={7}><Skeleton height={18} /></td></tr>)}
                {rows.map((a) => (
                  <tr key={a.id}>
                    <td className="text-nowrap" title={formatDateTime(a.created_at)}>{relative(a.created_at)}</td>
                    <td>{a.module_label}</td>
                    <td className="font-monospace small">{a.action}</td>
                    <td className="text-truncate" style={{ maxWidth: 360 }}>{a.description}</td>
                    <td><span className="font-monospace small">{a.method}</span></td>
                    <td className="num">
                      {a.status_code
                        ? <StatusBadge status={a.status_code < 400 ? 'good' : 'critical'}>{a.status_code}</StatusBadge>
                        : '—'}
                    </td>
                    <td className="num">{a.duration_ms != null ? `${a.duration_ms} ms` : '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {!activity.isLoading && rows.length === 0 && <EmptyState icon="activity" title="No requests match" />}
        </GlassCard>
      )}
      <Pagination meta={activity.data?.meta} onPage={(page) => set('page', page)} />
    </>
  );
}
