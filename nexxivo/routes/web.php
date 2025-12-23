<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\FlowManagementController;
use App\Http\Controllers\Auth\LoginController;

// Rotas públicas (login)
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Redirecionar raiz para login se não autenticado
Route::get('/', function () {
    if (auth()->check() && auth()->user()->is_admin) {
        return redirect('/chat');
    }
    return redirect('/login');
});

// Rotas protegidas (apenas admin)
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
    Route::get('/chat/{id}', [ChatController::class, 'show'])->name('chat.show');

    Route::get('/flows', [FlowManagementController::class, 'index'])->name('flows.index');
    Route::get('/flows/create', [FlowManagementController::class, 'create'])->name('flows.create');
    Route::get('/flows/{id}/edit', [FlowManagementController::class, 'edit'])->name('flows.edit');

    Route::get('/ai-settings', [App\Http\Controllers\AISettingsController::class, 'index'])->name('ai-settings.index');
    Route::post('/ai-settings', [App\Http\Controllers\AISettingsController::class, 'store'])->name('ai-settings.store');

    Route::get('/crm', [App\Http\Controllers\CrmController::class, 'index'])->name('crm.index');
    Route::post('/crm/conversations/{id}/status', [App\Http\Controllers\CrmController::class, 'updateStatus'])->name('crm.update-status');
});
