import { useQuery } from '@tanstack/react-query';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Modal } from 'react-bootstrap';
import { useNavigate } from 'react-router-dom';
import { ALL_ROUTES } from '@/config/navigation';
import { useTheme } from '@/context/ThemeContext';
import { useDebounce } from '@/hooks/useDebounce';
import { get } from '@/lib/api';

const ACTIONS = [
  { id: 'study', label: 'Start a study session', icon: 'play-circle', to: '/study?start=1' },
  { id: 'upload', label: 'Upload learning material', icon: 'cloud-arrow-up', to: '/learning/library?upload=1' },
  { id: 'note', label: 'Write a lecture note', icon: 'pencil-square', to: '/learning/library?note=1' },
  { id: 'ask', label: 'Ask the academic assistant', icon: 'chat-square-text', to: '/assistant/chat' },
  { id: 'assignment', label: 'Add an assignment', icon: 'plus-square', to: '/assignments/list?new=1' },
  { id: 'whatif', label: 'Run a what-if risk scenario', icon: 'sliders', to: '/assignments/what-if' },
  { id: 'export', label: 'Export my data (PDF / Excel / JSON)', icon: 'cloud-arrow-down', to: '/exports' },
  { id: 'theme', label: 'Toggle dark / light mode', icon: 'circle-half', run: 'theme' },
];

function Highlight({ text, query }) {
  if (!query || !text) return <>{text}</>;
  const index = text.toLowerCase().indexOf(query.toLowerCase());
  if (index < 0) return <>{text}</>;
  return (
    <>
      {text.slice(0, index)}
      <mark>{text.slice(index, index + query.length)}</mark>
      {text.slice(index + query.length)}
    </>
  );
}

export function CommandPalette({ open, onClose }) {
  const navigate = useNavigate();
  const { theme, setTheme } = useTheme();
  const [query, setQuery] = useState('');
  const [active, setActive] = useState(0);
  const debounced = useDebounce(query.trim(), 250);
  const listRef = useRef(null);

  const search = useQuery({
    queryKey: ['search', debounced],
    queryFn: () => get('/search', { q: debounced, limit: 4 }),
    enabled: open && debounced.length >= 2,
    staleTime: 15_000,
  });
  const history = useQuery({ queryKey: ['search', 'history'], queryFn: () => get('/search/history'), enabled: open && !query });

  useEffect(() => {
    if (open) {
      setQuery('');
      setActive(0);
    }
  }, [open]);

  const groups = useMemo(() => {
    const q = query.trim().toLowerCase();
    const pages = ALL_ROUTES.filter((route) => !q || route.label.toLowerCase().includes(q) || route.section?.toLowerCase().includes(q)).slice(0, q ? 6 : 5);
    const actions = ACTIONS.filter((action) => !q || action.label.toLowerCase().includes(q));
    const result = [];
    if (!q && history.data?.length) {
      result.push({ label: 'Recent searches', items: history.data.slice(0, 4).map((h) => ({ key: `h${h.id}`, label: h.query, sub: `${h.results} results`, icon: 'clock-history', fill: h.query })) });
    }
    if (actions.length) result.push({ label: 'Quick actions', items: actions.map((a) => ({ key: a.id, label: a.label, icon: a.icon, to: a.to, run: a.run })) });
    if (pages.length) result.push({ label: 'Go to', items: pages.map((p) => ({ key: p.to, label: p.label, sub: p.parent ?? p.section, icon: p.icon, to: p.to })) });
    (search.data?.groups ?? []).forEach((group) => {
      result.push({
        label: group.label,
        items: group.items.map((item) => ({ key: `${group.key}-${item.id}-${item.url}`, label: item.title, sub: item.snippet || item.subtitle, icon: item.icon || group.icon, to: item.url })),
      });
    });
    return result;
  }, [query, history.data, search.data]);

  const flat = groups.flatMap((group) => group.items);

  const choose = (item) => {
    if (!item) return;
    if (item.fill) {
      setQuery(item.fill);
      return;
    }
    if (item.run === 'theme') setTheme(theme === 'dark' ? 'light' : 'dark');
    if (item.to) navigate(item.to);
    onClose();
  };

  const onKeyDown = (event) => {
    if (event.key === 'ArrowDown') {
      event.preventDefault();
      setActive((i) => Math.min(flat.length - 1, i + 1));
    } else if (event.key === 'ArrowUp') {
      event.preventDefault();
      setActive((i) => Math.max(0, i - 1));
    } else if (event.key === 'Enter') {
      event.preventDefault();
      choose(flat[active]);
    }
  };

  useEffect(() => {
    listRef.current?.querySelector('.cmdk-item.active')?.scrollIntoView({ block: 'nearest' });
  }, [active]);

  let index = -1;

  return (
    <Modal show={open} onHide={onClose} size="lg" contentClassName="cmdk" dialogClassName="mt-5" aria-label="Command palette">
      <div className="cmdk-input">
        <i className="bi bi-search text-3" aria-hidden="true" />
        <input
          autoFocus
          value={query}
          onChange={(e) => { setQuery(e.target.value); setActive(0); }}
          onKeyDown={onKeyDown}
          placeholder="Search everything or type a command…"
          aria-label="Search"
        />
        {search.isFetching && <span className="spinner-border spinner-border-sm text-3" />}
        <kbd className="small text-3">Esc</kbd>
      </div>
      <div className="cmdk-list" ref={listRef} role="listbox">
        {groups.map((group) => (
          <div key={group.label}>
            <div className="cmdk-group">{group.label}</div>
            {group.items.map((item) => {
              index += 1;
              const itemIndex = index;
              return (
                <button
                  type="button"
                  key={item.key}
                  role="option"
                  aria-selected={itemIndex === active}
                  className={`cmdk-item ${itemIndex === active ? 'active' : ''}`}
                  onMouseEnter={() => setActive(itemIndex)}
                  onClick={() => choose(item)}
                >
                  <i className={`bi bi-${item.icon}`} aria-hidden="true" />
                  <span className="min-w-0 flex-grow-1">
                    <span className="d-block text-truncate fw-semibold"><Highlight text={item.label} query={query.trim()} /></span>
                    {item.sub && <span className="d-block cmdk-sub text-truncate"><Highlight text={item.sub} query={query.trim()} /></span>}
                  </span>
                  {itemIndex === active && <i className="bi bi-arrow-return-left" aria-hidden="true" />}
                </button>
              );
            })}
          </div>
        ))}
        {debounced.length >= 2 && !search.isFetching && (search.data?.total ?? 0) === 0 && (
          <div className="text-center text-3 small py-3">No matches in your notes, documents, chats or deadlines.</div>
        )}
      </div>
      <div className="cmdk-foot">
        <span><kbd>↑</kbd> <kbd>↓</kbd> navigate</span>
        <span><kbd>Enter</kbd> open</span>
        <span className="ms-auto">Search covers all four modules</span>
      </div>
    </Modal>
  );
}
