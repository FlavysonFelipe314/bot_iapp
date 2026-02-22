<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BotInstance;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Simula o recebimento de um MESSAGES_UPSERT para testar o fluxo do Chat.
 * Uso: GET /api/webhooks/evolution-simulate?instance=user-1-suporte&contact=5579999999999&text=Oi
 */
class EvolutionWebhookSimulateController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $instanceName = $request->query('instance', 'user-1-suporte');
        $contact = $request->query('contact', '5579999999999');
        $text = $request->query('text', 'Oi (mensagem de teste)');

        $instance = BotInstance::where('instance_name', $instanceName)->first();
        if (! $instance) {
            return response("Instância {$instanceName} não encontrada.", 404);
        }

        $conversation = Conversation::firstOrCreate(
            [
                'instance_name' => $instanceName,
                'contact' => $contact,
            ],
            [
                'contact_name' => 'Contato teste',
                'last_message_at' => now(),
            ]
        );
        $conversation->update(['last_message_at' => now()]);

        Message::create([
            'conversation_id' => $conversation->id,
            'instance_name' => $instanceName,
            'message_id' => 'sim_'.uniqid(),
            'from' => $contact.'@s.whatsapp.net',
            'to' => null,
            'message' => $text,
            'direction' => 'incoming',
            'raw_data' => [],
            'timestamp' => now(),
        ]);

        return response("OK. Conversa criada. Atualize a página do Chat.", 200);
    }
}
