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

Route::get('/messages', [MessageController::class, 'index']);

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

