<?php

/*
|--------------------------------------------------------------------------
| EDU-SMART domain configuration
|--------------------------------------------------------------------------
| Tunables for the four feature modules and the shared platform services.
| Everything here has a sensible default so the platform runs with an empty
| .env; the comments explain what each knob does to the user experience.
*/

return [

    'version' => '2.0.0',

    /*
    | Upload limits and accepted formats. The extractor supports PDF, Word
    | (.docx and best-effort legacy .doc), PowerPoint (.pptx), plain text and
    | Markdown. Sizes are in kilobytes for Laravel's `max` validation rule.
    */
    'uploads' => [
        'max_kb' => (int) env('UPLOAD_MAX_MB', 25) * 1024,
        'learning_extensions' => ['pdf', 'docx', 'doc', 'pptx', 'txt', 'md'],
        'knowledge_extensions' => ['pdf', 'docx', 'doc', 'pptx', 'txt', 'md'],
        'disk' => 'local',
    ],

    /*
    | Natural-language processing engine (pure PHP, no external services).
    */
    'nlp' => [
        // Sentences kept by each summary length: [ratio of the document, min, max].
        'summary_lengths' => [
            'short' => [0.10, 2, 4],
            'medium' => [0.22, 4, 8],
            'detailed' => [0.38, 7, 16],
        ],
        'keywords' => 12,
        'max_flashcards' => 16,
        'max_quiz_questions' => 10,
    ],

    /*
    | Academic assistant retrieval (BM25 + optional semantic re-ranking).
    */
    'assistant' => [
        'chunk_min_words' => 60,
        'chunk_max_words' => 200,
        'top_k' => 5,
        'min_relevance' => 0.18,
        'bm25_k1' => 1.4,
        'bm25_b' => 0.72,
    ],

    /*
    | Study sessions & engagement (Pasindu's module).
    */
    'study' => [
        'engagement' => ['high' => 70, 'moderate' => 40],
        'default_planned_minutes' => 25,
        'max_session_minutes' => 600,
    ],

    /*
    | Assignment risk engine (Jithmi's module).
    |   probability = sigmoid(steepness * (loadRatio - midpoint) + adjustments)
    | A load ratio of 1.0 means the work left exactly fills the study time
    | available before the deadline (after earlier deadlines are served).
    */
    'risk' => [
        'midpoint' => 0.9,
        'steepness' => 6.5,
        'study_day_start' => 8,   // hour the study day starts
        'study_day_end' => 23,    // hour the study day ends
        'default_availability' => [
            'mon' => 3, 'tue' => 3, 'wed' => 3, 'thu' => 3, 'fri' => 2, 'sat' => 5, 'sun' => 4,
        ],
        'levels' => ['critical' => 75, 'high' => 50, 'medium' => 25],
        'horizon_days' => 30,
    ],

    /*
    | Firebase realtime layer. With FIRESTORE_EMULATOR_HOST set, the REST
    | client talks to the local emulator; otherwise it uses the service
    | account in FIREBASE_CREDENTIALS against the real project.
    */
    'firebase' => [
        'enabled' => (bool) env('FIREBASE_ENABLED', false),
        'project_id' => env('FIREBASE_PROJECT_ID', 'demo-edusmart'),
        'credentials' => env('FIREBASE_CREDENTIALS'),
        'firestore_emulator_host' => env('FIRESTORE_EMULATOR_HOST'),
        'auth_emulator_host' => env('FIREBASE_AUTH_EMULATOR_HOST'),
        'timeout' => 3,
        // After a failed call the mirror pauses for this many seconds so an
        // offline emulator never slows down the API.
        'circuit_breaker_seconds' => 60,
    ],

    /*
    | Optional Python semantic ranking service (services/ai-engine).
    */
    'ai_engine' => [
        'url' => env('AI_ENGINE_URL'),
        'timeout' => (int) env('AI_ENGINE_TIMEOUT', 6),
    ],

    /*
    | Optional OpenAI-compatible LLM used to phrase answers/summaries.
    */
    'llm' => [
        'enabled' => (bool) env('LLM_ENABLED', false),
        'base_url' => env('LLM_BASE_URL', 'https://api.openai.com/v1'),
        'api_key' => env('LLM_API_KEY'),
        'model' => env('LLM_MODEL', 'gpt-4o-mini'),
        'timeout' => (int) env('LLM_TIMEOUT', 30),
    ],

    /*
    | API request auditing. Every non-GET request is written to activity_logs;
    | GET routes opt in with the `audit:<action>` middleware.
    */
    'activity' => [
        'retention_days' => 365,
    ],
];
