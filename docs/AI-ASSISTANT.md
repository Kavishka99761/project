# Academic Assistant — the language model

*Module owner: Kavishka. Covers `apps/api` (Laravel) and `frontend-web/assistant.html` (static demo).*

The assistant answers **any** question the student asks, and cites their own
documents whenever those documents actually contain the answer.

---

## The model

Qwen3 8B, served locally by [Ollama](https://ollama.com) on
`http://127.0.0.1:11434/v1` — an OpenAI-compatible endpoint that needs no API
key. One command sets it up:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\setup\install-qwen-ollama.ps1
```

That installs Ollama (winget), pulls `qwen3:8b` (~5 GB), starts the server and
asks it one question to prove it works. On a laptop without a GPU, `-Model
qwen3:4b` is roughly twice as fast.

Any other OpenAI-compatible server works instead — only `apps/api/.env`
changes:

| Provider | `LLM_BASE_URL` | `LLM_MODEL` | `LLM_API_KEY` |
| -------- | -------------- | ----------- | ------------- |
| Ollama (default) | `http://127.0.0.1:11434/v1` | `qwen3:8b` | *(empty)* |
| LM Studio | `http://127.0.0.1:1234/v1` | the loaded model id | *(empty)* |
| OpenRouter | `https://openrouter.ai/api/v1` | `qwen/qwen3-8b` | your key |
| Alibaba DashScope | `https://dashscope-intl.aliyuncs.com/compatible-mode/v1` | `qwen3-8b` | your key |

Qwen3 is a *hybrid reasoning* model: it can write a `<think>…</think>` block
before the answer. Thinking is switched off in every request (`think: false`
for Ollama, `chat_template_kwargs.enable_thinking: false` for vLLM/DashScope)
and any block that still arrives is stripped, in the API and in the browser
client, so a student never sees the model's scratch work.

---

## How a question is answered

```
question
   │
   ├─ BM25 retrieval over the student's indexed documents (+ semantic re-ranking)
   │
   ├─ top relevance ≥ 0.45  ──▶  GROUNDED: the model may only rephrase the cited
   │                             passages and must keep every [n] citation.
   │                             No citation in its reply → the extractive answer
   │                             is used instead, so a source is never invented.
   │
   ├─ 0.18 ≤ relevance < 0.45 ─▶ WEAK MATCH: the model gets the passages but is
   │                             told to ignore them if they are irrelevant. It
   │                             either cites them, or answers generally.
   │
   └─ below 0.18 / nothing  ──▶  GENERAL: the model answers from its own
                                 knowledge, like any AI assistant, and the reply
                                 is labelled "Answered from general knowledge —
                                 not from your uploaded documents".
```

The last six turns of the conversation are sent with each question, so
follow-ups such as *"explain that more simply"* work.

`answer.meta.method` in the API response says which path ran: `llm` (grounded),
`llm-general`, `extractive` (local engine, no model) or `none` (nothing found
and no model).

### Settings (`apps/api/.env`)

| Variable | Default | Meaning |
| -------- | ------- | ------- |
| `LLM_ENABLED` | `true` | Use a model at all. `false` → the local extractive engine only. |
| `LLM_BASE_URL` | `http://127.0.0.1:11434/v1` | OpenAI-compatible endpoint. |
| `LLM_MODEL` | `qwen3:8b` | Model id/tag. |
| `LLM_API_KEY` | *(empty)* | Sent as a bearer token when set; local servers need none. |
| `LLM_TIMEOUT` | `120` | Seconds. An 8B model on CPU can take 30–60 s for a long answer. |
| `LLM_GENERAL_ANSWERS` | `true` | `false` keeps the assistant to cited document answers only. |
| `acadealert.assistant.grounded_relevance` | `0.45` | Score at which an answer *must* come from the documents. |

A model problem is never a request failure: if the server is down, slow or
returns nonsense, the exchange is logged and the local engine answers instead.

### Is it working?

```bash
curl -s http://127.0.0.1:8000/api/v1/system/status -H "Authorization: Bearer <token>" | jq .llm
```

```json
{ "enabled": true, "model": "qwen3:8b", "base_url": "http://127.0.0.1:11434/v1",
  "reachable": true, "model_available": true, "models": ["qwen3:8b"], "general_answers": true }
```

`reachable: false` → the server is not running (`ollama serve`).
`model_available: false` → it is running but the model is not pulled
(`ollama pull qwen3:8b`).

---

## The static demo

`frontend-web/` has no backend, so `assistant.html` calls the same endpoint
**directly from the browser** through `assets/js/llm.js`, with the same two
paths and the same prompts, and streams the answer as it arrives. A chip above
the chat says `qwen3:8b · local` when the model is answering, or *"No model
running — demo answers only"* with the exact command to fix it; in that state
the five hand-written knowledge-base answers still work.

Model output is escaped before it reaches the DOM (only `**bold**`, `` `code` ``
and fenced blocks are rendered), so a reply can never inject markup.

To point the demo somewhere else, once, in the browser console:

```js
ES.llm.save({ model: 'qwen3:4b' })                               // smaller model
ES.llm.save({ baseUrl: 'http://127.0.0.1:1234/v1' })             // LM Studio
```

Ollama accepts requests from `localhost` origins, so serving the demo with
`node serve.js` needs no extra configuration. If a browser console shows a CORS
error, allow the origin explicitly:

```powershell
setx OLLAMA_ORIGINS "http://localhost:5173,http://127.0.0.1:5173"   # then restart Ollama
```

---

## Tests

```bash
cd apps/api
php artisan test --filter AssistantLlmTest
```

Eleven tests with the model faked (no Ollama, no network): general answers,
grounded answers with citations, refusal to replace a grounded answer with an
uncited one, request shape for Qwen3, bearer key for hosted providers,
behaviour when the server is unreachable, `LLM_GENERAL_ANSWERS=false`,
follow-up history, thinking-block stripping and the health report.

The browser client has its own checks:

```bash
node <scratch>/test-demo-llm.mjs frontend-web/assets/js/llm.js
```

---

## Note on the React client

`apps/web/src/features/assistant/pages/*` are still placeholders ("Coming
together…"), so the React chat UI does not exist yet. The API work above is
complete and the static demo is the working assistant interface today.
