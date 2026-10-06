// Data-visualisation palette — validated with the dataviz palette validator
// (CVD + normal-vision separation, lightness band, chroma floor) in both
// light and dark mode. Never generate extra hues: fold to "Other" instead.

// Categorical slots in their fixed, validated order (light → dark steps).
export const CATEGORICAL = [
  { light: '#2a78d6', dark: '#3987e5', name: 'blue' },
  { light: '#eb6834', dark: '#d95926', name: 'orange' },
  { light: '#1baf7a', dark: '#199e70', name: 'aqua' },
  { light: '#eda100', dark: '#c98500', name: 'yellow' },
  { light: '#e87ba4', dark: '#d55181', name: 'magenta' },
  { light: '#008300', dark: '#008300', name: 'green' },
  { light: '#4a3aa7', dark: '#9085e9', name: 'violet' },
  { light: '#e34948', dark: '#e66767', name: 'red' },
];

// Feature-module identity (validated as an adjacent set; platform = neutral).
export const MODULE_COLORS = {
  learning: { light: '#2a78d6', dark: '#3987e5' },
  study: { light: '#1baf7a', dark: '#199e70' },
  assistant: { light: '#4a3aa7', dark: '#9085e9' },
  assignments: { light: '#eb6834', dark: '#d95926' },
  platform: { light: '#898781', dark: '#898781' },
};

// Status scale — reserved meaning, always paired with an icon and a label.
export const STATUS = {
  good: '#0ca30c',
  warning: '#fab219',
  serious: '#ec835a',
  critical: '#d03b3b',
};

export const RISK_STATUS = {
  low: { status: 'good', icon: 'shield-check', label: 'Low' },
  medium: { status: 'warning', icon: 'shield', label: 'Medium' },
  high: { status: 'serious', icon: 'shield-exclamation', label: 'High' },
  critical: { status: 'critical', icon: 'shield-fill-exclamation', label: 'Critical' },
};

export const ENGAGEMENT_STATUS = {
  high: { status: 'good', icon: 'lightning-charge-fill', label: 'Focused' },
  moderate: { status: 'warning', icon: 'activity', label: 'Moderate' },
  low: { status: 'critical', icon: 'cloud-drizzle-fill', label: 'Distracted' },
};

// Sequential blue ramp (100 → 700) for magnitude (heatmaps).
export const SEQUENTIAL_BLUE = ['#cde2fb', '#b7d3f6', '#9ec5f4', '#86b6ef', '#6da7ec', '#5598e7', '#3987e5', '#2a78d6', '#256abf', '#1c5cab', '#184f95', '#104281', '#0d366b'];

export const CHART_CHROME = {
  light: { surface: '#fcfcfb', grid: '#e1e0d9', axis: '#c3c2b7', ink: '#0b0b0b', ink2: '#52514e', muted: '#898781' },
  dark: { surface: '#1a1a19', grid: '#2c2c2a', axis: '#383835', ink: '#ffffff', ink2: '#c3c2b7', muted: '#898781' },
};

const lightToDark = Object.fromEntries(CATEGORICAL.map((slot) => [slot.light.toLowerCase(), slot.dark]));

/** Same hue, stepped for the active theme (stored module colours are light steps). */
export function themed(hex, theme) {
  if (!hex) return theme === 'dark' ? CATEGORICAL[0].dark : CATEGORICAL[0].light;
  return theme === 'dark' ? lightToDark[hex.toLowerCase()] ?? hex : hex;
}

export function moduleColor(key, theme) {
  return (MODULE_COLORS[key] ?? MODULE_COLORS.platform)[theme === 'dark' ? 'dark' : 'light'];
}

export function seriesSlot(index, theme) {
  const slot = CATEGORICAL[index % CATEGORICAL.length];
  return theme === 'dark' ? slot.dark : slot.light;
}

/** Map a 0..1 magnitude onto the sequential ramp (dark mode flips the anchor). */
export function sequential(value, theme) {
  const v = Math.max(0, Math.min(1, value));
  const ramp = theme === 'dark' ? [...SEQUENTIAL_BLUE].reverse() : SEQUENTIAL_BLUE;
  const start = theme === 'dark' ? 0 : 1;
  const index = Math.round(start + v * (ramp.length - 1 - start));
  return ramp[index];
}

/** Module colour choices offered in the module editor (validated slots, in order). */
export const MODULE_COLOR_CHOICES = CATEGORICAL.map((slot) => slot.light);
