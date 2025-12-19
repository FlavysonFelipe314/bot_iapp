<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\BotInstance;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function index()
    {
        $conversations = Conversation::with('latestMessage')
            ->where('is_archived', false)
            ->orderBy('last_message_at', 'desc')
            ->paginate(20);

        $instances = BotInstance::all();

        return view('chat.index', compact('conversations', 'instances'));
    }

    public function show($id)
    {
        $conversation = Conversation::with(['messages' => function ($query) {
            $query->orderBy('created_at', 'asc');
        }])->findOrFail($id);

        $instance = BotInstance::where('instance_name', $conversation->instance_name)->first();

        return view('chat.show', compact('conversation', 'instance'));
    }
}

