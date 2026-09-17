/**
 * EDU-SMART — bundled demo dataset.
 *
 * The mobile app is offline-first: it renders this data when the Laravel API is
 * unreachable, so the app is always demonstrable (no PHP/MySQL required). Shapes
 * mirror frontend-web/assets/js/mock-data.js and the SQL schema. Deadlines are
 * generated relative to "today" so risk levels always look meaningful.
 */

import { isoDate } from '../api/risk';

/* Single source of truth for demo dates: api/risk.js owns the date <-> offset
 * rule and daysUntil() is its inverse, so delegating keeps the bundled data and
 * the risk engine from disagreeing about what "today" means. Mirrors
 * ES.isoDate() in frontend-web/assets/js/mock-data.js. */
const iso = (offsetDays = 0) => isoDate(offsetDays);

export const demoUser = {
  id: 1,
  name: 'Ayesha Perera',
  email: 'student@edusmart.lk',
  initials: 'AP',
  program: 'BSc (Hons) Software Engineering',
  year: 'Year 2 · Semester 1',
  dailyTargetMinutes: 180,
};

export const demoModules = [
  { id: 1, code: 'SE201', name: 'Software Engineering', accent: 'bethmi' },
  { id: 2, code: 'DB202', name: 'Database Systems', accent: 'jithmi' },
  { id: 3, code: 'PR210', name: 'Programming (OOP)', accent: 'pasindu' },
  { id: 4, code: 'AI301', name: 'AI & Machine Learning', accent: 'kavishka' },
];

/* ---------------- Bethmi: Learning Materials ---------------- */
export const demoDocuments = [
  { id: 1, title: 'OOP Lecture 05 — Inheritance', module: 'Programming (OOP)', type: 'PDF', pages: 34, size: '1.8 MB', topic: 'Inheritance', uploaded: iso(-3) },
  { id: 2, title: 'Database Normalisation Notes', module: 'Database Systems', type: 'Word', pages: 22, size: '640 KB', topic: 'Normalisation', uploaded: iso(-5) },
  { id: 3, title: 'Software Engineering Ch.3', module: 'Software Engineering', type: 'PDF', pages: 48, size: '3.1 MB', topic: 'Requirements', uploaded: iso(-6) },
  { id: 4, title: 'Agile & Scrum Slides', module: 'Software Engineering', type: 'PDF', pages: 27, size: '2.2 MB', topic: 'Agile', uploaded: iso(-8) },
  { id: 5, title: 'ER Diagrams Workshop', module: 'Database Systems', type: 'Word', pages: 15, size: '510 KB', topic: 'ER Modelling', uploaded: iso(-10) },
  { id: 6, title: 'Neural Networks Intro', module: 'AI & Machine Learning', type: 'PDF', pages: 40, size: '2.7 MB', topic: 'Neural Nets', uploaded: iso(-12) },
];

export const demoSummaries = [
  { id: 1, docId: 1, title: 'OOP Lecture 05 Summary', length: 'Medium', keywords: ['inheritance', 'superclass', 'override', 'polymorphism'], text: 'Inheritance lets a subclass reuse behaviour and state from a superclass. Key ideas: single vs multiple inheritance, method overriding with super calls, and the is-a relationship. Advantages include code reuse and extensibility; risks include fragile base-class coupling.' },
  { id: 2, docId: 2, title: 'Database Revision Notes', length: 'Short', keywords: ['1NF', '2NF', '3NF', 'functional dependency'], text: 'Normalisation reduces redundancy. 1NF: atomic values. 2NF: no partial dependencies. 3NF: no transitive dependencies. BCNF handles overlapping candidate keys.' },
  { id: 3, docId: 3, title: 'Requirements Engineering', length: 'Detailed', keywords: ['SRS', 'stakeholder', 'functional', 'non-functional'], text: 'Requirements engineering covers elicitation, analysis, specification (SRS), validation and management. Functional requirements describe behaviour; non-functional cover quality attributes like performance and security.' },
];

/* ---------------- Pasindu: Study & Engagement ---------------- */
export const demoStudy = {
  studiedMinutes: 155,
  targetMinutes: 180,
  averageEngagement: 78,
  streakDays: 4,
  weekly: [
    { day: 'Mon', minutes: 120 }, { day: 'Tue', minutes: 165 }, { day: 'Wed', minutes: 95 },
    { day: 'Thu', minutes: 180 }, { day: 'Fri', minutes: 140 }, { day: 'Sat', minutes: 60 }, { day: 'Sun', minutes: 155 },
  ],
  history: [
    { id: 1, module: 'Database Systems', date: iso(-1), planned: 90, actual: 85, engagement: 80 },
    { id: 2, module: 'Programming (OOP)', date: iso(-2), planned: 60, actual: 72, engagement: 74 },
    { id: 3, module: 'Software Engineering', date: iso(-3), planned: 120, actual: 100, engagement: 68 },
  ],
};

