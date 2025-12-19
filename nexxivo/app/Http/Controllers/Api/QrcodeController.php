<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BotInstance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class QrcodeController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'instance_name' => 'required|string',
            'qrcode' => 'required|string',
            'code' => 'nullable|string',
        ]);

        $instance = BotInstance::updateOrCreate(
            ['instance_name' => $validated['instance_name']],
            [
                'qrcode' => $validated['qrcode'],
                'qrcode_generated_at' => now(),
                'status' => 'started',
            ]
        );

        Log::info('QR Code recebido', [
            'instance' => $validated['instance_name'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'QR Code recebido',
            'data' => $instance,
        ]);
    }
}

