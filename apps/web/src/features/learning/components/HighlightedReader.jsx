/**
 * Reading view built from the analysis blocks: key sentences highlighted
 * strongly, important ones softly (legend + toggle in the parent).
 */
export function HighlightedReader({ blocks, showHighlights = true }) {
  return (
    <article className={`reader ${showHighlights ? '' : 'hl-off'}`}>
      {blocks.map((block, i) => {
        if (block.type === 'heading') return <h4 key={i}>{block.text}</h4>;
        const sentences = block.sentences.map((s) => (
          <span key={s.index} className={s.level === 'key' ? 'hl-key' : s.level === 'important' ? 'hl-important' : undefined} title={s.level ? `${s.level === 'key' ? 'Key' : 'Important'} sentence · score ${Math.round(s.score * 100)}` : undefined}>
            {s.text}{' '}
          </span>
        ));
        return block.type === 'bullet'
          ? <p key={i} className="ps-3"><span className="me-2 text-3">•</span>{sentences}</p>
          : <p key={i}>{sentences}</p>;
      })}
    </article>
  );
}
