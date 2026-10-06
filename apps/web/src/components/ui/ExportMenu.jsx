import { useState } from 'react';
import { Dropdown } from 'react-bootstrap';
import { useToast } from '@/context/ToastContext';
import { download, errorMessage } from '@/lib/api';

const LABELS = {
  pdf: ['PDF report', 'file-earmark-pdf'],
  xlsx: ['Excel workbook', 'file-earmark-excel'],
  csv: ['CSV spreadsheet', 'filetype-csv'],
  json: ['JSON data', 'filetype-json'],
  docx: ['Word document', 'file-earmark-word'],
  md: ['Markdown', 'markdown'],
  txt: ['Plain text', 'file-earmark-text'],
};

/** Dropdown that downloads `url?format=…` in the chosen format. */
export function ExportMenu({ url, params, formats = ['pdf', 'xlsx', 'csv', 'json'], label = 'Export', size = 'sm', variant = 'btn-glass', align = 'end' }) {
  const toast = useToast();
  const [busy, setBusy] = useState(null);

  const run = async (format) => {
    setBusy(format);
    try {
      const result = await download(url, { ...params, format }, `export.${format}`);
      toast.success('Download ready', `${result.name}${result.rows ? ` · ${result.rows} rows` : ''}`);
    } catch (error) {
      toast.error('Export failed', errorMessage(error));
    } finally {
      setBusy(null);
    }
  };

  return (
    <Dropdown align={align}>
      <Dropdown.Toggle className={`btn ${variant} btn-${size}`} variant="" disabled={Boolean(busy)}>
        {busy ? <span className="spinner-border spinner-border-sm me-1" /> : <i className="bi bi-download me-1" aria-hidden="true" />}
        {label}
      </Dropdown.Toggle>
      <Dropdown.Menu>
        {formats.map((format) => (
          <Dropdown.Item key={format} onClick={() => run(format)}>
            <i className={`bi bi-${LABELS[format][1]}`} aria-hidden="true" />
            {LABELS[format][0]}
          </Dropdown.Item>
        ))}
      </Dropdown.Menu>
    </Dropdown>
  );
}
