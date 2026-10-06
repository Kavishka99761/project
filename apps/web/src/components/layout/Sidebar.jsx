import { motion } from 'framer-motion';
import { NavLink, useLocation } from 'react-router-dom';
import { NAVIGATION } from '@/config/navigation';
import { useRealtime } from '@/context/RealtimeContext';

const accentVar = (accent) => `var(--es-${accent ?? 'platform'})`;

function NavItem({ item, accent, collapsed, onNavigate, unread }) {
  const location = useLocation();
  const isActive = item.end ? location.pathname === item.to : location.pathname === item.to || location.pathname.startsWith(`${item.to}/`);

  return (
    <NavLink
      to={item.to}
      end={item.end}
      className={`nav-item-link ${isActive ? 'active' : ''}`}
      style={{ '--nav-accent': accentVar(accent) }}
      title={collapsed ? item.label : undefined}
      onClick={onNavigate}
    >
      {isActive && <motion.span layoutId="nav-active-pill" className="nav-active-pill liquid-refract" transition={{ type: 'spring', stiffness: 380, damping: 34 }} />}
      <i className={`nav-icon bi bi-${item.icon}`} aria-hidden="true" />
      <span className="nav-text">{item.label}</span>
      {item.badge === 'notifications' && unread > 0 && <span className="nav-badge es-badge es-badge-critical">{unread > 99 ? '99+' : unread}</span>}
    </NavLink>
  );
}

export function Sidebar({ collapsed, onToggle, mobileOpen, onNavigate }) {
  const location = useLocation();
  const { unreadCount, status } = useRealtime();

  return (
    <aside className={`sidebar glass-strong ${collapsed ? 'collapsed' : ''} ${mobileOpen ? 'mobile-open' : ''}`} aria-label="Main navigation">
      <NavLink to="/dashboard" className="sidebar-brand" onClick={onNavigate}>
        <span className="brand-mark"><i className="bi bi-mortarboard-fill" aria-hidden="true" /></span>
        <span className="brand-text">
          <span className="brand-name">EDU-SMART</span>
          <span className="brand-sub">Study smarter</span>
        </span>
      </NavLink>

      <nav className="sidebar-scroll">
        {NAVIGATION.map((section) => (
          <div key={section.label}>
            <div className="nav-section-label">
              {section.label}
              {section.owner && <span className="ms-1 fw-semibold text-lowercase" style={{ letterSpacing: 0, opacity: 0.75 }}>· {section.owner}</span>}
            </div>
            {section.items.map((item) => {
              const expanded = item.children && (location.pathname === item.to || location.pathname.startsWith(`${item.to}/`));
              return (
                <div key={item.to}>
                  <NavItem item={item} accent={item.accent} collapsed={collapsed} onNavigate={onNavigate} unread={unreadCount} />
                  {item.children && expanded && !collapsed && (
                    <motion.div className="nav-sub" initial={{ opacity: 0, height: 0 }} animate={{ opacity: 1, height: 'auto' }} transition={{ duration: 0.25 }}>
                      {item.children.map((child) => (
                        <NavItem key={child.to} item={child} accent={item.accent} collapsed={collapsed} onNavigate={onNavigate} />
                      ))}
                    </motion.div>
                  )}
                </div>
              );
            })}
          </div>
        ))}
      </nav>

      <div className="sidebar-footer d-flex align-items-center gap-2">
        <span className="d-inline-flex align-items-center gap-2 small text-3 footer-text flex-grow-1 text-truncate" title="Realtime sync (Firebase)">
          <span className={status === 'live' ? 'live-dot' : ''} style={status === 'live' ? undefined : { width: 8, height: 8, borderRadius: '50%', background: 'var(--es-chart-muted)', display: 'inline-block' }} />
          {status === 'live' ? 'Realtime sync on' : status === 'connecting' ? 'Connecting…' : 'Realtime off · polling'}
        </span>
        <button type="button" className="icon-btn d-none d-lg-inline-grid" onClick={onToggle} aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}>
          <i className={`bi bi-layout-sidebar${collapsed ? '' : '-inset'}`} aria-hidden="true" />
        </button>
      </div>
    </aside>
  );
}
