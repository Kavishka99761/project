import { StatusBadge } from '@/components/ui/Badges';

const OPERATIONS = {
  insert: { status: 'good', icon: 'plus-circle', label: 'Created' },
  update: { status: 'warning', icon: 'pencil', label: 'Updated' },
  delete: { status: 'critical', icon: 'trash3', label: 'Deleted' },
};

/** CRUD operation as colour + icon + word (never colour alone). */
export function OperationBadge({ operation }) {
  const meta = OPERATIONS[operation] ?? { status: 'good', icon: 'dot', label: operation };
  return <StatusBadge status={meta.status} icon={meta.icon}>{meta.label}</StatusBadge>;
}

const SOURCES = {
  api: ['phone', 'App'],
  console: ['terminal', 'Artisan'],
  queue: ['gear', 'Background job'],
  sql: ['database', 'Direct SQL'],
};

/** Where a change came from: the app, a job, or a direct SQL edit (with its login). */
export function SourceLabel({ change }) {
  const [icon, label] = SOURCES[change.source] ?? ['question-circle', change.source];
  return (
    <span className="text-nowrap" title={change.db_login ? `${change.db_login} · ${change.app_name ?? ''}` : undefined}>
      <i className={`bi bi-${icon} me-1`} aria-hidden="true" />
      {label}
      {change.source === 'sql' && change.db_login && <span className="text-3"> ({change.db_login})</span>}
    </span>
  );
}
