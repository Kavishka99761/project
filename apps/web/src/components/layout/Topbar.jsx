import { Dropdown } from 'react-bootstrap';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { routeMeta } from '@/config/navigation';
import { useAuth } from '@/context/AuthContext';
import { useRealtime } from '@/context/RealtimeContext';
import { useTheme } from '@/context/ThemeContext';
import { useNow } from '@/hooks/useNow';
import { clock, relative } from '@/lib/format';

function ThemeToggle() {
  const { theme, setTheme } = useTheme();
  const next = theme === 'dark' ? 'light' : 'dark';

  return (
    <button type="button" className="icon-btn" onClick={(event) => setTheme(next, event)} aria-label={`Switch to ${next} mode`} title={`Switch to ${next} mode`}>
      <i className={`bi bi-${theme === 'dark' ? 'sun' : 'moon-stars'}`} aria-hidden="true" />
    </button>
  );
}

function LiveStudyChip() {
  const { liveStudy } = useRealtime();
  const navigate = useNavigate();
  const running = liveStudy?.status === 'active';
  const now = useNow(1000, Boolean(liveStudy));
  if (!liveStudy) return null;

  const resumed = liveStudy.last_resumed_at?.toDate?.() ?? (liveStudy.last_resumed_at ? new Date(liveStudy.last_resumed_at) : null);
  const elapsed = (liveStudy.focus_seconds ?? 0) + (running && resumed ? Math.max(0, (now - resumed.getTime()) / 1000) : 0);

  return (
    <button type="button" className="btn btn-glass btn-sm d-none d-md-inline-flex align-items-center gap-2 accent-study" onClick={() => navigate('/study')} title="Live study session (synced in realtime)">
      <span className={running ? 'live-dot' : ''} style={running ? undefined : { width: 8, height: 8, borderRadius: '50%', background: 'var(--es-warning)', display: 'inline-block' }} />
      <span className="tabular fw-bold">{clock(elapsed)}</span>
      <span className="text-3 small">{running ? liveStudy.module ?? 'Studying' : liveStudy.status === 'on_break' ? 'Break' : 'Paused'}</span>
    </button>
  );
}

function NotificationBell() {
  const { unreadCount, latest } = useRealtime();
  const navigate = useNavigate();

  return (
    <Dropdown align="end">
      <Dropdown.Toggle as="button" className="icon-btn" aria-label={`Notifications (${unreadCount} unread)`} bsPrefix="x">
        <i className="bi bi-bell" aria-hidden="true" />
        {unreadCount > 0 && <span className="dot-badge">{unreadCount > 99 ? '99+' : unreadCount}</span>}
      </Dropdown.Toggle>
      <Dropdown.Menu style={{ width: 360 }}>
        <div className="d-flex justify-content-between align-items-center px-2 pt-1 pb-2">
          <strong>Notifications</strong>
          <Link to="/notifications" className="small fw-semibold">View all</Link>
        </div>
        {latest.length === 0 && <div className="px-2 pb-2 small text-3">You're all caught up.</div>}
        {latest.slice(0, 6).map((n) => (
          <Dropdown.Item key={n.id} onClick={() => navigate(n.action_url || '/notifications')} className="align-items-start">
            <i className={`bi bi-${n.icon || 'bell'} mt-1`} aria-hidden="true" />
            <span className="min-w-0 flex-grow-1">
              <span className={`d-block text-truncate ${n.read ? 'fw-semibold text-2' : 'fw-bold'}`}>{n.title}</span>
              <span className="d-block small text-3">{relative(n.created_at)}</span>
            </span>
            {!n.read && <span className="mt-2 rounded-circle flex-shrink-0" style={{ width: 8, height: 8, background: 'var(--es-primary)' }} />}
          </Dropdown.Item>
        ))}
      </Dropdown.Menu>
    </Dropdown>
  );
}

function UserMenu() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();

  return (
    <Dropdown align="end">
      <Dropdown.Toggle as="button" bsPrefix="x" className="btn btn-ghost d-flex align-items-center gap-2 px-2" aria-label="Account menu">
        {user?.avatar_url ? (
          <img src={user.avatar_url} alt="" width="36" height="36" className="rounded-3 object-fit-cover" />
        ) : (
          <span className="d-grid rounded-3 fw-bold text-white" style={{ width: 36, height: 36, placeItems: 'center', background: 'linear-gradient(135deg, var(--es-primary), var(--es-assistant))' }}>{user?.initials}</span>
        )}
        <span className="d-none d-xl-block text-start lh-sm">
          <span className="d-block fw-bold small">{user?.name}</span>
          <span className="d-block text-3" style={{ fontSize: '0.72rem' }}>{user?.program ?? user?.email}</span>
        </span>
      </Dropdown.Toggle>
      <Dropdown.Menu>
        <Dropdown.Item onClick={() => navigate('/profile')}><i className="bi bi-person-circle" />Profile</Dropdown.Item>
        <Dropdown.Item onClick={() => navigate('/settings')}><i className="bi bi-gear" />Settings</Dropdown.Item>
        <Dropdown.Item onClick={() => navigate('/exports')}><i className="bi bi-cloud-arrow-down" />Export my data</Dropdown.Item>
        <Dropdown.Item onClick={() => navigate('/activity')}><i className="bi bi-activity" />Activity log</Dropdown.Item>
        <Dropdown.Divider />
        <Dropdown.Item onClick={async () => { await logout(); navigate('/login'); }}><i className="bi bi-box-arrow-right" />Sign out</Dropdown.Item>
      </Dropdown.Menu>
    </Dropdown>
  );
}

export function Topbar({ onMenu, onSearch }) {
  const location = useLocation();
  const meta = routeMeta(location.pathname);

  return (
    <header className="topbar glass-strong">
      <button type="button" className="icon-btn d-lg-none" onClick={onMenu} aria-label="Open navigation">
        <i className="bi bi-list" aria-hidden="true" />
      </button>
      <div className="topbar-title d-none d-sm-block">
        <nav aria-label="breadcrumb">
          <ol className="breadcrumb">
            <li className="breadcrumb-item"><Link to="/dashboard">Home</Link></li>
            {meta?.section && meta.section !== 'Overview' && <li className="breadcrumb-item">{meta.section}</li>}
            {meta?.parent && <li className="breadcrumb-item">{meta.parent}</li>}
          </ol>
        </nav>
        <h1>{meta?.label ?? 'EDU-SMART'}</h1>
      </div>

      <div className="flex-grow-1 d-flex justify-content-center">
        <button type="button" className="search-trigger" onClick={onSearch}>
          <i className="bi bi-search" aria-hidden="true" />
          <span className="text-truncate">Search notes, chats, deadlines…</span>
          <kbd>Ctrl K</kbd>
        </button>
      </div>

      <div className="d-flex align-items-center gap-1">
        <LiveStudyChip />
        <ThemeToggle />
        <NotificationBell />
        <UserMenu />
      </div>
    </header>
  );
}
