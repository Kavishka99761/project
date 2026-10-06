import { motion } from 'framer-motion';
import { useId } from 'react';

/** Segmented switcher with a gliding glass pill. */
export function SegmentedControl({ options, value, onChange, size = 'md', ariaLabel }) {
  const layoutId = useId();

  return (
    <div className="segmented" role="tablist" aria-label={ariaLabel} style={size === 'sm' ? { fontSize: '0.78rem' } : undefined}>
      {options.map((option) => {
        const active = option.value === value;
        return (
          <button key={option.value} type="button" role="tab" aria-selected={active} className={active ? 'active' : ''} onClick={() => onChange(option.value)}>
            {active && <motion.span layoutId={layoutId} className="segmented-pill" transition={{ type: 'spring', stiffness: 500, damping: 38 }} />}
            <span>
              {option.icon && <i className={`bi bi-${option.icon} me-1`} aria-hidden="true" />}
              {option.label}
              {option.count != null && <span className="ms-1 text-3">{option.count}</span>}
            </span>
          </button>
        );
      })}
    </div>
  );
}
