import { motion } from 'framer-motion';

/** Route transition: fade + rise + soft blur. */
export function PageTransition({ children, accent = 'platform' }) {
  return (
    <motion.main
      className={`page-container accent-${accent}`}
      initial={{ opacity: 0, y: 14, filter: 'blur(6px)' }}
      animate={{ opacity: 1, y: 0, filter: 'blur(0px)' }}
      exit={{ opacity: 0, y: -8, filter: 'blur(4px)' }}
      transition={{ duration: 0.38, ease: [0.2, 0.8, 0.2, 1] }}
      id="main-content"
    >
      {children}
    </motion.main>
  );
}
