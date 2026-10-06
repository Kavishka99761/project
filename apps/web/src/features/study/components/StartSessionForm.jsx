import { useQuery } from '@tanstack/react-query';
import { useEffect, useMemo, useState } from 'react';
import { Panel } from '@/components/ui/GlassCard';
import { ModuleSelect } from '@/components/ui/ModuleSelect';
import { Spinner } from '@/components/ui/States';
import { useToast } from '@/context/ToastContext';
import { useOptions } from '@/hooks/usePlatform';
import { errorMessage, get } from '@/lib/api';
import { useStartSession } from '../api';

const PRESETS = [25, 45, 50, 60, 90];

/**
 * Start a study session: module, activity, planned time, goal — optionally
 * linked to JITHMI's priority assignment and BETHMI's learning material.
 */
export function StartSessionForm({ initial = {}, focusPeriod, recommendation }) {
  const toast = useToast();
  const { data: options } = useOptions();
  const start = useStartSession();
  const [form, setForm] = useState({
    module_id: initial.module_id ?? null,
    activity: initial.activity ?? 'revision',
    planned_minutes: initial.planned_minutes ?? focusPeriod?.minutes ?? 25,
    goal: initial.goal ?? '',
    assignment_id: initial.assignment_id ?? null,
    document_id: initial.document_id ?? null,
  });

  useEffect(() => {
    setForm((f) => ({ ...f, ...Object.fromEntries(Object.entries(initial).filter(([, v]) => v !== undefined && v !== null)) }));
  }, [initial.module_id, initial.assignment_id, initial.document_id, initial.planned_minutes, initial.activity]); // eslint-disable-line react-hooks/exhaustive-deps

  const assignments = useQuery({ queryKey: ['assignments', 'list', { bucket: 'active' }], queryFn: () => get('/assignments', { bucket: 'active' }), staleTime: 30_000 });
  const documents = useQuery({ queryKey: ['learning', 'documents', { module: form.module_id, picker: true }], queryFn: () => get('/documents', { module_id: form.module_id ?? undefined, per_page: 100, sort: 'opened' }), staleTime: 30_000 });

  const moduleAssignments = useMemo(() => (assignments.data ?? []).filter((a) => !form.module_id || a.module?.id === form.module_id), [assignments.data, form.module_id]);

  const submit = async (event) => {
    event.preventDefault();
    try {
      await start.mutateAsync({ ...form, goal: form.goal || null });
      toast.success('Session started', 'Stay focused — EDU-SMART will suggest breaks when you need them.');
      if ('Notification' in window && Notification.permission === 'default') Notification.requestPermission();
    } catch (error) {
      toast.error('Could not start', errorMessage(error));
    }
  };

  const useRecommendation = () => {
    if (!recommendation) return;
    setForm((f) => ({
      ...f,
      module_id: recommendation.module?.id ?? f.module_id,
      assignment_id: recommendation.assignment?.id ?? null,
      document_id: recommendation.document?.id ?? null,
      activity: recommendation.activity,
      planned_minutes: recommendation.planned_minutes,
      goal: recommendation.assignment ? `Work on ${recommendation.assignment.title}` : '',
    }));
  };

  return (
    <Panel title="Start a focus session" icon="play-circle" subtitle="Pick what you'll study — the timer, engagement and breaks are tracked automatically." className="accent-study">
      {recommendation && (
        <div className="p-3 rounded-4 mb-3 d-flex flex-wrap align-items-center gap-3" style={{ background: 'color-mix(in srgb, var(--es-study) 12%, transparent)' }}>
          <i className="bi bi-stars fs-4" style={{ color: 'var(--es-study-ink)' }} aria-hidden="true" />
          <div className="flex-grow-1 small">
            <div className="fw-bold">Suggested next session</div>
            <div className="text-2">
              {recommendation.assignment ? <>“{recommendation.assignment.title}”</> : 'Revision'}
              {recommendation.document && <> with “{recommendation.document.title}”</>} · {recommendation.planned_minutes} min · best time {recommendation.when}
            </div>
            <div className="text-3">{recommendation.reason}</div>
          </div>
          <button type="button" className="btn btn-glass btn-sm" onClick={useRecommendation}>Use suggestion</button>
        </div>
      )}
      <form onSubmit={submit}>
        <div className="row g-3">
          <div className="col-md-6">
            <label className="form-label" htmlFor="s-module">Module</label>
            <ModuleSelect id="s-module" value={form.module_id} onChange={(module_id) => setForm({ ...form, module_id, document_id: null, assignment_id: null })} noneLabel="General study" />
          </div>
          <div className="col-md-6">
            <label className="form-label" htmlFor="s-activity">Study activity</label>
            <select id="s-activity" className="form-select" value={form.activity} onChange={(e) => setForm({ ...form, activity: e.target.value })}>
              {(options?.study_activities ?? []).map((a) => <option key={a.value} value={a.value}>{a.label}</option>)}
            </select>
          </div>
          <div className="col-12">
            <label className="form-label">Planned time</label>
            <div className="d-flex flex-wrap gap-2 align-items-center">
              {PRESETS.map((m) => (
                <button key={m} type="button" className={`btn btn-glass btn-sm ${form.planned_minutes === m ? 'active' : ''}`} onClick={() => setForm({ ...form, planned_minutes: m })}>
                  {m} min{focusPeriod?.minutes === m ? ' ★' : ''}
                </button>
              ))}
              <div className="input-group input-group-sm" style={{ width: 150 }}>
                <input type="number" min={5} max={600} className="form-control" value={form.planned_minutes} onChange={(e) => setForm({ ...form, planned_minutes: Number(e.target.value) })} aria-label="Custom minutes" />
                <span className="input-group-text">min</span>
              </div>
            </div>
            {focusPeriod && <div className="form-text">★ {focusPeriod.explanation}</div>}
          </div>
          <div className="col-md-6">
            <label className="form-label" htmlFor="s-assignment">Work on an assignment <span className="text-3">(optional)</span></label>
            <select id="s-assignment" className="form-select" value={form.assignment_id ?? ''} onChange={(e) => {
              const id = e.target.value ? Number(e.target.value) : null;
              const picked = (assignments.data ?? []).find((a) => a.id === id);
              setForm({ ...form, assignment_id: id, module_id: picked?.module?.id ?? form.module_id, activity: id ? (picked?.type === 'project' ? 'project' : 'assignment') : form.activity });
            }}>
              <option value="">None</option>
              {moduleAssignments.map((a) => <option key={a.id} value={a.id}>{a.title} · {a.risk_score ?? '–'}% risk</option>)}
            </select>
            <div className="form-text">Focused time is logged to the assignment and its risk is recalculated.</div>
          </div>
          <div className="col-md-6">
            <label className="form-label" htmlFor="s-document">Study material <span className="text-3">(optional)</span></label>
            <select id="s-document" className="form-select" value={form.document_id ?? ''} onChange={(e) => setForm({ ...form, document_id: e.target.value ? Number(e.target.value) : null })}>
              <option value="">None</option>
              {(documents.data?.data ?? []).map((d) => <option key={d.id} value={d.id}>{d.title}</option>)}
            </select>
          </div>
          <div className="col-12">
            <label className="form-label" htmlFor="s-goal">Session goal</label>
            <input id="s-goal" className="form-control" value={form.goal} onChange={(e) => setForm({ ...form, goal: e.target.value })} placeholder="e.g. Finish the 3NF exercise and write the stored procedures" />
          </div>
        </div>
        <button type="submit" className="btn btn-accent btn-lg mt-4 px-4" disabled={start.isPending}>
          {start.isPending ? <Spinner className="me-2" /> : <i className="bi bi-play-fill me-2" />}Start {form.planned_minutes}-minute session
        </button>
      </form>
    </Panel>
  );
}
