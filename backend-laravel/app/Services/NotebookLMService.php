<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * NotebookLM Integration Service
 * 
 * Provides AI-powered features:
 * - Document analysis and insights
 * - Audio generation from study materials
 * - Interactive study guides
 * - Quiz generation
 * - Key concept extraction
 * - Study plan recommendations
 */
class NotebookLMService
{
    private bool $available;
    private ?string $apiKey;
    private string $baseUrl = 'https://notebooklm.google.com/api/v1';

    public function __construct()
    {
        $this->apiKey = config('services.notebooklm.api_key');
        $this->available = !empty($this->apiKey);
    }

    /**
     * Generate audio study guide from document text
     */
    public function generateAudioGuide(string $documentText, string $title = 'Study Guide'): ?array
    {
        if (!$this->available) {
            Log::warning('[NotebookLM] Audio generation skipped — API key not configured.');
            return $this->generateOfflineAudioGuide($documentText, $title);
        }

        try {
            $response = $this->callApi('POST', '/audio/generate', [
                'content' => $documentText,
                'title'   => $title,
                'format'  => 'mp3',
                'voice'   => 'natural',
            ]);

            return [
                'success'    => true,
                'audio_url'  => $response['audio_url'] ?? null,
                'duration'   => $response['duration'] ?? 0,
                'transcript' => $response['transcript'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::error('[NotebookLM] Audio generation failed: ' . $e->getMessage());
            return $this->generateOfflineAudioGuide($documentText, $title);
        }
    }

    /**
     * Generate interactive quiz from document
     */
    public function generateQuiz(string $documentText, int $questionCount = 10): array
    {
        if (!$this->available) {
            return $this->generateOfflineQuiz($documentText, $questionCount);
        }

        try {
            $response = $this->callApi('POST', '/quiz/generate', [
                'content'          => $documentText,
                'question_count'   => $questionCount,
                'difficulty'       => 'medium',
                'question_types'   => ['multiple_choice', 'short_answer', 'true_false'],
            ]);

            return [
                'success'  => true,
                'quiz_id'  => $response['quiz_id'] ?? null,
                'questions' => $response['questions'] ?? [],
                'estimated_time' => $response['estimated_time'] ?? 0,
            ];
        } catch (\Throwable $e) {
            Log::error('[NotebookLM] Quiz generation failed: ' . $e->getMessage());
            return $this->generateOfflineQuiz($documentText, $questionCount);
        }
    }

    /**
     * Extract key concepts and learning objectives
     */
    public function extractConcepts(string $documentText): array
    {
        if (!$this->available) {
            return $this->extractConceptsOffline($documentText);
        }

        try {
            $response = $this->callApi('POST', '/concepts/extract', [
                'content' => $documentText,
                'depth'   => 'detailed',
            ]);

            return [
                'success'              => true,
                'key_concepts'         => $response['concepts'] ?? [],
                'learning_objectives'  => $response['objectives'] ?? [],
                'difficulty_level'     => $response['difficulty'] ?? 'medium',
                'estimated_study_time' => $response['study_time'] ?? 0,
            ];
        } catch (\Throwable $e) {
            Log::error('[NotebookLM] Concept extraction failed: ' . $e->getMessage());
            return $this->extractConceptsOffline($documentText);
        }
    }

    /**
     * Generate personalized study plan
     */
    public function generateStudyPlan(
        string $documentText,
        int $availableHours = 5,
        string $learningStyle = 'mixed'
    ): array {
        if (!$this->available) {
            return $this->generateOfflineStudyPlan($documentText, $availableHours, $learningStyle);
        }

        try {
            $response = $this->callApi('POST', '/study-plan/generate', [
                'content'           => $documentText,
                'available_hours'   => $availableHours,
                'learning_style'    => $learningStyle,
                'include_resources' => true,
            ]);

            return [
                'success'      => true,
                'plan_id'      => $response['plan_id'] ?? null,
                'sessions'     => $response['sessions'] ?? [],
                'milestones'   => $response['milestones'] ?? [],
                'resources'    => $response['resources'] ?? [],
                'total_hours'  => $response['total_hours'] ?? $availableHours,
            ];
        } catch (\Throwable $e) {
            Log::error('[NotebookLM] Study plan generation failed: ' . $e->getMessage());
            return $this->generateOfflineStudyPlan($documentText, $availableHours, $learningStyle);
        }
    }

    /**
     * Generate flashcards from document
     */
    public function generateFlashcards(string $documentText, int $cardCount = 20): array
    {
        if (!$this->available) {
            return $this->generateOfflineFlashcards($documentText, $cardCount);
        }

        try {
            $response = $this->callApi('POST', '/flashcards/generate', [
                'content'     => $documentText,
                'card_count'  => $cardCount,
                'format'      => 'spaced_repetition',
            ]);

            return [
                'success'     => true,
                'deck_id'     => $response['deck_id'] ?? null,
                'cards'       => $response['cards'] ?? [],
                'total_cards' => count($response['cards'] ?? []),
            ];
        } catch (\Throwable $e) {
            Log::error('[NotebookLM] Flashcard generation failed: ' . $e->getMessage());
            return $this->generateOfflineFlashcards($documentText, $cardCount);
        }
    }

    /**
     * Analyze document for comprehension gaps
     */
    public function analyzeComprehension(string $documentText): array
    {
        if (!$this->available) {
            return $this->analyzeComprehensionOffline($documentText);
        }

        try {
            $response = $this->callApi('POST', '/analysis/comprehension', [
                'content' => $documentText,
            ]);

            return [
                'success'           => true,
                'clarity_score'     => $response['clarity_score'] ?? 0,
                'complexity_level'  => $response['complexity'] ?? 'medium',
                'gaps'              => $response['gaps'] ?? [],
                'recommendations'   => $response['recommendations'] ?? [],
            ];
        } catch (\Throwable $e) {
            Log::error('[NotebookLM] Comprehension analysis failed: ' . $e->getMessage());
            return $this->analyzeComprehensionOffline($documentText);
        }
    }

    // ─── Offline Fallbacks ───────────────────────────────────────────────────

    private function generateOfflineAudioGuide(string $text, string $title): array
    {
        $summary = $this->extractSummary($text, 200);
        return [
            'success'    => true,
            'audio_url'  => null,
            'duration'   => ceil(strlen($summary) / 150),
            'transcript' => $summary,
            'offline'    => true,
        ];
    }

    private function generateOfflineQuiz(string $text, int $count): array
    {
        $sentences = preg_split('/[.!?]+/', $text);
        $questions = [];

        for ($i = 0; $i < min($count, count($sentences)); $i++) {
            $sentence = trim($sentences[$i]);
            if (strlen($sentence) > 20) {
                $questions[] = [
                    'id'       => $i + 1,
                    'question' => "What is the main idea of: \"" . substr($sentence, 0, 80) . "...\"?",
                    'type'     => 'short_answer',
                    'answer'   => $sentence,
                ];
            }
        }

        return [
            'success'          => true,
            'quiz_id'          => null,
            'questions'        => $questions,
            'estimated_time'   => count($questions) * 2,
            'offline'          => true,
        ];
    }

    private function extractConceptsOffline(string $text): array
    {
        preg_match_all('/\b[A-Z][a-z]+(?:\s+[A-Z][a-z]+)*\b/', $text, $matches);
        $concepts = array_unique($matches[0] ?? []);

        return [
            'success'              => true,
            'key_concepts'         => array_slice($concepts, 0, 10),
            'learning_objectives'  => ['Understand key concepts', 'Apply knowledge', 'Analyze information'],
            'difficulty_level'     => 'medium',
            'estimated_study_time' => ceil(strlen($text) / 500),
            'offline'              => true,
        ];
    }

    private function generateOfflineStudyPlan(string $text, int $hours, string $style): array
    {
        $sessions = [];
        $sessionDuration = max(1, floor($hours / 5));

        for ($i = 0; $i < 5; $i++) {
            $sessions[] = [
                'session_number' => $i + 1,
                'duration'       => $sessionDuration,
                'focus'          => ['Overview', 'Deep dive', 'Practice', 'Review', 'Assessment'][$i],
                'activities'     => ['Read', 'Summarize', 'Quiz', 'Discuss', 'Test'][$i],
            ];
        }

        return [
            'success'     => true,
            'plan_id'     => null,
            'sessions'    => $sessions,
            'milestones'  => ['Concepts understood', 'Practice completed', 'Ready for assessment'],
            'resources'   => [],
            'total_hours' => $hours,
            'offline'     => true,
        ];
    }

    private function generateOfflineFlashcards(string $text, int $count): array
    {
        $sentences = preg_split('/[.!?]+/', $text);
        $cards = [];

        for ($i = 0; $i < min($count, count($sentences)); $i++) {
            $sentence = trim($sentences[$i]);
            if (strlen($sentence) > 15) {
                $words = explode(' ', $sentence);
                $cards[] = [
                    'id'     => $i + 1,
                    'front'  => implode(' ', array_slice($words, 0, 3)) . '...',
                    'back'   => $sentence,
                    'level'  => 1,
                ];
            }
        }

        return [
            'success'     => true,
            'deck_id'     => null,
            'cards'       => $cards,
            'total_cards' => count($cards),
            'offline'     => true,
        ];
    }

    private function analyzeComprehensionOffline(string $text): array
    {
        $wordCount = str_word_count($text);
        $avgWordLength = strlen(str_replace(' ', '', $text)) / max(1, $wordCount);
        $complexity = $avgWordLength > 6 ? 'high' : ($avgWordLength > 4 ? 'medium' : 'low');

        return [
            'success'           => true,
            'clarity_score'     => min(100, 50 + ($wordCount / 100)),
            'complexity_level'  => $complexity,
            'gaps'              => [],
            'recommendations'   => ['Add more examples', 'Simplify language', 'Include visuals'],
            'offline'           => true,
        ];
    }

    private function extractSummary(string $text, int $maxLength): string
    {
        $sentences = preg_split('/[.!?]+/', $text);
        $summary = '';

        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if (strlen($summary) + strlen($sentence) < $maxLength && !empty($sentence)) {
                $summary .= $sentence . '. ';
            }
        }

        return trim($summary) ?: substr($text, 0, $maxLength);
    }

    private function callApi(string $method, string $endpoint, array $data): array
    {
        // Placeholder for actual API calls
        return [];
    }
}
