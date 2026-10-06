import { useState } from 'react';
import { GlassCard } from '@/components/ui/GlassCard';

/**
 * Chart container: title, legend (rect keys for bars, line keys for lines),
 * actions and the accessible table-view twin of every chart.
 */
export function ChartCard({ title, subtitle, legend, table, actions, children, refetching = false, className = '', delay = 0 }) {
  const [view, setView] = useState('chart');

  return (
    <GlassCard variant="solid" className={`chart-card h-100 ${className}`} delay={delay}>
      <div className="chart-head">
        <div className="min-w-0">
          <h3 className="chart-title">{title}</h3>
          {subtitle && <div className="chart-sub">{subtitle}</div>}
        </div>
        <div className="d-flex align-items-center gap-2">
          {actions}
          {table && (
            <button
              type="button"
              className="btn btn-ghost btn-sm btn-icon"
              onClick={() => setView(view === 'chart' ? 'table' : 'chart')}
              aria-pressed={view === 'table'}
              title={view === 'chart' ? 'Show as table' : 'Show chart'}
            >
              <i className={`bi bi-${view === 'chart' ? 'table' : 'bar-chart-line'}`} aria-hidden="true" />
              <span className="visually-hidden">{view === 'chart' ? 'Show as table' : 'Show chart'}</span>
            </button>
          )}
        </div>
      </div>

      {legend?.length > 1 && view === 'chart' && (
        <div className="chart-legend mb-2">
          {legend.map((item) => (
            <span className="key" key={item.label}>
              <span className={item.type === 'line' ? 'key-line' : 'key-rect'} style={{ background: item.color }} />
              {item.label}
            </span>
          ))}
        </div>
      )}

      {view === 'chart' ? (
        <div className={`chart-body ${refetching ? 'refetching' : ''}`}>{children}</div>
      ) : (
        <div className="table-responsive" style={{ maxHeight: 320 }}>
          <table className="table table-glass table-sm">
            <thead>
              <tr>{table.columns.map((column, i) => <th key={column} className={i > 0 ? 'num' : ''}>{column}</th>)}</tr>
            </thead>
            <tbody>
              {table.rows.map((row, r) => (
                <tr key={r}>{row.map((cell, i) => <td key={i} className={i > 0 ? 'num' : ''}>{cell}</td>)}</tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </GlassCard>
  );
}
