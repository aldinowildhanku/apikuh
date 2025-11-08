<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SecretMessage;
use Illuminate\Support\Carbon;

class DeleteExpiredMessages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'messages:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete expired secret messages';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Hapus pesan yang sudah kedaluwarsa (expires_at sudah lewat)
        $deleted = SecretMessage::where('expires_at', '<', Carbon::now())
            // Jika Anda menggunakan Opsi B (opened_at), Anda bisa menambahkan:
            // ->orWhereNotNull('opened_at') // Hapus juga yang sudah dibuka (jika dibuka tapi belum dihapus)
            ->delete();

        $this->info("Deleted $deleted expired messages.");
    }
}
