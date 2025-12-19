<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\FlowManagementController;

Route::get('/', function () {
    return redirect('/chat');
});

Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
Route::get('/chat/{id}', [ChatController::class, 'show'])->name('chat.show');

Route::get('/flows', [FlowManagementController::class, 'index'])->name('flows.index');
Route::get('/flows/create', [FlowManagementController::class, 'create'])->name('flows.create');
Route::get('/flows/{id}/edit', [FlowManagementController::class, 'edit'])->name('flows.edit');

Route::get('/ai-settings', [App\Http\Controllers\AISettingsController::class, 'index'])->name('ai-settings.index');
Route::post('/ai-settings', [App\Http\Controllers\AISettingsController::class, 'store'])->name('ai-settings.store');
