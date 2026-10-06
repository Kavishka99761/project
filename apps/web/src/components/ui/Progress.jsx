/** Circular meter: the unfilled track is a lighter step of the same colour. */
export function ProgressRing({ value = 0, size = 120, stroke = 10, color, children, label }) {
  const radius = (size - stroke) / 2;
  const circumference = 2 * Math.PI * radius;
  const clamped = Math.max(0, Math.min(100, Number(value) || 0));
  const offset = circumference * (1 - clamped / 100);

  return (
    <div className="ring" style={{ width: size, height: size, '--ring-color': color }} role="img" aria-label={label ?? `${Math.round(clamped)}%`}>
      <svg width={size} height={size} viewBox={`0 0 ${size} ${size}`} aria-hidden="true">
        <circle className="ring-track" cx={size / 2} cy={size / 2} r={radius} fill="none" strokeWidth={stroke} />
        <circle
          className="ring-fill"
          cx={size / 2}
          cy={size / 2}
          r={radius}
          fill="none"
          strokeWidth={stroke}
          strokeLinecap="round"
          strokeDasharray={circumference}
          strokeDashoffset={offset}
        />
      </svg>
      <div className="ring-label">{children}</div>
    </div>
  );
}

/** Linear meter (ratio against a limit). */
export function Meter({ value = 0, max = 100, color, large = false, label }) {
  const percent = max > 0 ? Math.max(0, Math.min(100, (Number(value) / max) * 100)) : 0;

  return (
    <div className={`meter ${large ? 'meter-lg' : ''}`} style={{ '--meter-color': color }} role="progressbar" aria-valuenow={Math.round(percent)} aria-valuemin={0} aria-valuemax={100} aria-label={label}>
      <div className="meter-fill" style={{ width: `${percent}%` }} />
    </div>
  );
}
