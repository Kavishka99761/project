/* ==========================================================================
   EDU-SMART — Assignment & Deadline Risk Module (Owner: Jithmi)
   Assignment CRUD, workload analysis, risk prediction & explanation,
   priority ranking, daily-hours recommendation and what-if scenarios.
   Integration: consumes study capacity from Pasindu's productivity data.
   ========================================================================== */

(function () {
  "use strict";

  ES.initPage({ active: "assignments.html", title: "Assignment Risk", subtitle: "Deadlines, workload & risk · Jithmi", accent: "var(--es-jithmi)" });

  const $ = (id) => document.getElementById(id);
  const css = (v) => getComputedStyle(document.documentElement).getPropertyValue(v).trim();

  let items = ES.assignments.map((a) => ({ ...a, completed: a.progress >= 100 }));
  let currentView = "upcoming";
  let riskChart = null;

  const RISK_COLOR = { Low: "var(--es-success)", Medium: "var(--es-warning)", High: "#ea580c", Critical: "var(--es-danger)" };
  const RISK_CLASS = { Low: "pill-low", Medium: "pill-medium", High: "pill-high", Critical: "pill-critical" };

  /* ---------- Derived analytics ---------- */
  function activeItems() { return items.filter((a) => !a.completed); }
  function withRisk() { return activeItems().map((a) => ({ a, r: ES.calcRisk(a) })); }

  // Available study time comes from Pasindu's weekly productivity (integration point)
  function weeklyCapacityHours() {
    const total = ES.weeklyStudy.reduce((s, d) => s + d.minutes, 0);
    return +(total / 60).toFixed(1);
  }
  function remainingWorkload() {
    return activeItems().reduce((s, a) => s + Math.max(a.estHours - a.doneHours, 0), 0);
  }
  function overallStatus() {
    const rem = remainingWorkload(), cap = weeklyCapacityHours();
    const ratio = cap > 0 ? rem / cap : rem;
    if (ratio <= 0.6) return { level: "Low", text: "Manageable", ratio };
    if (ratio <= 1) return { level: "Medium", text: "Moderate", ratio };
    if (ratio <= 1.6) return { level: "High", text: "High risk", ratio };
    return { level: "Critical", text: "Critical risk", ratio };
  }

  /* ---------- Stats ---------- */
  function renderStats() {
    const ranked = withRisk().sort((x, y) => y.r.score - x.r.score);
    const top = ranked[0];
    const status = overallStatus();
    const upcomingCount = activeItems().filter((a) => ES.daysUntil(a.deadline) >= 0).length;
    const stats = [
      { icon: "shield-exclamation", color: status.level === "Low" ? "success" : "jithmi", value: status.text, label: "Overall workload status" },
      { icon: "fire", color: "danger", value: top ? top.a.title : "—", label: "Today's top priority", small: true },
      { icon: "calendar-x", color: "jithmi", value: upcomingCount, label: "Upcoming deadlines" },
      { icon: "hourglass-split", color: "warning", value: remainingWorkload() + "h", label: "Remaining workload" },
    ];
    $("statRow").innerHTML = stats.map((s) => `
      <div class="col-6 col-xl-3">
        <div class="es-card es-stat">
          <span class="es-stat-icon bg-soft-${s.color}"><i class="bi bi-${s.icon}"></i></span>
          <div style="min-width:0">
            <div class="es-stat-value ${s.small ? "text-truncate" : ""}" style="${s.small ? "font-size:1.05rem" : ""}">${s.value}</div>
            <div class="es-stat-label">${s.label}</div>
          </div>
        </div>
      </div>`).join("");
  }

  /* ---------- Priority ranking ---------- */
  function renderPriority() {
    const ranked = withRisk().sort((x, y) => y.r.score - x.r.score).slice(0, 4);
    $("priorityList").innerHTML = ranked.length ? ranked.map(({ a, r }, i) => `
      <div class="es-list-item es-fade-in">
        <span class="es-li-icon" style="background:${RISK_COLOR[r.level]}22;color:${RISK_COLOR[r.level]};font-weight:800">${i + 1}</span>
        <div style="flex:1;min-width:0">
          <div class="d-flex align-items-center gap-2">
            <span class="es-li-title text-truncate">${a.title}</span>
            <span class="es-pill ${RISK_CLASS[r.level]}">${r.level} ${r.score}%</span>
          </div>
          <div class="es-progress mt-1"><span style="width:${a.progress}%;background:${RISK_COLOR[r.level]}"></span></div>
          <div class="es-li-sub mt-1">${a.progress}% done · due in ${r.days} day(s) · ${a.module}</div>
        </div>
      </div>`).join("") : `<p class="text-muted-es small mb-0">No active assignments — nice work! 🎉</p>`;
  }

  /* ---------- Assignments table ---------- */
  function renderTable() {
    let list = items.map((a) => ({ a, r: ES.calcRisk(a) }));
    if (currentView === "upcoming") list = list.filter((x) => !x.a.completed && ES.daysUntil(x.a.deadline) >= 0);
    else if (currentView === "overdue") list = list.filter((x) => !x.a.completed && ES.daysUntil(x.a.deadline) < 0);
    else list = list.filter((x) => x.a.completed);
    list.sort((x, y) => new Date(x.a.deadline) - new Date(y.a.deadline));

    $("assignBody").innerHTML = list.length ? list.map(({ a, r }) => {
      const days = ES.daysUntil(a.deadline);
      const due = a.completed ? "Completed" : days < 0 ? `${Math.abs(days)}d overdue` : days === 0 ? "Due today" : `${days}d left`;
      const dueCls = a.completed ? "pill-low" : days < 0 ? "pill-critical" : days <= 3 ? "pill-high" : "pill-medium";
      return `<tr>
        <td><div class="fw-semibold">${a.title}</div><div class="text-muted-es small">${a.module}</div></td>
        <td><span class="es-pill ${dueCls}">${due}</span></td>
        <td style="min-width:120px">
          <div class="d-flex align-items-center gap-2">
            <div class="es-progress" style="flex:1"><span style="width:${a.progress}%"></span></div>
            <span class="small fw-bold">${a.progress}%</span>
          </div>
        </td>
        <td>${a.completed ? '<span class="es-pill pill-low">Done</span>' : `<span class="es-pill ${RISK_CLASS[r.level]}">${r.level}</span>`}</td>
        <td class="text-end">
          <button class="es-icon-btn" style="width:32px;height:32px" data-act="progress" data-id="${a.id}" title="Update progress"><i class="bi bi-plus-slash-minus"></i></button>
          <button class="es-icon-btn" style="width:32px;height:32px" data-act="edit" data-id="${a.id}" title="Edit"><i class="bi bi-pencil"></i></button>
          ${!a.completed ? `<button class="es-icon-btn" style="width:32px;height:32px" data-act="complete" data-id="${a.id}" title="Mark complete"><i class="bi bi-check2-circle"></i></button>` : ""}
          <button class="es-icon-btn" style="width:32px;height:32px" data-act="del" data-id="${a.id}" title="Delete"><i class="bi bi-trash"></i></button>
        </td>
      </tr>`;
    }).join("") : `<tr><td colspan="5" class="text-center text-muted-es py-4">Nothing here.</td></tr>`;
  }

  /* ---------- Workload box ---------- */
  function renderWorkload() {
    const rem = remainingWorkload(), cap = weeklyCapacityHours(), st = overallStatus();
    const gap = rem - cap;
    $("workloadBox").innerHTML = `
      <div class="d-flex justify-content-between mb-2"><span class="text-muted-es small">Remaining workload</span><span class="fw-bold">${rem}h</span></div>
      <div class="es-progress mb-3"><span style="width:${Math.min(100, (rem / Math.max(cap, 1)) * 50)}%;background:var(--es-jithmi)"></span></div>
      <div class="d-flex justify-content-between mb-2"><span class="text-muted-es small">Available this week</span><span class="fw-bold">${cap}h</span></div>
      <div class="es-progress mb-3"><span style="width:100%;background:var(--es-pasindu)"></span></div>
      <div class="d-flex justify-content-between align-items-center">
        <span class="text-muted-es small">Status</span>
        <span class="es-pill ${RISK_CLASS[st.level]}">${st.level === "Low" ? "🟢" : st.level === "Medium" ? "🟡" : "🔴"} ${st.text}</span>
      </div>
      <p class="text-muted-es small mt-2 mb-0">${gap > 0 ? `You need ~${gap.toFixed(1)}h more than your usual weekly capacity. Consider starting early or extending study time.` : "Your workload fits within your normal weekly study capacity."}</p>
      <div class="es-divider"></div>
      <div class="text-muted-es" style="font-size:.72rem"><i class="bi bi-link-45deg me-1"></i>Available time synced from Pasindu's Study &amp; Productivity module.</div>`;
  }

  /* ---------- Recommendation ---------- */
  function renderRecommend() {
    const ranked = withRisk().sort((x, y) => y.r.score - x.r.score);
    const top = ranked[0];
    if (!top) { $("recommendBox").innerHTML = `<p class="text-muted-es small mb-0">All assignments complete. Enjoy the break!</p>`; return; }
    const { a, r } = top;
    const perDay = r.days > 0 ? ((a.estHours - a.doneHours) / r.days) : (a.estHours - a.doneHours);
    $("recommendBox").innerHTML = `
      <div class="fw-bold mb-1"><i class="bi bi-arrow-right-circle-fill me-1" style="color:var(--es-jithmi)"></i>Work on: ${a.title}</div>
      <p class="small text-muted-es mb-2">Study approximately <strong style="color:var(--es-jithmi)">${Math.max(perDay, 0.5).toFixed(1)} hours/day</strong> to finish before the deadline.</p>
      <div class="mb-2">${r.reasons.map((x) => `<div class="small"><i class="bi bi-dot"></i>${x}</div>`).join("")}</div>
      <button class="btn-es-ghost btn-sm w-100" id="startStudy"><i class="bi bi-stopwatch me-1"></i>Start study session</button>
      <div class="text-muted-es mt-2" style="font-size:.72rem"><i class="bi bi-link-45deg me-1"></i>Hands off to Pasindu's Study module.</div>`;
    $("startStudy").addEventListener("click", () => { ES.toast("Recommended task sent to Study module", "info"); setTimeout(() => location.href = "study.html", 700); });
  }

  /* ---------- Risk distribution chart ---------- */
  function renderRiskChart() {
    const counts = { Low: 0, Medium: 0, High: 0, Critical: 0 };
    withRisk().forEach(({ r }) => counts[r.level]++);
    const data = [counts.Low, counts.Medium, counts.High, counts.Critical];
    const colors = [css("--es-success"), css("--es-warning"), "#ea580c", css("--es-danger")];
    if (riskChart) riskChart.destroy();
    riskChart = new Chart($("riskChart"), {
      type: "doughnut",
      data: { labels: ["Low", "Medium", "High", "Critical"], datasets: [{ data, backgroundColor: colors, borderWidth: 0 }] },
      options: { responsive: true, cutout: "62%", plugins: { legend: { position: "bottom", labels: { color: css("--es-text-muted"), boxWidth: 12, padding: 12 } } } },
    });
  }

  /* ---------- What-if planner ---------- */
  function renderWhatIf(hoursPerDay) {
    $("hrsLabel").textContent = hoursPerDay;
    const extra = hoursPerDay * 7; // over a week
    const rem = remainingWorkload();
    const after = Math.max(rem - extra, 0);
    const cap = weeklyCapacityHours() + extra;
    const ratio = cap > 0 ? rem / cap : rem;
    let level = ratio <= 0.6 ? "Low" : ratio <= 1 ? "Medium" : ratio <= 1.6 ? "High" : "Critical";
    // How many assignments become comfortable
    let cleared = 0, acc = 0;
    withRisk().sort((x, y) => ES.daysUntil(x.a.deadline) - ES.daysUntil(y.a.deadline)).forEach(({ a }) => {
      const need = Math.max(a.estHours - a.doneHours, 0);
      if (acc + need <= extra) { cleared++; acc += need; }
    });
    $("whatIfResult").innerHTML = `
      <div class="row g-2 text-center">
        <div class="col-4"><div class="es-card p-2" style="box-shadow:none"><div class="fw-bold" style="font-size:1.3rem">${extra}h</div><div class="text-muted-es small">Added this week</div></div></div>
        <div class="col-4"><div class="es-card p-2" style="box-shadow:none"><div class="fw-bold" style="font-size:1.3rem">${after}h</div><div class="text-muted-es small">Workload left</div></div></div>
        <div class="col-4"><div class="es-card p-2" style="box-shadow:none"><div class="es-pill ${RISK_CLASS[level]}">${level}</div><div class="text-muted-es small mt-1">New status</div></div></div>
      </div>
      <p class="small text-muted-es mt-2 mb-0"><i class="bi bi-info-circle me-1"></i>At ${hoursPerDay}h/day you could comfortably clear <strong>${cleared}</strong> assignment${cleared === 1 ? "" : "s"} within a week.</p>`;
  }

  /* ---------- Modal (add/edit) ---------- */
  function fillModules() { $("aModule").innerHTML = ES.modules.map((m) => `<option>${m.name}</option>`).join(""); }
  function openModal(a) {
    fillModules();
    $("assignModalTitle").innerHTML = a
      ? '<i class="bi bi-pencil-square me-2" style="color:var(--es-jithmi)"></i>Edit Assignment'
      : '<i class="bi bi-clipboard-plus me-2" style="color:var(--es-jithmi)"></i>Add Assignment';
    $("aId").value = a ? a.id : "";
    $("aTitle").value = a ? a.title : "";
    $("aModule").value = a ? a.module : ES.modules[0].name;
    $("aDeadline").value = a ? a.deadline : ES.isoDate(7);
    $("aPriority").value = a ? a.priority : "High";
    $("aEst").value = a ? a.estHours : 10;
    $("aDone").value = a ? a.doneHours : 0;
    $("aProgress").value = a ? a.progress : 0;
  }
  $("addBtn").addEventListener("click", () => openModal(null));
  $("saveAssign").addEventListener("click", () => {
    const title = $("aTitle").value.trim();
    if (!title) return ES.toast("Enter a title", "warn");
    const payload = {
      title, module: $("aModule").value, deadline: $("aDeadline").value || ES.isoDate(0),
      priority: $("aPriority").value, estHours: +$("aEst").value || 1, doneHours: +$("aDone").value || 0,
      progress: Math.min(100, Math.max(0, +$("aProgress").value || 0)),
    };
    const id = $("aId").value;
    if (id) {
      const a = items.find((x) => x.id === +id);
      Object.assign(a, payload); a.completed = a.progress >= 100;
      ES.toast("Assignment updated");
    } else {
      items.unshift({ id: Date.now(), ...payload, completed: payload.progress >= 100 });
      ES.toast("Assignment added");
    }
    bootstrap.Modal.getInstance($("assignModal")).hide();
    renderAll();
  });

  /* ---------- Table actions ---------- */
  $("assignBody").addEventListener("click", (e) => {
    const btn = e.target.closest("[data-act]");
    if (!btn) return;
    const a = items.find((x) => x.id === +btn.dataset.id);
    if (!a) return;
    const act = btn.dataset.act;
    if (act === "progress") {
      const p = prompt(`Update progress for "${a.title}" (0-100):`, a.progress);
      if (p !== null && !isNaN(p)) {
        a.progress = Math.min(100, Math.max(0, +p));
        a.doneHours = +(a.estHours * a.progress / 100).toFixed(1);
        a.completed = a.progress >= 100;
        renderAll(); ES.toast("Risk recalculated", "info");
      }
    } else if (act === "edit") { openModal(a); new bootstrap.Modal($("assignModal")).show(); }
    else if (act === "complete") { a.completed = true; a.progress = 100; renderAll(); ES.toast("Marked complete 🎉"); }
    else if (act === "del") { if (confirm(`Delete "${a.title}"?`)) { items = items.filter((x) => x.id !== a.id); renderAll(); ES.toast("Assignment deleted", "danger"); } }
  });

  /* ---------- View tabs ---------- */
  $("viewTabs").addEventListener("click", (e) => {
    const b = e.target.closest("[data-view]");
    if (!b) return;
    $("viewTabs").querySelectorAll("button").forEach((x) => x.classList.remove("active"));
    b.classList.add("active");
    currentView = b.dataset.view;
    renderTable();
  });

  /* ---------- What-if slider ---------- */
  $("whatIfHours").addEventListener("input", (e) => renderWhatIf(+e.target.value));

  function renderAll() { renderStats(); renderPriority(); renderTable(); renderWorkload(); renderRecommend(); renderRiskChart(); }

  /* ---------- Init ---------- */
  renderAll();
  renderWhatIf(3);
})();
