<?php

use App\Http\Controllers\SpaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
| Semua URL non-API diarahkan ke shell SPA Vue. Routing halaman
| ditangani oleh Vue Router. /api dan /sanctum dikecualikan.
*/

Route::get('/{any?}', SpaController::class)
    ->where('any', '^(?!api(/|$)|sanctum(/|$)).*$');