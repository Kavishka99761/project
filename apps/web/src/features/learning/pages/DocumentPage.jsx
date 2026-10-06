import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { PageHeader } from '@/components/layout/PageHeader';
import { ModuleChip, Pill, StatusBadge } from '@/components/ui/Badges';
import { GlassCard, Panel } from '@/components/ui/GlassCard';
import { Markdown } from '@/components/ui/Markdown';
import { SegmentedControl } from '@/components/ui/SegmentedControl';
import { SpeakButton } from '@/components/ui/SpeakButton';
import { EmptyState, ErrorState, Skeleton, Spinner } from '@/components/ui/States';
import { useConfirm } from '@/context/ConfirmContext';
import { useToast } from '@/context/ToastContext';
import { download, errorMessage, objectUrl } from '@/lib/api';
import { bytes, formatDateTime, relative } from '@/lib/format';
import {
  useDeleteDocument, useDocument, useDocumentAnalysis, useGenerateStudyAid, useGenerateSummary, useReextract, useStudyAids, useSummaries, useUpdateDocument,
} from '../api';
import { HighlightedReader } from '../components/HighlightedReader';
import { NoteEditorModal } from '../components/NoteEditorModal';
import { RenameDocumentModal } from '../components/RenameDocumentModal';

const TABS = [
  { value: 'read', label: 'Read', icon: 'book' },
  { value: 'original', label: 'Original', icon: 'file-earmark' },
  { value: 'summary', label: 'Summaries', icon: 'card-text' },
  { value: 'concepts', label: 'Concepts', icon: 'lightbulb' },
  { value: 'aids', label: 'Study aids', icon: 'stars' },
  { value: 'details', label: 'Details', icon: 'info-circle' },
];

function OriginalViewer({ document }) {
  const [url, setUrl] = useState(null);
  const [failed, setFailed] = useState(false);
  const isPdf = document.extension === 'pdf';

  useEffect(() => {
    if (!isPdf) return undefined;
    let revoked = null;
    objectUrl(`/documents/${document.id}/file`).then((u) => { revoked = u; setUrl(u); }).catch(() => setFailed(true));
    return () => revoked && URL.revokeObjectURL(revoked);
  }, [document.id, isPdf]);

  const save = () => download(`/documents/${document.id}/file`, { download: 1 }, document.original_name ?? 'document');

  if (!isPdf) {
    return (
      <EmptyState icon={document.icon} title={`${document.kind_label} file`} action={<button type="button" className="btn btn-accent" onClick={save}><i className="bi bi-download me-1" />Download original</button>}>
        Browsers can't display {document.extension?.toUpperCase()} files inline — use the Read tab for the extracted text, or download the original.
      </EmptyState>
    );
  }
  if (failed) return <EmptyState icon="file-earmark-x" title="Original file unavailable" />;
  if (!url) return <div className="py-5 text-center"><Spinner /></div>;

  return (
    <div>
      <div className="d-flex justify-content-end mb-2"><button type="button" className="btn btn-glass btn-sm" onClick={save}><i className="bi bi-download me-1" />Download</button></div>
      <iframe title={document.title} src={url} style={{ width: '100%', height: '78vh', border: 0, borderRadius: 16, background: '#fff' }} />
    </div>
  );
}

