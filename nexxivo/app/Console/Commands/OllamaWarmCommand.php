<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class OllamaWarmCommand extends Command
{
    protected $signature = 'ollama:warm';
    protected $description = 'Mantém o modelo Ollama carregado para respostas rápidas no fluxo com IA';

    public function handle(): int
    {
        $url = config('services.ai.ollama_url', env('OLLAMA_URL', 'http://127.0.0.1:11434'));
        $url = str_replace('http://localhost', 'http://127.0.0.1', $url);
        $model = config('services.ai.ollama_model', env('OLLAMA_MODEL', 'llama2'));

        try {
            $r = Http::timeout(90)->post(rtrim($url, '/') . '/api/generate', [
                'model' => $model,
                'prompt' => '.',
                'stream' => false,
            ]);
            if ($r->successful()) {
                $this->info('Ollama aquecido.');
                return 0;
            }
            $this->warn('Ollama respondeu com status: ' . $r->status());
            return 1;
        } catch (\Throwable $e) {
            $this->warn('Ollama warm falhou: ' . $e->getMessage());
            return 1;
        }
    }
}
