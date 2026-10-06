import { useModules } from '@/hooks/usePlatform';

/** Select one of the student's registered modules. */
export function ModuleSelect({ value, onChange, includeAll = false, allLabel = 'All modules', includeNone = true, noneLabel = 'No module', className = '', id, size, disabled }) {
  const { data: modules = [] } = useModules();

  return (
    <select
      id={id}
      className={`form-select ${size ? `form-select-${size}` : ''} ${className}`}
      value={value ?? ''}
      disabled={disabled}
      onChange={(e) => onChange(e.target.value === '' ? null : Number(e.target.value))}
    >
      {includeAll && <option value="">{allLabel}</option>}
      {!includeAll && includeNone && <option value="">{noneLabel}</option>}
      {includeAll && includeNone && <option value="0">{noneLabel}</option>}
      {modules.map((module) => <option key={module.id} value={module.id}>{module.code} — {module.name}</option>)}
    </select>
  );
}
