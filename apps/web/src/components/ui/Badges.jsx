import { ENGAGEMENT_STATUS, RISK_STATUS } from '@/config/palette';

/** Status colour + icon + label — never colour alone. */
export function StatusBadge({ status = 'good', icon, children, className = '' }) {
  return (
    <span className={`es-badge es-badge-${status} ${className}`}>
      {icon && <i className={`bi bi-${icon}`} aria-hidden="true" />}
      {children}
    </span>
  );
}

export function RiskBadge({ level, score, showScore = true, className = '' }) {
  if (!level) return <span className={`es-badge ${className}`}>No risk data</span>;
  const meta = RISK_STATUS[level] ?? RISK_STATUS.low;

  return (
    <StatusBadge status={meta.status} icon={meta.icon} className={className}>
      {meta.label}{showScore && score != null ? ` · ${score}%` : ''}
    </StatusBadge>
  );
}

export function EngagementBadge({ level, score, className = '' }) {
  if (!level) return null;
  const meta = ENGAGEMENT_STATUS[level] ?? ENGAGEMENT_STATUS.moderate;

  return (
    <StatusBadge status={meta.status} icon={meta.icon} className={className}>
      {meta.label}{score != null ? ` · ${score}%` : ''}
    </StatusBadge>
  );
}

export function ModuleChip({ module, className = '' }) {
  if (!module) return <span className={`module-chip ${className}`}><span className="swatch" style={{ '--chip-color': 'var(--es-chart-muted)' }} />Unfiled</span>;

  return (
    <span className={`module-chip ${className}`} title={module.name}>
      <span className="swatch" style={{ '--chip-color': module.color }} />
      {module.code}
    </span>
  );
}

export function Pill({ icon, children, className = '' }) {
  return (
    <span className={`es-badge ${className}`}>
      {icon && <i className={`bi bi-${icon}`} aria-hidden="true" />}
      {children}
    </span>
  );
}
