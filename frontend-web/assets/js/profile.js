/* ==========================================================================
   EDU-SMART — Profile & Settings (Common Platform Layer)
   Student profile, module management, preferences and data export/backup.
   ========================================================================== */

(function () {
  "use strict";
  ES.initPage({ active: "profile.html", title: "Profile & Settings", subtitle: "Common Platform Layer" });

  const $ = (id) => document.getElementById(id);
  let modules = ES.modules.slice();

  function initials(name) {
    return name.split(" ").map((p) => p[0]).slice(0, 2).join("").toUpperCase();
  }

  function renderProfile() {
    const u = ES.currentUser;
    $("pAvatar").textContent = initials(u.name);
    $("pName").textContent = u.name;
    $("pEmail").textContent = u.email;
    $("pProgram").textContent = u.program;
    $("pYear").textContent = u.year;
    $("inName").value = u.name;
    $("inEmail").value = u.email;
    $("inProgram").value = u.program;
    $("inYear").value = u.year;

    $("pQuickStats").innerHTML = [
      { v: ES.documents.length, l: "Docs" }, { v: ES.assignments.length, l: "Tasks" },
      { v: ES.sessionHistory.length, l: "Sessions" }, { v: modules.length, l: "Modules" },
    ].map((s) => `<div class="col-3"><div class="fw-bold" style="font-size:1.2rem">${s.v}</div><div class="text-muted-es" style="font-size:.7rem">${s.l}</div></div>`).join("");
  }

  function renderModules() {
    $("moduleChips").innerHTML = modules.map((m, i) => `
      <span class="es-pill bg-soft-${m.color || "primary"}">
        <i class="bi bi-${m.icon || "book"}"></i> ${m.code} — ${m.name}
        <i class="bi bi-x-circle ms-1" style="cursor:pointer" data-rm="${i}"></i>
      </span>`).join("") || `<span class="text-muted-es small">No modules yet.</span>`;
    $("moduleChips").querySelectorAll("[data-rm]").forEach((x) =>
      x.addEventListener("click", () => { modules.splice(+x.dataset.rm, 1); renderModules(); renderProfile(); ES.toast("Module removed", "info"); })
    );
  }

  /* ---------- Events ---------- */
  $("saveProfile").addEventListener("click", () => {
    ES.currentUser.name = $("inName").value.trim() || ES.currentUser.name;
    ES.currentUser.email = $("inEmail").value.trim() || ES.currentUser.email;
    ES.currentUser.program = $("inProgram").value.trim();
    ES.currentUser.year = $("inYear").value.trim();
    renderProfile();
    ES.renderSidebar("profile.html"); // refresh sidebar user card
    ES.toast("Profile saved");
  });

  $("addModule").addEventListener("click", () => {
    const val = $("newModule").value.trim();
    if (!val) return ES.toast("Enter a module", "warn");
    const parts = val.split("—");
    const code = (parts[1] ? parts[0] : val.split(" ")[0]).trim();
    const name = (parts[1] ? parts[1] : val.replace(code, "")).trim() || code;
    modules.push({ id: Date.now(), code, name, color: "primary", icon: "book" });
    $("newModule").value = "";
    renderModules(); renderProfile(); ES.toast("Module added");
  });

  // Dark mode switch stays in sync with the global theme
  $("setDark").checked = document.documentElement.getAttribute("data-theme") === "dark";
  $("setDark").addEventListener("change", (e) => ES.applyTheme(e.target.checked ? "dark" : "light"));

  $("setTarget").value = ES.studyToday.targetMinutes;
  $("setTarget").addEventListener("change", (e) => { ES.studyToday.targetMinutes = +e.target.value || 180; ES.toast("Study target updated", "info"); });

  $("setNotif").addEventListener("change", (e) => ES.toast("Deadline notifications " + (e.target.checked ? "on" : "off"), "info"));

  /* ---------- Export / backup ---------- */
  function download(name, content, type) {
    const blob = new Blob([content], { type });
    const a = document.createElement("a");
    a.href = URL.createObjectURL(blob); a.download = name; a.click();
    URL.revokeObjectURL(a.href);
  }
  $("exportJson").addEventListener("click", () => {
    const snapshot = { exportedAt: new Date().toISOString(), user: ES.currentUser, modules, documents: ES.documents, summaries: ES.summaries, assignments: ES.assignments, studySessions: ES.sessionHistory, academicDates: ES.academicDates };
    download("edu-smart-backup.json", JSON.stringify(snapshot, null, 2), "application/json");
    ES.toast("Backup exported", "info");
  });
  $("exportCsv").addEventListener("click", () => {
    const head = "Title,Module,Deadline,Priority,EstHours,DoneHours,Progress,Risk\n";
    const rows = ES.assignments.map((a) => {
      const r = ES.calcRisk(a);
      return [a.title, a.module, a.deadline, a.priority, a.estHours, a.doneHours, a.progress + "%", r.level].map((x) => `"${String(x).replace(/"/g, '""')}"`).join(",");
    }).join("\n");
    download("assignments.csv", head + rows, "text/csv");
    ES.toast("CSV exported", "info");
  });

  $("logoutBtn").addEventListener("click", ES.logout);

  renderProfile(); renderModules();
})();
