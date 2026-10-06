import { motion } from 'framer-motion';
import { useState } from 'react';
import { GlassCard } from '@/components/ui/GlassCard';
import { Meter, ProgressRing } from '@/components/ui/Progress';
import { STATUS } from '@/config/palette';

/** Multiple-choice quiz with instant feedback and the source sentence. */
export function QuizPlayer({ questions, onFinish }) {
  const [index, setIndex] = useState(0);
  const [picked, setPicked] = useState(null);
  const [score, setScore] = useState(0);
  const done = index >= questions.length;

  const choose = (option) => {
    if (picked !== null) return;
    setPicked(option);
    if (option === questions[index].answer) setScore((s) => s + 1);
  };

  const next = () => {
    const last = index + 1 >= questions.length;
    setIndex((i) => i + 1);
    setPicked(null);
    if (last) onFinish?.(score, questions.length);
  };

  if (done) {
    const percent = Math.round((score / questions.length) * 100);
    const color = percent >= 70 ? STATUS.good : percent >= 40 ? STATUS.warning : STATUS.critical;
    return (
      <GlassCard className="p-5 text-center">
        <ProgressRing value={percent} size={140} stroke={12} color={color}>
          <div className="fw-800 fs-3">{percent}%</div>
          <div className="small text-3">{score}/{questions.length}</div>
        </ProgressRing>
        <h4 className="fw-800 mt-3">{percent >= 70 ? 'Great work!' : percent >= 40 ? 'Getting there' : 'Keep practising'}</h4>
        <p className="text-2">Your result was saved to your activity history.</p>
        <button type="button" className="btn btn-primary" onClick={() => { setIndex(0); setScore(0); setPicked(null); }}>Try again</button>
      </GlassCard>
    );
  }

  const question = questions[index];

  return (
    <div>
      <div className="d-flex align-items-center gap-3 mb-3">
        <div className="flex-grow-1"><Meter value={index} max={questions.length} label="Quiz progress" /></div>
        <span className="small text-3 tabular">Question {index + 1} of {questions.length} · score {score}</span>
      </div>
      <motion.div key={index} initial={{ opacity: 0, y: 12 }} animate={{ opacity: 1, y: 0 }}>
        <GlassCard className="p-4" animate={false}>
          <h5 className="fw-bold lh-base mb-4">{question.question}</h5>
          {question.options.map((option, i) => {
            const state = picked === null ? '' : i === question.answer ? 'correct' : i === picked ? 'wrong' : '';
            return (
              <button type="button" key={option} className={`quiz-option ${state}`} onClick={() => choose(i)} disabled={picked !== null}>
                <span className="opt-key">{String.fromCharCode(65 + i)}</span>
                <span className="flex-grow-1">{option}</span>
                {state === 'correct' && <i className="bi bi-check-circle-fill" style={{ color: 'var(--es-good)' }} aria-label="Correct" />}
                {state === 'wrong' && <i className="bi bi-x-circle-fill" style={{ color: 'var(--es-critical)' }} aria-label="Incorrect" />}
              </button>
            );
          })}
          {picked !== null && (
            <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="mt-3 p-3 rounded-4 small" style={{ background: 'var(--es-hover)' }}>
              <strong>{picked === question.answer ? 'Correct!' : `Answer: ${question.options[question.answer]}`}</strong>
              <div className="text-2 mt-1">From your notes: “{question.explanation}”</div>
              <button type="button" className="btn btn-primary btn-sm mt-3" onClick={next}>{index + 1 >= questions.length ? 'See results' : 'Next question'}<i className="bi bi-arrow-right ms-1" /></button>
            </motion.div>
          )}
        </GlassCard>
      </motion.div>
    </div>
  );
}
