<?php

use Illuminate\Support\Facades\Route;

// The web UI is the separate React app; this is only the API server.
Route::get('/', fn () => response()->json([
    'app' => config('app.name'),
    'message' => 'Lizy Reminder API. The web app is served by the React frontend.',
]));
