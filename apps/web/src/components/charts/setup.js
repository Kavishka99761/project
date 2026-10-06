import {
  BarElement,
  CategoryScale,
  Chart,
  Filler,
  LinearScale,
  LineElement,
  PointElement,
  Tooltip,
} from 'chart.js';

// Vertical hairline that follows the hovered x position (line/area charts):
// readers aim at a date, never at a 2 px line.
const crosshair = {
  id: 'esCrosshair',
  afterDatasetsDraw(chart, _args, options) {
    if (!options?.enabled) return;
    const active = chart.tooltip?.getActiveElements?.() ?? [];
    if (!active.length) return;
    const { ctx, chartArea } = chart;
    const x = active[0].element.x;
    ctx.save();
    ctx.beginPath();
    ctx.moveTo(x, chartArea.top);
    ctx.lineTo(x, chartArea.bottom);
    ctx.lineWidth = 1;
    ctx.strokeStyle = options.color ?? 'rgba(0,0,0,0.25)';
    ctx.stroke();
    ctx.restore();
  },
};

// Background bands (e.g. Low / Medium / High / Critical risk zones).
const bands = {
  id: 'esBands',
  beforeDatasetsDraw(chart, _args, options) {
    const list = options?.bands;
    if (!list?.length) return;
    const { ctx, chartArea, scales } = chart;
    const y = scales.y;
    ctx.save();
    list.forEach((band) => {
      const top = y.getPixelForValue(band.to);
      const bottom = y.getPixelForValue(band.from);
      ctx.fillStyle = band.color;
      ctx.fillRect(chartArea.left, top, chartArea.right - chartArea.left, bottom - top);
    });
    ctx.restore();
  },
};

Chart.register(CategoryScale, LinearScale, BarElement, LineElement, PointElement, Filler, Tooltip, crosshair, bands);

Chart.defaults.font.family = "'Plus Jakarta Sans Variable', system-ui, -apple-system, 'Segoe UI', sans-serif";
Chart.defaults.font.size = 11;
Chart.defaults.maintainAspectRatio = false;
Chart.defaults.animation.duration = 700;
Chart.defaults.animation.easing = 'easeOutQuart';

export { Chart };
