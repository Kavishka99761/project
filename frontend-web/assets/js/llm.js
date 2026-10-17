/* ===========================================================================
   AcadeAlert · Academic Assistant — local LLM client (Owner: Kavishka)
   ---------------------------------------------------------------------------
   Talks to an OpenAI-compatible chat server straight from the browser. The
   default is Qwen3 8B served by Ollama on this machine:

       winget install Ollama.Ollama        (or https://ollama.com/download)
       ollama pull qwen3:8b
       ollama serve                        (usually already running)

   Ollama accepts browser requests from localhost origins, so this page can
   call it with no backend of its own. The Laravel API (apps/api) uses the same
   model through the same endpoint — this file is the static demo's equivalent.

   Qwen3 is a hybrid reasoning model and may emit a <think>…</think> block
   before the answer; thinking is switched off in the request and any block
   that still arrives is stripped as the answer streams in.
   =========================================================================== */
(function () {
  const ES = window.ES = window.ES || {};

  var SETTINGS_KEY = "es-llm";
  var DEFAULTS = {
    baseUrl: "http://127.0.0.1:11434/v1",
    model: "qwen3:8b",
    apiKey: "",
    temperature: 0.4,
    maxTokens: 700
  };

  var llm = ES.llm = {};
  var available = null;    // null = not checked yet, true/false afterwards
  var lastError = "";

  /** Settings, overridable from the console: ES.llm.save({ model: "qwen3:4b" }). */
  llm.settings = function () {
    var saved = {};
    try { saved = JSON.parse(localStorage.getItem(SETTINGS_KEY) || "{}") || {}; } catch (e) {}
    return Object.assign({}, DEFAULTS, saved);
  };

  llm.save = function (patch) {
    var next = Object.assign(llm.settings(), patch || {});
    try { localStorage.setItem(SETTINGS_KEY, JSON.stringify(next)); } catch (e) {}
    available = null;
    return next;
  };

  llm.lastError = function () { return lastError; };

  function headers() {
    var h = { "Content-Type": "application/json" };
    var key = llm.settings().apiKey;
    if (key) h.Authorization = "Bearer " + key;   // local Ollama needs none
    return h;
  }

  /** Is the server up and does it have the model? Cached after the first call. */
  llm.check = function (force) {
    if (available !== null && !force) return Promise.resolve(available);
    var cfg = llm.settings();

    return fetch(cfg.baseUrl + "/models", { headers: headers() })
      .then(function (response) {
        if (!response.ok) throw new Error("HTTP " + response.status);
        return response.json();
      })
      .then(function (body) {
        var ids = (body.data || []).map(function (m) { return m.id; });
        var has = ids.some(function (id) { return id === cfg.model || id.indexOf(cfg.model) === 0 || cfg.model.indexOf(id) === 0; });
        if (!has && ids.length) {
          lastError = 'The server is running but has no "' + cfg.model + '" model. Pull it with: ollama pull ' + cfg.model +
            (ids.length ? " (available: " + ids.join(", ") + ")" : "");
          available = false;
        } else {
          lastError = "";
          available = true;
        }
        return available;
      })
      .catch(function (error) {
        lastError = "Cannot reach " + cfg.baseUrl + " (" + error.message + "). Start it with: ollama serve";
        available = false;
        return false;
      });
  };

  /** Drop a Qwen3 reasoning block, including one that is still open. */
  llm.stripThinking = function (text) {
    return String(text || "")
      .replace(/<think>[\s\S]*?<\/think>/gi, "")
      .replace(/<think>[\s\S]*$/i, "")
      .replace(/^\s+/, "");
  };

  /**
   * Stream a reply.
   *
   * @param {Array}    messages  [{ role, content }, …]
   * @param {Function} onDelta   called with the answer so far, as it arrives
   * @returns {Promise<string>}  the finished answer
   */
  llm.chat = function (messages, onDelta) {
    var cfg = llm.settings();
    var body = {
      model: cfg.model,
      messages: messages,
      temperature: cfg.temperature,
      max_tokens: cfg.maxTokens,
      stream: true,
      // Answer directly rather than reasoning out loud. Ollama reads "think";
      // vLLM and DashScope read chat_template_kwargs. Other servers ignore both.
      think: false,
      chat_template_kwargs: { enable_thinking: false }
    };

    return fetch(cfg.baseUrl + "/chat/completions", { method: "POST", headers: headers(), body: JSON.stringify(body) })
      .then(function (response) {
        if (!response.ok) {
          return response.text().then(function (text) {
            throw new Error("HTTP " + response.status + (text ? " — " + text.slice(0, 200) : ""));
          });
        }
        if (!response.body || !response.body.getReader) {
          // No streaming support in this browser: read the whole response.
          return response.json().then(function (data) {
            var once = llm.stripThinking((((data.choices || [])[0] || {}).message || {}).content || "");
            if (onDelta) onDelta(once);
            return once;
          });
        }

        var reader = response.body.getReader();
        var decoder = new TextDecoder();
        var buffer = "";
        var raw = "";

        function pump() {
          return reader.read().then(function (chunk) {
            if (chunk.done) return llm.stripThinking(raw);

            buffer += decoder.decode(chunk.value, { stream: true });
            var lines = buffer.split("\n");
            buffer = lines.pop() || "";

            lines.forEach(function (line) {
              line = line.trim();
              if (line.indexOf("data:") !== 0) return;
              var payload = line.slice(5).trim();
              if (!payload || payload === "[DONE]") return;
              try {
                var json = JSON.parse(payload);
                var delta = (((json.choices || [])[0] || {}).delta || {});
                // Some servers stream reasoning in its own field: ignore it.
                if (delta.content) {
                  raw += delta.content;
                  if (onDelta) onDelta(llm.stripThinking(raw));
                }
              } catch (e) { /* partial frame: wait for the rest */ }
            });

            return pump();
          });
        }

        return pump();
      });
  };

  /**
   * The Academic Assistant's instructions. Mirrors apps/api's prompts: cite the
   * extracts when they answer the question, otherwise answer from general
   * knowledge and never invent a source.
   */
  llm.systemPrompt = function (context) {
    var base = "You are AcadeAlert's Academic Assistant, helping a university student. " +
      "Answer in clear British English. You may answer anything: study topics, programming, maths, " +
      "academic writing, exam technique or everyday questions. Explain step by step when it helps, " +
      "and give short worked examples or code where useful. Keep answers under 200 words unless more detail is needed.";

    if (context) {
      return base + " Extracts from the student's own academic documents are given below. If an extract answers " +
        "the question, use it and cite it as [1]. If the extracts are not relevant, ignore them and answer from " +
        "your own knowledge with no citation. Never invent a citation, page number, deadline, mark or regulation.\n\n" +
        "Extracts:\n" + context;
    }

    return base + " Never invent citations, page numbers, deadlines, marks or university regulations. " +
      "If asked about the student's own course rules or dates, answer generally and say to check the handbook " +
      "or add it to the knowledge base.";
  };
})();
