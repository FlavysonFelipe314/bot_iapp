<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearConversations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'conversations:clear 
                            {--instance= : Limpar apenas contextos de uma instância específica}
                            {--force : Confirmar sem perguntar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Limpa todos os contextos de conversa (conversas e mensagens)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $instanceName = $this->option('instance');
        $force = $this->option('force');

        if (!$force) {
            $message = $instanceName 
                ? "Tem certeza que deseja limpar TODOS os contextos da instância '{$instanceName}'? Isso não pode ser desfeito!"
                : "Tem certeza que deseja limpar TODOS os contextos? Isso não pode ser desfeito!";
            
            if (!$this->confirm($message)) {
                $this->info('Operação cancelada.');
                return 0;
            }
        }

        try {
            DB::beginTransaction();

            if ($instanceName) {
                $this->info("Limpando contextos da instância: {$instanceName}");
                
                // Deletar mensagens de conversas específicas da instância
                $conversationIds = Conversation::where('instance_name', $instanceName)
                    ->pluck('id');
                
                $deletedMessages = Message::whereIn('conversation_id', $conversationIds)->delete();
                $this->info("  - {$deletedMessages} mensagens deletadas");
                
                // Deletar conversas da instância
                $deletedConversations = Conversation::where('instance_name', $instanceName)->delete();
                $this->info("  - {$deletedConversations} conversas deletadas");
            } else {
                $this->info('Limpando TODOS os contextos...');
                
                // Deletar todas as mensagens
                $deletedMessages = Message::count();
                Message::truncate();
                $this->info("  - {$deletedMessages} mensagens deletadas");
                
                // Deletar todas as conversas
                $deletedConversations = Conversation::count();
                Conversation::truncate();
                $this->info("  - {$deletedConversations} conversas deletadas");
            }

            DB::commit();

            $this->info('✅ Contextos limpos com sucesso!');
            return 0;
        } catch (\Exception $e) {
            DB::rollBack();
            
            $this->error('❌ Erro ao limpar contextos: ' . $e->getMessage());
            return 1;
        }
    }
}
