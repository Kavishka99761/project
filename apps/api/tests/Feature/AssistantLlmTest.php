<?php

namespace Tests\Feature;

use App\Enums\KnowledgeCategory;
use App\Enums\KnowledgeStatus;
use App\Models\KnowledgeDocument;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\Ai\LlmClient;
use App\Services\Assistant\KnowledgeIndexer;
use App\Services\Documents\ExtractedText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * KAVISHKA — the Academic Assistant answering through an OpenAI-compatible
 * model (Qwen3 8B on Ollama by default).
 *
 * Two paths are covered: a strong knowledge-base match must be answered from
 * the documents with citations, and anything else is answered from the model's
 * own knowledge like a normal AI assistant. The model is faked here, so these
 * tests need neither Ollama nor a network.
 */
class AssistantLlmTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = 'http://127.0.0.1:11434/v1/chat/completions';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('acadealert.llm.enabled', true);
        config()->set('acadealert.llm.base_url', 'http://127.0.0.1:11434/v1');
        config()->set('acadealert.llm.api_key', '');
        config()->set('acadealert.llm.model', 'qwen3:8b');
        config()->set('acadealert.llm.general_answers', true);
    }

    private function student(): User
    {
        $user = User::create(['name' => 'Ayesha Perera', 'email' => 'ayesha@acadealert.lk', 'password' => 'Study2026x']);
        $user->settings()->create(UserSetting::defaults());

        return $user;
    }

    /** Index one handbook page so BM25 has something real to retrieve. */
    private function handbook(User $user): KnowledgeDocument
    {
        $document = $user->knowledgeDocuments()->create([
            'title' => 'Student Handbook 2026/27',
            'category' => KnowledgeCategory::Handbook,
            'status' => KnowledgeStatus::Indexed,
            'original_name' => 'handbook.pdf',
            'mime_type' => 'application/pdf',
            'pages' => 1,
        ]);

        app(KnowledgeIndexer::class)->index($document, new ExtractedText(
            text: 'Attendance',
            pages: [
                'Attendance and eligibility. Students must attend at least 80 per cent of the timetabled sessions '
                .'of each module. A student whose attendance falls below the eighty per cent attendance requirement '
                .'may be refused entry to the examination for that module. Attendance is recorded every week by the '
                .'module leader and reported to the faculty office.',
            ],
            pageCount: 1,
        ));

        return $document->refresh();
    }

    /** Fake the chat-completions endpoint with a Qwen3-style reply. */
    private function fakeModel(string $answer, int $status = 200): void
    {
        Http::fake([
            'http://127.0.0.1:11434/v1/models' => Http::response(['data' => [['id' => 'qwen3:8b']]]),
            self::ENDPOINT => $status === 200
                ? Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => $answer]]]])
                : Http::response(['error' => 'boom'], $status),
        ]);
    }

    public function test_a_question_the_documents_do_not_cover_is_answered_from_general_knowledge(): void
    {
        $this->fakeModel('<think>general question</think>Photosynthesis converts light, water and carbon dioxide into glucose and oxygen.');
        $user = $this->student();

        $response = $this->actingAs($user)->postJson('/api/v1/assistant/chat', ['message' => 'What is photosynthesis?']);

        $response->assertCreated()
            ->assertJsonPath('answer.meta.method', 'llm-general')
            ->assertJsonPath('answer.meta.found', false);

        $content = $response->json('answer.content');
        $this->assertStringContainsString('Photosynthesis converts light', $content);
        $this->assertStringNotContainsString('<think>', $content, 'the reasoning block must never reach the student');
        $this->assertStringContainsString('general knowledge', $content, 'the answer says it is not from their documents');
    }

    public function test_a_strong_document_match_is_answered_from_the_documents_with_a_citation(): void
    {
        $this->fakeModel('<think>checking</think>You must attend at least 80% of each module, or you can be refused entry to the exam [1].');
        $user = $this->student();
        $this->handbook($user);

        $response = $this->actingAs($user)->postJson('/api/v1/assistant/chat', ['message' => 'What is the attendance requirement for each module?']);

        $response->assertCreated()
            ->assertJsonPath('answer.meta.method', 'llm')
            ->assertJsonPath('answer.meta.found', true);

        $this->assertStringContainsString('[1]', $response->json('answer.content'));
        $this->assertStringNotContainsString('general knowledge', $response->json('answer.content'));
        $this->assertNotEmpty($response->json('answer.sources'));
        $this->assertSame('Student Handbook 2026/27', $response->json('answer.sources.0.document'));
    }

    public function test_an_uncited_model_answer_never_replaces_a_grounded_one(): void
    {
        // The model ignored the instruction to cite: the extractive answer wins,
        // so the student is never shown a document answer without a source.
        $this->fakeModel('Attendance is probably around 75% but I am not certain.');
        $user = $this->student();
        $this->handbook($user);

        $response = $this->actingAs($user)->postJson('/api/v1/assistant/chat', ['message' => 'What is the attendance requirement for each module?']);

        $response->assertCreated()->assertJsonPath('answer.meta.method', 'extractive');
        $this->assertStringNotContainsString('probably around 75%', $response->json('answer.content'));
    }

    public function test_the_request_sent_to_the_model_is_shaped_for_qwen3_without_a_key(): void
    {
        $this->fakeModel('<think>x</think>An answer.');
        $user = $this->student();

        $this->actingAs($user)->postJson('/api/v1/assistant/chat', ['message' => 'Explain recursion in simple terms'])->assertCreated();

        Http::assertSent(function (Request $request) {
            if (! str_contains($request->url(), '/chat/completions')) {
                return false;
            }
            $body = $request->data();

            return $body['model'] === 'qwen3:8b'
                && $body['stream'] === false
                && $body['think'] === false                                  // Ollama: answer, do not reason aloud
                && $body['chat_template_kwargs']['enable_thinking'] === false // vLLM / DashScope
                && $body['messages'][0]['role'] === 'system'
                && ! $request->hasHeader('Authorization');                   // local Ollama needs no key
        });
    }

    public function test_a_hosted_provider_gets_the_api_key(): void
    {
        config()->set('acadealert.llm.base_url', 'https://openrouter.ai/api/v1');
        config()->set('acadealert.llm.api_key', 'sk-test-123');
        config()->set('acadealert.llm.model', 'qwen/qwen3-8b');
        Http::fake(['https://openrouter.ai/api/v1/*' => Http::response(['choices' => [['message' => ['content' => 'Hello.']]]])]);

        $user = $this->student();
        $this->actingAs($user)->postJson('/api/v1/assistant/chat', ['message' => 'Say hello'])->assertCreated();

        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer sk-test-123')
            && $request->data()['model'] === 'qwen/qwen3-8b');
    }

    public function test_the_assistant_still_answers_when_the_model_is_unreachable(): void
    {
        $this->fakeModel('', 500);
        $user = $this->student();
        $this->handbook($user);

        $grounded = $this->actingAs($user)->postJson('/api/v1/assistant/chat', ['message' => 'What is the attendance requirement for each module?']);
        $grounded->assertCreated()->assertJsonPath('answer.meta.method', 'extractive');
        // The local engine quotes the handbook sentence and keeps its citation.
        $this->assertStringContainsString('attendance', mb_strtolower($grounded->json('answer.content')));
        $this->assertStringContainsString('[1]', $grounded->json('answer.content'));

        $general = $this->actingAs($user)->postJson('/api/v1/assistant/chat', ['message' => 'Who painted the Mona Lisa?']);
        $general->assertCreated()->assertJsonPath('answer.meta.method', 'none');
    }

    public function test_general_answers_can_be_switched_off(): void
    {
        config()->set('acadealert.llm.general_answers', false);
        $this->fakeModel('<think>x</think>Leonardo da Vinci painted it.');
        $user = $this->student();

        $response = $this->actingAs($user)->postJson('/api/v1/assistant/chat', ['message' => 'Who painted the Mona Lisa?']);

        $response->assertCreated()->assertJsonPath('answer.meta.method', 'none');
        $this->assertStringNotContainsString('Leonardo', $response->json('answer.content'));
    }

    public function test_follow_up_questions_carry_the_conversation_to_the_model(): void
    {
        $this->fakeModel('<think>x</think>Here is a simpler explanation.');
        $user = $this->student();

        $first = $this->actingAs($user)->postJson('/api/v1/assistant/chat', ['message' => 'Explain recursion'])->assertCreated();
        $conversationId = $first->json('conversation.id');

        $this->actingAs($user)->postJson('/api/v1/assistant/chat', [
            'message' => 'explain that more simply',
            'conversation_id' => $conversationId,
        ])->assertCreated();

        Http::assertSent(function (Request $request) {
            if (! str_contains($request->url(), '/chat/completions')) {
                return false;
            }
            $roles = array_column($request->data()['messages'], 'role');

            return in_array('assistant', $roles, true) && count($roles) >= 4;
        });
    }

    public function test_thinking_blocks_are_stripped_in_every_shape(): void
    {
        $this->assertSame('Answer.', LlmClient::stripThinking('<think>reasoning</think>Answer.'));
        $this->assertSame('Answer.', LlmClient::stripThinking("<think>\nmulti\nline\n</think>\n\nAnswer."));
        $this->assertSame('', LlmClient::stripThinking('<think>cut off mid-thought'));
        $this->assertSame('Plain answer.', LlmClient::stripThinking('Plain answer.'));
        $this->assertSame('A. B.', LlmClient::stripThinking('A. <think>aside</think>B.'));
    }

    public function test_health_reports_the_model_and_whether_it_is_reachable(): void
    {
        // This is what /system/status returns under "llm". (The endpoint itself
        // also reports SQL Server edition, which the sqlite test database cannot
        // answer, so the payload is checked directly.) One fake with a sequence:
        // repeated Http::fake() calls merge their stubs, so the first would win.
        Http::fake([
            'http://127.0.0.1:11434/v1/models' => Http::sequence()
                ->push(['data' => [['id' => 'qwen3:8b'], ['id' => 'nomic-embed-text']]])
                ->push(['data' => [['id' => 'llama3.2']]])
                ->pushStatus(500),
        ]);

        $up = app(LlmClient::class)->health();
        $this->assertTrue($up['enabled']);
        $this->assertSame('qwen3:8b', $up['model']);
        $this->assertSame('http://127.0.0.1:11434/v1', $up['base_url']);
        $this->assertTrue($up['reachable']);
        $this->assertTrue($up['model_available']);
        $this->assertSame(['qwen3:8b', 'nomic-embed-text'], $up['models']);

        // Server up, but the model has not been pulled: the UI can say so.
        $missing = app(LlmClient::class)->health();
        $this->assertTrue($missing['reachable']);
        $this->assertFalse($missing['model_available']);

        // Server down.
        $this->assertFalse(app(LlmClient::class)->health()['reachable']);
    }

    public function test_health_is_quiet_when_the_feature_is_switched_off(): void
    {
        config()->set('acadealert.llm.enabled', false);

        $health = app(LlmClient::class)->health();

        $this->assertFalse($health['enabled']);
        $this->assertNull($health['model']);
        $this->assertNull($health['reachable']);
    }
}
