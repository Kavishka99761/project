import { AnimatePresence, motion } from 'framer-motion';
import { useEffect, useMemo, useState } from 'react';
import { GlassCard } from '@/components/ui/GlassCard';
import { Meter } from '@/components/ui/Progress';
import { useHotkey } from '@/hooks/useHotkey';

/** Flip-card trainer with "known / still learning" tracking. */
export function Flashcards({ cards, onFinish }) {
  const [order, setOrder] = useState(() => cards.map((_, i) => i));
  const [index, setIndex] = useState(0);
  const [flipped, setFlipped] = useState(false);
  const [known, setKnown] = useState(() => new Set());

  useEffect(() => {
    setOrder(cards.map((_, i) => i));
    setIndex(0);
    setKnown(new Set());
  }, [cards]);

  const card = cards[order[index]];
  const done = index >= order.length;

  const next = (markKnown) => {
    if (markKnown !== undefined) {
      setKnown((set) => {
        const copy = new Set(set);
        if (markKnown) copy.add(order[index]);
        else copy.delete(order[index]);
        return copy;
      });
    }
    setFlipped(false);
    setTimeout(() => setIndex((i) => i + 1), 120);
  };

  useHotkey(' ', () => !done && setFlipped((f) => !f));
  useHotkey('arrowright', () => !done && next(true));
  useHotkey('arrowleft', () => !done && next(false));

  const shuffle = () => {
    setOrder((current) => [...current].sort(() => Math.random() - 0.5));
    setIndex(0);
    setFlipped(false);
  };

  const finishedScore = useMemo(() => known.size, [known]);

  useEffect(() => {
    if (done && order.length) onFinish?.(finishedScore, order.length);
  }, [done]); // eslint-disable-line react-hooks/exhaustive-deps

  if (!cards.length) return null;

  if (done) {
    return (
      <GlassCard className="p-5 text-center">
        <div className="fs-1 mb-2" style={{ color: 'var(--es-good)' }}><i className="bi bi-trophy" aria-hidden="true" /></div>
        <h4 className="fw-800">Deck complete</h4>
        <p className="text-2">You knew <strong>{finishedScore}</strong> of {order.length} cards.</p>
        <div className="d-flex gap-2 justify-content-center">
          <button type="button" className="btn btn-glass" onClick={() => { setIndex(0); setKnown(new Set()); }}>Restart</button>
          <button type="button" className="btn btn-primary" onClick={() => { setOrder(order.filter((i) => !known.has(i))); setIndex(0); setKnown(new Set()); }} disabled={finishedScore === order.length}>
            Review the {order.length - finishedScore} I missed
          </button>
        </div>
      </GlassCard>
    );
  }

  return (
    <div>
      <div className="d-flex align-items-center gap-3 mb-3">
        <div className="flex-grow-1"><Meter value={index} max={order.length} label="Deck progress" /></div>
        <span className="small text-3 tabular">{index + 1} / {order.length}</span>
        <button type="button" className="btn btn-ghost btn-sm" onClick={shuffle}><i className="bi bi-shuffle me-1" />Shuffle</button>
      </div>
      <AnimatePresence mode="wait">
        <motion.div key={order[index]} initial={{ opacity: 0, x: 40 }} animate={{ opacity: 1, x: 0 }} exit={{ opacity: 0, x: -40 }} transition={{ duration: 0.25 }}>
          <div className={`flashcard ${flipped ? 'flipped' : ''}`} onClick={() => setFlipped(!flipped)} role="button" tabIndex={0} aria-label="Flip card" onKeyDown={(e) => e.key === 'Enter' && setFlipped(!flipped)}>
            <div className="flashcard-inner">
              <div className="face face-front glass-strong">
                <div className="small text-3 fw-bold text-uppercase mb-3" style={{ letterSpacing: '0.1em' }}>{card.kind === 'cloze' ? 'Fill the blank' : 'Question'}</div>
                {card.front}
                {card.hint && <div className="small text-3 mt-3 fw-normal">{card.hint}</div>}
              </div>
              <div className="face face-back glass-strong" style={{ background: 'color-mix(in srgb, var(--es-learning) 12%, var(--es-glass-strong))' }}>
                <div className="small text-3 fw-bold text-uppercase mb-3" style={{ letterSpacing: '0.1em' }}>Answer</div>
                {card.back}
              </div>
            </div>
          </div>
        </motion.div>
      </AnimatePresence>
      <div className="d-flex justify-content-center gap-2 mt-3">
        <button type="button" className="btn btn-glass" onClick={() => next(false)}><i className="bi bi-arrow-repeat me-1" />Still learning</button>
        <button type="button" className="btn btn-glass" onClick={() => setFlipped(!flipped)}><i className="bi bi-arrow-left-right me-1" />Flip</button>
        <button type="button" className="btn btn-primary" onClick={() => next(true)}><i className="bi bi-check2 me-1" />I knew it</button>
      </div>
      <div className="text-center small text-3 mt-2">Space to flip · → knew it · ← still learning</div>
    </div>
  );
}
