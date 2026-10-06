import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { PageHeader } from '@/components/layout/PageHeader';
import { GlassCard } from '@/components/ui/GlassCard';
import { SegmentedControl } from '@/components/ui/SegmentedControl';
import { EmptyState, ErrorState, SkeletonGrid, Spinner } from '@/components/ui/States';
import { useConfirm } from '@/context/ConfirmContext';
import { useToast } from '@/context/ToastContext';
import { errorMessage } from '@/lib/api';
import { relative } from '@/lib/format';
import { useDeleteStudyAid, useDocuments, useGenerateStudyAid, useStudyAids } from '../api';

export default function StudyAidsPage() {
  const navigate = useNavigate();
  const toast = useToast();
  const confirm = useConfirm();
  const [type, setType] = useState('');
  const [documentId, setDocumentId] = useState('');
  const [newType, setNewType] = useState('flashcards');
  const aids = useStudyAids({ type: type || undefined });
  const documents = useDocuments({ per_page: 100, sort: 'title' });
  const generate = useGenerateStudyAid();
  const remove = useDeleteStudyAid();

  const make = async (event) => {
    event.preventDefault();
    try {
      const aid = await generate.mutateAsync({ documentId, type: newType });
      navigate(`/learning/study-aids/${aid.id}`);
    } catch (error) {
      toast.error('Could not generate', errorMessage(error));
    }
  };

  const destroy = async (aid) => {
    if (!(await confirm({ title: `Delete “${aid.title}”?`, confirmLabel: 'Delete' }))) return;
    await remove.mutateAsync(aid.id);
  };

  return (
    <>
      <PageHeader module="learning" title="Study aids" subtitle="Flashcards, quizzes and mind maps generated from your own notes — like NotebookLM, fully on your data." />
      <GlassCard variant="strong" className="p-3 mb-3">
        <form className="row g-2 align-items-end" onSubmit={make}>
          <div className="col-md-5">
            <label className="form-label" htmlFor="aid-doc">Document</label>
            <select id="aid-doc" className="form-select" value={documentId} onChange={(e) => setDocumentId(e.target.value)} required>
              <option value="">Choose a document…</option>
              {(documents.data?.data ?? []).map((d) => <option key={d.id} value={d.id}>{d.title}</option>)}
            </select>
          </div>
          <div className="col-md-auto">
            <label className="form-label d-block">Type</label>
            <SegmentedControl options={[{ value: 'flashcards', label: 'Flashcards', icon: 'card-text' }, { value: 'quiz', label: 'Quiz', icon: 'patch-question' }, { value: 'mindmap', label: 'Mind map', icon: 'diagram-3' }]} value={newType} onChange={setNewType} />
          </div>
          <div className="col-md-auto">
            <button type="submit" className="btn btn-accent" disabled={!documentId || generate.isPending}>{generate.isPending ? <Spinner className="me-1" /> : <i className="bi bi-magic me-1" />}Generate</button>
          </div>
        </form>
      </GlassCard>

      <div className="mb-3"><SegmentedControl size="sm" options={[{ value: '', label: 'All' }, { value: 'flashcards', label: 'Flashcards' }, { value: 'quiz', label: 'Quizzes' }, { value: 'mindmap', label: 'Mind maps' }]} value={type} onChange={setType} /></div>

      {aids.isError ? <ErrorState error={aids.error} onRetry={aids.refetch} /> : aids.isLoading ? <SkeletonGrid count={6} height={150} columns="col-md-6 col-xl-4" /> : (aids.data ?? []).length === 0 ? (
        <GlassCard className="p-4"><EmptyState icon="stars" title="No study aids yet">Pick a document above and generate flashcards, a quiz or a mind map.</EmptyState></GlassCard>
      ) : (
        <div className="row g-3">
          {aids.data.map((aid, i) => (
            <div className="col-md-6 col-xl-4" key={aid.id}>
              <GlassCard interactive className="p-3 h-100 position-relative" delay={Math.min(i, 8) * 0.03}>
                <div className="d-flex align-items-center gap-3">
                  <span className="d-grid rounded-4 fs-4 flex-shrink-0" style={{ width: 52, height: 52, placeItems: 'center', color: 'var(--es-learning-ink)', background: 'color-mix(in srgb, var(--es-learning) 14%, transparent)' }}><i className={`bi bi-${aid.icon}`} /></span>
                  <div className="min-w-0 flex-grow-1">
                    <Link to={`/learning/study-aids/${aid.id}`} className="stretched-link text-decoration-none"><div className="fw-800 text-truncate" style={{ color: 'var(--es-text)' }}>{aid.type_label}</div></Link>
                    <div className="small text-3 text-truncate">{aid.document?.title}</div>
                    <div className="small text-3">{aid.item_count} items · {relative(aid.created_at)}</div>
                  </div>
                  <button type="button" className="btn btn-ghost btn-sm position-relative" style={{ zIndex: 2 }} onClick={() => destroy(aid)} aria-label="Delete"><i className="bi bi-trash3" /></button>
                </div>
              </GlassCard>
            </div>
          ))}
        </div>
      )}
    </>
  );
}
