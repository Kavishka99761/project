import { useEffect, useState } from 'react';
import { Modal } from 'react-bootstrap';
import { Markdown } from '@/components/ui/Markdown';
import { ModuleSelect } from '@/components/ui/ModuleSelect';
import { SegmentedControl } from '@/components/ui/SegmentedControl';
import { useToast } from '@/context/ToastContext';
import { errorMessage, fieldErrors } from '@/lib/api';
import { useCreateNote, useUpdateDocument } from '../api';

/** Write or edit a lecture note (Markdown with live preview). */
export function NoteEditorModal({ show, onHide, document, onSaved }) {
  const toast = useToast();
  const create = useCreateNote();
  const update = useUpdateDocument();
  const [mode, setMode] = useState('write');
  const [form, setForm] = useState({ title: '', topic: '', module_id: null, content: '' });
  const [errors, setErrors] = useState({});

  useEffect(() => {
    if (show) {
      setForm(document
        ? { title: document.title, topic: document.topic ?? '', module_id: document.module?.id ?? null, content: document.content ?? '' }
        : { title: '', topic: '', module_id: null, content: '# Lecture title\n\n## Key idea\n\nWrite or paste your notes here…' });
      setMode('write');
      setErrors({});
    }
  }, [show, document]);

  const busy = create.isPending || update.isPending;

  const submit = async (event) => {
    event.preventDefault();
    try {
      const saved = document
        ? await update.mutateAsync({ id: document.id, ...form })
        : await create.mutateAsync(form);
      toast.success(document ? 'Note updated' : 'Note saved', `${saved.word_count?.toLocaleString() ?? ''} words analysed`);
      onHide();
      onSaved?.(saved);
    } catch (error) {
      setErrors(fieldErrors(error));
      toast.error('Could not save the note', errorMessage(error));
    }
  };

  return (
    <Modal show={show} onHide={onHide} size="xl" centered>
      <form onSubmit={submit} className="accent-learning">
        <Modal.Header closeButton><Modal.Title><i className="bi bi-pencil-square me-2" />{document ? 'Edit lecture note' : 'New lecture note'}</Modal.Title></Modal.Header>
        <Modal.Body>
          <div className="row g-3 mb-3">
            <div className="col-md-5">
              <label className="form-label" htmlFor="note-title">Title</label>
              <input id="note-title" className={`form-control ${errors.title ? 'is-invalid' : ''}`} value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} required />
              {errors.title && <div className="invalid-feedback">{errors.title}</div>}
            </div>
            <div className="col-md-4">
              <label className="form-label" htmlFor="note-module">Module</label>
              <ModuleSelect id="note-module" value={form.module_id} onChange={(module_id) => setForm({ ...form, module_id })} />
            </div>
            <div className="col-md-3">
              <label className="form-label" htmlFor="note-topic">Topic</label>
              <input id="note-topic" className="form-control" value={form.topic} onChange={(e) => setForm({ ...form, topic: e.target.value })} />
            </div>
          </div>
          <div className="d-flex justify-content-between align-items-center mb-2">
            <SegmentedControl options={[{ value: 'write', label: 'Write', icon: 'pencil' }, { value: 'preview', label: 'Preview', icon: 'eye' }]} value={mode} onChange={setMode} />
            <span className="small text-3">Markdown supported · {form.content.split(/\s+/).filter(Boolean).length} words</span>
          </div>
          {mode === 'write' ? (
            <textarea className={`form-control font-monospace ${errors.content ? 'is-invalid' : ''}`} rows={16} value={form.content} onChange={(e) => setForm({ ...form, content: e.target.value })} style={{ fontSize: '0.88rem' }} />
          ) : (
            <div className="glass p-4" style={{ minHeight: 360 }}><Markdown>{form.content}</Markdown></div>
          )}
          {errors.content && <div className="text-danger small mt-1">{errors.content}</div>}
        </Modal.Body>
        <Modal.Footer>
          <button type="button" className="btn btn-glass" onClick={onHide}>Cancel</button>
          <button type="submit" className="btn btn-accent" disabled={busy}>{busy && <span className="spinner-border spinner-border-sm me-2" />}Save note</button>
        </Modal.Footer>
      </form>
    </Modal>
  );
}
