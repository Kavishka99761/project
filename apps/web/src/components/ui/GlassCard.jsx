import { motion } from 'framer-motion';
import { useGlassPointer } from '@/hooks/useGlassPointer';

/**
 * Liquid-glass surface. `interactive` adds lift + a pointer-tracking specular
 * light; `variant` picks the opacity (glass | strong | solid).
 */
export function GlassCard({ as = 'div', variant = 'glass', interactive = false, accent, className = '', children, delay = 0, animate = true, ...props }) {
  const onPointerMove = useGlassPointer();
  const Component = animate ? motion[as] ?? motion.div : as;
  const base = variant === 'solid' ? 'glass-solid' : variant === 'strong' ? 'glass-strong' : 'glass';
  const classes = [base, interactive && 'glass-interactive', accent && `accent-${accent}`, className].filter(Boolean).join(' ');
  const motionProps = animate
    ? { initial: { opacity: 0, y: 14 }, animate: { opacity: 1, y: 0 }, transition: { duration: 0.45, delay, ease: [0.2, 0.8, 0.2, 1] } }
    : {};

  return (
    <Component className={classes} onPointerMove={interactive ? onPointerMove : undefined} {...motionProps} {...props}>
      {interactive && <span className="glass-light" aria-hidden="true" />}
      {children}
    </Component>
  );
}

export function Panel({ title, icon, subtitle, actions, children, className = '', bodyClassName = '', ...props }) {
  return (
    <GlassCard className={`panel ${className}`} {...props}>
      {(title || actions) && (
        <div className="panel-head">
          <div className="min-w-0">
            {title && (
              <h3>
                {icon && <span className="panel-icon"><i className={`bi bi-${icon}`} aria-hidden="true" /></span>}
                <span className="text-truncate">{title}</span>
              </h3>
            )}
            {subtitle && <div className="panel-sub mt-1">{subtitle}</div>}
          </div>
          {actions && <div className="d-flex align-items-center gap-2 flex-shrink-0">{actions}</div>}
        </div>
      )}
      <div className={bodyClassName}>{children}</div>
    </GlassCard>
  );
}
