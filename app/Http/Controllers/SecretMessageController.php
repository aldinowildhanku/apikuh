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
            'message' => 'required|string',
            // Opsional: tambahkan validasi untuk expiry jika masih ingin digunakan
        ]);

        $secret = SecretMessage::create([
            'message' => $request->message,
            // expires_at tetap dipertahankan sebagai fallback security
            'expires_at' => Carbon::now()->addHours(24) 
        ]);

        // Catatan: Anda perlu mengganti url() dengan route yang benar 
        // yang mengarah ke endpoint API /show yang baru ini.
        $url = url('/message/' . $secret->uuid); 

        return response()->json([
            'status' => 'ok',
            'url' => $url
        ]);
    }

    /**
     * Menampilkan pesan dan menghapusnya dari database segera setelah diakses.
     */
    public function show($uuid)
    {
        // 1. Cari pesan berdasarkan UUID dan pastikan belum expired
        // Kita tidak lagi memfilter berdasarkan opened_at, karena kita akan menghapusnya
        $secret = SecretMessage::where('uuid', $uuid)
            ->where('expires_at', '>', now()) // Filter expired messages
            ->first();

        if (!$secret) {
            // Jika tidak ditemukan atau sudah expired
            return response()->json(['error' => 'Pesan tidak ditemukan, sudah dibuka, atau sudah kedaluwarsa.'], 404);
        }
        
        // Simpan pesan untuk respons, lalu HAPUS
        $messageContent = $secret->message;
        
        // 2. Hapus pesan setelah diakses
        $secret->delete();

        // 3. Kembalikan konten pesan
        return response()->json([
            'message' => $messageContent
        ]);
    }
}