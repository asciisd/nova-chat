<?php

use Asciisd\NovaChat\Http\Controllers\ConversationsController;
use Illuminate\Support\Facades\Route;

Route::get('/topics', [ConversationsController::class, 'topics']);
Route::get('/topics/{topic}/conversations', [ConversationsController::class, 'index']);
Route::get('/topics/{topic}/conversations/{id}/messages', [ConversationsController::class, 'messages']);
Route::post('/topics/{topic}/conversations/{id}/messages', [ConversationsController::class, 'store']);
Route::post('/topics/{topic}/conversations/{id}/read', [ConversationsController::class, 'read']);
