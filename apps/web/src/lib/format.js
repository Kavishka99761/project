import { format, formatDistanceToNowStrict, isToday, isTomorrow, isYesterday, parseISO } from 'date-fns';

const toDate = (value) => (value instanceof Date ? value : typeof value === 'string' ? parseISO(value) : new Date(value));

export function formatDate(value, pattern = 'd MMM yyyy') {
  if (!value) return '—';
  try {
    return format(toDate(value), pattern);
  } catch {
    return '—';
  }
}

export function formatDateTime(value) {
  return formatDate(value, 'd MMM yyyy, HH:mm');
}

export function formatTime(value) {
  return formatDate(value, 'HH:mm');
}

export function relative(value) {
  if (!value) return '—';
  const date = toDate(value);
  return formatDistanceToNowStrict(date, { addSuffix: true });
}

export function friendlyDay(value) {
  if (!value) return '—';
  const date = toDate(value);
  if (isToday(date)) return 'Today';
  if (isTomorrow(date)) return 'Tomorrow';
  if (isYesterday(date)) return 'Yesterday';
  return format(date, 'EEE d MMM');
}

/** 135 → "2h 15m" */
export function minutes(total) {
  const m = Math.round(Number(total) || 0);
  if (m < 60) return `${m}m`;
  const h = Math.floor(m / 60);
  const rest = m % 60;
  return rest ? `${h}h ${rest}m` : `${h}h`;
}

export function hours(value, digits = 1) {
  const n = Number(value) || 0;
  return `${n.toFixed(n % 1 === 0 ? 0 : digits)} h`;
}

/** Seconds → "1:05:09" / "25:00" */
export function clock(totalSeconds) {
  const s = Math.max(0, Math.floor(totalSeconds || 0));
  const h = Math.floor(s / 3600);
  const m = Math.floor((s % 3600) / 60);
  const sec = s % 60;
  const mm = String(m).padStart(h ? 2 : 1, '0');
  const ss = String(sec).padStart(2, '0');
  return h ? `${h}:${mm}:${ss}` : `${mm}:${ss}`;
}

export function bytes(size) {
  const n = Number(size) || 0;
  if (n < 1024) return `${n} B`;
  if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`;
  return `${(n / 1024 / 1024).toFixed(1)} MB`;
}

export function compact(value) {
  return new Intl.NumberFormat('en', { notation: 'compact', maximumFractionDigits: 1 }).format(Number(value) || 0);
}

export function number(value) {
  return new Intl.NumberFormat('en').format(Number(value) || 0);
}

export function daysLeftLabel(daysLeft) {
  const d = Number(daysLeft);
  if (Number.isNaN(d)) return '—';
  if (d < 0) return `${Math.ceil(Math.abs(d))} day${Math.ceil(Math.abs(d)) === 1 ? '' : 's'} overdue`;
  if (d < 1) return `${Math.max(1, Math.round(d * 24))} h left`;
  const days = Math.floor(d);
  return `${days} day${days === 1 ? '' : 's'}`;
}

export function initials(name = '') {
  return name.split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0]?.toUpperCase()).join('') || 'S';
}

export function pluralize(count, word, plural = `${word}s`) {
  return `${number(count)} ${count === 1 ? word : plural}`;
}
