import { Line } from 'react-chartjs-2';
import './setup';
import { useChartTheme } from './useChartTheme';

const withAlpha = (hex, alpha) => {
  const value = hex.replace('#', '');
  const r = parseInt(value.slice(0, 2), 16);
  const g = parseInt(value.slice(2, 4), 16);
  const b = parseInt(value.slice(4, 6), 16);
  return `rgba(${r}, ${g}, ${b}, ${alpha})`;
};

/**
 * Line / area chart — 2 px lines, ~10 % area wash, end markers ≥ 8 px with a
 * surface ring, crosshair tooltip listing every series at the hovered x.
 */
export function LineChart({ labels, series, height = 240, format = (v) => v, yMin, yMax, bands, spanGaps = true }) {
  const { chrome, tooltip, scale, animation } = useChartTheme();

  const datasets = series.map((s) => ({
    label: s.label,
    data: s.data,
    borderColor: s.color,
    backgroundColor: s.fill === false ? 'transparent' : withAlpha(s.color, 0.1),
    fill: s.fill === false ? false : 'origin',
    borderWidth: 2,
    tension: 0.35,
    borderCapStyle: 'round',
    borderJoinStyle: 'round',
    pointRadius: s.data.map((_, i) => (i === s.data.length - 1 ? 4 : 0)),
    pointHoverRadius: 5,
    pointBackgroundColor: s.color,
    pointBorderColor: chrome.surface,
    pointBorderWidth: 2,
    pointHitRadius: 12,
    pointStyle: 'circle',
    spanGaps,
  }));

  const options = {
    animation,
    interaction: { mode: 'index', intersect: false },
    plugins: {
      tooltip: { ...tooltip(format), usePointStyle: true, callbacks: { ...tooltip(format).callbacks, labelPointStyle: () => ({ pointStyle: 'line', rotation: 0 }) } },
      legend: { display: false },
      esCrosshair: { enabled: true, color: chrome.axis },
      esBands: { bands },
    },
    scales: {
      x: { grid: { display: false }, border: { color: chrome.axis }, ticks: { color: chrome.muted, autoSkip: true, maxTicksLimit: 8, maxRotation: 0, font: { size: 11 } } },
      y: scale((value) => format(value), { min: yMin, max: yMax, beginAtZero: yMin == null }),
    },
  };

  return (
    <div style={{ height }}>
      <Line data={{ labels, datasets }} options={options} role="img" aria-label={series.map((s) => s.label).join(', ')} />
    </div>
  );
}