function SummaryWorkbench({ document }) {
  const toast = useToast();
  const navigate = useNavigate();
  const [length, setLength] = useState('medium');
  const [preview, setPreview] = useState(null);
  const generate = useGenerateSummary();
  const existing = useSummaries({ document_id: document.id, per_page: 20 });

  const run = async (save) => {
    try {
      const result = await generate.mutateAsync({ documentId: document.id, length, save });
      if (save) {
        toast.success('Summary saved', result.summary.title);
        navigate(`/learning/summaries/${result.summary.id}`);
      } else {
        setPreview(result.summary);
      }
    } catch (error) {
      toast.error('Could not summarise', errorMessage(error));
    }
  };

  return (
    <div className="row g-3">
      <div className="col-lg-8">
        <Panel title="Generate a summary" icon="magic" subtitle="TextRank picks the most central sentences; MMR keeps them non-repetitive.">
          <div className="d-flex flex-wrap gap-2 align-items-center mb-3">
            <SegmentedControl options={[{ value: 'short', label: 'Short' }, { value: 'medium', label: 'Medium' }, { value: 'detailed', label: 'Detailed' }]} value={length} onChange={(v) => { setLength(v); setPreview(null); }} />
            <button type="button" className="btn btn-glass" onClick={() => run(false)} disabled={generate.isPending}>
              {generate.isPending ? <Spinner className="me-1" /> : <i className="bi bi-eye me-1" />}Preview
            </button>
            <button type="button" className="btn btn-accent" onClick={() => run(true)} disabled={generate.isPending}><i className="bi bi-save me-1" />Generate & save</button>
          </div>
          {preview ? (
            <div>
              <div className="d-flex flex-wrap gap-2 mb-3">
                <Pill icon="text-paragraph">{preview.word_count} words</Pill>
                <Pill icon="arrows-collapse">{preview.compression}% of the original</Pill>
                <Pill icon="clock">{preview.reading_minutes} min read</Pill>
                <SpeakButton text={preview.content} />
              </div>
              <Markdown>{preview.content}</Markdown>
              {preview.bullet_points?.length > 0 && (
                <>
                  <h5 className="fw-800 mt-4 mb-2">Revision notes</h5>
                  {preview.bullet_points.map((group) => (
                    <div key={group.heading} className="mb-2">
                      <div className="fw-bold small">{group.heading}</div>
                      <ul className="small mb-1">{group.bullets.map((b) => <li key={b}>{b}</li>)}</ul>
                    </div>
                  ))}
                </>
              )}
              <button type="button" className="btn btn-accent mt-2" onClick={() => run(true)}><i className="bi bi-save me-1" />Save this summary</button>
            </div>
          ) : (
            <EmptyState icon="card-text" title="Choose a length and preview">Short = the essentials, Medium = a solid overview, Detailed = section by section.</EmptyState>
          )}
        </Panel>
      </div>
      <div className="col-lg-4">
        <Panel title="Saved for this document" icon="bookmark">
          {(existing.data?.data ?? []).length === 0 ? <div className="small text-3">No saved summaries yet.</div> : existing.data.data.map((s) => (
            <Link key={s.id} to={`/learning/summaries/${s.id}`} className="d-block p-2 rounded-3 text-decoration-none mb-1" style={{ color: 'inherit', background: 'var(--es-hover)' }}>
              <div className="fw-bold small">{s.length_label} summary</div>
              <div className="small text-3">{s.word_count} words · {relative(s.created_at)}</div>
            </Link>
          ))}
        </Panel>
      </div>
    </div>
  );
}

function StudyAidLauncher({ document }) {
  const navigate = useNavigate();
  const toast = useToast();
  const generate = useGenerateStudyAid();
  const aids = useStudyAids({ document_id: document.id });
  const kinds = [
    { type: 'flashcards', icon: 'card-text', title: 'Flashcards', text: 'Definitions and fill-in-the-blank cards from key sentences.' },
    { type: 'quiz', icon: 'patch-question', title: 'Quiz', text: 'Multiple-choice questions with explanations from your notes.' },
    { type: 'mindmap', icon: 'diagram-3', title: 'Mind map', text: 'Key concepts and how they connect, at a glance.' },
  ];

  const make = async (type) => {
    try {
      const aid = await generate.mutateAsync({ documentId: document.id, type });
      toast.success(`${aid.type_label} ready`, `${aid.item_count} items`);
      navigate(`/learning/study-aids/${aid.id}`);
    } catch (error) {
      toast.error('Could not generate', errorMessage(error));
    }
  };

  return (
    <>
      <div className="row g-3 mb-3">
        {kinds.map((kind) => (
          <div className="col-md-4" key={kind.type}>
            <GlassCard interactive className="p-4 h-100 d-flex flex-column">
              <i className={`bi bi-${kind.icon} fs-3`} style={{ color: 'var(--es-learning)' }} aria-hidden="true" />
              <h5 className="fw-800 mt-2">{kind.title}</h5>
              <p className="small text-2 flex-grow-1">{kind.text}</p>
              <button type="button" className="btn btn-accent btn-sm align-self-start position-relative" style={{ zIndex: 2 }} onClick={() => make(kind.type)} disabled={generate.isPending}>
                {generate.isPending && generate.variables?.type === kind.type ? <Spinner className="me-1" /> : <i className="bi bi-magic me-1" />}Generate
              </button>
            </GlassCard>
          </div>
        ))}
      </div>
      {(aids.data ?? []).length > 0 && (
        <Panel title="Previously generated" icon="clock-history">
          <div className="d-flex flex-wrap gap-2">
            {aids.data.map((aid) => (
              <Link key={aid.id} to={`/learning/study-aids/${aid.id}`} className="btn btn-glass btn-sm"><i className={`bi bi-${aid.icon} me-1`} />{aid.type_label} · {aid.item_count}</Link>
            ))}
          </div>
        </Panel>
      )}
    </>
  );
}

