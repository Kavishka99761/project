/* ==========================================================================
   EDU-SMART — Common Calendar (shared service)
   Merges events from all four modules into one month view.
   ========================================================================== */

(function () {
  "use strict";
  ES.initPage({ active: "calendar.html", title: "Calendar", subtitle: "Shared Common Platform service" });

  const $ = (id) => document.getElementById(id);

  /* Merge events from every module */
  const events = [];
  ES.academicDates.forEach((d) => events.push({ date: d.date, title: d.title, kind: "academic", color: "var(--es-assistant)" }));
  ES.assignments.forEach((a) => events.push({ date: a.deadline, title: a.title + " (deadline)", kind: "assignment", color: "var(--es-risk)" }));
  ES.sessionHistory.forEach((s) => events.push({ date: s.date, title: "Studied " + s.module, kind: "study", color: "var(--es-study)" }));

  let view = new Date();
  view.setDate(1);

  function eventsOn(y, m, d) {
    const key = `${y}-${String(m + 1).padStart(2, "0")}-${String(d).padStart(2, "0")}`;
    return events.filter((e) => e.date === key);
  }

  function render() {
    const y = view.getFullYear(), m = view.getMonth();
    $("calTitle").textContent = view.toLocaleDateString("en-GB", { month: "long", year: "numeric" });
    const firstDay = (new Date(y, m, 1).getDay() + 6) % 7; // Monday-first
    const daysInMonth = new Date(y, m + 1, 0).getDate();
    const today = new Date(); today.setHours(0, 0, 0, 0);

    const headers = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];
    let html = headers.map((h) => `<div class="col text-center text-muted-es small fw-bold py-2" style="flex:0 0 14.28%;max-width:14.28%">${h}</div>`).join("");

    for (let i = 0; i < firstDay; i++) {
      html += `<div class="col" style="flex:0 0 14.28%;max-width:14.28%"><div class="p-2" style="min-height:74px"></div></div>`;
    }
    for (let d = 1; d <= daysInMonth; d++) {
      const isToday = today.getFullYear() === y && today.getMonth() === m && today.getDate() === d;
      const evs = eventsOn(y, m, d);
      const dots = evs.slice(0, 3).map((e) => `<span title="${e.title}" style="display:inline-block;width:7px;height:7px;border-radius:50%;background:${e.color};margin-right:3px"></span>`).join("");
      html += `<div class="col" style="flex:0 0 14.28%;max-width:14.28%">
        <div class="p-2" style="min-height:74px;border-radius:12px;border:1px solid ${isToday ? "var(--es-primary)" : "var(--es-border)"};background:${isToday ? "var(--es-primary-soft)" : "transparent"}">
          <div class="fw-bold small ${isToday ? "" : "text-muted-es"}">${d}</div>
          <div class="mt-1">${dots}</div>
          ${evs.length ? `<div class="text-truncate" style="font-size:.62rem;color:var(--es-text-muted)">${evs[0].title}</div>` : ""}
        </div></div>`;
    }
    $("calGrid").innerHTML = html;

    // Month event list
    const monthEvents = events
      .filter((e) => e.date.startsWith(`${y}-${String(m + 1).padStart(2, "0")}`))
      .sort((a, b) => new Date(a.date) - new Date(b.date));
    $("monthEvents").innerHTML = monthEvents.length ? monthEvents.map((e) => `
      <div class="es-list-item">
        <span class="es-li-icon" style="background:${e.color}22;color:${e.color}"><i class="bi bi-calendar-event"></i></span>
        <div style="flex:1;min-width:0"><div class="es-li-title text-truncate">${e.title}</div>
        <div class="es-li-sub">${new Date(e.date).toLocaleDateString("en-GB", { weekday: "short", day: "numeric", month: "short" })}</div></div>
      </div>`).join("") : `<p class="text-muted-es small mb-0">No events this month.</p>`;
  }

  $("prevMonth").addEventListener("click", () => { view.setMonth(view.getMonth() - 1); render(); });
  $("nextMonth").addEventListener("click", () => { view.setMonth(view.getMonth() + 1); render(); });
  render();
})();
