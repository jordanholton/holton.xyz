<?php

use App\Http\Controllers\BlogController;
use Illuminate\Support\Facades\Route;

Route::get('/', [BlogController::class, 'home'])->name('home');

Route::prefix('posts')->name('posts.')->group(function () {
    Route::get('/', [BlogController::class, 'index'])->name('index');
    Route::get('{year}.php', [BlogController::class, 'year'])
        ->where('year', '[0-9]{4}')
        ->name('year');
});
