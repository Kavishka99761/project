/* ==========================================================================
   EDU-SMART — Overview Dashboard
   Aggregates a snapshot from all four modules and shows the integration flow.
   Owner: Common Platform Layer (central hub)
   ========================================================================== */

(function () {
  "use strict";

  ES.initPage({ active: "dashboard.html", title: "Overview", subtitle: "AcadeAlert central hub" });

  /* ---------- Greeting ---------- */
  const hour = new Date().getHours();
  const part = hour < 12 ? "Good morning" : hour < 18 ? "Good afternoon" : "Good evening";
  const firstName = (ES.currentUser.name || "Student").split(" ")[0];
  document.getElementById("greeting").textContent = `${part}, ${firstName} 👋`;
  document.getElementById("greetingSub").textContent =
    new Date().toLocaleDateString("en-GB", { weekday: "long", day: "numeric", month: "long", year: "numeric" }) +
    " · here's your academic day across all four modules.";

  /* ---------- Module quick-access cards ---------- */
  const topAssignment = ES.assignments
    .map((a) => ({ a, risk: ES.calcRisk(a) }))
    .sort((x, y) => y.risk.score - x.risk.score)[0];

  const cards = [
    { href: "learning.html", owner: "Learning", color: "learning", icon: "journal-bookmark-fill",
      title: "Learning Materials", stat: ES.documents.length + " documents",
      sub: ES.summaries.length + " saved summaries" },
    { href: "study.html", owner: "Study", color: "study", icon: "stopwatch-fill",
      title: "Study & Engagement", stat: ES.fmtDuration(ES.studyToday.studiedMinutes),
      sub: "Engagement " + ES.engagement.level },
    { href: "assistant.html", owner: "Assistant", color: "assistant", icon: "robot",
      title: "Academic Assistant", stat: (ES.knowledgeBase.handbooks + ES.knowledgeBase.projectDocs + ES.knowledgeBase.regulations) + " sources",
      sub: ES.academicDates.length + " key dates tracked" },
    { href: "assignments.html", owner: "Risk", color: "risk", icon: "clipboard2-pulse-fill",
      title: "Assignment Risk", stat: topAssignment.risk.level + " risk",
      sub: topAssignment.a.title },
  ];

  document.getElementById("moduleCards").innerHTML = cards.map((c) => `
    <div class="col-sm-6 col-xl-3">
      <a href="${c.href}" class="es-card hoverable d-block h-100 es-fade-in">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <span class="es-stat-icon bg-soft-${c.color}" style="width:46px;height:46px;border-radius:13px;display:grid;place-items:center;font-size:1.3rem">
            <i class="bi bi-${c.icon}"></i>
          </span>
          <span class="es-pill bg-soft-${c.color}" style="font-size:.68rem">${c.owner}</span>
        </div>
        <div class="fw-bold mb-1" style="font-size:1.05rem;color:var(--es-text)">${c.title}</div>
        <div class="fw-bold" style="font-size:1.35rem;color:var(--es-${c.color})">${c.stat}</div>
        <div class="text-muted-es small">${c.sub}</div>
      </a>
    </div>`).join("");

  /* ---------- Day at a glance ---------- */
  const studyPct = Math.round((ES.studyToday.studiedMinutes / ES.studyToday.targetMinutes) * 100);
  const remaining = ES.assignments.reduce((s, a) => s + Math.max(a.estHours - a.doneHours, 0), 0);

  const glance = [
    { icon: "stopwatch-fill", color: "study", label: "Study today",
      value: ES.fmtDuration(ES.studyToday.studiedMinutes), bar: studyPct,
      note: `${studyPct}% of ${ES.fmtDuration(ES.studyToday.targetMinutes)} target` },
    { icon: "exclamation-triangle-fill", color: "risk", label: "Top priority",
      value: topAssignment.a.title, bar: topAssignment.a.progress,
      note: `${topAssignment.risk.level} risk · due in ${topAssignment.risk.days} day(s)` },
    { icon: "hourglass-split", color: "risk", label: "Workload left",
      value: remaining + " hours", bar: Math.min(100, Math.round((remaining / 40) * 100)),
      note: "Across all active assignments" },
    { icon: "journal-text", color: "learning", label: "Recently added",
      value: ES.documents[0].title, bar: 100, note: ES.documents[0].module },
  ];

  document.getElementById("glanceRow").innerHTML = glance.map((g) => `
    <div class="col-md-6">
      <div class="d-flex align-items-start gap-3 p-2">
        <span class="es-stat-icon bg-soft-${g.color}" style="width:44px;height:44px;flex:0 0 44px;border-radius:12px;display:grid;place-items:center;font-size:1.15rem">
          <i class="bi bi-${g.icon}"></i>
        </span>
        <div style="min-width:0;flex:1">
          <div class="text-muted-es small fw-semibold">${g.label}</div>
          <div class="fw-bold text-truncate" style="font-size:1.02rem">${g.value}</div>
          <div class="es-progress mt-2"><span style="width:${g.bar}%;background:var(--es-${g.color})"></span></div>
          <div class="text-muted-es small mt-1">${g.note}</div>
        </div>
      </div>
    </div>`).join("");

  /* ---------- Integration flow ---------- */
  const flow = [
    { m: "Assistant", c: "assistant", i: "robot", t: "Extracts deadline", d: "“Assignment deadline = 20 October”" },
    { m: "Risk", c: "risk", i: "clipboard2-pulse", t: "Calculates priority", d: "“Priority = Database Project”" },
    { m: "Study", c: "study", i: "stopwatch", t: "Starts study session", d: "“Recommended task = Database Project”" },
    { m: "Learning", c: "learning", i: "journal-bookmark", t: "Provides material", d: "“Study OOP Lecture 05”" },
    { m: "Study", c: "study", i: "graph-up", t: "Records progress", d: "“Studied Database for 2 hours”" },
    { m: "Risk", c: "risk", i: "arrow-repeat", t: "Risk recalculated", d: "Workload & risk updated" },
  ];
  document.getElementById("integrationFlow").innerHTML = `
    <div class="d-flex flex-wrap align-items-stretch gap-2">
      ${flow.map((f, idx) => `
        <div class="d-flex align-items-center gap-2" style="flex:1 1 150px">
          <div class="es-card p-3 text-center w-100" style="box-shadow:none;border-color:var(--es-border)">
            <span class="es-stat-icon bg-soft-${f.c}" style="width:40px;height:40px;border-radius:11px;display:grid;place-items:center;margin:0 auto 8px">
              <i class="bi bi-${f.i}"></i>
            </span>
            <div class="fw-bold small" style="color:var(--es-${f.c})">${f.m}</div>
            <div class="small fw-semibold">${f.t}</div>
            <div class="text-muted-es" style="font-size:.72rem">${f.d}</div>
          </div>
          ${idx < flow.length - 1 ? '<i class="bi bi-arrow-right text-muted-es d-none d-lg-block"></i>' : ""}
        </div>`).join("")}
    </div>
    <p class="text-muted-es small mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>
      The four dashboards stay separate internally and exchange data through the Laravel API — the student experiences one system.</p>`;

  /* ---------- Upcoming academic dates ---------- */
  const upcoming = ES.academicDates.slice().sort((a, b) => new Date(a.date) - new Date(b.date)).slice(0, 4);
  document.getElementById("upcomingDates").innerHTML = upcoming.map((d) => {
    const days = ES.daysUntil(d.date);
    return `<div class="es-list-item">
      <span class="es-li-icon bg-soft-assistant"><i class="bi bi-calendar-event"></i></span>
      <div style="min-width:0;flex:1">
        <div class="es-li-title text-truncate">${d.title}</div>
        <div class="es-li-sub">${new Date(d.date).toLocaleDateString("en-GB", { day: "numeric", month: "short" })} · ${d.type}</div>
      </div>
      <span class="es-pill ${days <= 3 ? "pill-critical" : "pill-medium"}">${days}d</span>
    </div>`;
  }).join("");

  /* ---------- Smart notifications (cross-module) ---------- */
  const notifs = [];
  if (topAssignment.risk.level === "Critical" || topAssignment.risk.level === "High") {
    notifs.push({ i: "exclamation-triangle-fill", c: "risk", t: `${topAssignment.a.title} is ${topAssignment.risk.level} risk`, s: topAssignment.risk.reasons[0] });
  }
  if (studyPct < 100) {
    notifs.push({ i: "stopwatch-fill", c: "study", t: `Keep going — ${ES.fmtDuration(ES.studyToday.targetMinutes - ES.studyToday.studiedMinutes)} to hit today's target`, s: "Study & Engagement" });
  }
  notifs.push({ i: "robot", c: "assistant", t: `Proposal submission in ${ES.daysUntil("2026-09-15")} days`, s: "Academic Assistant" });
  notifs.push({ i: "journal-bookmark-fill", c: "learning", t: "New summary ready: " + ES.summaries[0].title, s: "Learning Materials" });

  document.getElementById("notifList").innerHTML = notifs.map((n) => `
    <div class="es-list-item">
      <span class="es-li-icon bg-soft-${n.c}"><i class="bi bi-${n.i}"></i></span>
      <div style="min-width:0;flex:1">
        <div class="es-li-title text-truncate">${n.t}</div>
        <div class="es-li-sub text-truncate">${n.s}</div>
      </div>
    </div>`).join("");
})();
