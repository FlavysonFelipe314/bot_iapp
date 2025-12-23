<?php

namespace App\Services;

use App\Models\AISetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ElevenLabsService
{
    private $apiKey;
    private $voiceId;
    private $model;

    public function __construct()
    {
        $this->apiKey = AISetting::get('elevenlabs_api_key', env('ELEVENLABS_API_KEY', ''));
        $this->voiceId = AISetting::get('elevenlabs_voice_id', env('ELEVENLABS_VOICE_ID', 'JBFqnCBsd6RMkjVDRZzb'));
        $this->model = AISetting::get('elevenlabs_model', env('ELEVENLABS_MODEL', 'eleven_multilingual_v2'));
    }

    /**
     * Gera áudio a partir de texto usando ElevenLabs
     * @return array ['audio' => base64, 'format' => 'ogg_opus'|'mp3'|'opus_raw']
     */
    public function textToSpeech(string $text, ?string $voiceId = null, ?string $model = null): array
    {
        if (empty($this->apiKey)) {
            throw new \Exception("API Key do ElevenLabs não configurada");
        }

        $voiceId = $voiceId ?? $this->voiceId;
        $model = $model ?? $this->model;

        $response = Http::withHeaders([
            'xi-api-key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(60)->post(
            "https://api.elevenlabs.io/v1/text-to-speech/{$voiceId}",
            [
                'text' => $text,
                'model_id' => $model,
                'output_format' => 'opus_48000_128', // Formato Opus 48kHz 128kbps para compatibilidade com WhatsApp
            ]
        );

        if (!$response->successful()) {
            throw new \Exception("Erro ao gerar áudio com ElevenLabs: " . $response->body());
        }

        // Obter o corpo da resposta como binário
        $audioData = $response->body();
        
        // Validar se o áudio não está vazio
        if (empty($audioData) || strlen($audioData) === 0) {
            throw new \Exception("Áudio retornado pelo ElevenLabs está vazio");
        }

        // Validar formato do áudio (verificar magic bytes)
        // OGG Opus começa com "OggS" (4F 67 67 53)
        $oggMagicBytes = "\x4F\x67\x67\x53";
        $firstBytes = substr($audioData, 0, 4);
        $firstBytesHex = bin2hex($firstBytes);
        
        // Detectar formato do áudio
        $isOggOpus = (strlen($audioData) >= 4 && $firstBytes === $oggMagicBytes);
        $isMp3 = ($firstBytesHex === '49443304' || substr($firstBytesHex, 0, 4) === 'fffb' || substr($firstBytesHex, 0, 4) === 'fff3');
        
        if ($isOggOpus) {
            Log::info('Áudio OGG Opus válido detectado', [
                'size' => strlen($audioData),
            ]);
        } elseif ($isMp3) {
            // A API retornou MP3 mesmo solicitando Opus
            // Converter MP3 para OGG Opus usando ffmpeg
            Log::warning('ElevenLabs retornou MP3 em vez de Opus. Convertendo para OGG Opus...', [
                'size' => strlen($audioData),
                'first_bytes' => $firstBytesHex,
                'requested_format' => 'opus_48000_128',
            ]);
            
            try {
                $audioData = $this->convertMp3ToOggOpus($audioData);
                // Re-verificar magic bytes após conversão
                $firstBytesAfterConversion = substr($audioData, 0, 4);
                if ($firstBytesAfterConversion === $oggMagicBytes) {
                    $isOggOpus = true;
                    $isMp3 = false;
                    Log::info('MP3 convertido para OGG Opus com sucesso', [
                        'new_size' => strlen($audioData),
                    ]);
                } else {
                    Log::warning('Conversão realizada mas formato não parece ser OGG Opus válido', [
                        'first_bytes' => bin2hex($firstBytesAfterConversion),
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Erro ao converter MP3 para OGG Opus', [
                    'error' => $e->getMessage(),
                ]);
                // Continuar com MP3 se a conversão falhar
                Log::warning('Continuando com MP3 devido ao erro na conversão');
            }
        } else {
            // Formato desconhecido - pode ser Opus puro ou outro formato
            Log::info('Áudio retornado pode ser Opus sem container OGG ou formato desconhecido', [
                'size' => strlen($audioData),
                'first_bytes' => $firstBytesHex,
            ]);
        }

        // Retornar áudio em base64 (sem quebras de linha)
        $base64 = base64_encode($audioData);
        
        // Validar se a codificação base64 foi bem-sucedida
        if (empty($base64)) {
            throw new \Exception("Erro ao codificar áudio em base64");
        }

        // Determinar formato detectado para retornar junto
        $detectedFormat = 'unknown';
        if ($isOggOpus) {
            $detectedFormat = 'ogg_opus';
        } elseif ($isMp3) {
            // Se ainda é MP3 (conversão falhou ou não foi tentada), marcar como mp3
            $detectedFormat = 'mp3';
        } else {
            $detectedFormat = 'opus_raw'; // Opus sem container ou formato desconhecido
        }

        Log::info('Áudio gerado com sucesso', [
            'size_bytes' => strlen($audioData),
            'base64_length' => strlen($base64),
            'detected_format' => $detectedFormat,
        ]);

        return [
            'audio' => $base64,
            'format' => $detectedFormat,
        ];
    }

    /**
     * Converte áudio em texto usando ElevenLabs Speech-to-Text
     * @param string $audioBase64 Áudio em base64
     * @param string|null $model Modelo a usar (padrão: scribe_v1)
     * @param string|null $mimetype Mimetype do áudio
     * @param string|null $extension Extensão do arquivo (ogg, mp3, wav)
     * @return array Array com 'text' e 'words' (opcional)
     */
    public function speechToText(string $audioBase64, ?string $model = null, ?string $mimetype = null, ?string $extension = null): array
    {
        if (empty($this->apiKey)) {
            throw new \Exception("API Key do ElevenLabs não configurada");
        }

        $model = $model ?? 'scribe_v1';
        
        // Determinar extensão do arquivo
        $ext = $extension ?? 'ogg';
        if ($mimetype) {
            if (strpos($mimetype, 'mpeg') !== false || strpos($mimetype, 'mp3') !== false) {
                $ext = 'mp3';
            } elseif (strpos($mimetype, 'wav') !== false) {
                $ext = 'wav';
            } elseif (strpos($mimetype, 'ogg') !== false) {
                $ext = 'ogg';
            }
        }

        // Decodificar base64 para binário
        $audioData = base64_decode($audioBase64, true);
        
        if ($audioData === false) {
            throw new \Exception("Erro ao decodificar áudio base64");
        }

        // Criar arquivo temporário com extensão correta
        $tempPath = tempnam(sys_get_temp_dir(), 'elevenlabs_audio_') . '.' . $ext;
        file_put_contents($tempPath, $audioData);
        
        Log::info('Arquivo temporário criado para transcrição', [
            'path' => $tempPath,
            'size' => strlen($audioData),
            'extension' => $ext,
        ]);

        try {
            // Ler conteúdo do arquivo
            $fileContents = file_get_contents($tempPath);
            
            // Upload do arquivo para ElevenLabs usando multipart/form-data
            // A API espera o campo 'file' (não 'audio_file')
            // Criar array multipart completo
            $multipart = [
                [
                    'name' => 'file',
                    'contents' => $fileContents,
                    'filename' => 'audio.' . $ext,
                ],
                [
                    'name' => 'model_id',
                    'contents' => $model,
                ],
                [
                    'name' => 'language_code',
                    'contents' => 'pt', // Português
                ],
            ];
            
            $response = Http::withHeaders([
                'xi-api-key' => $this->apiKey,
            ])->timeout(120)->asMultipart()->post(
                'https://api.elevenlabs.io/v1/speech-to-text',
                $multipart
            );

            if (!$response->successful()) {
                Log::error('Erro ao converter áudio para texto', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \Exception("Erro ao converter áudio para texto: " . $response->body());
            }

            $result = $response->json();

            return [
                'text' => $result['text'] ?? '',
                'language_code' => $result['language_code'] ?? 'pt',
                'words' => $result['words'] ?? [],
            ];
        } finally {
            // Limpar arquivo temporário
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    /**
     * Converte MP3 para OGG Opus usando ffmpeg
     * @param string $mp3Data Dados binários do MP3
     * @return string Dados binários do OGG Opus
     * @throws \Exception Se a conversão falhar
     */
    private function convertMp3ToOggOpus(string $mp3Data): string
    {
        // Verificar se ffmpeg está disponível
        $ffmpegPath = $this->findFfmpegPath();
        if (!$ffmpegPath) {
            throw new \Exception('ffmpeg não encontrado no sistema. Instale ffmpeg para converter MP3 para OGG Opus.');
        }

        // Criar arquivo temporário para MP3 de entrada
        $inputPath = tempnam(sys_get_temp_dir(), 'elevenlabs_mp3_') . '.mp3';
        $outputPath = tempnam(sys_get_temp_dir(), 'elevenlabs_ogg_') . '.ogg';

        try {
            // Salvar MP3 em arquivo temporário
            file_put_contents($inputPath, $mp3Data);

            // Comando ffmpeg para converter MP3 para OGG Opus
            // -i: arquivo de entrada
            // -c:a libopus: codec de áudio Opus
            // -b:a 128k: bitrate de 128kbps
            // -ar 48000: sample rate de 48kHz
            // -y: sobrescrever arquivo de saída se existir
            $command = escapeshellarg($ffmpegPath) . 
                       ' -i ' . escapeshellarg($inputPath) . 
                       ' -c:a libopus' . 
                       ' -b:a 128k' . 
                       ' -ar 48000' . 
                       ' -y' . 
                       ' ' . escapeshellarg($outputPath) . 
                       ' 2>&1';

            Log::info('Convertendo MP3 para OGG Opus', [
                'command' => $command,
            ]);

            // Executar conversão
            $output = [];
            $returnCode = 0;
            exec($command, $output, $returnCode);

            if ($returnCode !== 0 || !file_exists($outputPath)) {
                $errorOutput = implode("\n", $output);
                Log::error('Erro ao converter MP3 para OGG Opus', [
                    'return_code' => $returnCode,
                    'output' => $errorOutput,
                ]);
                throw new \Exception("Erro ao converter MP3 para OGG Opus: " . $errorOutput);
            }

            // Ler arquivo OGG Opus convertido
            $oggData = file_get_contents($outputPath);
            
            if (empty($oggData)) {
                throw new \Exception("Arquivo OGG Opus convertido está vazio");
            }

            // Validar que é realmente OGG Opus
            $oggMagicBytes = "\x4F\x67\x67\x53";
            if (strlen($oggData) < 4 || substr($oggData, 0, 4) !== $oggMagicBytes) {
                Log::warning('Arquivo convertido não parece ser OGG Opus válido', [
                    'first_bytes' => bin2hex(substr($oggData, 0, 4)),
                ]);
                // Continuar mesmo assim, pode funcionar
            }

            Log::info('MP3 convertido para OGG Opus com sucesso', [
                'input_size' => strlen($mp3Data),
                'output_size' => strlen($oggData),
            ]);

            return $oggData;
        } finally {
            // Limpar arquivos temporários
            if (file_exists($inputPath)) {
                @unlink($inputPath);
            }
            if (file_exists($outputPath)) {
                @unlink($outputPath);
            }
        }
    }

    /**
     * Encontra o caminho do executável ffmpeg
     * @return string|null Caminho do ffmpeg ou null se não encontrado
     */
    private function findFfmpegPath(): ?string
    {
        // Possíveis caminhos do ffmpeg
        $possiblePaths = [
            'ffmpeg', // No PATH
            '/usr/bin/ffmpeg',
            '/usr/local/bin/ffmpeg',
            'C:\\ffmpeg\\bin\\ffmpeg.exe', // Windows comum
            'C:\\Program Files\\ffmpeg\\bin\\ffmpeg.exe',
            'C:\\Program Files (x86)\\ffmpeg\\bin\\ffmpeg.exe',
        ];

        // Verificar se está no PATH primeiro
        $output = [];
        $returnCode = 0;
        exec('ffmpeg -version 2>&1', $output, $returnCode);
        if ($returnCode === 0) {
            return 'ffmpeg';
        }

        // Tentar caminhos específicos
        foreach ($possiblePaths as $path) {
            if (file_exists($path) && is_executable($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Busca todas as vozes disponíveis na conta do ElevenLabs
     * @return array Array com informações das vozes
     */
    public function getVoices(): array
    {
        if (empty($this->apiKey)) {
            throw new \Exception("API Key do ElevenLabs não configurada");
        }

        $response = Http::withHeaders([
            'xi-api-key' => $this->apiKey,
        ])->timeout(30)->get('https://api.elevenlabs.io/v1/voices');

        if (!$response->successful()) {
            throw new \Exception("Erro ao buscar vozes do ElevenLabs: " . $response->body());
        }

        $data = $response->json();
        
        return $data['voices'] ?? [];
    }
}

