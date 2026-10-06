import { motion } from 'framer-motion';
import { Link } from 'react-router-dom';
import { LiquidBackdrop } from '@/components/layout/LiquidBackdrop';

const FEATURES = [
  { icon: 'journal-richtext', color: 'var(--es-learning)', title: 'Smart learning materials', text: 'Summaries, key concepts, flashcards and quizzes from your lecture notes.' },
  { icon: 'stopwatch', color: 'var(--es-study)', title: 'Focus studio', text: 'A study timer that tracks engagement and suggests the right breaks.' },
  { icon: 'robot', color: 'var(--es-assistant)', title: 'Academic assistant', text: 'Answers from your handbooks with the exact section and page.' },
  { icon: 'clipboard-data', color: 'var(--es-assignments)', title: 'Deadline risk', text: 'Know which assignment is at risk and what to work on first.' },
];

/** Split layout for sign-in / registration screens. */
export function AuthLayout({ title, subtitle, children, footer }) {
  return (
    <div className="min-vh-100 d-flex align-items-center py-4 px-3">
      <LiquidBackdrop />
      <div className="container" style={{ maxWidth: 1140 }}>
        <div className="row g-4 align-items-center">
          <motion.div className="col-lg-6 d-none d-lg-block" initial={{ opacity: 0, x: -24 }} animate={{ opacity: 1, x: 0 }} transition={{ duration: 0.6 }}>
            <Link to="/" className="d-inline-flex align-items-center gap-3 text-decoration-none mb-4">
              <span className="d-grid rounded-4 text-white fs-4" style={{ width: 54, height: 54, placeItems: 'center', background: 'linear-gradient(135deg, #6366f1, var(--es-learning) 55%, var(--es-study))', boxShadow: '0 14px 30px -12px rgba(79,70,229,.8)' }}>
                <i className="bi bi-mortarboard-fill" aria-hidden="true" />
              </span>
              <span>
                <span className="d-block fs-4 fw-800" style={{ color: 'var(--es-text)' }}>EDU-SMART</span>
                <span className="d-block small text-3 fw-semibold">Your academic co-pilot</span>
              </span>
            </Link>
            <h1 className="display-5 fw-800 mb-3" style={{ letterSpacing: '-0.03em', lineHeight: 1.05 }}>
              Learn, focus and deliver — <span style={{ background: 'linear-gradient(120deg, var(--es-learning), var(--es-assistant) 50%, var(--es-assignments))', WebkitBackgroundClip: 'text', backgroundClip: 'text', color: 'transparent' }}>all in one place.</span>
            </h1>
            <p className="text-2 fs-6 mb-4" style={{ maxWidth: 480 }}>Four intelligent modules that share your data: deadlines found in a handbook become assignments, the riskiest one becomes your next study session, and your study time updates the risk.</p>
            <div className="row g-3">
              {FEATURES.map((feature, i) => (
                <motion.div className="col-6" key={feature.title} initial={{ opacity: 0, y: 16 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.2 + i * 0.08 }}>
                  <div className="glass p-3 h-100">
                    <i className={`bi bi-${feature.icon} fs-4`} style={{ color: feature.color }} aria-hidden="true" />
                    <div className="fw-bold mt-2 small">{feature.title}</div>
                    <div className="text-3" style={{ fontSize: '0.78rem' }}>{feature.text}</div>
                  </div>
                </motion.div>
              ))}
            </div>
          </motion.div>

          <motion.div className="col-lg-5 offset-lg-1" initial={{ opacity: 0, y: 24, scale: 0.98 }} animate={{ opacity: 1, y: 0, scale: 1 }} transition={{ duration: 0.55, ease: [0.2, 0.8, 0.2, 1] }}>
            <div className="glass-strong p-4 p-md-5" style={{ borderRadius: 28 }}>
              <div className="d-lg-none d-flex align-items-center gap-2 mb-4">
                <span className="d-grid rounded-3 text-white" style={{ width: 40, height: 40, placeItems: 'center', background: 'linear-gradient(135deg, #6366f1, var(--es-learning))' }}><i className="bi bi-mortarboard-fill" /></span>
                <span className="fw-800 fs-5">EDU-SMART</span>
              </div>
              <h2 className="fw-800 mb-1" style={{ letterSpacing: '-0.02em' }}>{title}</h2>
              {subtitle && <p className="text-2 mb-4">{subtitle}</p>}
              {children}
            </div>
            {footer && <div className="text-center mt-3 small text-2">{footer}</div>}
          </motion.div>
        </div>
      </div>
    </div>
  );
}
