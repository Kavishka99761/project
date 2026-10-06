import { AnimatedNumber } from '@/components/ui/AnimatedNumber';
import { GlassCard } from '@/components/ui/GlassCard';
import { Sparkline } from '@/components/charts/Sparkline';

/**
 * Stat tile contract: label · value · optional delta · optional sparkline.
 * The value uses proportional figures; colour lives in the icon, not the text.
 */
export function StatTile({ label, value, unit, icon, accent, delta, deltaLabel, upIsGood = true, foot, trend, format, decimals = 0, delay = 0, to }) {
  const deltaGood = delta == null ? null : (delta >= 0) === upIsGood;

  return (
    <GlassCard className="stat-tile" accent={accent} interactive={Boolean(to)} delay={delay}>
      <div className="d-flex align-items-start justify-content-between gap-2">
        <div className="stat-label">{label}</div>
        {icon && <div className="stat-icon"><i className={`bi bi-${icon}`} aria-hidden="true" /></div>}
      </div>
      <div className="d-flex align-items-end justify-content-between gap-2">
        <div className="stat-value">
          {typeof value === 'number' ? <AnimatedNumber value={value} decimals={decimals} format={format} /> : value}
          {unit && <span className="stat-unit">{unit}</span>}
        </div>
        {trend?.length > 1 && <Sparkline values={trend} />}
      </div>
      {(delta != null || foot) && (
        <div className="stat-foot">
          {delta != null && (
            <span className={deltaGood ? 'delta-up' : 'delta-down'}>
              <i className={`bi bi-arrow-${delta >= 0 ? 'up' : 'down'}-right`} aria-hidden="true" /> {delta >= 0 ? '+' : ''}{delta}{deltaLabel ?? ''}
            </span>
          )}
          {foot && <span className="text-truncate">{foot}</span>}
        </div>
      )}
      {to && <a href={to} className="stretched-link" aria-label={label} />}
    </GlassCard>
  );
}
