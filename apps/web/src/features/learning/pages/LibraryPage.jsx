import { useEffect, useMemo, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { PageHeader } from '@/components/layout/PageHeader';
import { ModuleChip } from '@/components/ui/Badges';
import { ExportMenu } from '@/components/ui/ExportMenu';
import { GlassCard } from '@/components/ui/GlassCard';
import { ModuleSelect } from '@/components/ui/ModuleSelect';
import { Pagination } from '@/components/ui/Pagination';
import { SegmentedControl } from '@/components/ui/SegmentedControl';
import { EmptyState, ErrorState, SkeletonGrid } from '@/components/ui/States';
import { useConfirm } from '@/context/ConfirmContext';
import { useToast } from '@/context/ToastContext';
import { useDebounce } from '@/hooks/useDebounce';
import { useOptions } from '@/hooks/usePlatform';
import { errorMessage } from '@/lib/api';
import { bytes, formatDate } from '@/lib/format';
import { useDeleteDocument, useDocuments, useTopics, useUpdateDocument } from '../api';
import { DocumentCard } from '../components/DocumentCard';
import { NoteEditorModal } from '../components/NoteEditorModal';
import { RenameDocumentModal } from '../components/RenameDocumentModal';
import { UploadDocumentModal } from '../components/UploadDocumentModal';

export default function LibraryPage() {
  const [params, setParams] = useSearchParams();
  const navigate = useNavigate();
  const toast = useToast();
  const confirm = useConfirm();
  const { data: options } = useOptions();
  const { data: topics = [] } = useTopics();
  const [search, setSearch] = useState(params.get('q') ?? '');
  const debounced = useDebounce(search, 300);
  const [view, setView] = useState('grid');
  const [groupByTopic, setGroupByTopic] = useState(false);
  const [renaming, setRenaming] = useState(null);
  const [upload, setUpload] = useState(params.get('upload') === '1');
  const [note, setNote] = useState(params.get('note') === '1');

  const filters = {
    q: debounced || undefined,
    module_id: params.get('module') ?? undefined,
    topic: params.get('topic') ?? undefined,
    kind: params.get('kind') ?? undefined,
    favorite: params.get('favorite') === '1' ? 1 : undefined,
    sort: params.get('sort') ?? 'recent',
    page: Number(params.get('page') ?? 1),
    per_page: 24,
  };
  const documents = useDocuments(filters);
  const update = useUpdateDocument();
  const remove = useDeleteDocument();

  useEffect(() => {
    const next = new URLSearchParams(params);
    if (debounced) next.set('q', debounced);
    else next.delete('q');
    next.delete('page');
    if (next.toString() !== params.toString()) setParams(next, { replace: true });
  }, [debounced]); // eslint-disable-line react-hooks/exhaustive-deps

  const setFilter = (key, value) => {
    const next = new URLSearchParams(params);
    if (value === null || value === undefined || value === '') next.delete(key);
    else next.set(key, value);
    next.delete('page');
    setParams(next);
  };

  const toggleFavorite = (doc) => update.mutate({ id: doc.id, is_favorite: !doc.is_favorite });
  const destroy = async (doc) => {
    if (!(await confirm({ title: `Move “${doc.title}” to trash?`, body: 'You can restore it from the trash for 30 days.', confirmLabel: 'Move to trash' }))) return;
    try {
      await remove.mutateAsync(doc.id);
      toast.success('Moved to trash', doc.title);
    } catch (error) {
      toast.error('Could not delete', errorMessage(error));
    }
  };

  const items = documents.data?.data ?? [];
  const grouped = useMemo(() => {
    if (!groupByTopic) return [['', items]];
    const map = new Map();
    items.forEach((doc) => {
      const key = doc.topic ?? 'No topic';
      map.set(key, [...(map.get(key) ?? []), doc]);
    });
    return [...map.entries()].sort(([a], [b]) => a.localeCompare(b));
  }, [items, groupByTopic]);

  const activeFilters = ['module', 'topic', 'kind', 'favorite'].filter((key) => params.get(key));

  return (
    <>
      <PageHeader
        module="learning"
        title="Library"
        subtitle="Every lecture note and learning document, organised by module and topic."
        actions={(
          <>
            <ExportMenu url="/exports/documents" label="Export list" />
            <button type="button" className="btn btn-glass" onClick={() => setNote(true)}><i className="bi bi-pencil-square me-1" />Write note</button>
            <button type="button" className="btn btn-accent" onClick={() => setUpload(true)}><i className="bi bi-cloud-arrow-up me-1" />Upload</button>
          </>
        )}
      />

      <GlassCard className="p-3 mb-3" variant="strong">
        <div className="row g-2 align-items-center">
          <div className="col-lg-4">
            <div className="input-glass">
              <i className="bi bi-search" aria-hidden="true" />
              <input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search titles, topics and full text…" aria-label="Search documents" />
              {search && <button type="button" className="btn btn-ghost btn-sm" onClick={() => setSearch('')} aria-label="Clear search"><i className="bi bi-x" /></button>}
            </div>
          </div>
          <div className="col-6 col-lg-2">
            <ModuleSelect includeAll allLabel="All modules" value={params.get('module') ? Number(params.get('module')) : null} onChange={(id) => setFilter('module', id ?? '')} />
          </div>
          <div className="col-6 col-lg-2">
            <select className="form-select" value={params.get('topic') ?? ''} onChange={(e) => setFilter('topic', e.target.value)} aria-label="Filter by topic">
              <option value="">All topics</option>
              {topics.map((t) => <option key={t.topic} value={t.topic}>{t.topic} ({t.documents})</option>)}
            </select>
          </div>
          <div className="col-6 col-lg-2">
            <select className="form-select" value={params.get('kind') ?? ''} onChange={(e) => setFilter('kind', e.target.value)} aria-label="Filter by type">
              <option value="">All types</option>
              {(options?.document_kinds ?? []).map((k) => <option key={k.value} value={k.value}>{k.label}</option>)}
            </select>
          </div>
          <div className="col-6 col-lg-2">
            <select className="form-select" value={params.get('sort') ?? 'recent'} onChange={(e) => setFilter('sort', e.target.value)} aria-label="Sort">
              <option value="recent">Newest first</option>
              <option value="opened">Recently opened</option>
              <option value="title">Title A–Z</option>
              <option value="size">Largest first</option>
              <option value="oldest">Oldest first</option>
            </select>
          </div>
        </div>
        <div className="d-flex flex-wrap align-items-center gap-2 mt-3">
          <SegmentedControl size="sm" options={[{ value: 'grid', label: 'Cards', icon: 'grid-3x2-gap' }, { value: 'list', label: 'Table', icon: 'list-ul' }]} value={view} onChange={setView} />
          <button type="button" className={`btn btn-glass btn-sm ${groupByTopic ? 'active' : ''}`} onClick={() => setGroupByTopic(!groupByTopic)} aria-pressed={groupByTopic}><i className="bi bi-collection me-1" />Group by topic</button>
          <button type="button" className={`btn btn-glass btn-sm ${params.get('favorite') ? 'active' : ''}`} onClick={() => setFilter('favorite', params.get('favorite') ? '' : '1')}><i className="bi bi-star me-1" />Pinned</button>
          {activeFilters.length > 0 && <button type="button" className="btn btn-ghost btn-sm" onClick={() => setParams({})}><i className="bi bi-x-circle me-1" />Clear filters</button>}
          <span className="ms-auto small text-3">{documents.data?.meta?.total ?? 0} documents</span>
        </div>
      </GlassCard>

      {documents.isError ? <ErrorState error={documents.error} onRetry={documents.refetch} /> : documents.isLoading ? <SkeletonGrid count={8} height={210} columns="col-12 col-sm-6 col-xl-3" /> : items.length === 0 ? (
        <GlassCard className="p-4">
          <EmptyState icon="search" title={debounced || activeFilters.length ? 'No documents match' : 'Your library is empty'} action={<button type="button" className="btn btn-accent" onClick={() => setUpload(true)}>Upload material</button>}>
            {debounced ? `Nothing contains “${debounced}”. Try another word or clear the filters.` : 'Upload lecture notes, PDFs or Word documents to get started.'}
          </EmptyState>
        </GlassCard>
      ) : view === 'grid' ? (
        grouped.map(([topic, docs]) => (
          <section key={topic || 'all'} className="mb-4">
            {groupByTopic && <h3 className="h6 fw-800 mb-3"><i className="bi bi-tag me-2 text-3" />{topic} <span className="text-3 fw-semibold">· {docs.length}</span></h3>}
            <div className="row g-3">
              {docs.map((doc, i) => (
                <div className="col-12 col-sm-6 col-xl-4 col-xxl-3" key={doc.id}>
                  <DocumentCard document={doc} delay={Math.min(i, 8) * 0.03} onRename={setRenaming} onToggleFavorite={toggleFavorite} onDelete={destroy} />
                </div>
              ))}
            </div>
          </section>
        ))
      ) : (
        <GlassCard variant="solid" className="p-2">
          <div className="table-responsive">
            <table className="table table-glass table-hover align-middle">
              <thead><tr><th>Title</th><th>Module</th><th>Topic</th><th>Type</th><th className="num">Pages</th><th className="num">Words</th><th className="num">Size</th><th>Uploaded</th><th /></tr></thead>
              <tbody>
                {items.map((doc) => (
                  <tr key={doc.id} className="cursor-pointer" onClick={() => navigate(`/learning/documents/${doc.id}`)}>
                    <td className="fw-semibold"><i className={`bi bi-${doc.icon} me-2`} style={{ color: doc.color }} />{doc.title}</td>
                    <td><ModuleChip module={doc.module} /></td>
                    <td>{doc.topic ?? '—'}</td>
                    <td>{doc.kind_label}</td>
                    <td className="num">{doc.pages}</td>
                    <td className="num">{doc.word_count.toLocaleString()}</td>
                    <td className="num">{bytes(doc.size_bytes)}</td>
                    <td className="text-nowrap">{formatDate(doc.created_at)}</td>
                    <td className="text-end" onClick={(e) => e.stopPropagation()}>
                      <button type="button" className="btn btn-ghost btn-sm" onClick={() => setRenaming(doc)} aria-label="Rename"><i className="bi bi-pencil" /></button>
                      <button type="button" className="btn btn-ghost btn-sm" onClick={() => destroy(doc)} aria-label="Delete"><i className="bi bi-trash3" /></button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </GlassCard>
      )}
      <Pagination meta={documents.data?.meta} onPage={(page) => setFilter('page', page)} />

      <UploadDocumentModal show={upload} onHide={() => setUpload(false)} defaultModuleId={params.get('module') ? Number(params.get('module')) : null} onUploaded={(d) => navigate(`/learning/documents/${d.id}`)} />
      <NoteEditorModal show={note} onHide={() => setNote(false)} onSaved={(d) => navigate(`/learning/documents/${d.id}`)} />
      <RenameDocumentModal document={renaming} onHide={() => setRenaming(null)} />
    </>
  );
}
