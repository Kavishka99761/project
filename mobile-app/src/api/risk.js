/**
 * JITHMI — deadline-miss risk engine (client mirror).
 *
 * This is a 1:1 port of backend-laravel/app/Services/RiskCalculator.php and
 * frontend-web ES.calcRisk. Keeping the same formula (DAILY_CAPACITY = 5h) means
 * the mobile UI shows identical risk scores to the API without a round-trip, and
 * still works offline against the bundled demo data.
 */

const DAILY_CAPACITY = 5.0;

export function daysUntil(dateStr) {
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const target = new Date(`${String(dateStr).slice(0, 10)}T00:00:00`);
  return Math.round((target - today) / 86400000);
}

/**
 * Local-calendar ISO date (YYYY-MM-DD), `offsetDays` from `from` (default today).
 *
 * The exact inverse of daysUntil() above, and built from LOCAL components on
 * purpose: toISOString() returns UTC, which is the PREVIOUS calendar day for any
 * positive UTC offset. Mixing the two silently shifts every deadline by a day —
 * e.g. a what-if simulation at zero delta would not reproduce the current score.
 * Noon is the anchor so a DST transition (where local midnight may not exist)
 * cannot push the result onto the wrong date.
 */
export function isoDate(offsetDays = 0, from = new Date()) {
  const d = new Date(from);
  d.setHours(12, 0, 0, 0);
  d.setDate(d.getDate() + offsetDays);
  const p = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`;
}

/**
 * @param {{deadline:string, estHours:number, doneHours:number, progress:number}} a
 * @returns {{score:number, level:string, reasons:string[], hoursPerDay:number, days:number}}
 */
export function calcRisk(a) {
  const days = Math.max(daysUntil(a.deadline), 0);
  const remainingHours = Math.max((a.estHours || 0) - (a.doneHours || 0), 0);
  const hoursPerDay = days > 0 ? remainingHours / days : remainingHours;

  let score;
  if (days === 0) {
    score = remainingHours > 0 ? 100 : 0;
  } else {
    score = Math.min(100, Math.round((hoursPerDay / DAILY_CAPACITY) * 60 + (100 - (a.progress || 0)) * 0.4));
  }

  let level = 'Low';
  if (score >= 75) level = 'Critical';
  else if (score >= 50) level = 'High';
  else if (score >= 25) level = 'Medium';

  const reasons = [];
  if (hoursPerDay > 3) reasons.push(`Needs ~${hoursPerDay.toFixed(1)}h/day to finish`);
  if (days <= 3) reasons.push(`Only ${days} day(s) left`);
  if ((a.progress || 0) < 40) reasons.push(`Progress is low (${a.progress || 0}%)`);
  if (!reasons.length) reasons.push('On track — workload fits available time');

  return { score, level, reasons, hoursPerDay: +hoursPerDay.toFixed(1), days };
}

export default { calcRisk, daysUntil, isoDate };
