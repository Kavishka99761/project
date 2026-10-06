/** Pager for Laravel paginator meta ({current_page, last_page, total}). */
export function Pagination({ meta, onPage }) {
  if (!meta || meta.last_page <= 1) return null;
  const { current_page: page, last_page: last, total, from, to } = meta;

  return (
    <div className="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3">
      <div className="small text-3">Showing {from}–{to} of {total}</div>
      <div className="d-flex gap-2">
        <button type="button" className="btn btn-glass btn-sm" disabled={page <= 1} onClick={() => onPage(page - 1)}>
          <i className="bi bi-chevron-left" aria-hidden="true" /> Previous
        </button>
        <span className="align-self-center small fw-semibold">{page} / {last}</span>
        <button type="button" className="btn btn-glass btn-sm" disabled={page >= last} onClick={() => onPage(page + 1)}>
          Next <i className="bi bi-chevron-right" aria-hidden="true" />
        </button>
      </div>
    </div>
  );
}