/* ---------------- Kavishka: Academic Assistant ---------------- */
export const demoKnowledgeBase = [
  {
    id: 1, title: 'University Student Handbook 2026', category: 'Handbook', pages: 48,
    sections: [
      { section: 'Academic Calendar & Key Dates', page: 6, keywords: ['academic calendar', 'semester', 'examination', 'deadline', 'late submission', 'penalty'], content: 'The academic year is divided into two semesters. Continuous assessment submissions must be uploaded before the stated deadline; late submissions incur a penalty of 5% of the total marks per day.' },
      { section: 'Attendance Requirement', page: 9, keywords: ['attendance', 'examination', 'eligibility', 'medical', 'threshold'], content: 'Students must maintain a minimum of 80% attendance for each module to be eligible to sit the final examination. Students below the threshold must submit a medical or special consideration request.' },
    ],
  },
  {
    id: 2, title: 'Final Year Project Guidelines', category: 'Project', pages: 22,
    sections: [
      { section: 'Project Proposal', page: 3, keywords: ['project', 'proposal', 'supervisor', 'objectives', 'methodology', 'scope'], content: 'The final year project begins with an individual proposal submitted in the first two weeks of semester 1. The proposal must state the problem, objectives, scope, methodology and expected outcomes, and is approved by a supervisor.' },
      { section: 'Project Report & Viva', page: 15, keywords: ['report', 'viva', 'plagiarism', 'literature review', 'references', 'submission', 'word count', 'length'], content: 'The final project report must follow the prescribed format: abstract, introduction, literature review, methodology, results, conclusion and references. The report is 10,000 words with a tolerance of plus or minus 10%, excluding references and appendices. It is submitted two weeks before the viva voce examination. Plagiarism above 20% similarity results in rejection of the report.' },
    ],
  },
  {
    id: 3, title: 'Examination & Grading Regulations', category: 'Regulation', pages: 16,
    sections: [
      { section: 'Special Consideration', page: 8, keywords: ['special consideration', 'extension', 'medical certificate', 'deferred', 'deadline'], content: 'A student affected by illness or bereavement may apply for special consideration within seven days of the assessment deadline, with supporting documentation. Approved applications may receive an extension or a deferred examination.' },
    ],
  },
];

export const demoAcademicDates = [
  { id: 1, date: iso(2), title: 'Project Proposal Submission', type: 'Milestone' },
  { id: 2, date: iso(9), title: 'Continuous Assessment Closes', type: 'Deadline' },
  { id: 3, date: iso(21), title: 'Semester 1 Examinations Begin', type: 'Exam' },
  { id: 4, date: iso(37), title: 'Final Project Submission', type: 'Deadline' },
];

export const demoSuggestions = [
  'When do semester exams start?',
  'What is the attendance requirement?',
  'How do I apply for special consideration?',
  'What should the project proposal include?',
];

export const demoChatSeed = [
  { role: 'bot', text: "Hi! I'm your Academic Assistant. Ask me about module handbooks, project guidelines, deadlines or university regulations.", src: null },
];

/* ---------------- Jithmi: Assignments & Risk ----------------
 * Tuned so the seeded set exercises ALL FOUR risk levels — Critical 90, High 55,
 * Medium 40, Low 14 — matching frontend-web/assets/js/mock-data.js exactly.
 * Showing that spread is the whole point of the module. */
export const demoAssignments = [
  { id: 1, title: 'Database Project', module: 'Database Systems', deadline: iso(3), priority: 'High', estHours: 20, doneHours: 5, progress: 25 },
  { id: 2, title: 'Web Assignment', module: 'Programming (OOP)', deadline: iso(4), priority: 'High', estHours: 16, doneHours: 6, progress: 38 },
  { id: 3, title: 'Research Report', module: 'Software Engineering', deadline: iso(9), priority: 'Medium', estHours: 15, doneHours: 5, progress: 33 },
  { id: 4, title: 'ML Mini Project', module: 'AI & Machine Learning', deadline: iso(20), priority: 'Low', estHours: 12, doneHours: 8, progress: 70 },
];

/* ---------------- Common: notifications ---------------- */
export const demoNotifications = [
  { id: 1, source: 'jithmi', title: 'Critical risk: Database Project', message: 'Due in 3 days and only 25% complete. Start a focus session today.', minutesAgo: 20 },
  { id: 2, source: 'kavishka', title: 'New date extracted', message: 'Continuous Assessment Closes was added to your calendar.', minutesAgo: 90 },
  { id: 3, source: 'pasindu', title: 'Daily target reached', message: 'You studied 155 minutes today.', minutesAgo: 180 },
];

export default {
  iso,
  demoUser, demoModules, demoDocuments, demoSummaries, demoStudy,
  demoKnowledgeBase, demoAcademicDates, demoSuggestions, demoChatSeed,
  demoAssignments, demoNotifications,
};
