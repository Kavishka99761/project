import { Bar } from 'react-chartjs-2';
import './setup';
import { useChartTheme } from './useChartTheme';

/**
 * Bar / column chart — thin bars (≤ 24 px), 4 px rounded data-end, square at
 * the baseline, hairline grid. `series`: [{ label, data, color }].
 * `referenceLine`: a labelled goal line drawn as an extra flat dataset.
 */
export function BarChart({ labels, series, horizontal = false, height = 240, format = (v) => v, stacked = false, max, referenceLine }) {
  const { chrome, tooltip, scale, animation } = useChartTheme();

  const datasets = series.map((s) => ({
    type: 'bar',
    label: s.label,
    data: s.data,
    backgroundColor: s.color,
    hoverBackgroundColor: s.hoverColor ?? s.color,
    borderRadius: 4,
    borderSkipped: 'start',
    maxBarThickness: 24,
    categoryPercentage: series.length > 1 ? 0.72 : 0.78,
    barPercentage: series.length > 1 ? 0.92 : 0.9,
    stack: stacked ? 'stack' : undefined,
    order: 2,
  }));

  if (referenceLine) {
    datasets.push({
      type: 'line',
      label: referenceLine.label,
      data: labels.map(() => referenceLine.value),
      borderColor: chrome.ink2,
      borderWidth: 1,
      pointRadius: 0,
      pointHoverRadius: 0,
      order: 1,
    });
  }

  const valueAxis = scale((value) => format(value), { beginAtZero: true, max, stacked });
  const categoryAxis = { grid: { display: false }, border: { color: chrome.axis }, ticks: { color: chrome.muted, font: { size: 11 }, autoSkip: true, maxRotation: 0 }, stacked };

  const options = {
    indexAxis: horizontal ? 'y' : 'x',
    animation,
    interaction: { mode: 'index', intersect: false },
    plugins: { tooltip: tooltip(format), legend: { display: false } },
    scales: horizontal ? { x: valueAxis, y: categoryAxis } : { x: categoryAxis, y: valueAxis },
  };

  return (
    <div style={{ height }}>
      <Bar data={{ labels, datasets }} options={options} aria-label={series.map((s) => s.label).join(', ')} role="img" />
    </div>
  );
}
