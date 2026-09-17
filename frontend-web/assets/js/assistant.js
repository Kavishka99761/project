/* ==========================================================================
   EDU-SMART — Academic Assistant Module (Owner: Kavishka)
   RAG-style chatbot over an academic knowledge base, document search,
   academic date extraction and calendar integration.

   NOTE — this is a SIMPLIFIED, standalone retrieval, not a port of the API.
   It matches whole documents by substring keyword overlap (raw threshold >= 2)
   and returns a hand-written answer per KB entry. The authoritative engine is
   backend-laravel/app/Services/RetrievalService.php (chunk-level, normalised
   0.08 threshold), faithfully ported in mobile-app/src/api/retrieve.js.
   This page keeps its own corpus so it stays build-free and backend-free.
   ========================================================================== */

(function () {
  "use strict";

  ES.initPage({ active: "assistant.html", title: "Academic Assistant", subtitle: "Chatbot & academic dates · Kavishka", accent: "var(--es-kavishka)" });

  const $ = (id) => document.getElementById(id);

  /* ---------- Knowledge base corpus (indexed academic documents) ---------- */
  const KB = [
    { id: 1, cat: "Handbook", title: "SE201 Module Handbook", pages: 42,
      keywords: ["attendance", "requirement", "module", "assessment", "grading", "coursework"],
      answer: "The SE201 attendance requirement is a minimum of 80% for each module. Assessment is 60% coursework and 40% final exam. A grade below 40% overall is a fail.",
      section: "Section 4 — Assessment & Attendance", page: 12 },
    { id: 2, cat: "Project", title: "Final Year Project Guidelines", pages: 28,
      keywords: ["project", "submission", "deadline", "requirements", "proposal", "interim", "final", "word", "count"],
      answer: "The final project submission deadline is 30 November 2026. Requirements: a 10,000-word report (±10%), source code, a 15-minute demo and a viva. Proposal is due 15 Sep; interim review 20 Oct.",
      section: "Section 2 — Milestones & Deliverables", page: 5 },
    { id: 3, cat: "Regulation", title: "University Academic Regulations", pages: 64,
      keywords: ["regulation", "plagiarism", "late", "penalty", "submission", "extension", "grading", "appeal"],
      answer: "Late submissions lose 5% of the awarded mark per working day, up to 5 working days, after which the work scores zero unless an approved extension is in place. Plagiarism above 30% similarity triggers an academic-integrity review.",
      section: "Regulation 7.3 — Late Submission & Extensions", page: 31 },
    { id: 4, cat: "Handbook", title: "DB202 Database Systems Handbook", pages: 38,
      keywords: ["database", "exam", "practical", "assessment", "db202", "coursework", "weighting"],
      answer: "DB202 is assessed 50% practical coursework (ER modelling + SQL implementation) and 50% written exam. The practical must be submitted one week before the exam period.",
      section: "Section 3 — Assessment Structure", page: 9 },
    { id: 5, cat: "Project", title: "Interim Review Requirements", pages: 12,
      keywords: ["interim", "review", "presentation", "progress", "requirements", "20 october"],
      answer: "The interim review (20 October) requires a progress report, a working prototype demo and a 10-minute presentation covering completed objectives and the plan for the remainder.",
      section: "Section 1 — Interim Deliverables", page: 2 },
  ];

  let conversation = ES.chatSeed.slice();
  let calendar = ES.academicDates.map((d) => ({ ...d, reminder: "1 day before" }));

  /* ---------- RAG retrieval: score KB entries by keyword overlap ---------- */
  function retrieve(query) {
    const q = query.toLowerCase();
    const tokens = q.replace(/[^a-z0-9\s]/g, "").split(/\s+/).filter((t) => t.length > 2);
    let best = null, bestScore = 0;
    KB.forEach((doc) => {
      let score = 0;
      doc.keywords.forEach((k) => { if (q.includes(k)) score += 3; });
      tokens.forEach((t) => { if (doc.keywords.some((k) => k.includes(t)) || doc.title.toLowerCase().includes(t)) score += 1; });
      if (score > bestScore) { bestScore = score; best = doc; }
    });
    return bestScore >= 2 ? best : null;
  }

  function botReply(query) {
    const hit = retrieve(query);
    if (hit) return { text: hit.answer, src: `${hit.title} · ${hit.section} · p.${hit.page}` };
    return { text: "I couldn't find that in the indexed academic documents. Try asking about attendance, project deadlines, submission rules, assessment weighting or the interim review.", src: "" };
  }

  /* ---------- Chat rendering ---------- */
  function renderChat() {
    const box = $("chatBox");
    box.innerHTML = conversation.map((m) => `
      <div class="es-bubble ${m.role}">
        ${m.text}
        ${m.src ? `<span class="es-src"><i class="bi bi-file-earmark-text me-1"></i>Source: ${m.src}</span>` : ""}
      </div>`).join("");
    box.scrollTop = box.scrollHeight;
  }

  function ask(text) {
    if (!text.trim()) return;
    conversation.push({ role: "user", text, src: "" });
    renderChat();
    $("chatInput").value = "";
    // Simulate retrieval latency
    setTimeout(() => {
      const r = botReply(text);
      conversation.push({ role: "bot", text: r.text, src: r.src });
      if (!ES.recentQuestions.includes(text)) ES.recentQuestions.unshift(text);
      renderChat();
    }, 500);
  }

  $("chatForm").addEventListener("submit", (e) => { e.preventDefault(); ask($("chatInput").value); });
  $("clearChat").addEventListener("click", () => { conversation = ES.chatSeed.slice(); renderChat(); ES.toast("Conversation cleared", "info"); });

  /* ---------- Suggestions & recent questions ---------- */
  function renderSuggestions() {
    const items = ES.recentQuestions.slice(0, 3);
    $("suggestions").innerHTML = items.map((q) =>
      `<button class="es-pill bg-soft-kavishka es-suggest" style="cursor:pointer;border:none" data-q="${q.replace(/"/g, "&quot;")}"><i class="bi bi-arrow-repeat"></i> ${q}</button>`
    ).join("");
    $("suggestions").querySelectorAll(".es-suggest").forEach((b) => b.addEventListener("click", () => ask(b.dataset.q)));
  }

  /* ---------- Knowledge base ---------- */
  function renderKB(filter) {
    const counts = { Handbook: 0, Project: 0, Regulation: 0 };
    KB.forEach((d) => counts[d.cat]++);
    $("kbStats").innerHTML = [
      { l: "Module Handbooks", v: ES.knowledgeBase.handbooks, i: "journal-bookmark", c: "bethmi" },
      { l: "Project Docs", v: ES.knowledgeBase.projectDocs, i: "folder2-open", c: "jithmi" },
      { l: "Regulations", v: ES.knowledgeBase.regulations, i: "shield-check", c: "pasindu" },
    ].map((s) => `
      <div class="col-4">
        <div class="es-stat-icon bg-soft-${s.c}" style="width:40px;height:40px;margin:0 auto 6px;border-radius:12px;display:grid;place-items:center"><i class="bi bi-${s.i}"></i></div>
        <div class="fw-bold" style="font-size:1.2rem">${s.v}</div>
        <div class="text-muted-es" style="font-size:.7rem">${s.l}</div>
      </div>`).join("");

    const q = (filter || "").toLowerCase();
    const list = KB.filter((d) => !q || d.title.toLowerCase().includes(q) || d.keywords.some((k) => k.includes(q)) || d.cat.toLowerCase().includes(q));
    $("kbList").innerHTML = list.length ? list.map((d) => `
      <div class="es-list-item">
        <span class="es-li-icon bg-soft-kavishka"><i class="bi bi-file-earmark-text"></i></span>
        <div style="flex:1;min-width:0">
          <div class="es-li-title text-truncate">${d.title}</div>
          <div class="es-li-sub">${d.cat} · ${d.pages} pages · indexed</div>
        </div>
      </div>`).join("") : `<p class="text-muted-es small text-center py-3 mb-0">No documents match.</p>`;
  }
  $("kbSearch").addEventListener("input", (e) => renderKB(e.target.value));

  /* ---------- Academic dates ---------- */
  function typePill(t) {
    const map = { Deadline: "pill-critical", Milestone: "pill-medium", Exam: "pill-high" };
    return `<span class="es-pill ${map[t] || "pill-low"}">${t}</span>`;
  }
  function renderDates() {
    $("dateList").innerHTML = calendar.slice().sort((a, b) => new Date(a.date) - new Date(b.date)).map((d) => `
      <div class="es-list-item">
        <span class="es-li-icon bg-soft-kavishka"><i class="bi bi-calendar-event"></i></span>
        <div style="flex:1;min-width:0">
          <div class="es-li-title text-truncate">${d.title}</div>
          <div class="es-li-sub">${new Date(d.date).toLocaleDateString("en-GB", { day: "numeric", month: "short", year: "numeric" })} · in ${ES.daysUntil(d.date)} days</div>
        </div>
        ${typePill(d.type)}
      </div>`).join("");
  }
  $("extractDates").addEventListener("click", (e) => {
    const btn = e.currentTarget;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    setTimeout(() => {
      btn.innerHTML = '<i class="bi bi-magic"></i>';
      renderDates(); renderCalendar();
      ES.toast("Scanned documents — academic dates refreshed", "info");
    }, 900);
  });

  /* ---------- Calendar table ---------- */
  function renderCalendar() {
    $("calBody").innerHTML = calendar.map((d, i) => `
      <tr>
        <td>${new Date(d.date).toLocaleDateString("en-GB", { day: "numeric", month: "short" })}</td>
        <td class="fw-semibold">${d.title}</td>
        <td>${typePill(d.type)}</td>
        <td class="text-muted-es small"><i class="bi bi-bell me-1"></i>${d.reminder}</td>
        <td class="text-end">
          <button class="es-icon-btn" style="width:32px;height:32px" data-cact="edit" data-i="${i}"><i class="bi bi-pencil"></i></button>
          <button class="es-icon-btn" style="width:32px;height:32px" data-cact="reminder" data-i="${i}"><i class="bi bi-bell"></i></button>
          <button class="es-icon-btn" style="width:32px;height:32px" data-cact="del" data-i="${i}"><i class="bi bi-trash"></i></button>
        </td>
      </tr>`).join("");
  }
  $("calBody").addEventListener("click", (e) => {
    const btn = e.target.closest("[data-cact]");
    if (!btn) return;
    const i = +btn.dataset.i;
    const ev = calendar[i];
    if (btn.dataset.cact === "edit") {
      const t = prompt("Edit event title:", ev.title);
      if (t) { ev.title = t; renderCalendar(); renderDates(); ES.toast("Event updated"); }
    } else if (btn.dataset.cact === "reminder") {
      const opts = ["1 day before", "3 days before", "1 week before", "No reminder"];
      const r = prompt("Set reminder:\n" + opts.map((o, n) => (n + 1) + ". " + o).join("\n"), ev.reminder);
      if (r) { ev.reminder = r; renderCalendar(); ES.toast("Reminder set: " + r, "info"); }
    } else if (btn.dataset.cact === "del") {
      if (confirm(`Delete "${ev.title}"?`)) { calendar.splice(i, 1); renderCalendar(); renderDates(); ES.toast("Event deleted", "danger"); }
    }
  });
  $("addEvent").addEventListener("click", () => {
    const title = prompt("Event title:");
    if (!title) return;
    const date = prompt("Date (YYYY-MM-DD):", ES.isoDate(0));
    if (!date) return;
    const type = prompt("Type (Deadline / Milestone / Exam):", "Milestone") || "Milestone";
    calendar.push({ id: Date.now(), title, date, type, reminder: "1 day before" });
    renderCalendar(); renderDates(); ES.toast("Event added to calendar");
  });

  /* ---------- Init ---------- */
  renderChat(); renderSuggestions(); renderKB(""); renderDates(); renderCalendar();
})();
