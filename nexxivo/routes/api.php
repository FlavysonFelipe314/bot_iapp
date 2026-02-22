<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\QrcodeController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\FlowController;
use App\Http\Controllers\Api\BotController;
use App\Http\Controllers\Api\FlowExecutionController;
use App\Http\Controllers\Api\AIController;

// Rotas da API para o bot
Route::post('/qrcode', [QrcodeController::class, 'store']);
Route::post('/messages', [MessageController::class, 'store']);
Route::post('/connection-status', [BotController::class, 'status']);
Route::post('/bot-status', [BotController::class, 'status']);
Route::post('/flow-executions', [FlowExecutionController::class, 'store']);

// Rotas da API para o painel
Route::get('/conversations', [ConversationController::class, 'index']);
Route::get('/conversations/{id}', [ConversationController::class, 'show']);
Route::post('/conversations/{id}/archive', [ConversationController::class, 'archive']);
Route::put('/conversations/{id}/block', [ConversationController::class, 'block']);
Route::delete('/conversations/clear-all', [ConversationController::class, 'clearAll']);

// Precisa de sessão web para o polling do chat em tempo real
Route::get('/messages', [MessageController::class, 'index'])->middleware(['web', 'auth']);

Route::get('/flows', [FlowController::class, 'index']);
Route::get('/flows/active', [FlowController::class, 'active']);
Route::post('/flows', [FlowController::class, 'store']);
Route::put('/flows/{id}', [FlowController::class, 'update']);
Route::delete('/flows/{id}', [FlowController::class, 'destroy']);

Route::get('/bot/qrcode/{instanceName}', [BotController::class, 'getQrcode']);
Route::post('/bot/send-message', [BotController::class, 'sendMessage']);

Route::get('/flow-executions', [FlowExecutionController::class, 'index']);

// Rotas de IA
Route::post('/ai/generate', [AIController::class, 'generate']);

// Rotas de ElevenLabs (Áudio)
Route::post('/elevenlabs/text-to-speech', [App\Http\Controllers\Api\ElevenLabsController::class, 'textToSpeech']);
Route::post('/elevenlabs/speech-to-text', [App\Http\Controllers\Api\ElevenLabsController::class, 'speechToText']);
Route::get('/elevenlabs/voices', [App\Http\Controllers\Api\ElevenLabsController::class, 'getVoices']);

// Rotas de Remarketing
Route::post('/remarketing/send', [App\Http\Controllers\Api\RemarketingController::class, 'send']);

// Webhook Evolution API (rota pública). Aceita também /evolution/MESSAGES_UPSERT quando webhookByEvents=true
Route::post('/webhooks/evolution', App\Http\Controllers\Api\EvolutionWebhookController::class)->name('api.webhooks.evolution');
Route::post('/webhooks/evolution/{evolutionEvent}', App\Http\Controllers\Api\EvolutionWebhookController::class)->where('evolutionEvent', '.*');
Route::post('/webhooks/evolution-messages-upsert', App\Http\Controllers\Api\EvolutionWebhookController::class);
Route::post('/webhooks/evolution/messages-upsert', App\Http\Controllers\Api\EvolutionWebhookController::class);
// Simula mensagem recebida (teste): GET /api/webhooks/evolution-simulate?instance=user-1-suporte&text=Oi
Route::get('/webhooks/evolution-simulate', App\Http\Controllers\Api\EvolutionWebhookSimulateController::class)->name('api.webhooks.evolution-simulate');

