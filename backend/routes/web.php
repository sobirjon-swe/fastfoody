<?php

use Illuminate\Support\Facades\Route;

/*
| The backend is an API only application; the user interface lives in the
| separate React SPA under ../frontend.
*/

Route::get('/', fn () => response()->json([
    'name' => config('app.name'),
    'api' => url('/api'),
]));
