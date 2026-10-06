function download(filename, content, mime) {
  const blob = new Blob([content], { type: mime });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(url);
}

export function exportAsJson(filename, data) {
  download(filename, JSON.stringify(data, null, 2), 'application/json');
}

function toCsvRow(values) {
  return values
    .map((v) => {
      const s = v === null || v === undefined ? '' : String(v);
      return /[",\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
    })
    .join(',');
}

export function exportAsCsv(filename, rows, headers) {
  const lines = [toCsvRow(headers), ...rows.map((r) => toCsvRow(headers.map((h) => r[h])))];
  download(filename, lines.join('\n'), 'text/csv');
}
