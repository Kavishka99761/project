/* ==========================================================================
   EDU-SMART — Mock Data Layer
   Simulates the Laravel REST API so the web app runs standalone (no backend).
   To go live, replace reads in module JS with fetch(API_BASE + '/...').
   Mirrors the SQL schema in backend-laravel/database/sql/schema.sql
   ========================================================================== */

window.ES = window.ES || {};

ES.API_BASE = "http://localhost:8000/api"; // Laravel backend base URL (when installed)

/* Date-relative helper: demo deadlines stay meaningful whenever the app is opened,
   instead of drifting into the past (which would pin every risk score at 100).
   Built from LOCAL components on purpose — ES.daysUntil() parses "YYYY-MM-DD"
   as local midnight, so using toISOString() here would shift the date by one
   day for users east of UTC and desync the two. */
ES.isoDate = function (offsetDays) {
  const d = new Date();
  d.setHours(12, 0, 0, 0);
  d.setDate(d.getDate() + (offsetDays || 0));
  const p = (n) => String(n).padStart(2, "0");
  return d.getFullYear() + "-" + p(d.getMonth() + 1) + "-" + p(d.getDate());
};

/* ---------- Common Platform Layer ---------- */
ES.currentUser = {
  id: 1,
  name: "Ayesha Perera",
  email: "student@edusmart.lk",
  initials: "AP",
  program: "BSc (Hons) Software Engineering",
  year: "Year 2 · Semester 1",
};

ES.modules = [
  { id: 1, code: "SE201", name: "Software Engineering", color: "bethmi",   icon: "diagram-3" },
  { id: 2, code: "DB202", name: "Database Systems",     color: "jithmi",   icon: "database" },
  { id: 3, code: "PR210", name: "Programming (OOP)",    color: "pasindu",  icon: "code-slash" },
  { id: 4, code: "AI301", name: "AI & Machine Learning",color: "kavishka", icon: "cpu" },
];

/* ---------- Bethmi: Learning Materials ----------
   Upload/save/session dates are day-offsets from today so "recent" stays recent. */
ES.documents = [
  { id: 1, title: "OOP Lecture 05 — Inheritance", module: "Programming (OOP)", type: "PDF",  pages: 34, size: "1.8 MB", uploaded: ES.isoDate(-3),  topic: "Inheritance" },
  { id: 2, title: "Database Normalisation Notes", module: "Database Systems",  type: "Word", pages: 22, size: "640 KB", uploaded: ES.isoDate(-5),  topic: "Normalisation" },
  { id: 3, title: "Software Engineering Ch.3",    module: "Software Engineering", type: "PDF", pages: 48, size: "3.1 MB", uploaded: ES.isoDate(-6),  topic: "Requirements" },
  { id: 4, title: "Agile & Scrum Slides",         module: "Software Engineering", type: "PDF", pages: 27, size: "2.2 MB", uploaded: ES.isoDate(-8),  topic: "Agile" },
  { id: 5, title: "ER Diagrams Workshop",         module: "Database Systems",  type: "Word", pages: 15, size: "510 KB", uploaded: ES.isoDate(-10), topic: "ER Modelling" },
  { id: 6, title: "Polymorphism Tutorial",        module: "Programming (OOP)", type: "PDF",  pages: 19, size: "1.1 MB", uploaded: ES.isoDate(-12), topic: "Polymorphism" },
  { id: 7, title: "Neural Networks Intro",        module: "AI & Machine Learning", type: "PDF", pages: 40, size: "2.7 MB", uploaded: ES.isoDate(-15), topic: "Neural Nets" },
];

ES.summaries = [
  { id: 1, docId: 1, title: "OOP Lecture 05 Summary", length: "Medium", keywords: ["inheritance", "superclass", "override", "polymorphism"], saved: ES.isoDate(-2),
    text: "Inheritance lets a subclass reuse behaviour and state from a superclass. Key ideas: single vs multiple inheritance, method overriding with super calls, and the is-a relationship. Advantages include code reuse and extensibility; risks include fragile base-class coupling." },
  { id: 2, docId: 2, title: "Database Revision Notes", length: "Short", keywords: ["1NF", "2NF", "3NF", "functional dependency"], saved: ES.isoDate(-4),
    text: "Normalisation reduces redundancy. 1NF: atomic values. 2NF: no partial dependencies. 3NF: no transitive dependencies. BCNF handles overlapping candidate keys." },
  { id: 3, docId: 3, title: "Requirements Engineering", length: "Detailed", keywords: ["SRS", "stakeholder", "functional", "non-functional"], saved: ES.isoDate(-5),
    text: "Requirements engineering covers elicitation, analysis, specification (SRS), validation and management. Functional requirements describe behaviour; non-functional cover quality attributes like performance and security." },
];

