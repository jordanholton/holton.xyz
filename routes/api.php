<?php

use App\Http\Controllers\Api\EntryController;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
| Remote publishing API for the mobile app. The app signs in with the SSO
| realm (authorization code + PKCE) and sends its access token as
| "Authorization: Bearer <token>". Permissions come from realm roles.
*/
Route::prefix('v1')->name('api.')->middleware(['auth:api', 'throttle:api'])->group(function () {
    Route::get('user', fn (Request $request) => (new UserResource($request->user()))->response()->setStatusCode(200))->name('user');

    Route::middleware('role:posts:read')->group(function () {
        Route::get('posts', [EntryController::class, 'index'])->name('posts.index');
        Route::get('posts/{id}', [EntryController::class, 'show'])->name('posts.show');
    });

    Route::middleware('role:posts:write')->group(function () {
        Route::post('posts', [EntryController::class, 'store'])->name('posts.store');
        Route::patch('posts/{id}', [EntryController::class, 'update'])->name('posts.update');
        Route::delete('posts/{id}', [EntryController::class, 'destroy'])->name('posts.destroy');
    });
});
