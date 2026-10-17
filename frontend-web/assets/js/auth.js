/* ===========================================================================
   AcadeAlert · Common Platform Layer — accounts for the static demo
   ---------------------------------------------------------------------------
   This folder is the no-backend demo, so there is no Laravel API and no mail
   server here. The flows below follow exactly the same rules as the real API
   (apps/api) so the demo behaves the same way:

     - passwords are never stored in readable form (SHA-256 of the password,
       never the password itself);
     - "forgot password" issues a 6-digit code, valid 15 minutes, single use,
       destroyed after 5 wrong guesses — your existing password is never shown,
       because only a hash of it was ever kept;
     - registration issues the same kind of code to confirm the address.

   The real app emails that code. With no server to send mail, the demo shows
   it in a box marked "demo mailbox" — that box is the only difference between
   this and the live flow in apps/web.

   Everything lives in localStorage, so accounts survive a page reload.
   =========================================================================== */
(function () {
  const ES = window.ES = window.ES || {};
  const ACCOUNTS_KEY = "es-accounts";
  const CODES_KEY = "es-auth-codes";
  const CODE_MINUTES = 15;
  const MAX_ATTEMPTS = 5;

  ES.auth = {};

  /* ---------- storage ---------- */

  function read(key, fallback) {
    try { return JSON.parse(localStorage.getItem(key) || "null") || fallback; }
    catch (e) { return fallback; }
  }
  function write(key, value) {
    try { localStorage.setItem(key, JSON.stringify(value)); } catch (e) {}
  }

  function accounts() { return read(ACCOUNTS_KEY, {}); }
  function saveAccounts(all) { write(ACCOUNTS_KEY, all); }
  const normalise = (email) => String(email || "").trim().toLowerCase();

  /* ---------- password hashing ---------- */

  /** SHA-256 where the browser allows it; a readable password is never stored. */
  ES.auth.hash = async function (password) {
    const text = "acadealert:" + password;
    if (window.crypto && window.crypto.subtle && window.isSecureContext) {
      const digest = await crypto.subtle.digest("SHA-256", new TextEncoder().encode(text));
      return Array.from(new Uint8Array(digest)).map((b) => b.toString(16).padStart(2, "0")).join("");
    }
    // file:// has no crypto.subtle: fall back to a non-reversible fold so the
    // demo still never keeps the typed password.
    let h1 = 0x811c9dc5;
    let h2 = 0x01000193;
    for (let i = 0; i < text.length; i++) {
      h1 = (h1 ^ text.charCodeAt(i)) * 16777619 >>> 0;
      h2 = (h2 + text.charCodeAt(i) * (i + 7)) >>> 0;
    }
    return "fallback$" + h1.toString(16) + h2.toString(16);
  };

  /* ---------- the seeded demo account ---------- */

  ES.auth.seed = async function () {
    const all = accounts();
    const email = "student@acadealert.lk";
    if (!all[email]) {
      all[email] = {
        name: "Ayesha Perera",
        email: email,
        program: "BSc (Hons) Software Engineering",
        password: await ES.auth.hash("password"),
        verified: true,
        created_at: new Date().toISOString(),
      };
      saveAccounts(all);
    }
    return all[email];
  };

  ES.auth.find = function (email) { return accounts()[normalise(email)] || null; };
  ES.auth.exists = function (email) { return Boolean(ES.auth.find(email)); };

  /* ---------- rules shared with the real API ---------- */

  /** Same rule as the Laravel validator: 8+ characters, letters and numbers. */
  ES.auth.passwordProblem = function (password) {
    const value = String(password || "");
    if (value.length < 8) return "Use at least 8 characters.";
    if (!/[A-Za-z]/.test(value)) return "Include at least one letter.";
    if (!/\d/.test(value)) return "Include at least one number.";
    return null;
  };

  ES.auth.strength = function (password) {
    const value = String(password || "");
    let score = 0;
    if (value.length >= 8) score++;
    if (value.length >= 12) score++;
    if (/[a-z]/.test(value) && /[A-Z]/.test(value)) score++;
    if (/\d/.test(value)) score++;
    if (/[^A-Za-z0-9]/.test(value)) score++;
    const labels = ["Too weak", "Weak", "Fair", "Good", "Strong", "Excellent"];
    const colors = ["var(--es-danger)", "var(--es-danger)", "var(--es-warning)", "var(--es-warning)", "var(--es-success)", "var(--es-success)"];
    return { score: score, label: labels[score], color: colors[score] };
  };

  ES.auth.emailValid = function (email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(email || "").trim());
  };

  /* ---------- one-time codes ---------- */

  function codes() { return read(CODES_KEY, {}); }
  function saveCodes(all) { write(CODES_KEY, all); }

  /**
   * Issue a 6-digit code for "reset" or "verify". The real API emails it and
   * stores only a hash; here it is kept hashed too and returned once, for the
   * demo mailbox box to display.
   */
  ES.auth.issueCode = async function (purpose, email) {
    const code = String(Math.floor(Math.random() * 1000000)).padStart(6, "0");
    const all = codes();
    all[purpose + ":" + normalise(email)] = {
      hash: await ES.auth.hash(code),
      attempts: 0,
      issued_at: Date.now(),
    };
    saveCodes(all);
    return code;
  };

  /** Check a code and consume it. → "ok" | "invalid" | "expired" | "locked" | "missing" */
  ES.auth.consumeCode = async function (purpose, email, code) {
    const key = purpose + ":" + normalise(email);
    const all = codes();
    const row = all[key];
    if (!row) return "missing";

    if (Date.now() - row.issued_at > CODE_MINUTES * 60000) {
      delete all[key];
      saveCodes(all);
      return "expired";
    }

    const matches = row.hash === await ES.auth.hash(String(code || "").replace(/[\s-]/g, ""));
    if (!matches) {
      row.attempts++;
      if (row.attempts >= MAX_ATTEMPTS) {
        delete all[key];
        saveCodes(all);
        return "locked";
      }
      saveCodes(all);
      return "invalid";
    }

    delete all[key];
    saveCodes(all);
    return "ok";
  };

  ES.auth.codeMessage = function (status) {
    switch (status) {
      case "expired": return "That code has expired. Request a new one.";
      case "locked": return "Too many incorrect codes. Request a new one.";
      case "missing": return "No code is pending for that email. Request a new one.";
      default: return "That code is not correct. Check the demo mailbox and try again.";
    }
  };

  ES.auth.codeMinutes = CODE_MINUTES;

  /* ---------- the three flows ---------- */

  /** Create an account. → { ok } or { error, field } */
  ES.auth.register = async function (details) {
    const email = normalise(details.email);
    if (!String(details.name || "").trim()) return { error: "Enter your full name.", field: "name" };
    if (!ES.auth.emailValid(email)) return { error: "Enter a valid email address.", field: "email" };
    if (ES.auth.exists(email)) return { error: "That email already has an AcadeAlert account.", field: "email" };

    const problem = ES.auth.passwordProblem(details.password);
    if (problem) return { error: problem, field: "password" };
    if (details.password !== details.password_confirmation) return { error: "The two passwords do not match.", field: "confirm" };

    const all = accounts();
    all[email] = {
      name: String(details.name).trim(),
      email: email,
      program: String(details.program || "").trim(),
      password: await ES.auth.hash(details.password),
      verified: false,
      created_at: new Date().toISOString(),
    };
    saveAccounts(all);

    return { ok: true, code: await ES.auth.issueCode("verify", email) };
  };

  /** Confirm the address with the emailed code. */
  ES.auth.verify = async function (email, code) {
    const status = await ES.auth.consumeCode("verify", email, code);
    if (status !== "ok") return { error: ES.auth.codeMessage(status) };

    const all = accounts();
    const account = all[normalise(email)];
    if (!account) return { error: "That account no longer exists." };
    account.verified = true;
    account.verified_at = new Date().toISOString();
    saveAccounts(all);

    return { ok: true, account: account };
  };

  /**
   * Step 1 of "forgot password". Returns a code only when the account exists,
   * but the caller shows the same message either way — the real API never
   * reveals whether an address is registered.
   */
  ES.auth.requestReset = async function (email) {
    if (!ES.auth.exists(email)) return { ok: true, code: null };
    return { ok: true, code: await ES.auth.issueCode("reset", email) };
  };

  /** Step 2 of "forgot password": the code plus a new password. */
  ES.auth.resetPassword = async function (email, code, password, confirmation) {
    const problem = ES.auth.passwordProblem(password);
    if (problem) return { error: problem, field: "password" };
    if (password !== confirmation) return { error: "The two passwords do not match.", field: "confirm" };

    const status = await ES.auth.consumeCode("reset", email, code);
    if (status !== "ok") return { error: ES.auth.codeMessage(status), field: "code" };

    const all = accounts();
    const account = all[normalise(email)];
    if (!account) return { error: "That account no longer exists.", field: "code" };
    account.password = await ES.auth.hash(password);
    account.password_changed_at = new Date().toISOString();
    saveAccounts(all);

    return { ok: true, account: account };
  };

  /** Sign in. The stored hash is compared — the password itself is not kept. */
  ES.auth.signIn = async function (email, password) {
    const account = ES.auth.find(email);
    const hash = await ES.auth.hash(password);
    if (!account || account.password !== hash) {
      return { error: "These credentials do not match our records." };
    }
    return { ok: true, account: account };
  };

  /** Put the signed-in student into the shared ES.currentUser used by every page. */
  ES.auth.startSession = function (account) {
    ES.login(account.email);
    try {
      const user = {
        id: 1,
        name: account.name,
        email: account.email,
        initials: ES.initialsOf(account),
        program: account.program || "BSc (Hons) Software Engineering",
        year: "Year 2 · Semester 1",
      };
      localStorage.setItem("es-user", JSON.stringify(user));
    } catch (e) {}
  };
})();