export default function DocumentPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const toast = useToast();
  const confirm = useConfirm();
  const [tab, setTab] = useState('read');
  const [highlights, setHighlights] = useState(true);
  const [renaming, setRenaming] = useState(false);
  const [editing, setEditing] = useState(false);
  const document = useDocument(id);
  const doc = document.data;
  const analysis = useDocumentAnalysis(id, Boolean(doc) && doc.extraction_status === 'completed');
  const reextract = useReextract();
  const update = useUpdateDocument();
  const remove = useDeleteDocument();

  if (document.isError) return <ErrorState error={document.error} onRetry={document.refetch} />;
  if (!doc) return <><Skeleton height={60} className="mb-3" /><Skeleton height={420} rounded={24} /></>;

  const destroy = async () => {
    if (!(await confirm({ title: `Move “${doc.title}” to trash?`, body: 'Summaries and study aids are kept. You can restore it from the trash.', confirmLabel: 'Move to trash' }))) return;
    await remove.mutateAsync(doc.id);
    toast.success('Moved to trash');
    navigate('/learning/library');
  };

  const runExtract = async () => {
    try {
      const result = await reextract.mutateAsync(doc.id);
      toast[result.extraction_status === 'completed' ? 'success' : 'warning']('Extraction finished', result.extraction_label);
    } catch (error) {
      toast.error('Extraction failed', errorMessage(error));
    }
  };

  const noText = doc.extraction_status !== 'completed';

  return (
    <>
      <PageHeader
        module="learning"
        title={doc.title}
        subtitle={doc.description}
        actions={(
          <>
            <SpeakButton text={doc.content?.slice(0, 6000)} label="Listen" />
            <button type="button" className="btn btn-glass btn-sm" onClick={() => update.mutate({ id: doc.id, is_favorite: !doc.is_favorite })}>
              <i className={`bi bi-star${doc.is_favorite ? '-fill' : ''} me-1`} style={doc.is_favorite ? { color: 'var(--es-warning)' } : undefined} />{doc.is_favorite ? 'Pinned' : 'Pin'}
            </button>
            <button type="button" className="btn btn-glass btn-sm" onClick={() => (doc.kind === 'note' ? setEditing(true) : setRenaming(true))}><i className="bi bi-pencil me-1" />{doc.kind === 'note' ? 'Edit note' : 'Rename'}</button>
            <button type="button" className="btn btn-accent btn-sm accent-study" onClick={() => navigate(`/study?start=1&document=${doc.id}${doc.module ? `&module=${doc.module.id}` : ''}`)}><i className="bi bi-stopwatch me-1" />Study this now</button>
            <button type="button" className="btn btn-ghost btn-sm" onClick={destroy} aria-label="Move to trash"><i className="bi bi-trash3" /></button>
          </>
        )}
      />

      <div className="d-flex flex-wrap align-items-center gap-2 mb-3">
        <ModuleChip module={doc.module} />
        {doc.topic && <Pill icon="tag">{doc.topic}</Pill>}
        <Pill icon={doc.icon}>{doc.kind_label}</Pill>
        <Pill icon="file-earmark-text">{doc.pages} pages</Pill>
        <Pill icon="type">{doc.word_count.toLocaleString()} words</Pill>
        <Pill icon="clock">{doc.reading_minutes} min read</Pill>
        {noText && <StatusBadge status="serious" icon="exclamation-triangle">{doc.extraction_label}</StatusBadge>}
      </div>

      <div className="mb-3 overflow-auto"><SegmentedControl options={TABS} value={tab} onChange={setTab} ariaLabel="Document sections" /></div>

      {noText && tab !== 'original' && tab !== 'details' ? (
        <GlassCard className="p-4">
          <EmptyState icon="file-earmark-x" title="No text could be extracted" action={<button type="button" className="btn btn-accent" onClick={runExtract} disabled={reextract.isPending}>{reextract.isPending && <Spinner className="me-1" />}Try extraction again</button>}>
            {doc.extraction_error ?? 'This file has no selectable text.'}
          </EmptyState>
        </GlassCard>
      ) : (
        <>
          {tab === 'read' && (
            <GlassCard variant="solid" className="p-4 p-lg-5">
              <div className="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
                <div className="chart-legend">
                  <span className="key"><span className="key-rect" style={{ background: 'color-mix(in srgb, var(--es-warning) 50%, transparent)' }} />Key sentence</span>
                  <span className="key"><span className="key-rect" style={{ background: 'color-mix(in srgb, var(--es-learning) 30%, transparent)' }} />Important</span>
                </div>
                <div className="form-check form-switch mb-0">
                  <input id="hl" className="form-check-input" type="checkbox" checked={highlights} onChange={(e) => setHighlights(e.target.checked)} />
                  <label className="form-check-label small" htmlFor="hl">Highlight important sentences</label>
                </div>
              </div>
              {analysis.isLoading ? <Skeleton height={300} /> : analysis.data ? <HighlightedReader blocks={analysis.data.blocks} showHighlights={highlights} /> : <Markdown>{doc.content}</Markdown>}
            </GlassCard>
          )}
          {tab === 'original' && <GlassCard className="p-3"><OriginalViewer document={doc} /></GlassCard>}
          {tab === 'summary' && <SummaryWorkbench document={doc} />}
          {tab === 'concepts' && (
            analysis.isLoading ? <Skeleton height={300} rounded={24} /> : (
              <div className="row g-3">
                <div className="col-lg-7">
                  <Panel title="Key concepts" icon="lightbulb" subtitle="Definitions found in the text, plus the strongest key phrases">
                    {(analysis.data?.concepts ?? []).map((c) => (
                      <div key={c.term} className="p-3 rounded-4 mb-2" style={{ background: 'var(--es-hover)' }}>
                        <div className="d-flex justify-content-between gap-2">
                          <strong>{c.term}</strong>
                          <StatusBadge status={c.source === 'definition' ? 'good' : 'warning'} icon={c.source === 'definition' ? 'book' : 'key'}>{c.source === 'definition' ? 'Definition' : 'Key phrase'}</StatusBadge>
                        </div>
                        <div className="small text-2 mt-1">{c.definition}</div>
                      </div>
                    ))}
                  </Panel>
                </div>
                <div className="col-lg-5">
                  <Panel title="Keywords" icon="tags" subtitle="Ranked by frequency, phrase length and headings">
                    <div className="keyword-cloud">
                      {(analysis.data?.keywords ?? []).map((k) => <span key={k.term} className="kw" style={{ '--w': k.score, fontSize: `${0.74 + k.score * 0.36}rem` }}>{k.term}</span>)}
                    </div>
                    {analysis.data?.headings?.length > 0 && (
                      <>
                        <div className="small fw-bold text-3 text-uppercase mt-4 mb-2" style={{ letterSpacing: '0.08em' }}>Outline</div>
                        <ol className="small ps-3 mb-0">{analysis.data.headings.map((h) => <li key={h}>{h}</li>)}</ol>
                      </>
                    )}
                  </Panel>
                </div>
              </div>
            )
          )}
          {tab === 'aids' && <StudyAidLauncher document={doc} />}
        </>
      )}

      {tab === 'details' && (
        <GlassCard variant="solid" className="p-4">
          <dl className="row mb-0">
            {[
              ['Title', doc.title],
              ['Original file', doc.original_name ?? '—'],
              ['Type', `${doc.kind_label} (${doc.extension?.toUpperCase()})`],
              ['Module', doc.module ? `${doc.module.code} — ${doc.module.name}` : 'Unfiled'],
              ['Topic', doc.topic ?? '—'],
              ['File size', bytes(doc.size_bytes)],
              ['Pages', doc.pages],
              ['Words', doc.word_count.toLocaleString()],
              ['Reading time', `${doc.reading_minutes} min`],
              ['Text extraction', `${doc.extraction_label}${doc.extracted_at ? ` · ${formatDateTime(doc.extracted_at)}` : ''}`],
              ['Summaries', doc.summaries_count ?? 0],
              ['Study aids', doc.study_aids_count ?? 0],
              ['Uploaded', formatDateTime(doc.created_at)],
              ['Last opened', doc.last_opened_at ? relative(doc.last_opened_at) : '—'],
            ].map(([label, value]) => (
              <div className="col-md-6 d-flex border-bottom py-2" key={label} style={{ borderColor: 'var(--es-divider)' }}>
                <dt className="text-3 fw-semibold small" style={{ width: 150 }}>{label}</dt>
                <dd className="mb-0 small fw-semibold">{value}</dd>
              </div>
            ))}
          </dl>
          <div className="d-flex gap-2 mt-4">
            <button type="button" className="btn btn-glass btn-sm" onClick={runExtract} disabled={reextract.isPending}>{reextract.isPending ? <Spinner className="me-1" /> : <i className="bi bi-arrow-repeat me-1" />}Re-extract text</button>
            <button type="button" className="btn btn-glass btn-sm" onClick={() => download(`/documents/${doc.id}/file`, { download: 1 }, doc.original_name ?? 'document')}><i className="bi bi-download me-1" />Download original</button>
          </div>
        </GlassCard>
      )}

      {renaming && <RenameDocumentModal document={doc} onHide={() => setRenaming(false)} />}
      <NoteEditorModal show={editing} onHide={() => setEditing(false)} document={doc} />
    </>
  );
}
