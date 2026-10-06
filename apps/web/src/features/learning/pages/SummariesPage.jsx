import { useState } from 'react';
import { Link } from 'react-router-dom';
import { PageHeader } from '@/components/layout/PageHeader';
import { ModuleChip, Pill } from '@/components/ui/Badges';
import { ExportMenu } from '@/components/ui/ExportMenu';
import { GlassCard } from '@/components/ui/GlassCard';
import { ModuleSelect } from '@/components/ui/ModuleSelect';
import { Pagination } from '@/components/ui/Pagination';
import { SegmentedControl } from '@/components/ui/SegmentedControl';
import { EmptyState, ErrorState, SkeletonGrid } from '@/components/ui/States';
import { useConfirm } from '@/context/ConfirmContext';
import { useToast } from '@/context/ToastContext';
import { useDebounce } from '@/hooks/useDebounce';
import { relative } from '@/lib/format';
import { useDeleteSummary, useSummaries, useUpdateSummary } from '../api';

export default function SummariesPage() {
  const toast = useToast();
  const confirm = useConfirm();
  const [q, setQ] = useState('');
  const [length, setLength] = useState('');
  const [moduleId, setModuleId] = useState(null);
  const [page, setPage] = useState(1);
  const search = useDebounce(q, 300);
  const summaries = useSummaries({ q: search || undefined, length: length || undefined, module_id: moduleId ?? undefined, page, per_page: 18 });
  const remove = useDeleteSummary();
  const update = useUpdateSummary();
  const items = summaries.data?.data ?? [];

  const destroy = async (summary) => {
    if (!(await confirm({ title: 'Delete this summary?', body: summary.title, confirmLabel: 'Move to trash' }))) return;
    await remove.mutateAsync(summary.id);
    toast.success('Summary moved to trash');
  };

  return (
    <>
      <PageHeader module="learning" title="Saved summaries" subtitle="Search, download and revise from every summary you've saved." actions={<ExportMenu url="/exports/summaries" label="Export all" />} />
      <GlassCard variant="strong" className="p-3 mb-3">
        <div className="row g-2 align-items-center">
          <div className="col-lg-5">
            <div className="input-glass"><i className="bi bi-search" /><input value={q} onChange={(e) => { setQ(e.target.value); setPage(1); }} placeholder="Search summaries and keywords…" aria-label="Search summaries" /></div>
          </div>
          <div className="col-md-auto">
            <SegmentedControl size="sm" options={[{ value: '', label: 'All' }, { value: 'short', label: 'Short' }, { value: 'medium', label: 'Medium' }, { value: 'detailed', label: 'Detailed' }]} value={length} onChange={(v) => { setLength(v); setPage(1); }} />
          </div>
          <div className="col-md-3"><ModuleSelect includeAll includeNone={false} value={moduleId} onChange={(v) => { setModuleId(v); setPage(1); }} /></div>
        </div>
      </GlassCard>

      {summaries.isError ? <ErrorState error={summaries.error} onRetry={summaries.refetch} /> : summaries.isLoading ? <SkeletonGrid count={6} height={180} columns="col-md-6 col-xl-4" /> : items.length === 0 ? (
        <GlassCard className="p-4"><EmptyState icon="card-text" title={search ? 'No summaries match' : 'No saved summaries yet'} action={<Link to="/learning/library" className="btn btn-accent">Open the library</Link>}>Open a document and generate a summary to see it here.</EmptyState></GlassCard>
      ) : (
        <div className="row g-3">
          {items.map((s, i) => (
            <div className="col-md-6 col-xl-4" key={s.id}>
              <GlassCard interactive className="p-3 h-100 d-flex flex-column position-relative" delay={Math.min(i, 8) * 0.03}>
                <div className="d-flex align-items-start gap-2">
                  <Pill icon="card-text">{s.length_label}</Pill>
                  <ModuleChip module={s.module} />
                  <button type="button" className="btn btn-ghost btn-sm btn-icon ms-auto position-relative" style={{ zIndex: 2 }} onClick={() => update.mutate({ id: s.id, is_favorite: !s.is_favorite })} aria-label="Toggle favourite">
                    <i className={`bi bi-star${s.is_favorite ? '-fill' : ''}`} style={s.is_favorite ? { color: 'var(--es-warning)' } : undefined} />
                  </button>
                </div>
                <Link to={`/learning/summaries/${s.id}`} className="stretched-link text-decoration-none"><h3 className="h6 fw-800 mt-2 mb-1 line-clamp-2" style={{ color: 'var(--es-text)' }}>{s.title}</h3></Link>
                <div className="small text-3 mb-2">{s.document?.title ?? 'Deleted document'} · {relative(s.created_at)}</div>
                <p className="small text-2 line-clamp-3 flex-grow-1">{s.excerpt}</p>
                <div className="d-flex flex-wrap gap-1 mb-2">{(s.keywords ?? []).slice(0, 4).map((k) => <span key={k.term} className="es-badge">{k.term}</span>)}</div>
                <div className="d-flex align-items-center gap-2 small text-3 position-relative" style={{ zIndex: 2 }}>
                  <span>{s.word_count} words · {s.compression}% of source</span>
                  <span className="ms-auto"><ExportMenu url={`/summaries/${s.id}/download`} formats={['pdf', 'docx', 'md', 'txt', 'json']} label="" variant="btn-ghost" /></span>
                  <button type="button" className="btn btn-ghost btn-sm" onClick={() => destroy(s)} aria-label="Delete"><i className="bi bi-trash3" /></button>
                </div>
              </GlassCard>
            </div>
          ))}
        </div>
      )}
      <Pagination meta={summaries.data?.meta} onPage={setPage} />
    </>
  );
}
