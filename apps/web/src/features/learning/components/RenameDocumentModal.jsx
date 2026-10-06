import { useEffect, useState } from 'react';
import { Modal } from 'react-bootstrap';
import { ModuleSelect } from '@/components/ui/ModuleSelect';
import { useToast } from '@/context/ToastContext';
import { errorMessage } from '@/lib/api';
import { useTopics, useUpdateDocument } from '../api';

/** Rename a document and organise it by module and topic. */
export function RenameDocumentModal({ document, onHide }) {
  const toast = useToast();
  const update = useUpdateDocument();
  const { data: topics = [] } = useTopics();
  const [form, setForm] = useState({ title: '', topic: '', module_id: null, description: '' });

  useEffect(() => {
    if (document) setForm({ title: document.title, topic: document.topic ?? '', module_id: document.module?.id ?? null, description: document.description ?? '' });
  }, [document]);

  const submit = async (event) => {
    event.preventDefault();
    try {
      await update.mutateAsync({ id: document.id, ...form, topic: form.topic || null });
      toast.success('Document updated');
      onHide();
    } catch (error) {
      toast.error('Could not update', errorMessage(error));
    }
  };

  return (
    <Modal show={Boolean(document)} onHide={onHide} centered>
      <form onSubmit={submit}>
        <Modal.Header closeButton><Modal.Title>Rename & organise</Modal.Title></Modal.Header>
        <Modal.Body>
          <div className="mb-3">
            <label className="form-label" htmlFor="rn-title">Title</label>
            <input id="rn-title" className="form-control" value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} required autoFocus />
          </div>
          <div className="mb-3">
            <label className="form-label" htmlFor="rn-module">Module</label>
            <ModuleSelect id="rn-module" value={form.module_id} onChange={(module_id) => setForm({ ...form, module_id })} />
          </div>
          <div className="mb-3">
            <label className="form-label" htmlFor="rn-topic">Topic</label>
            <input id="rn-topic" className="form-control" list="rn-topics" value={form.topic} onChange={(e) => setForm({ ...form, topic: e.target.value })} />
            <datalist id="rn-topics">{topics.map((t) => <option key={t.topic} value={t.topic} />)}</datalist>
          </div>
          <div>
            <label className="form-label" htmlFor="rn-desc">Description</label>
            <textarea id="rn-desc" className="form-control" rows={2} value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} />
          </div>
        </Modal.Body>
        <Modal.Footer>
          <button type="button" className="btn btn-glass" onClick={onHide}>Cancel</button>
          <button type="submit" className="btn btn-primary" disabled={update.isPending}>Save</button>
        </Modal.Footer>
      </form>
    </Modal>
  );
}
