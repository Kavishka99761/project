/* ==========================================================================
  AcadeAlert — Learning Materials Module
   Document management: upload, view, search, filter, rename, delete, details.
   Summary management: generate (short/medium/detailed), keywords, save, download.
   ========================================================================== */

(function () {
  "use strict";

  ES.initPage({ active: "learning.html", title: "Learning Materials", subtitle: "Documents & summaries", accent: "var(--es-learning)" });

  let docs = ES.documents.slice();
  let summaries = ES.summaries.slice();

  /* ---------- Stats ---------- */
  function renderStats() {
    const totalSize = docs.reduce((s, d) => s + parseFloat(d.size) * (d.size.includes("MB") ? 1 : 0.001), 0);
    const stats = [
      { icon: "file-earmark-text-fill", color: "learning", value: docs.length, label: "Documents" },
      { icon: "collection-fill", color: "assistant", value: new Set(docs.map((d) => d.module)).size, label: "Modules used" },
      { icon: "journal-check", color: "study", value: summaries.length, label: "Saved summaries" },
      { icon: "hdd-fill", color: "risk", value: totalSize.toFixed(1) + " MB", label: "Storage" },
    ];
    document.getElementById("statRow").innerHTML = stats.map((s) => `
      <div class="col-6 col-xl-3">
        <div class="es-card es-stat">
          <span class="es-stat-icon bg-soft-${s.color}"><i class="bi bi-${s.icon}"></i></span>
          <div>
            <div class="es-stat-value">${s.value}</div>
            <div class="es-stat-label">${s.label}</div>
          </div>
        </div>
      </div>`).join("");
  }

  /* ---------- Module list (group documents by module) ---------- */
  function renderModules() {
    const groups = {};
    docs.forEach((d) => { groups[d.module] = (groups[d.module] || 0) + 1; });
    const rows = Object.keys(groups).map((m) => {
      const mod = ES.modules.find((x) => x.name === m);
      const color = mod ? mod.color : "learning";
      const icon = mod ? mod.icon : "journal";
      return `<div class="es-list-item">
        <span class="es-li-icon bg-soft-${color}"><i class="bi bi-${icon}"></i></span>
        <div style="flex:1;min-width:0">
          <div class="es-li-title text-truncate">${m}</div>
          <div class="es-li-sub">${groups[m]} document${groups[m] > 1 ? "s" : ""}</div>
        </div>
      </div>`;
    }).join("");
    document.getElementById("moduleList").innerHTML = rows || `<p class="text-muted-es small mb-0">No documents yet.</p>`;
  }

  /* ---------- Documents ---------- */
  function renderDocs() {
    const q = (document.getElementById("docSearch").value || "").toLowerCase();
    const mod = document.getElementById("docFilter").value;
    const filtered = docs.filter((d) =>
      (!mod || d.module === mod) &&
      (!q || d.title.toLowerCase().includes(q) || d.topic.toLowerCase().includes(q) || d.module.toLowerCase().includes(q))
    );
    const typeIcon = (t) => t === "PDF" ? "file-earmark-pdf-fill" : t === "Word" ? "file-earmark-word-fill" : "file-earmark-text-fill";
    const typeColor = (t) => t === "PDF" ? "danger" : t === "Word" ? "learning" : "study";

    document.getElementById("docList").innerHTML = filtered.length ? filtered.map((d) => `
      <div class="es-list-item es-fade-in">
        <span class="es-li-icon bg-soft-${typeColor(d.type)}"><i class="bi bi-${typeIcon(d.type)}"></i></span>
        <div style="flex:1;min-width:0">
          <div class="es-li-title text-truncate">${d.title}</div>
          <div class="es-li-sub text-truncate">${d.module} · ${d.topic} · ${d.pages} pages · ${d.size}</div>
        </div>
        <div class="d-flex gap-1">
          <button class="es-icon-btn" style="width:34px;height:34px" data-act="view" data-id="${d.id}" title="View details"><i class="bi bi-eye"></i></button>
          <button class="es-icon-btn" style="width:34px;height:34px" data-act="rename" data-id="${d.id}" title="Rename"><i class="bi bi-pencil"></i></button>
          <button class="es-icon-btn" style="width:34px;height:34px" data-act="delete" data-id="${d.id}" title="Delete"><i class="bi bi-trash"></i></button>
        </div>
      </div>`).join("") : `<p class="text-muted-es small text-center py-4 mb-0">No documents match your search.</p>`;
  }

  /* ---------- Summaries ---------- */
  function renderSummaries() {
    document.getElementById("summaryList").innerHTML = summaries.length ? summaries.map((s) => `
      <div class="es-list-item es-fade-in">
        <span class="es-li-icon bg-soft-study"><i class="bi bi-check2-circle"></i></span>
        <div style="flex:1;min-width:0">
          <div class="es-li-title text-truncate">${s.title}</div>
          <div class="es-li-sub text-truncate">${s.length} · ${s.keywords.slice(0, 3).join(", ")}</div>
        </div>
        <div class="d-flex gap-1">
          <button class="es-icon-btn" style="width:34px;height:34px" data-sact="view" data-id="${s.id}" title="View"><i class="bi bi-eye"></i></button>
          <button class="es-icon-btn" style="width:34px;height:34px" data-sact="download" data-id="${s.id}" title="Download"><i class="bi bi-download"></i></button>
          <button class="es-icon-btn" style="width:34px;height:34px" data-sact="delete" data-id="${s.id}" title="Delete"><i class="bi bi-trash"></i></button>
        </div>
      </div>`).join("") : `<p class="text-muted-es small text-center py-3 mb-0">No summaries yet.</p>`;
  }

  function populateSelects() {
    const mods = [...new Set(docs.map((d) => d.module))];
    const filter = document.getElementById("docFilter");
    filter.innerHTML = `<option value="">All modules</option>` + mods.map((m) => `<option>${m}</option>`).join("");
    const upModule = document.getElementById("upModule");
    upModule.innerHTML = ES.modules.map((m) => `<option>${m.name}</option>`).join("");
    const sumDoc = document.getElementById("sumDoc");
    sumDoc.innerHTML = docs.map((d) => `<option value="${d.id}">${d.title}</option>`).join("");
  }

  function renderAll() { renderStats(); renderModules(); renderDocs(); renderSummaries(); populateSelects(); }

  /* ---------- Keyword extraction (simple TF simulation) ---------- */
  function extractKeywords(title) {
    const stop = new Set(["the", "and", "for", "with", "lecture", "notes", "chapter", "of", "a", "to", "in"]);
    const words = title.toLowerCase().replace(/[^a-z0-9\s]/g, "").split(/\s+/).filter((w) => w.length > 3 && !stop.has(w));
    const base = words.length ? words : ["concept", "theory", "revision"];
    return [...new Set(base)].slice(0, 4);
  }

  function generateSummaryText(doc, length) {
    const kw = extractKeywords(doc.title).join(", ");
    const core = `This ${doc.type.toLowerCase()} covers ${doc.topic} within ${doc.module}. Key concepts: ${kw}.`;
    if (length === "Short") return core + " A concise revision of the main definitions.";
    if (length === "Detailed") return core + " It explains the theory step-by-step, provides worked examples, highlights common exam pitfalls, and links the topic to related modules. Recommended revision: re-read highlighted sentences, then attempt past-paper questions on " + doc.topic + ".";
    return core + " It outlines the main ideas, important definitions and typical applications, with highlighted sentences for revision.";
  }

  /* ---------- Events: document actions ---------- */
  document.getElementById("docList").addEventListener("click", (e) => {
    const btn = e.target.closest("[data-act]");
    if (!btn) return;
    const id = +btn.dataset.id;
    const doc = docs.find((d) => d.id === id);
    if (!doc) return;
    if (btn.dataset.act === "view") {
      document.getElementById("detTitle").textContent = doc.title;
      document.getElementById("detBody").innerHTML = `
        <div class="row g-2 small">
          <div class="col-6"><span class="text-muted-es">Module:</span><br><strong>${doc.module}</strong></div>
          <div class="col-6"><span class="text-muted-es">Topic:</span><br><strong>${doc.topic}</strong></div>
          <div class="col-6"><span class="text-muted-es">Type:</span><br><strong>${doc.type}</strong></div>
          <div class="col-6"><span class="text-muted-es">Pages:</span><br><strong>${doc.pages}</strong></div>
          <div class="col-6"><span class="text-muted-es">Size:</span><br><strong>${doc.size}</strong></div>
          <div class="col-6"><span class="text-muted-es">Uploaded:</span><br><strong>${doc.uploaded}</strong></div>
        </div>
        <div class="es-divider"></div>
        <div class="small text-muted-es">Extracted text preview:</div>
        <p class="small mt-1">${generateSummaryText(doc, "Medium")}</p>`;
      new bootstrap.Modal(document.getElementById("detailsModal")).show();
    } else if (btn.dataset.act === "rename") {
      const name = prompt("Rename document:", doc.title);
      if (name && name.trim()) { doc.title = name.trim(); renderAll(); ES.toast("Document renamed"); }
    } else if (btn.dataset.act === "delete") {
      if (confirm(`Delete "${doc.title}"?`)) { docs = docs.filter((d) => d.id !== id); renderAll(); ES.toast("Document deleted", "danger"); }
    }
  });

  /* ---------- Events: summary actions ---------- */
  document.getElementById("summaryList").addEventListener("click", (e) => {
    const btn = e.target.closest("[data-sact]");
    if (!btn) return;
    const id = +btn.dataset.id;
    const s = summaries.find((x) => x.id === id);
    if (!s) return;
    if (btn.dataset.sact === "view") {
      document.getElementById("detTitle").textContent = s.title;
      document.getElementById("detBody").innerHTML = `
        <div class="mb-2">${s.keywords.map((k) => `<span class="es-pill bg-soft-learning me-1 mb-1">#${k}</span>`).join("")}</div>
        <p class="small">${s.text}</p>
        <div class="text-muted-es small">Length: ${s.length} · Saved ${s.saved}</div>`;
      new bootstrap.Modal(document.getElementById("detailsModal")).show();
    } else if (btn.dataset.sact === "download") {
      const blob = new Blob([`${s.title}\n\nKeywords: ${s.keywords.join(", ")}\n\n${s.text}\n`], { type: "text/plain" });
      const a = document.createElement("a");
      a.href = URL.createObjectURL(blob);
      a.download = s.title.replace(/\s+/g, "_") + ".txt";
      a.click();
      URL.revokeObjectURL(a.href);
      ES.toast("Summary downloaded", "info");
    } else if (btn.dataset.sact === "delete") {
      if (confirm(`Delete "${s.title}"?`)) { summaries = summaries.filter((x) => x.id !== id); renderSummaries(); renderStats(); ES.toast("Summary deleted", "danger"); }
    }
  });

  /* ---------- Search & filter ---------- */
  document.getElementById("docSearch").addEventListener("input", renderDocs);
  document.getElementById("docFilter").addEventListener("change", renderDocs);

  /* ---------- Generate summary ---------- */
  document.getElementById("genSummary").addEventListener("click", () => {
    const docId = +document.getElementById("sumDoc").value;
    const doc = docs.find((d) => d.id === docId);
    if (!doc) return ES.toast("Select a document first", "warn");
    const length = document.querySelector('input[name="sumlen"]:checked').value;
    const btn = document.getElementById("genSummary");
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Generating...';
    btn.disabled = true;
    setTimeout(() => {
      const kw = extractKeywords(doc.title);
      const summary = { id: Date.now(), docId: doc.id, title: doc.title + " Summary", length, keywords: kw, saved: ES.isoDate(0), text: generateSummaryText(doc, length) };
      summaries.unshift(summary);
      renderSummaries(); renderStats();
      const box = document.getElementById("sumResult");
      box.classList.remove("d-none");
      box.innerHTML = `
        <div class="es-card p-3" style="box-shadow:none;background:var(--es-surface-2)">
          <div class="fw-bold small mb-1">${summary.title} <span class="es-pill bg-soft-learning ms-1">${length}</span></div>
          <div class="mb-2">${kw.map((k) => `<span class="es-pill bg-soft-study me-1 mb-1">#${k}</span>`).join("")}</div>
          <p class="small mb-0">${summary.text}</p>
        </div>`;
      btn.innerHTML = '<i class="bi bi-stars me-1"></i> Generate';
      btn.disabled = false;
      ES.toast("Summary generated & saved");
    }, 900);
  });

  /* ---------- Upload ---------- */
  document.getElementById("doUpload").addEventListener("click", () => {
    const title = document.getElementById("upTitle").value.trim();
    const fileInput = document.getElementById("upFile");
    if (!title) return ES.toast("Enter a document title", "warn");
    const file = fileInput.files[0];
    const type = file ? (/\.(pdf)$/i.test(file.name) ? "PDF" : /\.(docx?|txt)$/i.test(file.name) ? "Word" : "PDF") : "PDF";
    docs.unshift({
      id: Date.now(),
      title,
      module: document.getElementById("upModule").value,
      type,
      pages: Math.floor(Math.random() * 40) + 8,
      size: file ? (file.size / 1048576).toFixed(1) + " MB" : "1.0 MB",
      uploaded: ES.isoDate(0),
      topic: document.getElementById("upTopic").value.trim() || "General",
    });
    renderAll();
    bootstrap.Modal.getInstance(document.getElementById("uploadModal")).hide();
    document.getElementById("upTitle").value = "";
    document.getElementById("upTopic").value = "";
    fileInput.value = "";
    ES.toast(document.getElementById("upExtract").checked ? "Uploaded & text extracted" : "Document uploaded");
  });

  renderAll();
})();
