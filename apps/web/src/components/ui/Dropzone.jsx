import { useRef, useState } from 'react';
import { bytes } from '@/lib/format';

/** Drag-and-drop / click-to-browse file picker. */
export function Dropzone({ accept, onFiles, multiple = false, hint, maxMb, file }) {
  const input = useRef(null);
  const [dragging, setDragging] = useState(false);

  const handle = (fileList) => {
    const files = Array.from(fileList ?? []);
    if (files.length) onFiles(multiple ? files : [files[0]]);
  };

  return (
    <div
      className={`dropzone ${dragging ? 'dragging' : ''}`}
      role="button"
      tabIndex={0}
      onClick={() => input.current?.click()}
      onKeyDown={(e) => (e.key === 'Enter' || e.key === ' ') && input.current?.click()}
      onDragOver={(e) => { e.preventDefault(); setDragging(true); }}
      onDragLeave={() => setDragging(false)}
      onDrop={(e) => { e.preventDefault(); setDragging(false); handle(e.dataTransfer.files); }}
    >
      <input ref={input} type="file" hidden accept={accept} multiple={multiple} onChange={(e) => handle(e.target.files)} />
      <div className="dz-icon mb-2"><i className={`bi ${file ? 'bi-file-earmark-check' : 'bi-cloud-arrow-up'}`} aria-hidden="true" /></div>
      {file ? (
        <>
          <div className="fw-bold text-truncate">{file.name}</div>
          <div className="small text-3">{bytes(file.size)} · click to choose another file</div>
        </>
      ) : (
        <>
          <div className="fw-bold">Drop a file here or click to browse</div>
          <div className="small text-3 mt-1">{hint}{maxMb ? ` · up to ${maxMb} MB` : ''}</div>
        </>
      )}
    </div>
  );
}
