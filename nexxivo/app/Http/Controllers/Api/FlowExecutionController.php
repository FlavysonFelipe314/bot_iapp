<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FlowExecution;
use Illuminate\Http\Request;

class FlowExecutionController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'flow_id' => 'required|exists:flows,id',
            'contact' => 'required|string',
            'message' => 'nullable|string', // Aceita message ou trigger_message
            'trigger_message' => 'nullable|string',
        ]);

        // Mapear 'message' para 'trigger_message' se necessário
        $data = [
            'flow_id' => $validated['flow_id'],
            'contact' => $validated['contact'],
            'trigger_message' => $validated['trigger_message'] ?? $validated['message'] ?? '',
        ];

        $execution = FlowExecution::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Execução registrada',
            'data' => $execution,
        ]);
    }

    public function index(Request $request)
    {
        $flowId = $request->query('flow_id');

        $query = FlowExecution::with('flow')
            ->orderBy('created_at', 'desc');

        if ($flowId) {
            $query->where('flow_id', $flowId);
        }

        $executions = $query->paginate(50);

        return response()->json($executions);
    }
}

