<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SecretMessage;
use Illuminate\Support\Carbon;

class SecretMessageController extends Controller
{
    public function create(Request $request)
    {
        $request->validate([
            'message' => 'required|string'
        ]);

        $secret = SecretMessage::create([
            'message' => $request->message,
            'expires_at' => Carbon::now()->addHours(24)
        ]);

        $url = url('/message/' . $secret->uuid);

        return response()->json([
            'status' => 'ok',
            'url' => $url
        ]);
    }

    public function show($uuid)
    {
        $secret = SecretMessage::where('uuid', $uuid)
            ->where('expires_at', '>', now())
            ->first();

        if (!$secret) {
            return response()->json(['error' => 'Message not found or expired'], 404);
        }

        return response()->json([
            'message' => $secret->message
        ]);
    }
}
