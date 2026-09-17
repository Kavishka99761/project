/* ==========================================================================
   EDU-SMART — App Shell
   Renders the shared sidebar + topbar, handles theming (light/dark),
   mobile navigation, auth guard and logout. Included on every dashboard page.
   ========================================================================== */

(function () {
  "use strict";
  window.ES = window.ES || {};

  /* ---------- Theme (Common Platform Layer: dark/light mode) ---------- */
  const THEME_KEY = "es-theme";
  ES.applyTheme = function (theme) {
    document.documentElement.setAttribute("data-theme", theme);
    try { localStorage.setItem(THEME_KEY, theme); } catch (e) {}
    const icon = document.getElementById("themeIcon");
    if (icon) icon.className = theme === "dark" ? "bi bi-sun-stars" : "bi bi-moon-stars";
  };
  ES.initTheme = function () {
    let saved = "light";
    try { saved = localStorage.getItem(THEME_KEY) || "light"; } catch (e) {}
    ES.applyTheme(saved);
  };
  ES.toggleTheme = function () {
    const cur = document.documentElement.getAttribute("data-theme") === "dark" ? "dark" : "light";
    ES.applyTheme(cur === "dark" ? "light" : "dark");
  };

  /* ---------- Auth guard (Common Platform Layer: authentication) ---------- */
  const AUTH_KEY = "es-auth";
  ES.isAuthed = function () {
    try { return localStorage.getItem(AUTH_KEY) === "1"; } catch (e) { return true; }
  };
  ES.login = function (email) {
    try { localStorage.setItem(AUTH_KEY, "1"); localStorage.setItem("es-email", email || ""); } catch (e) {}
  };
  ES.logout = function () {
    try { localStorage.removeItem(AUTH_KEY); } catch (e) {}
    window.location.href = "index.html";
  };
  ES.guard = function () {
    // Allow local file:// demo without hard-blocking; only redirect on http(s)
    if (!ES.isAuthed() && location.protocol.startsWith("http")) {
      window.location.href = "index.html";
    }
  };

  /* ---------- Navigation config ---------- */
  ES.NAV = [
    { href: "dashboard.html",   label: "Overview",            icon: "grid-1x2",        group: "main" },
    { href: "learning.html",    label: "Learning Materials",  icon: "journal-bookmark", group: "main", mod: "bethmi",   dot: "var(--es-bethmi)" },
    { href: "study.html",       label: "Study & Engagement",  icon: "stopwatch",        group: "main", mod: "pasindu",  dot: "var(--es-pasindu)" },
    { href: "assistant.html",   label: "Academic Assistant",  icon: "robot",            group: "main", mod: "kavishka", dot: "var(--es-kavishka)" },
    { href: "assignments.html", label: "Assignment Risk",     icon: "clipboard2-pulse", group: "main", mod: "jithmi",   dot: "var(--es-jithmi)" },
    { href: "calendar.html",    label: "Calendar",            icon: "calendar3",        group: "common" },
    { href: "profile.html",     label: "Profile & Settings",  icon: "person-gear",      group: "common" },
  ];

  ES.renderSidebar = function (activeHref) {
    const el = document.getElementById("esSidebar");
    if (!el) return;
    const user = (window.ES && ES.currentUser) || { name: "Student", initials: "ST" };
    const link = (n) => {
      const cls = ["es-nav-link"];
      if (n.href === activeHref) { cls.push("active"); if (n.mod) cls.push("mod-" + n.mod); }
      const dot = n.dot ? `<span class="es-nav-dot" style="background:${n.dot}"></span>` : "";
      return `<a href="${n.href}" class="${cls.join(" ")}"><i class="bi bi-${n.icon}"></i><span>${n.label}</span>${dot}</a>`;
    };
    const main = ES.NAV.filter((n) => n.group === "main").map(link).join("");
    const common = ES.NAV.filter((n) => n.group === "common").map(link).join("");
    el.innerHTML = `
      <div class="es-brand">
        <span class="es-logo"><i class="bi bi-mortarboard-fill"></i></span>
        <span>EDU-SMART</span>
      </div>
      <div class="es-nav-label">Dashboards</div>
      ${main}
      <div class="es-nav-label">Common Platform</div>
      ${common}
      <div class="es-divider"></div>
      <a href="#" class="es-nav-link" id="esLogoutBtn"><i class="bi bi-box-arrow-right"></i><span>Log out</span></a>
      <div class="es-list-item mt-2" style="border:none">
        <span class="es-avatar">${user.initials || "ST"}</span>
        <div style="min-width:0">
          <div class="es-li-title text-truncate">${user.name || "Student"}</div>
          <div class="es-li-sub text-truncate">${(user.year || "")}</div>
        </div>
      </div>`;
    const lo = document.getElementById("esLogoutBtn");
    if (lo) lo.addEventListener("click", (e) => { e.preventDefault(); ES.logout(); });
  };

  ES.renderTopbar = function (title, subtitle, accent) {
    const el = document.getElementById("esTopbar");
    if (!el) return;
    const accentBar = accent ? `style="border-left:4px solid ${accent};padding-left:12px"` : "";
    el.innerHTML = `
      <button class="es-icon-btn es-sidebar-toggle" id="esSidebarToggle" aria-label="Menu"><i class="bi bi-list"></i></button>
      <div ${accentBar}>
        <h1 class="es-page-title">${title}</h1>
        <p class="es-page-sub">${subtitle || ""}</p>
      </div>
      <div class="ms-auto d-flex align-items-center gap-2">
        <button class="es-icon-btn" id="esThemeBtn" title="Toggle theme"><i class="bi bi-moon-stars" id="themeIcon"></i></button>
        <button class="es-icon-btn" title="Notifications" data-es-notif><i class="bi bi-bell"></i></button>
        <span class="es-avatar" id="esTopAvatar"></span>
      </div>`;
    const tb = document.getElementById("esThemeBtn");
    if (tb) tb.addEventListener("click", ES.toggleTheme);
    const av = document.getElementById("esTopAvatar");
    if (av && ES.currentUser) av.textContent = ES.currentUser.initials;
    ES.syncThemeIcon();

    const st = document.getElementById("esSidebarToggle");
    const sb = document.getElementById("esSidebar");
    const bd = document.getElementById("esBackdrop");
    if (st && sb) {
      st.addEventListener("click", () => { sb.classList.add("open"); if (bd) bd.classList.add("show"); });
      if (bd) bd.addEventListener("click", () => { sb.classList.remove("open"); bd.classList.remove("show"); });
    }
  };

  ES.syncThemeIcon = function () {
    const theme = document.documentElement.getAttribute("data-theme");
    const icon = document.getElementById("themeIcon");
    if (icon) icon.className = theme === "dark" ? "bi bi-sun-stars" : "bi bi-moon-stars";
  };

  /* ---------- Bootstrap modal accessibility fix (Common UI) ----------
     Bootstrap applies aria-hidden to a modal as it closes. When focus is still
     on a control inside it — usually the × close button — the browser refuses
     the attribute and logs "Blocked aria-hidden on an element because its
     descendant retained focus". hide.bs.modal fires before aria-hidden is
     applied, so releasing focus here keeps the console clean and matches what
     assistive technology expects. Registered once; covers every modal on every
     page, so individual pages do not each need their own handler. */
  document.addEventListener("hide.bs.modal", function (e) {
    const active = document.activeElement;
    if (active && active !== document.body && e.target.contains(active)) active.blur();
  }, true);

  /* ---------- Bootstrapping a dashboard page ---------- */
  ES.initPage = function (cfg) {
    ES.initTheme();
    ES.guard();
    ES.renderSidebar(cfg.active);
    ES.renderTopbar(cfg.title, cfg.subtitle, cfg.accent);
  };

  /* ---------- Lightweight toast (Common UI) ---------- */
  ES.toast = function (message, type) {
    type = type || "success";
    let host = document.getElementById("esToastHost");
    if (!host) {
      host = document.createElement("div");
      host.id = "esToastHost";
      host.style.cssText = "position:fixed;top:18px;right:18px;z-index:2000;display:flex;flex-direction:column;gap:10px";
      document.body.appendChild(host);
    }
    const icons = { success: "check-circle-fill", info: "info-circle-fill", warn: "exclamation-triangle-fill", danger: "x-circle-fill" };
    const colors = { success: "var(--es-success)", info: "var(--es-info)", warn: "var(--es-warning)", danger: "var(--es-danger)" };
    const el = document.createElement("div");
    el.className = "es-card es-fade-in d-flex align-items-center gap-2";
    el.style.cssText = "min-width:240px;max-width:340px;padding:12px 16px;box-shadow:var(--es-shadow-lg)";
    el.innerHTML = `<i class="bi bi-${icons[type] || icons.info}" style="color:${colors[type] || colors.info};font-size:1.1rem"></i><span class="small fw-semibold">${message}</span>`;
    host.appendChild(el);
    setTimeout(() => { el.style.opacity = "0"; el.style.transform = "translateY(-8px)"; el.style.transition = "all .3s"; setTimeout(() => el.remove(), 300); }, 2600);
  };
})();
