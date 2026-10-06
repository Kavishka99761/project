/** Empty, loading and error states. */

export function EmptyState({ icon = 'inbox', title, children, action, className = '' }) {
  return (
    <div className={`empty-state ${className}`}>
      <div className="empty-icon"><i className={`bi bi-${icon}`} aria-hidden="true" /></div>
      {title && <h4>{title}</h4>}
      {children && <p className="mb-3 small text-2 mx-auto" style={{ maxWidth: 420 }}>{children}</p>}
      {action}
    </div>
  );
}

export function Skeleton({ height = 16, width = '100%', className = '', rounded }) {
  return <div className={`skeleton ${className}`} style={{ height, width, borderRadius: rounded }} aria-hidden="true" />;
}

export function SkeletonGrid({ count = 4, height = 120, columns = 'col-12 col-sm-6 col-xl-3' }) {
  return (
    <div className="row g-3">
      {Array.from({ length: count }, (_, i) => (
        <div className={columns} key={i}><Skeleton height={height} rounded={20} /></div>
      ))}
    </div>
  );
}

export function Spinner({ size = 'sm', className = '' }) {
  return <span className={`spinner-border spinner-border-${size} ${className}`} role="status" aria-label="Loading" />;
}

export function ErrorState({ error, onRetry }) {
  const message = error?.response?.data?.message || (error?.response ? 'The server returned an error.' : 'Cannot reach the EDU-SMART API. Make sure the backend is running.');
  return (
    <EmptyState icon="wifi-off" title="Could not load this page" action={onRetry && <button type="button" className="btn btn-glass btn-sm" onClick={onRetry}><i className="bi bi-arrow-clockwise me-1" />Try again</button>}>
      {message}
    </EmptyState>
  );
}
