import DOMPurify from 'dompurify';
import { marked } from 'marked';
import { useMemo } from 'react';

marked.setOptions({ gfm: true, breaks: false });

/** Markdown rendered to sanitised HTML (summaries, notes). */
export function Markdown({ children, className = '' }) {
  const html = useMemo(() => DOMPurify.sanitize(marked.parse(children ?? '')), [children]);

  // eslint-disable-next-line react/no-danger
  return <div className={`markdown-body ${className}`} dangerouslySetInnerHTML={{ __html: html }} />;
}
