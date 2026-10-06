import { useTheme } from '@/context/ThemeContext';
import { CHART_CHROME, seriesSlot } from '@/config/palette';

/** 12-point trend line: history in the de-emphasis hue, current point in accent. */
export function Sparkline({ values, width = 84, height = 30 }) {
  const { theme } = useTheme();
  const data = values.slice(-12).map((v) => Number(v) || 0);
  const max = Math.max(...data, 1);
  const min = Math.min(...data, 0);
  const span = max - min || 1;
  const step = width / Math.max(data.length - 1, 1);
  const points = data.map((v, i) => [i * step, height - 3 - ((v - min) / span) * (height - 6)]);
  const path = points.map(([x, y], i) => `${i ? 'L' : 'M'}${x.toFixed(1)},${y.toFixed(1)}`).join(' ');
  const [lastX, lastY] = points[points.length - 1];

  return (
    <svg className="sparkline flex-shrink-0" width={width} height={height} viewBox={`0 0 ${width} ${height}`} aria-hidden="true">
      <path className="line" d={path} stroke={CHART_CHROME[theme].muted} />
      <circle cx={lastX} cy={lastY} r="3.5" fill={seriesSlot(0, theme)} stroke={CHART_CHROME[theme].surface} strokeWidth="2" />
    </svg>
  );
}
