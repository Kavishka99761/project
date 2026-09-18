/* ==========================================================================
  AcadeAlert — Study & Engagement Module
   Study timer, engagement tracking, break recommendations, productivity analytics.
   ========================================================================== */

(function () {
  "use strict";

  ES.initPage({ active: "study.html", title: "Study & Engagement", subtitle: "Focus sessions & productivity", accent: "var(--es-study)" });

  const FOCUS_MINUTES = 25; // one focused study period before a break is suggested
  const RING = 553;         // circumference of the SVG progress ring
  let seconds = 0, timerId = null, running = false;
  let engagement = ES.engagement.percent;

  const $ = (id) => document.getElementById(id);
  const css = (v) => getComputedStyle(document.documentElement).getPropertyValue(v).trim();

  /* ---------- Populate module select ---------- */
  $("sessModule").innerHTML = ES.modules.map((m) => `<option>${m.name}</option>`).join("");

  /* ---------- Today's stats ---------- */
  function renderStats() {
    const studied = ES.studyToday.studiedMinutes + Math.floor(seconds / 60);
    const target = ES.studyToday.targetMinutes;
    const pct = Math.min(100, Math.round((studied / target) * 100));
    $("statStudied").textContent = ES.fmtDuration(studied);
    $("statTarget").textContent = ES.fmtDuration(target);
    $("statProgress").textContent = pct + "%";
  }

  /* ---------- Timer ---------- */
  function fmt(sec) {
    const m = Math.floor(sec / 60), s = sec % 60;
    return `${String(m).padStart(2, "0")}:${String(s).padStart(2, "0")}`;
  }
  function paintRing() {
    const frac = Math.min(seconds / (FOCUS_MINUTES * 60), 1);
    $("ringProgress").setAttribute("stroke-dashoffset", String(RING * (1 - frac)));
    $("timerText").textContent = fmt(seconds);
  }
  function tick() {
    seconds++;
    paintRing();
    renderStats();
    updateEngagementPassive();
    updateBreak();
    // Session reminder at the end of a focus period
    if (seconds === FOCUS_MINUTES * 60) {
      $("timerLabel").textContent = "Focus period complete!";
      ES.toast("Great work — time for a short break ☕", "info");
    }
  }

  function start() {
    if (running) return;
    running = true;
    timerId = setInterval(tick, 1000);
    $("timerLabel").textContent = "Focusing on " + $("sessModule").value;
    $("btnStart").innerHTML = '<i class="bi bi-play-fill"></i> Resume';
    $("btnPause").disabled = false; $("btnStop").disabled = false;
    ES.currentSession.active = true;
  }
  function pause() {
    running = false; clearInterval(timerId);
    $("timerLabel").textContent = "Paused";
    $("btnPause").disabled = true;
  }
  function stop() {
    clearInterval(timerId); running = false;
    const mins = Math.floor(seconds / 60);
    if (mins >= 1) {
      ES.sessionHistory.unshift({
        id: Date.now(), module: $("sessModule").value, date: ES.isoDate(0),
        planned: FOCUS_MINUTES, actual: mins, engagement,
      });
      ES.studyToday.studiedMinutes += mins;
      renderHistory();
      $("sessionFeedback").innerHTML = `
        <div class="es-card p-3" style="box-shadow:none;background:var(--es-surface-2)">
          <div class="fw-bold small"><i class="bi bi-check-circle-fill me-1" style="color:var(--es-success)"></i>Session recorded</div>
          <div class="text-muted-es small">You studied <strong>${ES.fmtDuration(mins)}</strong> of ${$("sessModule").value}.
          Engagement ${engagement}%. ${mins >= FOCUS_MINUTES ? "Excellent focus — take a 5 min break." : "Try to reach a full 25-min focus block next time."}</div>
        </div>`;
      ES.toast("Session saved (" + mins + " min)");
    }
    seconds = 0; paintRing(); renderStats(); updateBreak();
    $("timerLabel").textContent = "Ready to focus";
    $("btnStart").innerHTML = '<i class="bi bi-play-fill"></i> Start';
    $("btnPause").disabled = true; $("btnStop").disabled = true;
    ES.currentSession.active = false;
  }

  $("btnStart").addEventListener("click", start);
  $("btnPause").addEventListener("click", pause);
  $("btnStop").addEventListener("click", stop);

  /* ---------- Engagement ---------- */
  function engColor(v) { return v >= 70 ? "var(--es-success)" : v >= 45 ? "var(--es-warning)" : "var(--es-danger)"; }
  function engLabel(v) { return v >= 70 ? "Good" : v >= 45 ? "Moderate" : "Low"; }
  function renderEngagement() {
    $("engLevel").textContent = engLabel(engagement);
    $("engPct").textContent = engagement + "%";
    $("engDot").style.background = engColor(engagement);
    $("engBar").style.width = engagement + "%";
    $("engBar").style.background = engColor(engagement);
    if (window.engChartInst) {
      engChartInst.data.labels.push("now");
      engChartInst.data.datasets[0].data.push(engagement);
      if (engChartInst.data.labels.length > 8) { engChartInst.data.labels.shift(); engChartInst.data.datasets[0].data.shift(); }
      engChartInst.update();
    }
  }
  // Passive drift: engagement slowly drops during long focus, recovers on break
  function updateEngagementPassive() {
    if (running && seconds % 60 === 0 && seconds > 0) {
      engagement = Math.max(20, engagement - 2);
      renderEngagement();
    }
  }
  document.querySelectorAll(".es-conc").forEach((b) =>
    b.addEventListener("click", () => {
      engagement = +b.dataset.val;
      renderEngagement();
      ES.toast("Concentration recorded: " + engLabel(engagement), "info");
    })
  );

  /* ---------- Break recommendation ---------- */
  function updateBreak() {
    const mins = Math.floor(seconds / 60);
    let html;
    if (mins >= FOCUS_MINUTES) {
      html = `<div class="es-pill pill-medium mb-2"><i class="bi bi-cup-hot"></i> Take a 5 min break</div>
              <p class="small text-muted-es mb-0">You've completed a full focus block. Stand up, stretch, hydrate, then start another session.</p>`;
    } else if (mins >= 15) {
      html = `<div class="es-pill pill-low mb-2"><i class="bi bi-hourglass-split"></i> ${FOCUS_MINUTES - mins} min to break</div>
              <p class="small text-muted-es mb-0">Stay in the flow — a short break is recommended after ${FOCUS_MINUTES} minutes of focus.</p>`;
    } else {
      html = `<div class="es-pill pill-low mb-2"><i class="bi bi-play-circle"></i> Focus period</div>
              <p class="small text-muted-es mb-0">Recommended pattern: ${FOCUS_MINUTES} min focused study → 5 min break. Start the timer to begin.</p>`;
    }
    $("breakBox").innerHTML = html;
  }

  /* ---------- Personalised tip ---------- */
  function renderTip() {
    const best = ES.weeklyStudy.slice().sort((a, b) => b.minutes - a.minutes)[0];
    $("studyTip").textContent = `You're most productive on ${best.day}s (${ES.fmtDuration(best.minutes)}). Try scheduling your hardest module — ${ES.assignments[0].module} — on that day.`;
  }

  /* ---------- Insights (planned vs actual) ---------- */
  function renderInsights() {
    const planned = ES.sessionHistory.reduce((s, x) => s + x.planned, 0);
    const actual = ES.sessionHistory.reduce((s, x) => s + x.actual, 0);
    const avgEng = Math.round(ES.sessionHistory.reduce((s, x) => s + x.engagement, 0) / ES.sessionHistory.length);
    const rows = [
      { label: "Sessions logged", value: ES.sessionHistory.length, icon: "list-check", color: "study" },
      { label: "Planned time", value: ES.fmtDuration(planned), icon: "calendar-check", color: "learning" },
      { label: "Actual time", value: ES.fmtDuration(actual), icon: "clock-fill", color: "risk" },
      { label: "Adherence", value: Math.round((actual / planned) * 100) + "%", icon: "speedometer2", color: "success" },
      { label: "Avg engagement", value: avgEng + "%", icon: "activity", color: "assistant" },
    ];
    $("insights").innerHTML = rows.map((r) => `
      <div class="es-list-item">
        <span class="es-li-icon bg-soft-${r.color}"><i class="bi bi-${r.icon}"></i></span>
        <div style="flex:1"><div class="es-li-title">${r.label}</div></div>
        <span class="fw-bold">${r.value}</span>
      </div>`).join("");
  }

  /* ---------- History table ---------- */
  function renderHistory() {
    $("historyBody").innerHTML = ES.sessionHistory.map((h) => `
      <tr>
        <td>${h.date}</td>
        <td>${h.module}</td>
        <td>${ES.fmtDuration(h.planned)}</td>
        <td>${ES.fmtDuration(h.actual)}</td>
        <td><span class="es-pill" style="background:${engColor(h.engagement)}22;color:${engColor(h.engagement)}">${h.engagement}%</span></td>
      </tr>`).join("");
  }

  /* ---------- Charts ---------- */
  function buildCharts() {
    Chart.defaults.font.family = "'Segoe UI', system-ui, sans-serif";
    Chart.defaults.color = css("--es-text-muted");
    const grid = css("--es-border");

    new Chart($("weekChart"), {
      type: "bar",
      data: {
        labels: ES.weeklyStudy.map((d) => d.day),
        datasets: [{ label: "Minutes", data: ES.weeklyStudy.map((d) => d.minutes),
          backgroundColor: css("--es-study"), borderRadius: 8, maxBarThickness: 34 }],
      },
      options: {
        responsive: true, plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, grid: { color: grid }, ticks: { callback: (v) => v + "m" } }, x: { grid: { display: false } } },
      },
    });

    window.engChartInst = new Chart($("engChart"), {
      type: "line",
      data: { labels: ES.engagement.history.map((_, i) => "t" + (i + 1)),
        datasets: [{ data: ES.engagement.history, borderColor: css("--es-assistant"),
          backgroundColor: css("--es-assistant") + "22", fill: true, tension: 0.4, pointRadius: 2 }] },
      options: { responsive: true, plugins: { legend: { display: false } },
        scales: { y: { min: 0, max: 100, display: false }, x: { display: false } } },
    });
  }

  /* ---------- Init ---------- */
  renderStats(); renderEngagement(); updateBreak(); renderTip(); renderInsights(); renderHistory(); paintRing(); buildCharts();
})();
