import { useState } from 'react';
import { useTheme } from '@/context/ThemeContext';
import { sequential, SEQUENTIAL_BLUE } from '@/config/palette';
import { minutes as fmtMinutes } from '@/lib/format';

/**
 * Weekday × hour heatmap (sequential one-hue ramp). Each cell is focusable
 * and carries its own tooltip.
 */
export function Heatmap({ days, cells }) {
  const { theme } = useTheme();
  const [hover, setHover] = useState(null);
  const max = Math.max(...cells.map((c) => c.minutes), 1);
  const lookup = new Map(cells.map((c) => [`${c.day}-${c.hour}`, c]));
  const hours = Array.from({ length: 24 }, (_, h) => h);

  const show = (event, cell, day, hour) => {
    const rect = event.currentTarget.getBoundingClientRect();
    setHover({ x: rect.left + rect.width / 2, y: rect.top - 8, day, hour, cell });
  };

  return (
    <div className="position-relative">
      <div className="heatmap" role="grid" aria-label="Study minutes by weekday and hour">
        <span />
        {hours.map((h) => <span key={h} className="hm-hour">{h % 3 === 0 ? h : ''}</span>)}
        {days.map((day, d) => (
          <div key={day} role="row" style={{ display: 'contents' }}>
            <span className="hm-label">{day}</span>
            {hours.map((h) => {
              const cell = lookup.get(`${d}-${h}`);
              const value = cell ? cell.minutes / max : 0;
              return (
                <span
                  key={h}
                  role="gridcell"
                  tabIndex={cell ? 0 : -1}
                  className="hm-cell"
                  aria-label={`${day} ${String(h).padStart(2, '0')}:00 — ${cell ? fmtMinutes(cell.minutes) : 'no study'}`}
                  style={cell ? { background: sequential(0.08 + value * 0.92, theme) } : undefined}
                  onPointerEnter={(e) => cell && show(e, cell, day, h)}
                  onFocus={(e) => cell && show(e, cell, day, h)}
                  onPointerLeave={() => setHover(null)}
                  onBlur={() => setHover(null)}
                />
              );
            })}
          </div>
        ))}
      </div>
      <div className="d-flex justify-content-end mt-2">
        <div className="scale-legend">
          <span>Less</span>
          {[0.1, 0.3, 0.5, 0.7, 0.9].map((v) => <span key={v} className="step" style={{ background: sequential(v, theme) }} />)}
          <span>More</span>
        </div>
      </div>
      {hover && (
        <div className="chart-tooltip" style={{ left: hover.x, top: hover.y, transform: 'translate(-50%, -100%)' }}>
          <div className="fw-bold">{fmtMinutes(hover.cell.minutes)}</div>
          <div className="text-3">{hover.day} · {String(hover.hour).padStart(2, '0')}:00{hover.cell.engagement != null ? ` · ${hover.cell.engagement}% engaged` : ''}</div>
        </div>
      )}
    </div>
  );
}

export const HEATMAP_RAMP = SEQUENTIAL_BLUE;
