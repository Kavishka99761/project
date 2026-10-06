import { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { PageHeader } from '@/components/layout/PageHeader';
import { ModuleChip, Pill, StatusBadge } from '@/components/ui/Badges';
import { ExportMenu } from '@/components/ui/ExportMenu';
import { GlassCard, Panel } from '@/components/ui/GlassCard';
import { Markdown } from '@/components/ui/Markdown';
import { SpeakButton } from '@/components/ui/SpeakButton';
import { ErrorState, Skeleton } from '@/components/ui/States';
import { useConfirm } from '@/context/ConfirmContext';
import { useToast } from '@/context/ToastContext';
import { formatDateTime } from '@/lib/format';
import { useDeleteSummary, useSummary, useUpdateSummary } from '../api';

export default function SummaryPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const toast = useToast();
  const confirm = useConfirm();
  const { data: summary, isError, error, refetch } = useSummary(id);
  const update = useUpdateSummary();
  const remove = useDeleteSummary();
  const [renaming, setRenaming] = useState(null);

  if (isError) return <ErrorState error={error} onRetry={refetch} />;
  if (!summary) return <Skeleton height={480} rounded={24} />;

  const copy = async () => {
    await navigator.clipboard.writeText(summary.content);
    toast.success('Summary copied to clipboard');
  };

  const saveTitle = async (event) => {
    event.preventDefault();
    await update.mutateAsync({ id: summary.id, title: renaming });
    setRenaming(null);
    refetch();
  };

  const destroy = async () => {
    if (!(await confirm({ title: 'Delete this summary?', confirmLabel: 'Move to trash' }))) return;
    await remove.mutateAsync(summary.id);
    toast.success('Summary moved to trash');
    navigate('/learning/summaries');
  };

  return (
    <>
      <PageHeader
        module="learning"
        title={renaming === null ? summary.title : (
          <form onSubmit={saveTitle} className="d-flex gap-2"><input className="form-control form-control-lg" value={renaming} onChange={(e) => setRenaming(e.target.value)} autoFocus /><button type="submit" className="btn btn-primary">Save</button></form>
        )}
        actions={(
          <>
            <SpeakButton text={summary.content} />
            <button type="button" className="btn btn-glass btn-sm" onClick={copy}><i className="bi bi-clipboard me-1" />Copy</button>
            <button type="button" className="btn btn-glass btn-sm" onClick={() => setRenaming(summary.title)}><i className="bi bi-pencil me-1" />Rename</button>
            <ExportMenu url={`/summaries/${summary.id}/download`} formats={['pdf', 'docx', 'md', 'txt', 'json']} label="Download" variant="btn-accent" />
            <button type="button" className="btn btn-ghost btn-sm" onClick={destroy} aria-label="Delete"><i className="bi bi-trash3" /></button>
          </>
        )}
      />
      <div className="d-flex flex-wrap gap-2 mb-3">
        <Pill icon="card-text">{summary.length_label}</Pill>
        <ModuleChip module={summary.module} />
        {summary.document && <Link to={`/learning/documents/${summary.document.id}`} className="text-decoration-none"><Pill icon="file-earmark-text">{summary.document.title}</Pill></Link>}
        <Pill icon="text-paragraph">{summary.word_count} words</Pill>
        <Pill icon="arrows-collapse">{summary.compression}% of {summary.source_word_count.toLocaleString()} words</Pill>
        <Pill icon="cpu">{summary.method === 'llm' ? 'LLM-polished' : 'TextRank extractive'}</Pill>
        <Pill icon="calendar">{formatDateTime(summary.created_at)}</Pill>
      </div>

      <div className="row g-3">
        <div className="col-lg-7">
          <GlassCard variant="solid" className="p-4 p-lg-5 mb-3"><Markdown className="reader">{summary.content}</Markdown></GlassCard>
          {summary.highlights?.length > 0 && (
            <Panel title="Important sentences" icon="highlighter" subtitle="Highlighted from the full document">
              {summary.highlights.slice(0, 10).map((h) => (
                <div key={h.index} className="d-flex gap-2 mb-2 small">
                  <StatusBadge status={h.level === 'key' ? 'warning' : 'good'} icon={h.level === 'key' ? 'star-fill' : 'dot'}>{h.level === 'key' ? 'Key' : 'Important'}</StatusBadge>
                  <span className="text-2">{h.text}</span>
                </div>
              ))}
            </Panel>
          )}
        </div>
        <div className="col-lg-5">
          <Panel title="Revision notes" icon="list-check" className="mb-3">
            {(summary.bullet_points ?? []).map((group) => (
              <div key={group.heading} className="mb-3">
                <div className="fw-bold small mb-1">{group.heading}</div>
                <ul className="small text-2 mb-0 ps-3">{group.bullets.map((b) => <li key={b} className="mb-1">{b}</li>)}</ul>
              </div>
            ))}
          </Panel>
          <Panel title="Key concepts" icon="lightbulb" className="mb-3">
            {(summary.key_concepts ?? []).map((c) => (
              <div key={c.term} className="mb-2 small"><strong>{c.term}</strong> — <span className="text-2">{c.definition}</span></div>
            ))}
          </Panel>
          <Panel title="Keywords" icon="tags">
            <div className="keyword-cloud">{(summary.keywords ?? []).map((k) => <span key={k.term} className="kw" style={{ '--w': k.score }}>{k.term}</span>)}</div>
          </Panel>
        </div>
      </div>
    </>
  );
}
