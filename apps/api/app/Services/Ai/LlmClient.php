<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OpenAI-compatible chat-completions client, used by the Academic Assistant
 * (Kavishka) and the summary engine.
 *
 * It talks to anything that speaks /v1/chat/completions. The project default
 * is **Qwen3 8B served locally by Ollama** (http://127.0.0.1:11434/v1), which
 * needs no API key — so a key is sent only when one is configured, and a
 * hosted provider (OpenRouter, DashScope, Together…) works by changing
 * LLM_BASE_URL, LLM_MODEL and LLM_API_KEY.
 *
 * Qwen3 is a hybrid reasoning model: it can emit a <think>…</think> block
 * before the answer. Thinking is switched off through the usual provider
 * options, and any block that still arrives is stripped, so callers always
 * receive the answer only.
 */
class LlmClient
{
    /** Models whose chat template understands the "no thinking" switch. */
    private const THINKING_MODELS = ['qwen3', 'qwq', 'deepseek-r1'];

    public function enabled(): bool
    {
        return (bool) config('acadealert.llm.enabled')
            && (string) config('acadealert.llm.base_url') !== '';
    }

    /** True when the assistant may answer questions the documents do not cover. */
    public function generalAnswersEnabled(): bool
    {
        return $this->enabled() && (bool) config('acadealert.llm.general_answers');
    }

    public function model(): string
    {
        return (string) config('acadealert.llm.model');
    }

    public function baseUrl(): string
    {
        return rtrim((string) config('acadealert.llm.base_url'), '/');
    }

    /**
     * Ask the model. Returns null on any failure, so every caller can fall
     * back to the local engine instead of showing an error.
     *
     * @param  list<array{role: string, content: string}>  $messages
     */
    public function complete(array $messages, int $maxTokens = 700, float $temperature = 0.2): ?string
    {
        if (! $this->enabled()) {
            return null;
        }

        try {
            $response = $this->request()->post($this->baseUrl().'/chat/completions', $this->payload($messages, $maxTokens, $temperature));

            if ($response->failed()) {
                Log::warning('LLM request failed', [
                    'status' => $response->status(),
                    'model' => $this->model(),
                    'body' => mb_substr((string) $response->body(), 0, 300),
                ]);

                return null;
            }

            $content = self::stripThinking((string) $response->json('choices.0.message.content'));

            return $content !== '' ? $content : null;
        } catch (\Throwable $e) {
            Log::warning('LLM request error: '.$e->getMessage(), ['model' => $this->model(), 'base_url' => $this->baseUrl()]);

            return null;
        }
    }

    /**
     * Is the configured server actually reachable, and does it have the model?
     * Used by /system/status so the UI can say what is wrong.
     *
     * @return array{enabled: bool, model: string|null, base_url: string|null, reachable: bool|null, model_available: bool|null, models: list<string>}
     */
    public function health(): array
    {
        $state = [
            'enabled' => $this->enabled(),
            'model' => $this->enabled() ? $this->model() : null,
            'base_url' => $this->enabled() ? $this->baseUrl() : null,
            'reachable' => null,
            'model_available' => null,
            'models' => [],
        ];

        if (! $this->enabled()) {
            return $state;
        }

        try {
            $response = $this->request()->timeout(5)->get($this->baseUrl().'/models');
            $state['reachable'] = $response->successful();

            if ($response->successful()) {
                $ids = array_values(array_filter(array_map(
                    fn ($entry) => is_array($entry) ? ($entry['id'] ?? null) : null,
                    (array) $response->json('data', []),
                )));
                $state['models'] = $ids;
                // Ollama reports "qwen3:8b"; hosted providers "qwen/qwen3-8b".
                $wanted = $this->model();
                $state['model_available'] = in_array($wanted, $ids, true)
                    || (bool) array_filter($ids, fn ($id) => str_contains((string) $id, $wanted) || str_contains($wanted, (string) $id));
            }
        } catch (\Throwable $e) {
            $state['reachable'] = false;
        }

        return $state;
    }

    private function request(): PendingRequest
    {
        $request = Http::timeout((int) config('acadealert.llm.timeout', 120))->acceptJson();
        $key = (string) config('acadealert.llm.api_key');

        // A local Ollama / LM Studio server takes no key; a hosted one needs it.
        return $key === '' ? $request : $request->withToken($key);
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @return array<string, mixed>
     */
    private function payload(array $messages, int $maxTokens, float $temperature): array
    {
        $payload = [
            'model' => $this->model(),
            'messages' => $messages,
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
            'stream' => false,
        ];

        if ($this->isThinkingModel()) {
            // Answer directly instead of reasoning out loud: Ollama reads
            // "think", vLLM/DashScope read chat_template_kwargs. Unknown keys
            // are ignored by servers that do not support them.
            $payload['think'] = false;
            $payload['chat_template_kwargs'] = ['enable_thinking' => false];
        }

        return $payload;
    }

    private function isThinkingModel(): bool
    {
        $model = mb_strtolower($this->model());

        foreach (self::THINKING_MODELS as $family) {
            if (str_contains($model, $family)) {
                return true;
            }
        }

        return false;
    }

    /** Remove a Qwen3-style <think>…</think> block (including an unclosed one). */
    public static function stripThinking(string $content): string
    {
        $clean = preg_replace('#<think>.*?</think>#isu', '', $content) ?? $content;
        // A truncated answer can leave an unterminated block: drop the rest.
        $clean = preg_replace('#<think>.*$#isu', '', $clean) ?? $clean;

        return trim($clean);
    }
}
