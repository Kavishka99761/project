import { useMemo } from 'react';
import { useTheme } from '@/context/ThemeContext';
import { CHART_CHROME } from '@/config/palette';

/** Chart chrome for the active theme + shared Chart.js option builders. */
export function useChartTheme() {
  const { theme, reduceMotion } = useTheme();

  return useMemo(() => {
    const chrome = CHART_CHROME[theme === 'dark' ? 'dark' : 'light'];

    const tooltip = (valueFormat) => ({
      backgroundColor: theme === 'dark' ? 'rgba(30, 31, 36, 0.96)' : 'rgba(255, 255, 255, 0.97)',
      titleColor: chrome.ink2,
      bodyColor: chrome.ink,
      borderColor: theme === 'dark' ? 'rgba(255,255,255,0.12)' : 'rgba(11,11,11,0.1)',
      borderWidth: 1,
      padding: 10,
      cornerRadius: 10,
      boxPadding: 6,
      usePointStyle: true,
      titleFont: { weight: '600', size: 11 },
      bodyFont: { weight: '700', size: 12 },
      callbacks: valueFormat
        ? {
            // Values lead, labels follow.
            label: (context) => ` ${valueFormat(context.parsed.y ?? context.parsed.x, context)}  ${context.dataset.label ?? ''}`,
          }
        : undefined,
    });

    const scale = (format, extra = {}) => ({
      grid: { color: chrome.grid, lineWidth: 1, drawTicks: false },
      border: { color: chrome.axis, width: 1 },
      ticks: { color: chrome.muted, padding: 8, callback: format, font: { size: 11 } },
      ...extra,
    });

    return { theme, chrome, tooltip, scale, animation: reduceMotion ? false : undefined };
  }, [theme, reduceMotion]);
}
