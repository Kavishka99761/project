import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { BarChart } from '@/components/charts/BarChart';
import { ChartCard } from '@/components/charts/ChartCard';
import { PageHeader } from '@/components/layout/PageHeader';
import { Pill } from '@/components/ui/Badges';
import { Panel } from '@/components/ui/GlassCard';
import { StatTile } from '@/components/ui/StatTile';
import { EmptyState, ErrorState, SkeletonGrid } from '@/components/ui/States';
import { seriesSlot, themed } from '@/config/palette';
import { useTheme } from '@/context/ThemeContext';
import { bytes, compact, relative } from '@/lib/format';
import { useLearningDashboard } from '../api';
import { NoteEditorModal } from '../components/NoteEditorModal';
import { UploadDocumentModal } from '../components/UploadDocumentModal';

export default function LearningDashboardPage() {
  const { data, isError, error, refetch } = useLearningDashboard();
  const { theme } = useTheme();
  const navigate = useNavigate();
  const [upload, setUpload] = useState(false);
  const [note, setNote] = useState(false);

  const actions = (
    <>
      <button type="button" className="btn btn-glass" onClick={() => setNote(true)}><i className="bi bi-pencil-square me-1" />Write note</button>
      <button type="button" className="btn btn-accent" onClick={() => setUpload(true)}><i className="bi bi-cloud-arrow-up me-1" />Upload material</button>
    </>
  );

  if (isError) return <ErrorState error={error} onRetry={refetch} />;

  return (
    <>
      <PageHeader module="learning" title="Learning hub" subtitle="Upload lecture notes, PDFs and Word files — EDU-SMART extracts the text, summarises it and builds study aids for you." actions={actions} />
      {!data ? <SkeletonGrid count={8} /> : (
        <>
          <div className="row g-3 mb-3">
            <div className="col-6 col-xl-3"><StatTile label="Documents" value={data.stats.documents} icon="files" accent="learning" foot={`${data.stats.favorites} pinned`} to="/learning/library" /></div>
            <div className="col-6 col-xl-3"><StatTile label="Saved summaries" value={data.stats.summaries} icon="card-text" accent="learning" foot={`${data.stats.study_aids} study aids`} delay={0.05} to="/learning/summaries" /></div>
            <div className="col-6 col-xl-3"><StatTile label="Words processed" value={data.stats.words} format={compact} icon="type" accent="learning" foot={`${data.stats.pages} pages`} delay={0.1} /></div>
            <div className="col-6 col-xl-3"><StatTile label="Storage used" value={bytes(data.stats.storage_bytes)} icon="hdd" accent="learning" foot={data.stats.needs_attention ? `${data.stats.needs_attention} need attention` : 'All text extracted'} delay={0.15} /></div>
          </div>

          <div className="row g-3 mb-3">
            <div className="col-lg-7">
              <ChartCard
                title="Materials by module"
                subtitle="Documents filed under each module"
                table={{ columns: ['Module', 'Documents', 'Pages'], rows: data.by_module.map((m) => [m.code, m.documents, m.pages]) }}
              >
                {data.by_module.length ? (
                  <BarChart
                    horizontal
                    height={Math.max(160, data.by_module.length * 46)}
                    labels={data.by_module.map((m) => m.code)}
                    series={[{ label: 'Documents', data: data.by_module.map((m) => m.documents), color: data.by_module.map((m) => themed(m.color, theme)) }]}
                    format={(v) => Math.round(v)}
                  />
                ) : <EmptyState icon="collection" title="No documents yet" />}
              </ChartCard>
            </div>
            <div className="col-lg-5">
              <Panel title="Your vocabulary" icon="tags" subtitle="Most important keywords across all materials" className="accent-learning">
                {data.keywords.length === 0 ? <EmptyState icon="tags" title="No keywords yet">Upload notes to build your vocabulary.</EmptyState> : (
                  <div className="keyword-cloud">
                    {data.keywords.map((k) => {
                      const max = data.keywords[0].weight || 1;
                      const w = k.weight / max;
                      return (
                        <button type="button" key={k.term} className="kw border-0" style={{ '--w': w, fontSize: `${0.72 + w * 0.4}rem` }} onClick={() => navigate(`/learning/library?q=${encodeURIComponent(k.term)}`)}>
                          {k.term}
                        </button>
                      );
                    })}
                  </div>
                )}
                {data.topics.length > 0 && (
                  <>
                    <div className="small fw-bold text-3 text-uppercase mt-4 mb-2" style={{ letterSpacing: '0.08em' }}>Topics</div>
                    <div className="d-flex flex-wrap gap-2">
                      {data.topics.map((t) => (
                        <Link key={t.topic} to={`/learning/library?topic=${encodeURIComponent(t.topic)}`} className="text-decoration-none"><Pill icon="tag">{t.topic} · {t.count}</Pill></Link>
                      ))}
                    </div>
                  </>
                )}
              </Panel>
            </div>
          </div>

          <div className="row g-3">
            <div className="col-lg-6">
              <Panel title="Recently added" icon="clock-history" actions={<Link to="/learning/library" className="btn btn-ghost btn-sm">Library</Link>}>
                {data.recent_documents.length === 0 ? (
                  <EmptyState icon="cloud-arrow-up" title="Your library is empty" action={<button type="button" className="btn btn-accent btn-sm" onClick={() => setUpload(true)}>Upload your first document</button>}>
                    PDF, Word, PowerPoint, text and Markdown are supported.
                  </EmptyState>
                ) : data.recent_documents.map((d) => (
                  <Link key={d.id} to={`/learning/documents/${d.id}`} className="d-flex align-items-center gap-3 p-2 rounded-4 text-decoration-none" style={{ color: 'inherit' }}>
                    <span className="d-grid rounded-3 flex-shrink-0" style={{ width: 38, height: 38, placeItems: 'center', background: 'var(--es-hover)' }}><i className={`bi bi-file-earmark-${d.kind === 'pdf' ? 'pdf' : d.kind === 'word' ? 'word' : 'text'}`} /></span>
                    <span className="flex-grow-1 min-w-0"><span className="d-block fw-bold text-truncate">{d.title}</span><span className="small text-3">{d.module ?? 'Unfiled'} · {d.pages} pages · {relative(d.created_at)}</span></span>
                    <i className="bi bi-chevron-right text-3" />
                  </Link>
                ))}
              </Panel>
            </div>
            <div className="col-lg-6">
              <Panel title="Latest summaries" icon="card-text" actions={<Link to="/learning/summaries" className="btn btn-ghost btn-sm">All summaries</Link>}>
                {data.recent_summaries.length === 0 ? <EmptyState icon="card-text" title="No summaries yet">Open a document and generate a short, medium or detailed summary.</EmptyState> : data.recent_summaries.map((s, i) => (
                  <Link key={s.id} to={`/learning/summaries/${s.id}`} className="d-flex align-items-center gap-3 p-2 rounded-4 text-decoration-none" style={{ color: 'inherit' }}>
                    <span className="d-grid rounded-3 flex-shrink-0 text-white fw-800 small" style={{ width: 38, height: 38, placeItems: 'center', background: seriesSlot(i % 3, theme) }}>{s.length[0].toUpperCase()}</span>
                    <span className="flex-grow-1 min-w-0"><span className="d-block fw-bold text-truncate">{s.title}</span><span className="small text-3">{s.word_count} words · {relative(s.created_at)}</span></span>
                    <i className="bi bi-chevron-right text-3" />
                  </Link>
                ))}
              </Panel>
            </div>
          </div>
        </>
      )}
      <UploadDocumentModal show={upload} onHide={() => setUpload(false)} onUploaded={(d) => navigate(`/learning/documents/${d.id}`)} />
      <NoteEditorModal show={note} onHide={() => setNote(false)} onSaved={(d) => navigate(`/learning/documents/${d.id}`)} />
    </>
  );
}
