<?php

use Asciisd\NovaChat\Http\Controllers\BlockedParticipantsController;
use Asciisd\NovaChat\Http\Controllers\ConversationsController;
use Illuminate\Support\Facades\Route;

Route::get('/topics', [ConversationsController::class, 'topics']);
Route::get('/topics/{topic}/conversations', [ConversationsController::class, 'index']);
Route::get('/topics/{topic}/conversations/{id}/messages', [ConversationsController::class, 'messages']);
Route::post('/topics/{topic}/conversations/{id}/messages', [ConversationsController::class, 'store']);
Route::delete('/topics/{topic}/conversations/{id}/messages/{message}', [ConversationsController::class, 'destroy']);
Route::post('/topics/{topic}/conversations/{id}/read', [ConversationsController::class, 'read']);

Route::get('/blocks', [BlockedParticipantsController::class, 'index']);
Route::post('/blocks', [BlockedParticipantsController::class, 'store']);
Route::delete('/blocks/{type}/{id}', [BlockedParticipantsController::class, 'destroy'])
    ->where('type', '[A-Za-z0-9_\-\\\\]+');
