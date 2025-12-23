<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Conversation;
use App\Models\Message;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class UpdateKanbanStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kanban:update-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Atualiza status do Kanban: move conversas com mais de 10 minutos sem resposta para "aguardando"';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Atualizando status do Kanban...');

        // Buscar conversas que estão em "em_atendimento" e não têm mensagem há mais de 10 minutos
        $tenMinutesAgo = Carbon::now()->subMinutes(10);
        
        $conversations = Conversation::where('kanban_status', 'em_atendimento')
            ->where('is_archived', false)
            ->where('last_message_at', '<=', $tenMinutesAgo)
            ->get();

        $movedCount = 0;

        foreach ($conversations as $conversation) {
            // Verificar se a última mensagem foi do bot (outgoing)
            $lastMessage = Message::where('conversation_id', $conversation->id)
                ->orderBy('created_at', 'desc')
                ->first();

            // Se a última mensagem foi do bot e já passou 10 minutos, mover para aguardando
            if ($lastMessage && $lastMessage->direction === 'outgoing') {
                $timeSinceLastMessage = Carbon::parse($lastMessage->created_at)->diffInMinutes(Carbon::now());
                
                if ($timeSinceLastMessage >= 10) {
                    $conversation->update(['kanban_status' => 'aguardando']);
                    $movedCount++;
                    
                    Log::info('Conversa movida para aguardando (10+ minutos sem resposta)', [
                        'conversation_id' => $conversation->id,
                        'contact' => $conversation->contact,
                        'minutes_since_last_message' => $timeSinceLastMessage,
                    ]);
                }
            }
        }

        $this->info("✅ {$movedCount} conversa(s) movida(s) para 'aguardando'");

        return Command::SUCCESS;
    }
}
