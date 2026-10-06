import { useState } from 'react';
import { Modal, ProgressBar } from 'react-bootstrap';
import { Dropzone } from '@/components/ui/Dropzone';
import { ModuleSelect } from '@/components/ui/ModuleSelect';
import { useToast } from '@/context/ToastContext';
import { useOptions } from '@/hooks/usePlatform';
import { errorMessage, fieldErrors } from '@/lib/api';
import { useTopics, useUploadDocument } from '../api';

/** Upload lecture notes / PDF / Word / slides with module + topic. */
export function UploadDocumentModal({ show, onHide, onUploaded, defaultModuleId }) {
  const toast = useToast();
  const { data: options } = useOptions();
  const { data: topics = [] } = useTopics();
  const upload = useUploadDocument();
  const [file, setFile] = useState(null);
  const [fields, setFields] = useState({ title: '', topic: '', description: '', module_id: defaultModuleId ?? null });
  const [progress, setProgress] = useState(0);
  const [errors, setErrors] = useState({});

  const extensions = options?.upload?.learning_extensions ?? ['pdf', 'docx', 'doc', 'pptx', 'txt', 'md'];

  const reset = () => {
    setFile(null);
    setFields({ title: '', topic: '', description: '', module_id: defaultModuleId ?? null });
    setProgress(0);
    setErrors({});
  };

  const submit = async (event) => {
    event.preventDefault();
    if (!file) return;
    setErrors({});
    try {
      const document = await upload.mutateAsync({ file, ...fields, onProgress: setProgress });
      const status = document.extraction_status === 'completed'
        ? `${document.pages} page${document.pages === 1 ? '' : 's'} · ${document.word_count.toLocaleString()} words extracted`
        : document.extraction_error ?? 'Text extraction needs attention.';
      toast[document.extraction_status === 'completed' ? 'success' : 'warning'](`Uploaded “${document.title}”`, status);
      reset();
      onHide();
      onUploaded?.(document);
    } catch (error) {
      setErrors(fieldErrors(error));
      toast.error('Upload failed', errorMessage(error));
    }
  };

  return (
    <Modal show={show} onHide={() => { onHide(); reset(); }} centered size="lg">
      <form onSubmit={submit} className="accent-learning">
        <Modal.Header closeButton><Modal.Title><i className="bi bi-cloud-arrow-up me-2" />Upload learning material</Modal.Title></Modal.Header>
        <Modal.Body>
          <Dropzone
            accept={extensions.map((e) => `.${e}`).join(',')}
            file={file}
            onFiles={([picked]) => {
              setFile(picked);
              if (!fields.title) setFields((f) => ({ ...f, title: picked.name.replace(/\.[^.]+$/, '').replace(/[_-]+/g, ' ') }));
            }}
            hint={`PDF, Word, PowerPoint, text or Markdown (${extensions.join(', ')})`}
            maxMb={options?.upload?.max_mb}
          />
          {errors.file && <div className="text-danger small mt-2">{errors.file}</div>}
          <div className="row g-3 mt-1">
            <div className="col-md-7">
              <label className="form-label" htmlFor="doc-title">Title</label>
              <input id="doc-title" className="form-control" value={fields.title} onChange={(e) => setFields({ ...fields, title: e.target.value })} placeholder="e.g. OOP Lecture 05 — Inheritance" />
            </div>
            <div className="col-md-5">
              <label className="form-label" htmlFor="doc-module">Module</label>
              <ModuleSelect id="doc-module" value={fields.module_id} onChange={(module_id) => setFields({ ...fields, module_id })} />
            </div>
            <div className="col-md-5">
              <label className="form-label" htmlFor="doc-topic">Topic</label>
              <input id="doc-topic" className="form-control" list="topic-list" value={fields.topic} onChange={(e) => setFields({ ...fields, topic: e.target.value })} placeholder="e.g. Normalization" />
              <datalist id="topic-list">{topics.map((t) => <option key={t.topic} value={t.topic} />)}</datalist>
            </div>
            <div className="col-md-7">
              <label className="form-label" htmlFor="doc-desc">Description (optional)</label>
              <input id="doc-desc" className="form-control" value={fields.description} onChange={(e) => setFields({ ...fields, description: e.target.value })} />
            </div>
          </div>
          {upload.isPending && (
            <div className="mt-3">
              <ProgressBar now={progress} label={progress < 100 ? `${progress}%` : 'Extracting text…'} animated={progress >= 100} />
              <div className="small text-3 mt-1">{progress < 100 ? 'Uploading securely…' : 'Extracting text, keywords and preparing your summary…'}</div>
            </div>
          )}
        </Modal.Body>
        <Modal.Footer>
          <button type="button" className="btn btn-glass" onClick={() => { onHide(); reset(); }}>Cancel</button>
          <button type="submit" className="btn btn-accent" disabled={!file || upload.isPending}>
            {upload.isPending ? <span className="spinner-border spinner-border-sm me-2" /> : <i className="bi bi-upload me-2" />}Upload
          </button>
        </Modal.Footer>
      </form>
    </Modal>
  );
}
