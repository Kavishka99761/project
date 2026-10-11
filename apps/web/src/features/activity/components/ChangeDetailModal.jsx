import { useState } from 'react';
import { Modal } from 'react-bootstrap';
import { ErrorState, Spinner } from '@/components/ui/States';
import { formatDateTime } from '@/lib/format';
import { useDataChange, useRecordHistory } from '../api';
import { OperationBadge, SourceLabel } from './OperationBadge';

const show = (value) => {
  if (value === null || value === undefined) return <span className="text-3">null</span>;
  if (typeof value === 'object') return <code className="small">{JSON.stringify(value)}</code>;
  const text = String(value);
  return text.length > 300 ? `${text.slice(0, 300)}…` : text;
};

/** Before / after of one recorded change, plus the record's full history. */
export function ChangeDetailModal({ changeId, onHide }) {
  const [onlyChanged, setOnlyChanged] = useState(true);
  const [tab, setTab] = useState('diff');
  const change = useDataChange(changeId);
  const c = change.data;
  const history = useRecordHistory(tab === 'history' ? c?.table : null, c?.record_id);
  const rows = (c?.diff ?? []).filter((d) => !onlyChanged || d.changed);

  return (
    <Modal show={Boolean(changeId)} onHide={onHide} size="xl" centered scrollable>
      <Modal.Header closeButton>
        <Modal.Title className="h5 d-flex align-items-center gap-2 flex-wrap">
          {c && <OperationBadge operation={c.operation} />}
          {c ? <>{c.table_label} <span className="text-3">#{c.record_id}</span></> : 'Change'}
        </Modal.Title>
      </Modal.Header>
      <Modal.Body>
        {change.isLoading && <div className="text-center py-5"><Spinner size="lg" /></div>}
        {change.isError && <ErrorState error={change.error} onRetry={change.refetch} />}
        {c && (
          <>
            <div className="row g-2 small mb-3">
              <div className="col-sm-6 col-lg-3"><div className="text-3">When</div><div className="fw-semibold">{formatDateTime(c.changed_at)}</div></div>
              <div className="col-sm-6 col-lg-3"><div className="text-3">Source</div><div className="fw-semibold"><SourceLabel change={c} /></div></div>
              <div className="col-sm-6 col-lg-3"><div className="text-3">API action</div><div className="fw-semibold">{c.activity?.description ?? c.activity?.action ?? '—'}</div></div>
              <div className="col-sm-6 col-lg-3"><div className="text-3">Audit id</div><div className="fw-semibold">#{c.id}{c.request_id && <span className="text-3"> · req {c.request_id.slice(0, 8)}</span>}</div></div>
            </div>

            <div className="d-flex flex-wrap gap-2 align-items-center mb-2">
              <div className="btn-group btn-group-sm" role="tablist">
                <button type="button" className={`btn btn-glass ${tab === 'diff' ? 'active' : ''}`} onClick={() => setTab('diff')}>This change</button>
                <button type="button" className={`btn btn-glass ${tab === 'history' ? 'active' : ''}`} onClick={() => setTab('history')}>Full record history</button>
              </div>
              {tab === 'diff' && c.operation === 'update' && (
                <div className="form-check form-switch ms-auto mb-0">
                  <input className="form-check-input" type="checkbox" id="only-changed" checked={onlyChanged} onChange={(e) => setOnlyChanged(e.target.checked)} />
                  <label className="form-check-label small" htmlFor="only-changed">Only changed fields</label>
                </div>
              )}
            </div>

            {tab === 'diff' ? (
              <div className="table-responsive">
                <table className="table table-glass align-middle audit-diff">
                  <thead>
                    <tr>
                      <th style={{ width: '22%' }}>Field</th>
                      {c.operation !== 'insert' && <th>Before</th>}
                      {c.operation !== 'delete' && <th>After</th>}
                    </tr>
                  </thead>
                  <tbody>
                    {rows.map((d) => (
                      <tr key={d.field} className={c.operation === 'update' && d.changed ? 'is-changed' : ''}>
                        <td className="fw-semibold font-monospace small">{d.field}</td>
                        {c.operation !== 'insert' && <td className="text-break old">{show(d.old)}</td>}
                        {c.operation !== 'delete' && <td className="text-break new">{show(d.new)}</td>}
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : (
              <>
                {history.isLoading && <div className="text-center py-4"><Spinner /></div>}
                <ol className="audit-timeline">
                  {(history.data ?? []).map((h) => (
                    <li key={h.id} className={h.id === c.id ? 'current' : ''}>
                      <div className="d-flex flex-wrap align-items-center gap-2">
                        <OperationBadge operation={h.operation} />
                        <span className="fw-semibold">{formatDateTime(h.changed_at)}</span>
                        <span className="text-3 small"><SourceLabel change={h} /></span>
                      </div>
                      <div className="small text-2 mt-1">
                        {h.operation === 'update'
                          ? h.diff.filter((d) => d.changed).map((d) => <div key={d.field}><code>{d.field}</code>: {show(d.old)} → {show(d.new)}</div>)
                          : h.summary}
                      </div>
                    </li>
                  ))}
                </ol>
              </>
            )}
          </>
        )}
      </Modal.Body>
    </Modal>
  );
}
