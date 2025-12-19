<?php

namespace App\Http\Controllers;

use App\Models\AISetting;
use Illuminate\Http\Request;

class AISettingsController extends Controller
{
    public function index()
    {
        $settings = [
            'default_provider' => AISetting::get('default_provider', 'ollama'),
            'ollama_url' => AISetting::get('ollama_url', 'http://localhost:11434'),
            'ollama_model' => AISetting::get('ollama_model', 'llama2'),
            'gemini_api_key' => AISetting::get('gemini_api_key', ''),
            'gemini_model' => AISetting::get('gemini_model', 'gemini-pro'),
            'elevenlabs_api_key' => AISetting::get('elevenlabs_api_key', ''),
            'elevenlabs_voice_id' => AISetting::get('elevenlabs_voice_id', 'JBFqnCBsd6RMkjVDRZzb'),
            'elevenlabs_model' => AISetting::get('elevenlabs_model', 'eleven_multilingual_v2'),
        ];

        return view('ai-settings.index', compact('settings'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'default_provider' => 'required|string|in:ollama,gemini',
            'ollama_url' => 'required|string|url',
            'ollama_model' => 'required|string',
            'gemini_api_key' => 'nullable|string',
            'gemini_model' => 'required|string',
            'elevenlabs_api_key' => 'nullable|string',
            'elevenlabs_voice_id' => 'required|string',
            'elevenlabs_model' => 'required|string',
        ]);

        foreach ($validated as $key => $value) {
            if ($key === 'elevenlabs_api_key' && empty($value)) {
                // Não atualizar se estiver vazio (manter valor existente)
                continue;
            }
            AISetting::set($key, $value);
        }

        return redirect()->route('ai-settings.index')
            ->with('success', 'Configurações de IA salvas com sucesso!');
    }
}
