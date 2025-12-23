<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ElevenLabsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ElevenLabsController extends Controller
{
    private $elevenLabsService;

    public function __construct(ElevenLabsService $elevenLabsService)
    {
        $this->elevenLabsService = $elevenLabsService;
    }

    /**
     * Gera áudio a partir de texto
     */
    public function textToSpeech(Request $request)
    {
        $validated = $request->validate([
            'text' => 'required|string',
            'voice_id' => 'nullable|string',
            'model' => 'nullable|string',
        ]);

        try {
            $result = $this->elevenLabsService->textToSpeech(
                $validated['text'],
                $validated['voice_id'] ?? null,
                $validated['model'] ?? null
            );

            // Validar se o base64 não está vazio
            if (empty($result['audio'])) {
                throw new \Exception('Áudio base64 vazio após geração');
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'audio' => $result['audio'],
                    'format' => $result['format'], // Formato detectado: ogg_opus, mp3, ou opus_raw
                ],
            ], 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            Log::error('Erro ao gerar áudio com ElevenLabs', [
                'error' => $e->getMessage(),
                'text' => substr($validated['text'], 0, 100),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao gerar áudio: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Converte áudio em texto usando Speech-to-Text
     */
    public function speechToText(Request $request)
    {
        $validated = $request->validate([
            'audio' => 'required|string', // base64
            'model' => 'nullable|string',
            'mimetype' => 'nullable|string',
            'extension' => 'nullable|string',
        ]);

        try {
            $result = $this->elevenLabsService->speechToText(
                $validated['audio'],
                $validated['model'] ?? null,
                $validated['mimetype'] ?? null,
                $validated['extension'] ?? null
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'text' => $result['text'],
                    'language_code' => $result['language_code'] ?? 'pt',
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao converter áudio para texto', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao converter áudio para texto: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Lista todas as vozes disponíveis
     */
    public function getVoices()
    {
        try {
            $voices = $this->elevenLabsService->getVoices();

            return response()->json([
                'success' => true,
                'data' => $voices,
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar vozes do ElevenLabs', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar vozes: ' . $e->getMessage(),
            ], 500);
        }
    }
}

