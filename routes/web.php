<?php

use App\Http\Controllers\Web\WebAuthController;
use App\Http\Controllers\Web\WebChatController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [WebAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [WebAuthController::class, 'login'])->name('web.login.submit');
});

Route::get('/quick-login/{user}', [WebAuthController::class, 'quickLogin'])->name('web.quick-login');
Route::post('/logout', [WebAuthController::class, 'logout'])->name('web.logout');

Route::middleware('auth:web')->group(function (): void {
    Route::get('/', [WebChatController::class, 'index'])->name('web.chat');
    Route::get('/chat', [WebChatController::class, 'index']);
});

Route::get('/storage/{path}', function (string $path) {
    $fullPath = storage_path('app/public/' . $path);
    if (!file_exists($fullPath)) {
        abort(404);
    }
    return response()->file($fullPath, [
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
    ]);
})->where('path', '.*');
