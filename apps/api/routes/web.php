<?php

use Illuminate\Support\Facades\Route;

/*
| The API is the product; the user interface is the React app in apps/web.
| The root URL just describes the service for anyone who opens it.
*/
Route::get('/', fn () => response()->json([
    'service' => 'AcadeAlert API',
    'version' => config('acadealert.version'),
    'api' => url('/api/v1'),
    'health' => url('/api/v1/health'),
    'web_app' => config('app.frontend_url'),
]));
