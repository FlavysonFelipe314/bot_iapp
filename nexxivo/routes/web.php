<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\FlowManagementController;
use App\Http\Controllers\Auth\LoginController;

// Rotas públicas (login)
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

Route::get('/', function () {
    if (auth()->check()) {
        return redirect('/chat');
    }
    return redirect('/login');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/instances', [App\Http\Controllers\InstanceController::class, 'index'])->name('instances.index');
    Route::post('/instances', [App\Http\Controllers\InstanceController::class, 'store'])->name('instances.store');
    Route::get('/instances/{instance}/refresh-qr', [App\Http\Controllers\InstanceController::class, 'refreshQr'])->name('instances.refresh-qr');
    Route::get('/instances/{instance}/evolution-connect-raw', [App\Http\Controllers\InstanceController::class, 'evolutionConnectRaw'])->name('instances.evolution-connect-raw');
    Route::post('/instances/{instance}/save-qr', [App\Http\Controllers\InstanceController::class, 'saveQrFromFrontend'])->name('instances.save-qr');
    Route::delete('/instances/{instance}', [App\Http\Controllers\InstanceController::class, 'destroy'])->name('instances.destroy');

    Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
    Route::get('/chat/{id}', [ChatController::class, 'show'])->name('chat.show');

    Route::get('/flows', [FlowManagementController::class, 'index'])->name('flows.index');
    Route::get('/flows/create', [FlowManagementController::class, 'create'])->name('flows.create');
    Route::get('/flows/{id}/edit', [FlowManagementController::class, 'edit'])->name('flows.edit');

    Route::middleware(['admin'])->group(function () {
        Route::get('/ai-settings', [App\Http\Controllers\AISettingsController::class, 'index'])->name('ai-settings.index');
        Route::post('/ai-settings', [App\Http\Controllers\AISettingsController::class, 'store'])->name('ai-settings.store');
    });

    Route::get('/crm', [App\Http\Controllers\CrmController::class, 'index'])->name('crm.index');
    Route::post('/crm/conversations/{id}/status', [App\Http\Controllers\CrmController::class, 'updateStatus'])->name('crm.update-status');
});
