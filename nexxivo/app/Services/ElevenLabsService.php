<?php

namespace App\Services;

use App\Models\AISetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Serviço de conversão de texto em áudio (TTS) via API ElevenLabs.
 * Usado quando o fluxo está configurado para enviar resposta em áudio (ex.: voz da Laura).
 */
class ElevenLabsService
{
    private string $apiKey;
    private string $voiceId;
    private string $modelId;

    public function __construct()
    {
        $this->apiKey = (string) AISetting::get('elevenlabs_api_key', config('services.elevenlabs.api_key', env('ELEVENLABS_API_KEY', '')));
        $this->voiceId = (string) AISetting::get('elevenlabs_voice_id', config('services.elevenlabs.voice_id', env('ELEVENLABS_VOICE_ID', '21m00Tcm4TlvDq8ikWAM')));
        $this->modelId = (string) AISetting::get('elevenlabs_model_id', config('services.elevenlabs.model_id', env('ELEVENLABS_MODEL_ID', 'eleven_multilingual_v2')));
    }

    /**
     * Converte texto em áudio (base64). Retorna string base64 do áudio (MP3) ou vazio se falhar.
     */
    public function textToSpeech(string $text, ?string $voiceId = null): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $voiceId = $voiceId ?? $this->voiceId;
        if ($this->apiKey === '') {
            Log::warning('ElevenLabs: API key não configurada.');
            return '';
        }

        $url = "https://api.elevenlabs.io/v1/text-to-speech/" . $voiceId;

        $response = Http::timeout(30)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'audio/mpeg',
                'xi-api-key' => $this->apiKey,
            ])
            ->withBody(json_encode([
                'text' => $text,
                'model_id' => $this->modelId,
                'voice_settings' => [
                    'stability' => 0.5,
                    'similarity_boost' => 0.75,
                ],
            ]), 'application/json')
            ->post($url);

        if (! $response->successful()) {
            Log::warning('ElevenLabs TTS falhou', [
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 300),
            ]);
            return '';
        }

        $binary = $response->body();
        if ($binary === '') {
            return '';
        }

        return base64_encode($binary);
    }
}
