@extends('layouts.app')

@section('title', 'Configurações de IA')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="bg-white rounded-lg shadow">
        <div class="p-6 border-b border-gray-200">
            <h1 class="text-2xl font-bold text-gray-900">Configurações de Inteligência Artificial</h1>
            <p class="mt-1 text-sm text-gray-600">Configure qual agente de IA usar nos fluxos do bot</p>
        </div>

        @if(session('success'))
        <div class="p-4 bg-green-50 border-l-4 border-green-400">
            <p class="text-green-700">{{ session('success') }}</p>
        </div>
        @endif

        <form method="POST" action="{{ route('ai-settings.store') }}" class="p-6 space-y-6">
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Provedor Padrão</label>
                <select name="default_provider" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="ollama" {{ $settings['default_provider'] === 'ollama' ? 'selected' : '' }}>Ollama (Local)</option>
                    <option value="gemini" {{ $settings['default_provider'] === 'gemini' ? 'selected' : '' }}>Google Gemini</option>
                </select>
                <p class="mt-1 text-sm text-gray-500">Este será o provedor padrão usado quando não especificado nos fluxos</p>
            </div>

            <div class="border-t border-gray-200 pt-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Configurações do Ollama</h2>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">URL do Ollama</label>
                        <input type="url" name="ollama_url" value="{{ $settings['ollama_url'] }}" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <p class="mt-1 text-sm text-gray-500">URL onde o Ollama está rodando (padrão: http://localhost:11434)</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Modelo do Ollama</label>
                        <input type="text" name="ollama_model" value="{{ $settings['ollama_model'] }}" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <p class="mt-1 text-sm text-gray-500">Nome do modelo instalado (ex: llama2, mistral, etc.)</p>
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-200 pt-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Configurações do Google Gemini</h2>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">API Key do Gemini</label>
                        <input type="password" name="gemini_api_key" value="{{ $settings['gemini_api_key'] }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <p class="mt-1 text-sm text-gray-500">Obtenha sua chave em: <a href="https://makersuite.google.com/app/apikey" target="_blank" class="text-blue-600 hover:underline">Google AI Studio</a></p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Modelo do Gemini</label>
                        <input type="text" name="gemini_model" value="{{ $settings['gemini_model'] }}" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <p class="mt-1 text-sm text-gray-500">Modelo do Gemini (ex: gemini-pro, gemini-pro-vision)</p>
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-200 pt-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Configurações do ElevenLabs (Áudio)</h2>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">API Key do ElevenLabs</label>
                        <input type="password" name="elevenlabs_api_key" value="{{ $settings['elevenlabs_api_key'] ?? '' }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <p class="mt-1 text-sm text-gray-500">Obtenha sua chave em: <a href="https://elevenlabs.io/app/settings/api-keys" target="_blank" class="text-blue-600 hover:underline">ElevenLabs Dashboard</a></p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Voice ID</label>
                        <input type="text" name="elevenlabs_voice_id" value="{{ $settings['elevenlabs_voice_id'] ?? 'JBFqnCBsd6RMkjVDRZzb' }}" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <p class="mt-1 text-sm text-gray-500">ID da voz a ser usada (padrão: JBFqnCBsd6RMkjVDRZzb)</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Modelo do ElevenLabs</label>
                        <input type="text" name="elevenlabs_model" value="{{ $settings['elevenlabs_model'] ?? 'eleven_multilingual_v2' }}" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <p class="mt-1 text-sm text-gray-500">Modelo de áudio (ex: eleven_multilingual_v2)</p>
                    </div>
                </div>
            </div>

            <div class="flex gap-4 pt-4">
                <button type="submit" class="px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                    Salvar Configurações
                </button>
                <a href="{{ route('flows.index') }}" class="px-6 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                    Voltar
                </a>
            </div>
        </form>
    </div>
</div>
@endsection