/* ---------- Pasindu: Study & Engagement ---------- */
ES.studyToday = { studiedMinutes: 155, targetMinutes: 180 };
ES.currentSession = { module: "Database Systems", minutes: 42, active: false };
ES.engagement = { level: "Good", percent: 78, history: [62, 70, 78, 74, 81, 78] };
ES.weeklyStudy = [
  { day: "Mon", minutes: 120 }, { day: "Tue", minutes: 165 }, { day: "Wed", minutes: 95 },
  { day: "Thu", minutes: 180 }, { day: "Fri", minutes: 140 }, { day: "Sat", minutes: 60 }, { day: "Sun", minutes: 155 },
];
ES.sessionHistory = [
  { id: 1, module: "Database Systems",     date: ES.isoDate(-1), planned: 90,  actual: 85,  engagement: 80 },
  { id: 2, module: "Programming (OOP)",    date: ES.isoDate(-2), planned: 60,  actual: 72,  engagement: 74 },
  { id: 3, module: "Software Engineering", date: ES.isoDate(-3), planned: 120, actual: 100, engagement: 68 },
];

/* ---------- Kavishka: Academic Assistant ---------- */
ES.knowledgeBase = { handbooks: 12, projectDocs: 8, regulations: 6 };
/* Offsets +2 / +37 / +78 / +88 keep the same spacing the absolute dates had,
   so the month grid and the "in N days" labels never decay. */
ES.academicDates = [
  { id: 1, date: ES.isoDate(2),  title: "Project Proposal Submission", type: "Milestone" },
  { id: 2, date: ES.isoDate(37), title: "Interim Review",              type: "Milestone" },
  { id: 3, date: ES.isoDate(78), title: "Final Project Submission",    type: "Deadline" },
  { id: 4, date: ES.isoDate(88), title: "End-of-Semester Exams Begin", type: "Exam" },
];
ES.recentQuestions = [
  "What are the project requirements?",
  "When is the interim submission?",
  "What is the attendance requirement?",
];
ES.chatSeed = [
  { role: "bot", text: "Hi! I'm your Academic Assistant. Ask me about module handbooks, project guidelines, deadlines or university regulations.", src: "" },
];

/* ---------- Jithmi: Assignment & Risk ----------
   Tuned so the seeded set exercises ALL FOUR risk levels — Critical 90, High 55,
   Medium 40, Low 14. Showing that spread is the whole point of the module.
   Deadlines are date-relative so the demo never decays into "all overdue". */
ES.assignments = [
  { id: 1, title: "Database Project",       module: "Database Systems",       deadline: ES.isoDate(3),  priority: "High",   estHours: 20, doneHours: 5, progress: 25 },
  { id: 2, title: "Web Assignment",         module: "Programming (OOP)",      deadline: ES.isoDate(4),  priority: "High",   estHours: 16, doneHours: 6, progress: 38 },
  { id: 3, title: "Research Report",        module: "Software Engineering",   deadline: ES.isoDate(9),  priority: "Medium", estHours: 15, doneHours: 5, progress: 33 },
  { id: 4, title: "ML Mini Project",        module: "AI & Machine Learning",  deadline: ES.isoDate(20), priority: "Low",    estHours: 12, doneHours: 8, progress: 70 },
];

/* ---------- Helpers ---------- */
ES.daysUntil = function (dateStr) {
  const today = new Date(); today.setHours(0, 0, 0, 0);
  const target = new Date(dateStr + "T00:00:00");
  return Math.round((target - today) / 86400000);
};

ES.fmtDuration = function (minutes) {
  const h = Math.floor(minutes / 60), m = minutes % 60;
  return (h ? h + "h " : "") + (m < 10 && h ? "0" : "") + m + "m";
};

/* Risk model — mirrors Jithmi's backend RiskCalculator logic */
ES.calcRisk = function (a) {
  const days = Math.max(ES.daysUntil(a.deadline), 0);
  const remainingHours = Math.max(a.estHours - a.doneHours, 0);
  const hoursPerDay = days > 0 ? remainingHours / days : remainingHours;
  let score;
  if (days === 0) score = remainingHours > 0 ? 100 : 0;
  else score = Math.min(100, Math.round((hoursPerDay / 5) * 60 + (100 - a.progress) * 0.4));
  let level = "Low";
  if (score >= 75) level = "Critical";
  else if (score >= 50) level = "High";
  else if (score >= 25) level = "Medium";
  const reasons = [];
  if (hoursPerDay > 3) reasons.push(`Needs ~${hoursPerDay.toFixed(1)}h/day to finish`);
  if (days <= 3) reasons.push(`Only ${days} day(s) left`);
  if (a.progress < 40) reasons.push(`Progress is low (${a.progress}%)`);
  if (!reasons.length) reasons.push("On track — workload fits available time");
  return { score, level, reasons, hoursPerDay: +hoursPerDay.toFixed(1), days };
};

ES.moduleName = function (id) {
  const m = ES.modules.find((x) => x.id === id);
  return m ? m.name : "Unknown";
};
