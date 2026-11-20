<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CallController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\StatusController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Authentication Routes
|--------------------------------------------------------------------------
*/
Route::middleware('throttle:auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes (Laravel Sanctum)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::middleware('presence')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me']);

        Route::get('/profile', [ProfileController::class, 'show']);
        Route::put('/profile', [ProfileController::class, 'update']);
        Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar']);

        // Phase 10: Blokir Kontak
        Route::get('/users/blocked', [UserController::class, 'blockedUsers']);
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users/{user}', [UserController::class, 'show']);
        Route::post('/users/{user}/block', [UserController::class, 'block']);
        Route::post('/users/{user}/unblock', [UserController::class, 'unblock']);

        // Phase 2: Real-Time Private Chat
        Route::get('/conversations/archived', [ConversationController::class, 'archived']);
        Route::get('/conversations', [ConversationController::class, 'index']);
        Route::post('/conversations', [ConversationController::class, 'store']);
        Route::get('/conversations/{conversation}', [ConversationController::class, 'show']);
        Route::post('/conversations/{conversation}/pin', [ConversationController::class, 'togglePin']);
        Route::post('/conversations/{conversation}/archive', [ConversationController::class, 'toggleArchive']);

        Route::get('/conversations/{conversation}/messages', [MessageController::class, 'index']);
        Route::post('/conversations/{conversation}/messages', [MessageController::class, 'store']);
        Route::delete('/conversations/{conversation}/messages/{message}', [MessageController::class, 'destroy']);
        Route::post('/conversations/{conversation}/read', [MessageController::class, 'markAsRead']);
        Route::post('/conversations/{conversation}/typing', [MessageController::class, 'typing']);

        // Phase 3: Real-Time Group Chat
        Route::post('/groups', [GroupController::class, 'store']);
        Route::put('/groups/{conversation}', [GroupController::class, 'update']);
        Route::post('/groups/{conversation}/avatar', [GroupController::class, 'uploadAvatar']);
        Route::post('/groups/{conversation}/members', [GroupController::class, 'addMembers']);
        Route::delete('/groups/{conversation}/members/{user}', [GroupController::class, 'removeMember']);
        Route::post('/groups/{conversation}/leave', [GroupController::class, 'leave']);

        // Phase 5: Voice Calls & Device Push Tokens
        Route::get('/calls', [CallController::class, 'index']);
        Route::post('/calls', [CallController::class, 'store']);
        Route::post('/calls/{call}/accept', [CallController::class, 'accept']);
        Route::post('/calls/{call}/reject', [CallController::class, 'reject']);
        Route::post('/calls/{call}/end', [CallController::class, 'end']);

        Route::post('/devices/token', [DeviceController::class, 'registerToken']);
        Route::delete('/devices/token', [DeviceController::class, 'removeToken']);

        // Phase 7: Status / Stories (Pembaruan Status 24 Jam)
        Route::get('/statuses', [StatusController::class, 'index']);
        Route::post('/statuses', [StatusController::class, 'store']);
        Route::post('/statuses/{status}/view', [StatusController::class, 'markView']);
        Route::get('/statuses/{status}/viewers', [StatusController::class, 'viewers']);
        Route::delete('/statuses/{status}', [StatusController::class, 'destroy']);

        // Phase 8: Dokumen, Pesan Berbintang & Pencarian Pesan
        Route::get('/conversations/{conversation}/search', [MessageController::class, 'search']);
        Route::post('/messages/{message}/star', [MessageController::class, 'toggleStar']);
        Route::get('/messages/starred', [MessageController::class, 'starred']);

        // Phase 9: Reaksi Emoji Pesan (Message Reactions)
        Route::post('/messages/{message}/reactions', [MessageController::class, 'react']);
        Route::delete('/messages/{message}/reactions', [MessageController::class, 'removeReaction']);

        // Phase 10: Teruskan Pesan (Forward Messages)
        Route::post('/messages/forward', [MessageController::class, 'forward']);
    });
});
