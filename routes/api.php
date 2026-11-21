<?php

use App\Http\Controllers\Api\EntryController;
use App\Http\Controllers\Api\TokenController;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
| Remote publishing API for the mobile app. Authenticate with
| POST /api/v1/tokens, then send "Authorization: Bearer <token>".
*/
Route::prefix('v1')->name('api.')->group(function () {
    Route::post('tokens', [TokenController::class, 'store'])
        ->middleware('throttle:api-tokens')
        ->name('tokens.store');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('user', fn (Request $request) => new UserResource($request->user()))->name('user');
        Route::delete('tokens/current', [TokenController::class, 'destroy'])->name('tokens.destroy');

        Route::middleware('abilities:posts:read')->group(function () {
            Route::get('posts', [EntryController::class, 'index'])->name('posts.index');
            Route::get('posts/{id}', [EntryController::class, 'show'])->name('posts.show');
        });

        Route::middleware('abilities:posts:write')->group(function () {
            Route::post('posts', [EntryController::class, 'store'])->name('posts.store');
            Route::patch('posts/{id}', [EntryController::class, 'update'])->name('posts.update');
            Route::delete('posts/{id}', [EntryController::class, 'destroy'])->name('posts.destroy');
        });
    });
});
