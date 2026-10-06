import { Dropdown } from 'react-bootstrap';
import { Link, useNavigate } from 'react-router-dom';
import { ModuleChip, Pill, StatusBadge } from '@/components/ui/Badges';
import { GlassCard } from '@/components/ui/GlassCard';
import { bytes, relative } from '@/lib/format';

const EXTRACTION = {
  completed: null,
  pending: ['warning', 'hourglass-split', 'Extracting'],
  empty: ['serious', 'exclamation-triangle', 'No text'],
  failed: ['critical', 'x-octagon', 'Extraction failed'],
};

/** Library card for a learning document. */
export function DocumentCard({ document, onRename, onToggleFavorite, onDelete, delay = 0 }) {
  const navigate = useNavigate();
  const extraction = EXTRACTION[document.extraction_status];

  return (
    <GlassCard interactive className="doc-card position-relative" style={{ '--doc-color': document.color }} delay={delay}>
      <div className="d-flex align-items-start justify-content-between gap-2">
        <div className="doc-icon"><i className={`bi bi-${document.icon}`} aria-hidden="true" /></div>
        <div className="d-flex align-items-center gap-1" style={{ position: 'relative', zIndex: 2 }}>
          <button type="button" className="btn btn-ghost btn-sm btn-icon" onClick={() => onToggleFavorite(document)} aria-label={document.is_favorite ? 'Unpin' : 'Pin to top'} title={document.is_favorite ? 'Pinned' : 'Pin'}>
            <i className={`bi bi-star${document.is_favorite ? '-fill' : ''}`} style={document.is_favorite ? { color: 'var(--es-warning)' } : undefined} aria-hidden="true" />
          </button>
          <Dropdown align="end">
            <Dropdown.Toggle as="button" bsPrefix="x" className="btn btn-ghost btn-sm btn-icon" aria-label="Document actions"><i className="bi bi-three-dots-vertical" /></Dropdown.Toggle>
            <Dropdown.Menu>
              <Dropdown.Item onClick={() => navigate(`/learning/documents/${document.id}`)}><i className="bi bi-book" />Open</Dropdown.Item>
              <Dropdown.Item onClick={() => onRename(document)}><i className="bi bi-input-cursor-text" />Rename / organise</Dropdown.Item>
              <Dropdown.Item onClick={() => navigate(`/study?start=1&document=${document.id}${document.module ? `&module=${document.module.id}` : ''}`)}><i className="bi bi-stopwatch" />Study this now</Dropdown.Item>
              <Dropdown.Divider />
              <Dropdown.Item className="text-danger" onClick={() => onDelete(document)}><i className="bi bi-trash3" />Move to trash</Dropdown.Item>
            </Dropdown.Menu>
          </Dropdown>
        </div>
      </div>
      <Link to={`/learning/documents/${document.id}`} className="stretched-link text-decoration-none">
        <div className="doc-title line-clamp-2">{document.title}</div>
      </Link>
      <div className="doc-meta">
        {document.kind_label} · {document.pages} pg · {document.word_count.toLocaleString()} words · {bytes(document.size_bytes)}
      </div>
      {document.excerpt && <div className="doc-excerpt line-clamp-2">{document.excerpt.replace(/^#+\s*/gm, '')}</div>}
      <div className="doc-foot">
        <ModuleChip module={document.module} />
        {document.topic && <Pill icon="tag">{document.topic}</Pill>}
        {extraction && <StatusBadge status={extraction[0]} icon={extraction[1]}>{extraction[2]}</StatusBadge>}
        {document.summaries_count > 0 && <Pill icon="card-text">{document.summaries_count}</Pill>}
        <span className="ms-auto small text-3">{relative(document.created_at)}</span>
      </div>
    </GlassCard>
  );
}
