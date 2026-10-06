import { useCallback, useEffect, useRef, useState } from 'react';

/**
 * Ambient focus sound generated in the browser (Web Audio API) — brown,
 * pink or white noise, no audio files needed.
 */
export function useAmbientSound() {
  const context = useRef(null);
  const nodes = useRef(null);
  const [playing, setPlaying] = useState(null);
  const [volume, setVolumeState] = useState(0.35);

  const stop = useCallback(() => {
    nodes.current?.source.stop();
    nodes.current?.source.disconnect();
    nodes.current = null;
    setPlaying(null);
  }, []);

  const play = useCallback((kind) => {
    stop();
    const ctx = context.current ?? new (window.AudioContext || window.webkitAudioContext)();
    context.current = ctx;
    const length = ctx.sampleRate * 4;
    const buffer = ctx.createBuffer(1, length, ctx.sampleRate);
    const data = buffer.getChannelData(0);
    let last = 0;
    let b0 = 0; let b1 = 0; let b2 = 0;
    for (let i = 0; i < length; i++) {
      const white = Math.random() * 2 - 1;
      if (kind === 'brown') {
        last = (last + 0.02 * white) / 1.02;
        data[i] = last * 3.5;
      } else if (kind === 'pink') {
        b0 = 0.99765 * b0 + white * 0.099046;
        b1 = 0.963 * b1 + white * 0.2965164;
        b2 = 0.57 * b2 + white * 1.0526913;
        data[i] = (b0 + b1 + b2 + white * 0.1848) * 0.11;
      } else {
        data[i] = white * 0.25;
      }
    }
    const source = ctx.createBufferSource();
    source.buffer = buffer;
    source.loop = true;
    const gain = ctx.createGain();
    gain.gain.value = volume;
    source.connect(gain).connect(ctx.destination);
    source.start();
    nodes.current = { source, gain };
    setPlaying(kind);
  }, [stop, volume]);

  const setVolume = useCallback((value) => {
    setVolumeState(value);
    if (nodes.current) nodes.current.gain.gain.value = value;
  }, []);

  useEffect(() => () => {
    nodes.current?.source.stop();
    context.current?.close();
  }, []);

  return { playing, play, stop, volume, setVolume };
}
