import { useEffect, useState } from 'react';

/**
 * "Listen" — reads text aloud with the browser's speech engine (an offline,
 * on-device take on NotebookLM-style audio overviews).
 */
export function SpeakButton({ text, label = 'Listen', className = 'btn btn-glass btn-sm' }) {
  const supported = typeof window !== 'undefined' && 'speechSynthesis' in window;
  const [state, setState] = useState('idle');

  useEffect(() => () => supported && window.speechSynthesis.cancel(), [supported]);

  if (!supported || !text) return null;

  const toggle = () => {
    const synth = window.speechSynthesis;
    if (state === 'playing') {
      synth.pause();
      setState('paused');
      return;
    }
    if (state === 'paused') {
      synth.resume();
      setState('playing');
      return;
    }
    synth.cancel();
    const clean = text.replace(/[#*_`>]/g, '').replace(/\[(\d+)\]/g, '');
    const utterance = new SpeechSynthesisUtterance(clean);
    utterance.rate = 1.02;
    utterance.lang = 'en-GB';
    const voice = synth.getVoices().find((v) => /en-(GB|US)/.test(v.lang) && /Natural|Google|Microsoft/.test(v.name));
    if (voice) utterance.voice = voice;
    utterance.onend = () => setState('idle');
    utterance.onerror = () => setState('idle');
    synth.speak(utterance);
    setState('playing');
  };

  const stop = () => {
    window.speechSynthesis.cancel();
    setState('idle');
  };

  return (
    <span className="d-inline-flex gap-1">
      <button type="button" className={className} onClick={toggle} aria-label={state === 'playing' ? 'Pause reading' : label}>
        <i className={`bi bi-${state === 'playing' ? 'pause-fill' : 'headphones'} me-1`} aria-hidden="true" />
        {state === 'playing' ? 'Pause' : state === 'paused' ? 'Resume' : label}
      </button>
      {state !== 'idle' && (
        <button type="button" className="btn btn-ghost btn-sm" onClick={stop} aria-label="Stop reading"><i className="bi bi-stop-fill" aria-hidden="true" /></button>
      )}
    </span>
  );
}
