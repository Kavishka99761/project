export const currentUser = {
  id: 1,
  name: 'Ayesha Perera',
  email: 'student@edusmart.lk',
  initials: 'AP',
  program: 'BSc (Hons) Software Engineering',
  year: 'Year 2 · Semester 1',
};

export const modules = [
  { id: 1, code: 'SE201', name: 'Software Engineering', color: 'learning', icon: 'diagram-3' },
  { id: 2, code: 'DB202', name: 'Database Systems', color: 'risk', icon: 'database' },
  { id: 3, code: 'PR210', name: 'Programming (OOP)', color: 'study', icon: 'code-slash' },
  { id: 4, code: 'AI301', name: 'AI & Machine Learning', color: 'assistant', icon: 'cpu' },
];

export const isoDate = (offsetDays = 0) => {
  const d = new Date();
  d.setHours(12, 0, 0, 0);
  d.setDate(d.getDate() + offsetDays);
  const pad = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
};

export const daysUntil = (dateStr) => {
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const target = new Date(`${dateStr}T00:00:00`);
  return Math.round((target - today) / 86400000);
};

export const fmtDuration = (minutes) => {
  const h = Math.floor(minutes / 60);
  const m = minutes % 60;
  return `${h ? `${h}h ` : ''}${h && m < 10 ? '0' : ''}${m}m`;
};

export const calcRisk = (a) => {
  const days = Math.max(daysUntil(a.deadline), 0);
  const remainingHours = Math.max(a.estHours - a.doneHours, 0);
  const hoursPerDay = days > 0 ? remainingHours / days : remainingHours;
  let score;
  if (days === 0) score = remainingHours > 0 ? 100 : 0;
  else score = Math.min(100, Math.round((hoursPerDay / 5) * 60 + (100 - a.progress) * 0.4));
  let level = 'Low';
  if (score >= 75) level = 'Critical';
  else if (score >= 50) level = 'High';
  else if (score >= 25) level = 'Medium';
  const reasons = [];
  if (hoursPerDay > 3) reasons.push(`Needs ~${hoursPerDay.toFixed(1)}h/day to finish`);
  if (days <= 3) reasons.push(`Only ${days} day(s) left`);
  if (a.progress < 40) reasons.push(`Progress is low (${a.progress}%)`);
  if (!reasons.length) reasons.push('On track — workload fits available time');
  return { score, level, reasons, hoursPerDay: +hoursPerDay.toFixed(1), days };
};

export const studyToday = { studiedMinutes: 155, targetMinutes: 180 };
export const currentSession = { module: 'Database Systems', minutes: 42, active: false };
export const engagement = { level: 'Good', percent: 78, history: [62, 70, 78, 74, 81, 78] };
export const weeklyStudy = [
  { day: 'Mon', minutes: 120 }, { day: 'Tue', minutes: 165 }, { day: 'Wed', minutes: 95 },
  { day: 'Thu', minutes: 180 }, { day: 'Fri', minutes: 140 }, { day: 'Sat', minutes: 60 }, { day: 'Sun', minutes: 155 },
];
export const sessionHistory = [
  { id: 1, module: 'Database Systems', date: isoDate(-1), planned: 90, actual: 85, engagement: 80 },
  { id: 2, module: 'Programming (OOP)', date: isoDate(-2), planned: 60, actual: 72, engagement: 74 },
  { id: 3, module: 'Software Engineering', date: isoDate(-3), planned: 120, actual: 100, engagement: 68 },
];

export const documents = [
  { id: 1, title: 'OOP Lecture 05 — Inheritance', module: 'Programming (OOP)', type: 'PDF', pages: 34, size: '1.8 MB', uploaded: isoDate(-3), topic: 'Inheritance' },
  { id: 2, title: 'Database Normalisation Notes', module: 'Database Systems', type: 'Word', pages: 22, size: '640 KB', uploaded: isoDate(-5), topic: 'Normalisation' },
  { id: 3, title: 'Software Engineering Ch.3', module: 'Software Engineering', type: 'PDF', pages: 48, size: '3.1 MB', uploaded: isoDate(-6), topic: 'Requirements' },
  { id: 4, title: 'Agile & Scrum Slides', module: 'Software Engineering', type: 'PDF', pages: 27, size: '2.2 MB', uploaded: isoDate(-8), topic: 'Agile' },
  { id: 5, title: 'ER Diagrams Workshop', module: 'Database Systems', type: 'Word', pages: 15, size: '510 KB', uploaded: isoDate(-10), topic: 'ER Modelling' },
  { id: 6, title: 'Polymorphism Tutorial', module: 'Programming (OOP)', type: 'PDF', pages: 19, size: '1.1 MB', uploaded: isoDate(-12), topic: 'Polymorphism' },
  { id: 7, title: 'Neural Networks Intro', module: 'AI & Machine Learning', type: 'PDF', pages: 40, size: '2.7 MB', uploaded: isoDate(-15), topic: 'Neural Nets' },
];

export const summaries = [
  { id: 1, docId: 1, title: 'OOP Lecture 05 Summary', length: 'Medium', keywords: ['inheritance', 'superclass', 'override', 'polymorphism'], saved: isoDate(-2), text: 'Inheritance lets a subclass reuse behaviour and state from a superclass. Key ideas: single vs multiple inheritance, method overriding with super calls, and the is-a relationship. Advantages include code reuse and extensibility; risks include fragile base-class coupling.' },
  { id: 2, docId: 2, title: 'Database Revision Notes', length: 'Short', keywords: ['1NF', '2NF', '3NF', 'functional dependency'], saved: isoDate(-4), text: 'Normalisation reduces redundancy. 1NF: atomic values. 2NF: no partial dependencies. 3NF: no transitive dependencies. BCNF handles overlapping candidate keys.' },
  { id: 3, docId: 3, title: 'Requirements Engineering', length: 'Detailed', keywords: ['SRS', 'stakeholder', 'functional', 'non-functional'], saved: isoDate(-5), text: 'Requirements engineering covers elicitation, analysis, specification (SRS), validation and management. Functional requirements describe behaviour; non-functional cover quality attributes like performance and security.' },
];

export const academicDates = [
  { id: 1, date: isoDate(2), title: 'Project Proposal Submission', type: 'Milestone' },
  { id: 2, date: isoDate(37), title: 'Interim Review', type: 'Milestone' },
  { id: 3, date: isoDate(78), title: 'Final Project Submission', type: 'Deadline' },
  { id: 4, date: isoDate(88), title: 'End-of-Semester Exams Begin', type: 'Exam' },
];

export const assignments = [
  { id: 1, title: 'Database Project', module: 'Database Systems', deadline: isoDate(3), priority: 'High', estHours: 20, doneHours: 5, progress: 25 },
  { id: 2, title: 'Web Assignment', module: 'Programming (OOP)', deadline: isoDate(4), priority: 'High', estHours: 16, doneHours: 6, progress: 38 },
  { id: 3, title: 'Research Report', module: 'Software Engineering', deadline: isoDate(9), priority: 'Medium', estHours: 15, doneHours: 5, progress: 33 },
  { id: 4, title: 'ML Mini Project', module: 'AI & Machine Learning', deadline: isoDate(20), priority: 'Low', estHours: 12, doneHours: 8, progress: 70 },
];

export const notifications = [
  { id: 1, title: 'Database project milestone', time: 'Today • 2:00 PM', kind: 'deadline' },
  { id: 2, title: 'Study streak approaching target', time: 'Today • 9:15 AM', kind: 'warning' },
  { id: 3, title: 'New summary generated', time: 'Yesterday', kind: 'success' },
];
