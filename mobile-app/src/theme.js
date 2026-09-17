/**
 * EDU-SMART mobile design tokens.
 * Colors match the web frontend (frontend-web/assets/css/style.css) so the two
 * clients feel like one product. Each module keeps its signature accent.
 */
export const colors = {
  primary: '#6366f1',
  primaryDark: '#4f46e5',
  bg: '#f6f7fb',
  surface: '#ffffff',
  surfaceAlt: '#f1f2f8',
  text: '#1e2233',
  textMuted: '#6b7280',
  border: '#e5e7eb',

  // Module accents
  bethmi: '#3b82f6',
  pasindu: '#14b8a6',
  kavishka: '#8b5cf6',
  jithmi: '#f97316',

  // Risk levels
  low: '#22c55e',
  medium: '#eab308',
  high: '#f97316',
  critical: '#ef4444',

  success: '#22c55e',
  danger: '#ef4444',
};

export const moduleAccent = {
  bethmi: colors.bethmi,
  pasindu: colors.pasindu,
  kavishka: colors.kavishka,
  jithmi: colors.jithmi,
  primary: colors.primary,
};

export const riskColor = {
  Low: colors.low,
  Medium: colors.medium,
  High: colors.high,
  Critical: colors.critical,
};

export const spacing = { xs: 4, sm: 8, md: 12, lg: 16, xl: 24 };
export const radius = { sm: 8, md: 12, lg: 16, pill: 999 };

export const shadow = {
  shadowColor: '#0b1020',
  shadowOpacity: 0.06,
  shadowRadius: 10,
  shadowOffset: { width: 0, height: 4 },
  elevation: 2,
};

export default { colors, moduleAccent, riskColor, spacing, radius, shadow };
