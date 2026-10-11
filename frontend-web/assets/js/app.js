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
        <span class="es-avatar">${ES.initialsOf ? ES.initialsOf(user) : (user.initials || "ST")}</span>
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
        <div class="es-dd" id="esNotifDd">
          <button class="es-icon-btn es-notif-btn" id="esNotifBtn" title="Notifications" aria-label="Notifications" aria-haspopup="true" aria-expanded="false" data-es-notif>
            <i class="bi bi-bell"></i><span class="es-notif-badge" id="esNotifBadge" hidden></span>
          </button>
          <div class="es-dd-menu es-notif-menu" id="esNotifMenu" role="menu" aria-label="Notifications"></div>
        </div>
        <div class="es-dd" id="esUserDd">
          <button class="es-avatar es-avatar-btn" id="esTopAvatar" title="Account" aria-label="Account menu" aria-haspopup="true" aria-expanded="false"></button>
          <div class="es-dd-menu es-user-menu" id="esUserMenu" role="menu" aria-label="Account"></div>
        </div>
      </div>`;
    const tb = document.getElementById("esThemeBtn");
    if (tb) tb.addEventListener("click", () => { ES.toggleTheme(); ES.renderUserMenu(); });
    const av = document.getElementById("esTopAvatar");
    if (av && ES.currentUser) av.textContent = ES.initialsOf(ES.currentUser);
    ES.syncThemeIcon();
    ES.initTopbarMenus();

    const st = document.getElementById("esSidebarToggle");
    const sb = document.getElementById("esSidebar");
    const bd = document.getElementById("esBackdrop");
    if (st && sb) {
      st.addEventListener("click", () => { sb.classList.add("open"); if (bd) bd.classList.add("show"); });
      if (bd) bd.addEventListener("click", () => { sb.classList.remove("open"); bd.classList.remove("show"); });
    }
  };

  /* ---------- Top-right menus: notifications + account (Common Platform Layer) ----------
     Cross-module notifications follow the integration flow
     KAVISHKA (dates) → JITHMI (risk) → PASINDU (study) → BETHMI (summaries).
     Read / dismissed state persists in localStorage so it survives page changes. */
  const NOTIF_KEY = "es-notif-state";
  const USER_KEY = "es-user";

  ES.initialsOf = function (u) {
    const parts = String((u && u.name) || "").trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return (u && u.initials) || "?";
    return (parts[0][0] + (parts.length > 1 ? parts[parts.length - 1][0] : "")).toUpperCase();
  };

  // Profile edits (profile.html) persist across pages.
  ES.saveUser = function () {
    try { localStorage.setItem(USER_KEY, JSON.stringify(ES.currentUser)); } catch (e) {}
    ES.refreshUserUI();
  };
  ES.loadUser = function () {
    try {
      const saved = JSON.parse(localStorage.getItem(USER_KEY) || "null");
      if (saved && ES.currentUser) Object.assign(ES.currentUser, saved);
    } catch (e) {}
  };
  ES.refreshUserUI = function () {
    const av = document.getElementById("esTopAvatar");
    if (av && ES.currentUser) av.textContent = ES.initialsOf(ES.currentUser);
    ES.renderUserMenu();
  };

  function notifState() {
    const base = { read: [], dismissed: [], enabled: true };
    try { return Object.assign(base, JSON.parse(localStorage.getItem(NOTIF_KEY) || "{}")); }
    catch (e) { return base; }
  }
  function saveNotifState(st) {
    try { localStorage.setItem(NOTIF_KEY, JSON.stringify(st)); } catch (e) {}
  }
  ES.notificationsEnabled = function () { return notifState().enabled !== false; };
  ES.setNotificationsEnabled = function (on) {
    const st = notifState();
    st.enabled = !!on;
    saveNotifState(st);
    ES.renderNotifMenu();
  };

  const esc = (v) => String(v == null ? "" : v).replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));

  // Same smart notifications the dashboard shows, built from the shared data.
  ES.buildNotifications = function () {
    const list = [];

    // JITHMI — high / critical assignment risk
    if (ES.assignments && ES.calcRisk) {
      ES.assignments
        .map((a) => ({ a, r: ES.calcRisk(a) }))
        .filter((x) => x.r.level === "Critical" || x.r.level === "High")
        .sort((x, y) => y.r.score - x.r.score)
        .forEach((x) => list.push({
          id: "risk-" + x.a.id + "-" + x.r.level, i: "exclamation-triangle-fill", c: "jithmi",
          t: `${x.a.title} is ${x.r.level} risk (${x.r.score}%)`, s: x.r.reasons[0],
          href: "assignments.html", mod: "Assignment Risk",
        }));
    }
    // KAVISHKA — academic dates in the next 14 days
    (ES.academicDates || []).forEach((d) => {
      const days = ES.daysUntil(d.date);
      if (days < 0 || days > 14) return;
      list.push({
        id: "date-" + d.id + "-" + d.date, i: "calendar-event-fill", c: "kavishka",
        t: `${d.title} ${days === 0 ? "is today" : "in " + days + " day" + (days === 1 ? "" : "s")}`,
        s: `${d.type} · ${d.date}`, href: "calendar.html", mod: "Academic Assistant",
      });
    });
    // PASINDU — today's study target
    if (ES.studyToday) {
      const left = ES.studyToday.targetMinutes - ES.studyToday.studiedMinutes;
      if (left > 0) list.push({
        id: "study-" + ES.isoDate(0), i: "stopwatch-fill", c: "pasindu",
        t: `Keep going — ${ES.fmtDuration(left)} to hit today's target`, s: "Start a focus session to stay on track",
        href: "study.html", mod: "Study & Engagement",
      });
    }
    // BETHMI — newest summary
    if (ES.summaries && ES.summaries[0]) {
      const sm = ES.summaries[0];
      list.push({
        id: "summary-" + sm.id, i: "journal-bookmark-fill", c: "bethmi",
        t: "New summary ready: " + sm.title, s: (sm.keywords || []).slice(0, 3).join(" · "),
        href: "learning.html", mod: "Learning Materials",
      });
    }
    return list;
  };

  function closeMenus(except) {
    document.querySelectorAll(".es-dd.open").forEach((dd) => {
      if (dd === except) return;
      dd.classList.remove("open");
      const b = dd.querySelector("[aria-expanded]");
      if (b) b.setAttribute("aria-expanded", "false");
    });
  }
  function toggleMenu(dd) {
    const willOpen = !dd.classList.contains("open");
    closeMenus(dd);
    dd.classList.toggle("open", willOpen);
    const b = dd.querySelector("[aria-expanded]");
    if (b) b.setAttribute("aria-expanded", String(willOpen));
    return willOpen;
  }
  const currentPage = () => location.pathname.split("/").pop() || "index.html";

  ES.renderNotifMenu = function () {
    const menu = document.getElementById("esNotifMenu");
    if (!menu) return;
    const badge = document.getElementById("esNotifBadge");
    const st = notifState();
    const enabled = st.enabled !== false;
    const items = ES.buildNotifications().filter((n) => st.dismissed.indexOf(n.id) === -1);
    const unread = enabled ? items.filter((n) => st.read.indexOf(n.id) === -1).length : 0;

    if (badge) {
      badge.hidden = unread === 0;
      badge.textContent = unread > 9 ? "9+" : String(unread);
    }
    const btn = document.getElementById("esNotifBtn");
    if (btn) btn.setAttribute("aria-label", unread ? `Notifications (${unread} unread)` : "Notifications");

    let body;
    if (!enabled) {
      body = `<div class="es-dd-empty"><i class="bi bi-bell-slash"></i><div>Notifications are turned off</div><a href="profile.html#settings" class="es-dd-link">Turn on in settings</a></div>`;
    } else if (!items.length) {
      body = `<div class="es-dd-empty"><i class="bi bi-check2-circle"></i><div>You're all caught up</div></div>`;
    } else {
      body = items.map((n) => `
        <div class="es-notif-item ${st.read.indexOf(n.id) === -1 ? "unread" : ""}" role="menuitem" tabindex="0" data-id="${esc(n.id)}" data-href="${esc(n.href)}">
          <span class="es-li-icon bg-soft-${n.c}"><i class="bi bi-${n.i}"></i></span>
          <div class="es-notif-text">
            <div class="es-notif-title">${esc(n.t)}</div>
            <div class="es-notif-sub">${esc(n.s)}</div>
            <div class="es-notif-mod" style="color:var(--es-${n.c})">${esc(n.mod)}</div>
          </div>
          <button class="es-notif-x" data-dismiss="${esc(n.id)}" title="Dismiss" aria-label="Dismiss notification"><i class="bi bi-x"></i></button>
        </div>`).join("");
    }

    menu.innerHTML = `
      <div class="es-dd-head">
        <div><strong>Notifications</strong>${unread ? ` <span class="es-dd-count">${unread} new</span>` : ""}</div>
        ${enabled && items.length ? `<button class="es-dd-link" id="esNotifReadAll" ${unread ? "" : "disabled"}>Mark all read</button>` : ""}
      </div>
      <div class="es-dd-body">${body}</div>
      <div class="es-dd-foot">
        ${st.dismissed.length ? `<button class="es-dd-link" id="esNotifRestore">Restore dismissed (${st.dismissed.length})</button>` : "<span></span>"}
        <a href="profile.html#settings" class="es-dd-link">Notification settings</a>
      </div>`;

    const readAll = document.getElementById("esNotifReadAll");
    if (readAll) readAll.addEventListener("click", () => {
      const s2 = notifState();
      items.forEach((n) => { if (s2.read.indexOf(n.id) === -1) s2.read.push(n.id); });
      saveNotifState(s2);
      ES.renderNotifMenu();
      ES.toast("All notifications marked as read", "info");
    });
    const restore = document.getElementById("esNotifRestore");
    if (restore) restore.addEventListener("click", () => {
      const s2 = notifState();
      s2.dismissed = [];
      saveNotifState(s2);
      ES.renderNotifMenu();
    });
    menu.querySelectorAll("[data-dismiss]").forEach((x) => x.addEventListener("click", (e) => {
      e.stopPropagation();
      const s2 = notifState();
      if (s2.dismissed.indexOf(x.dataset.dismiss) === -1) s2.dismissed.push(x.dataset.dismiss);
      saveNotifState(s2);
      ES.renderNotifMenu();
    }));
    menu.querySelectorAll(".es-notif-item").forEach((it) => {
      const open = () => {
        const s2 = notifState();
        if (s2.read.indexOf(it.dataset.id) === -1) s2.read.push(it.dataset.id);
        saveNotifState(s2);
        if (it.dataset.href && it.dataset.href !== currentPage()) { location.href = it.dataset.href; return; }
        closeMenus();
        ES.renderNotifMenu();
      };
      it.addEventListener("click", open);
      it.addEventListener("keydown", (e) => { if (e.key === "Enter" || e.key === " ") { e.preventDefault(); open(); } });
    });
  };

  ES.renderUserMenu = function () {
    const menu = document.getElementById("esUserMenu");
    const u = ES.currentUser;
    if (!menu || !u) return;
    const dark = document.documentElement.getAttribute("data-theme") === "dark";
    menu.innerHTML = `
      <div class="es-user-head">
        <span class="es-avatar">${esc(ES.initialsOf(u))}</span>
        <div style="min-width:0">
          <div class="es-notif-title text-truncate">${esc(u.name)}</div>
          <div class="es-notif-sub text-truncate">${esc(u.email)}</div>
          <div class="es-notif-sub text-truncate">${esc(u.program || "")}${u.year ? " · " + esc(u.year) : ""}</div>
        </div>
      </div>
      <div class="es-dd-sep"></div>
      <a class="es-dd-item" role="menuitem" href="profile.html"><i class="bi bi-person-circle"></i>My profile</a>
      <a class="es-dd-item" role="menuitem" href="profile.html#settings"><i class="bi bi-gear"></i>Settings</a>
      <a class="es-dd-item" role="menuitem" href="calendar.html"><i class="bi bi-calendar3"></i>Calendar</a>
      <a class="es-dd-item" role="menuitem" href="profile.html#export"><i class="bi bi-download"></i>Export my data</a>
      <button class="es-dd-item" role="menuitem" id="esUserTheme"><i class="bi bi-${dark ? "sun" : "moon-stars"}"></i>${dark ? "Light" : "Dark"} mode</button>
      <div class="es-dd-sep"></div>
      <button class="es-dd-item es-dd-danger" role="menuitem" id="esUserLogout"><i class="bi bi-box-arrow-right"></i>Sign out</button>`;
    document.getElementById("esUserTheme").addEventListener("click", () => {
      ES.toggleTheme();
      ES.renderUserMenu();
      const dk = document.getElementById("setDark");
      if (dk) dk.checked = document.documentElement.getAttribute("data-theme") === "dark";
    });
    document.getElementById("esUserLogout").addEventListener("click", () => ES.logout());
  };

  let globalMenuHandlers = false;
  ES.initTopbarMenus = function () {
    const nDd = document.getElementById("esNotifDd");
    const uDd = document.getElementById("esUserDd");
    if (nDd) {
      ES.renderNotifMenu();
      document.getElementById("esNotifBtn").addEventListener("click", (e) => {
        e.stopPropagation();
        if (toggleMenu(nDd)) ES.renderNotifMenu();
      });
      nDd.querySelector(".es-dd-menu").addEventListener("click", (e) => e.stopPropagation());
    }
    if (uDd) {
      ES.renderUserMenu();
      document.getElementById("esTopAvatar").addEventListener("click", (e) => {
        e.stopPropagation();
        if (toggleMenu(uDd)) ES.renderUserMenu();
      });
      uDd.querySelector(".es-dd-menu").addEventListener("click", (e) => e.stopPropagation());
    }
    if (globalMenuHandlers) return;
    globalMenuHandlers = true;
    document.addEventListener("click", () => closeMenus());
    document.addEventListener("keydown", (e) => {
      if (e.key !== "Escape") return;
      const open = document.querySelector(".es-dd.open");
      if (!open) return;
      closeMenus();
      const b = open.querySelector("[aria-expanded]");
      if (b) b.focus();
    });
    // Keep every open tab in sync (badge, avatar).
    window.addEventListener("storage", (e) => {
      if (e.key === NOTIF_KEY) ES.renderNotifMenu();
      if (e.key === USER_KEY) { ES.loadUser(); ES.refreshUserUI(); }
    });
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
    ES.loadUser();
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
      host.style.cssText = "position:fixed;top:80px;right:18px;z-index:2000;display:flex;flex-direction:column;gap:10px";
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
