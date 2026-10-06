<?php

use Illuminate\Support\Facades\Route;

/*
| The API is the product; this root route just advertises it. The interactive
| user interface lives in ../frontend-web (HTML/CSS/JS) and ../mobile-app
| (React Native), both of which consume the /api endpoints in routes/api.php.
*/
Route::get('/', fn () => response()->json([
    'service' => 'EDU-SMART API',
    'version' => '1.0.0',
    'status'  => 'ok',
    'health'  => url('/up'),
    'endpoints' => [
        'auth'      => ['POST /api/register', 'POST /api/login', 'POST /api/logout', 'GET|PUT /api/me'],
        'dashboard' => ['GET /api/dashboard', 'GET /api/calendar'],
        'bethmi'    => ['GET|POST /api/documents', 'POST /api/documents/{id}/summaries', 'GET /api/summaries'],
        'pasindu'   => ['GET|POST /api/study/sessions', 'GET /api/study/analytics', 'GET /api/study/current'],
        'kavishka'  => ['POST /api/assistant/chat', 'GET /api/assistant/knowledge', 'GET /api/assistant/dates'],
        'jithmi'    => ['GET|POST /api/assignments', 'GET /api/assignments/rank', 'GET /api/assignments/recommendation', 'POST /api/assignments/whatif'],
    ],
]));
