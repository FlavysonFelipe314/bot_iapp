<?php

namespace App\Http\Controllers;

use App\Models\Flow;
use App\Models\AISetting;
use Illuminate\Http\Request;

class FlowManagementController extends Controller
{
    public function index()
    {
        $flows = Flow::orderBy('priority', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('flows.index', compact('flows'));
    }

    public function create()
    {
        $defaultProvider = AISetting::get('default_provider', 'ollama');
        return view('flows.create', compact('defaultProvider'));
    }

    public function edit($id)
    {
        $flow = Flow::findOrFail($id);
        $defaultProvider = AISetting::get('default_provider', 'ollama');
        return view('flows.edit', compact('flow', 'defaultProvider'));
    }
}

