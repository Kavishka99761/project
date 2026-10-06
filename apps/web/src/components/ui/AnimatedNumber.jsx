import { animate, useMotionValue, useMotionValueEvent } from 'framer-motion';
import { useEffect, useState } from 'react';
import { useTheme } from '@/context/ThemeContext';

/** Count-up number (respects reduced motion). */
export function AnimatedNumber({ value, decimals = 0, format }) {
  const { reduceMotion } = useTheme();
  const target = Number(value) || 0;
  const motionValue = useMotionValue(reduceMotion ? target : 0);
  const [display, setDisplay] = useState(reduceMotion ? target : 0);

  useMotionValueEvent(motionValue, 'change', (latest) => setDisplay(latest));

  useEffect(() => {
    if (reduceMotion) {
      motionValue.set(target);
      return undefined;
    }
    const controls = animate(motionValue, target, { duration: 1.1, ease: [0.2, 0.8, 0.2, 1] });
    return () => controls.stop();
  }, [target, reduceMotion, motionValue]);

  const rounded = Number(display.toFixed(decimals));
  return <>{format ? format(rounded) : rounded.toLocaleString('en', { minimumFractionDigits: decimals, maximumFractionDigits: decimals })}</>;
}
