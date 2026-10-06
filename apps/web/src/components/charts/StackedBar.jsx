/**
 * Single part-to-whole bar with 2 px surface gaps and a legend (labels are
 * never colour-only: each key carries its label and value).
 */
export function StackedBar({ segments, format = (v) => v }) {
  const total = segments.reduce((sum, s) => sum + s.value, 0);

  return (
    <div>
      <div className="stacked-bar" role="img" aria-label={segments.map((s) => `${s.label}: ${format(s.value)}`).join(', ')}>
        {total === 0 ? (
          <div className="seg" style={{ flexGrow: 1, background: 'var(--es-chart-grid)' }} />
        ) : (
          segments.filter((s) => s.value > 0).map((s) => (
            <div key={s.label} className="seg" style={{ flexGrow: s.value, background: s.color }} title={`${s.label}: ${format(s.value)}`} />
          ))
        )}
      </div>
      <div className="chart-legend mt-2">
        {segments.map((s) => (
          <span key={s.label} className="key">
            <span className="key-rect" style={{ background: s.color }} />
            {s.icon && <i className={`bi bi-${s.icon}`} aria-hidden="true" />}
            {s.label} <strong className="ms-1" style={{ color: 'var(--es-chart-ink)' }}>{format(s.value)}</strong>
          </span>
        ))}
      </div>
    </div>
  );
}
