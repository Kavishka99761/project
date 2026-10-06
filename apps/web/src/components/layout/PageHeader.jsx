import { motion } from 'framer-motion';

const MODULES = {
  learning: { name: 'Learning materials', owner: 'Bethmi' },
  study: { name: 'Study & engagement', owner: 'Pasindu' },
  assistant: { name: 'Academic assistant', owner: 'Kavishka' },
  assignments: { name: 'Assignments & risk', owner: 'Jithmi' },
  platform: { name: 'EDU-SMART platform', owner: null },
};

/** Page title block with module eyebrow, subtitle and actions. */
export function PageHeader({ module = 'platform', eyebrow, title, subtitle, actions }) {
  const meta = MODULES[module] ?? MODULES.platform;

  return (
    <motion.div className="page-header" initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.4 }}>
      <div className="min-w-0">
        <div className="eyebrow">
          <span className="eyebrow-dot" />
          {eyebrow ?? meta.name}
          {meta.owner && !eyebrow && <span className="text-3 fw-semibold text-none" style={{ textTransform: 'none', letterSpacing: 0 }}>· {meta.owner}</span>}
        </div>
        <h2 className="text-balance">{title}</h2>
        {subtitle && <p className="subtitle">{subtitle}</p>}
      </div>
      {actions && <div className="d-flex flex-wrap align-items-center gap-2">{actions}</div>}
    </motion.div>
  );
}
