// Sidebar / command-palette navigation. Each feature module owns its section.

export const NAVIGATION = [
  {
    label: 'Overview',
    items: [{ to: '/dashboard', label: 'Dashboard', icon: 'grid-1x2-fill', accent: 'platform' }],
  },
  {
    label: 'Learning materials',
    owner: 'Bethmi',
    items: [
      {
        to: '/learning',
        label: 'Learning hub',
        icon: 'journal-richtext',
        accent: 'learning',
        end: true,
        children: [
          { to: '/learning/library', label: 'Library', icon: 'collection' },
          { to: '/learning/summaries', label: 'Summaries', icon: 'card-text' },
          { to: '/learning/study-aids', label: 'Study aids', icon: 'lightbulb' },
        ],
      },
    ],
  },
  {
    label: 'Study & engagement',
    owner: 'Pasindu',
    items: [
      {
        to: '/study',
        label: 'Focus studio',
        icon: 'stopwatch',
        accent: 'study',
        end: true,
        children: [
          { to: '/study/history', label: 'Session history', icon: 'clock-history' },
          { to: '/study/analytics', label: 'Productivity', icon: 'graph-up-arrow' },
          { to: '/study/planner', label: 'Plans & reminders', icon: 'calendar2-check' },
        ],
      },
    ],
  },
  {
    label: 'Academic assistant',
    owner: 'Kavishka',
    items: [
      {
        to: '/assistant',
        label: 'Assistant hub',
        icon: 'robot',
        accent: 'assistant',
        end: true,
        children: [
          { to: '/assistant/chat', label: 'Chatbot', icon: 'chat-square-text' },
          { to: '/assistant/knowledge', label: 'Knowledge base', icon: 'book-half' },
          { to: '/assistant/search', label: 'Search documents', icon: 'search' },
          { to: '/assistant/dates', label: 'Academic dates', icon: 'calendar-event' },
        ],
      },
    ],
  },
  {
    label: 'Assignments & risk',
    owner: 'Jithmi',
    items: [
      {
        to: '/assignments',
        label: 'Risk dashboard',
        icon: 'clipboard-data',
        accent: 'assignments',
        end: true,
        children: [
          { to: '/assignments/list', label: 'Assignments', icon: 'list-check' },
          { to: '/assignments/what-if', label: 'What-if simulator', icon: 'sliders' },
          { to: '/assignments/history', label: 'History', icon: 'archive' },
        ],
      },
    ],
  },
  {
    label: 'Platform',
    items: [
      { to: '/calendar', label: 'Calendar', icon: 'calendar3', accent: 'platform' },
      { to: '/notifications', label: 'Notifications', icon: 'bell', accent: 'platform', badge: 'notifications' },
      { to: '/activity', label: 'Activity log', icon: 'activity', accent: 'platform' },
      { to: '/exports', label: 'Export centre', icon: 'cloud-arrow-down', accent: 'platform' },
      { to: '/modules', label: 'Modules', icon: 'collection-fill', accent: 'platform' },
      { to: '/trash', label: 'Trash', icon: 'trash3', accent: 'platform' },
      { to: '/settings', label: 'Settings', icon: 'gear', accent: 'platform' },
    ],
  },
];

export const MOBILE_TABS = [
  { to: '/dashboard', label: 'Home', icon: 'grid-1x2-fill', accent: 'platform' },
  { to: '/learning', label: 'Learn', icon: 'journal-richtext', accent: 'learning' },
  { to: '/study', label: 'Study', icon: 'stopwatch', accent: 'study' },
  { to: '/assistant/chat', label: 'Ask', icon: 'robot', accent: 'assistant' },
  { to: '/assignments', label: 'Tasks', icon: 'clipboard-data', accent: 'assignments' },
];

/** Flat list of every destination (command palette, breadcrumbs). */
export const ALL_ROUTES = NAVIGATION.flatMap((section) => section.items.flatMap((item) => [
  { ...item, section: section.label },
  ...(item.children ?? []).map((child) => ({ ...child, accent: item.accent, section: section.label, parent: item.label })),
])).concat([
  { to: '/profile', label: 'Profile', icon: 'person-circle', accent: 'platform', section: 'Account' },
]);

export function routeMeta(pathname) {
  const sorted = [...ALL_ROUTES].sort((a, b) => b.to.length - a.to.length);
  return sorted.find((route) => pathname === route.to || pathname.startsWith(`${route.to}/`)) ?? null;
}
