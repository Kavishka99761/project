import { motion } from 'framer-motion';
import { NavLink, useLocation } from 'react-router-dom';
import { MOBILE_TABS } from '@/config/navigation';

export function MobileTabbar() {
  const location = useLocation();

  return (
    <nav className="mobile-tabbar glass-strong" aria-label="Quick navigation">
      {MOBILE_TABS.map((tab) => {
        const active = location.pathname === tab.to || location.pathname.startsWith(`${tab.to.split('/').slice(0, 2).join('/')}/`) || location.pathname === tab.to.split('/').slice(0, 2).join('/');
        return (
          <NavLink key={tab.to} to={tab.to} className={active ? 'active' : ''} style={{ '--nav-accent': `var(--es-${tab.accent})` }}>
            {active && <motion.span layoutId="mobile-tab-pill" className="nav-active-pill" style={{ position: 'absolute', inset: 0, borderRadius: 14, background: 'var(--es-active)' }} />}
            <i className={`bi bi-${tab.icon}`} aria-hidden="true" />
            <span>{tab.label}</span>
          </NavLink>
        );
      })}
    </nav>
  );
}
