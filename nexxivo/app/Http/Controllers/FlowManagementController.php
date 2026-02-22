<?php

namespace App\Http\Controllers;

use App\Models\Flow;
use App\Models\AISetting;
use Illuminate\Http\Request;

class FlowManagementController extends Controller
{
    public function index()
    {
        $instanceNames = \App\Models\BotInstance::where('user_id', auth()->id())->pluck('instance_name');
        $flows = Flow::whereNull('instance_name')->orWhereIn('instance_name', $instanceNames)
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('flows.index', compact('flows'));
    }

    public function create()
    {
        $defaultProvider = AISetting::get('default_provider', 'ollama');
        $instances = \App\Models\BotInstance::where('user_id', auth()->id())->orderBy('instance_name')->get();
        return view('flows.create', compact('defaultProvider', 'instances'));
    }

    public function edit($id)
    {
        $instanceNames = \App\Models\BotInstance::where('user_id', auth()->id())->pluck('instance_name');
        $flow = Flow::whereNull('instance_name')->orWhereIn('instance_name', $instanceNames)->findOrFail($id);
        $defaultProvider = AISetting::get('default_provider', 'ollama');
        $instances = \App\Models\BotInstance::where('user_id', auth()->id())->orderBy('instance_name')->get();
        return view('flows.edit', compact('flow', 'defaultProvider', 'instances'));
    }
}

