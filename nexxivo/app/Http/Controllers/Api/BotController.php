<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BotInstance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BotController extends Controller
{
    public function status(Request $request)
    {
        $validated = $request->validate([
            'instance_name' => 'required|string',
            'status' => 'required|string|in:started,stopped,connected,disconnected,auth_failure',
            'phone' => 'nullable|string',
            'name' => 'nullable|string',
        ]);

        // Buscar instância existente
        $instance = BotInstance::firstOrNew(['instance_name' => $validated['instance_name']]);
        
        // Não permitir downgrade de status (connected não pode voltar para started)
        $statusHierarchy = ['stopped' => 0, 'started' => 1, 'connected' => 2];
        $currentStatus = $instance->status ?? 'stopped';
        $currentStatusLevel = isset($statusHierarchy[$currentStatus]) 
            ? $statusHierarchy[$currentStatus] 
            : 0;
        $newStatus = $validated['status'] ?? 'stopped';
        $newStatusLevel = isset($statusHierarchy[$newStatus]) 
            ? $statusHierarchy[$newStatus] 
            : 0;
        
        // Só atualizar se o novo status for maior ou igual
        if ($newStatusLevel >= $currentStatusLevel) {
            $instance->status = $validated['status'];
            
            // Se for conexão bem-sucedida, limpar QR code
            if ($validated['status'] === 'connected') {
                $instance->qrcode = null;
                $instance->qrcode_generated_at = null;
            }
        }
        
        $instance->save();

        Log::info('Status do bot atualizado', [
            'instance' => $validated['instance_name'],
            'status' => $validated['status'],
            'phone' => $validated['phone'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data' => $instance,
        ]);
    }

    public function getQrcode($instanceName)
    {
        $instance = BotInstance::where('instance_name', $instanceName)->first();

        if (!$instance) {
            return response()->json([
                'success' => true,
                'data' => [
                    'qrcode' => null,
                    'status' => 'disconnected',
                    'generated_at' => null,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'qrcode' => $instance->qrcode,
                'status' => $instance->status ?? 'disconnected',
                'generated_at' => $instance->qrcode_generated_at,
            ],
        ]);
    }

    public function sendMessage(Request $request)
    {
        $validated = $request->validate([
            'instance_name' => 'required|string',
            'contact' => 'required|string',
            'message' => 'required|string',
        ]);

        $botUrl = config('services.bot.url', env('BOT_URL', 'http://localhost:3001'));

        try {
            $response = Http::timeout(30)->post("{$botUrl}/send-message", [
                'contact' => $validated['contact'],
                'message' => $validated['message'],
            ]);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Mensagem enviada com sucesso',
                    'data' => $response->json(),
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Erro ao enviar mensagem',
                'error' => $response->json(),
            ], $response->status());
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao enviar mensagem. Verifique se o bot está rodando.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

