<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Optional OpenAI-compatible chat-completions client.
 *
 * Disabled by default: EDU-SMART's summaries and chatbot answers are produced
 * by the built-in NLP engine. When LLM_ENABLED=true the assistant uses the
 * model only to *phrase* answers from retrieved, cited passages — retrieval,
 * grounding and citations always stay local and deterministic.
 */
class LlmClient
{
    public function enabled(): bool
    {
        return (bool) config('edusmart.llm.enabled') && (string) config('edusmart.llm.api_key') !== '';
    }

    public function model(): string
    {
        return (string) config('edusmart.llm.model');
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     */
    public function complete(array $messages, int $maxTokens = 700, float $temperature = 0.2): ?string
    {
        if (! $this->enabled()) {
            return null;
        }

        try {
            $response = Http::withToken((string) config('edusmart.llm.api_key'))
                ->timeout((int) config('edusmart.llm.timeout', 30))
                ->acceptJson()
                ->post(rtrim((string) config('edusmart.llm.base_url'), '/').'/chat/completions', [
                    'model' => $this->model(),
                    'messages' => $messages,
                    'max_tokens' => $maxTokens,
                    'temperature' => $temperature,
                ]);

            if ($response->failed()) {
                Log::warning('LLM request failed', ['status' => $response->status()]);

                return null;
            }

            $content = trim((string) $response->json('choices.0.message.content'));

            return $content !== '' ? $content : null;
        } catch (\Throwable $e) {
            Log::warning('LLM request error: '.$e->getMessage());

            return null;
        }
    }
}
