import { AnimatePresence } from 'framer-motion';
import { useEffect, useState } from 'react';
import { useLocation, useOutlet } from 'react-router-dom';
import { CommandPalette } from '@/components/layout/CommandPalette';
import { LiquidBackdrop } from '@/components/layout/LiquidBackdrop';
import { MobileTabbar } from '@/components/layout/MobileTabbar';
import { PageTransition } from '@/components/layout/PageTransition';
import { Sidebar } from '@/components/layout/Sidebar';
import { Topbar } from '@/components/layout/Topbar';
import { routeMeta } from '@/config/navigation';
import { useHotkey } from '@/hooks/useHotkey';

/** Authenticated application frame. */
export function AppShell() {
  const location = useLocation();
  const outlet = useOutlet();
  const [collapsed, setCollapsed] = useState(() => {
    try {
      return localStorage.getItem('edusmart.sidebar') === 'collapsed';
    } catch {
      return false;
    }
  });
  const [mobileOpen, setMobileOpen] = useState(false);
  const [paletteOpen, setPaletteOpen] = useState(false);

  useHotkey('mod+k', () => setPaletteOpen((open) => !open), { allowInInputs: true });
  useHotkey('/', () => setPaletteOpen(true));

  useEffect(() => setMobileOpen(false), [location.pathname]);
  useEffect(() => {
    window.scrollTo({ top: 0, behavior: 'instant' });
  }, [location.pathname]);

  const toggle = () => {
    setCollapsed((value) => {
      try {
        localStorage.setItem('edusmart.sidebar', value ? 'expanded' : 'collapsed');
      } catch {
        /* ignore */
      }
      return !value;
    });
  };

  const accent = routeMeta(location.pathname)?.accent ?? 'platform';
  const transitionKey = location.pathname.split('/').slice(0, 3).join('/');

  return (
    <div className="app-shell">
      <a href="#main-content" className="visually-hidden-focusable">Skip to content</a>
      <LiquidBackdrop />
      <Sidebar collapsed={collapsed} onToggle={toggle} mobileOpen={mobileOpen} onNavigate={() => setMobileOpen(false)} />
      {mobileOpen && <div className="sidebar-scrim d-lg-none" onClick={() => setMobileOpen(false)} aria-hidden="true" />}

      <div className={`main-column ${collapsed ? 'sidebar-collapsed' : ''}`}>
        <Topbar onMenu={() => setMobileOpen(true)} onSearch={() => setPaletteOpen(true)} />
        <AnimatePresence mode="wait" initial={false}>
          <PageTransition key={transitionKey} accent={accent}>
            {outlet}
          </PageTransition>
        </AnimatePresence>
      </div>

      <MobileTabbar />
      <CommandPalette open={paletteOpen} onClose={() => setPaletteOpen(false)} />
    </div>
  );
}
