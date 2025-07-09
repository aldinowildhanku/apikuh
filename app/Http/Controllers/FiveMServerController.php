<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log; // Untuk logging error atau debug
use Exception;

class FiveMServerController extends Controller
{
   /**
     * Mengambil dan memfilter data server FiveM berdasarkan nama server.
     *
     * @param string $serverName Nama pendek server (misal: 'indopride', 'nusaindah')
     * @return \Illuminate\Http\JsonResponse
     */
    public function getServerData(string $serverName)
    {
       $fivemServers = config('fivem_servers.servers');
        $baseUrl = config('fivem_servers.base_url');

        if (!isset($fivemServers[$serverName])) {
            return response()->json([
                'status'  => 'failed',
                'message' => 'Server not found or not configured.'
            ], 404);
        }

        $serverId = $fivemServers[$serverName];
        $apiUrl   = $baseUrl . $serverId;

        try {
            // *** PERUBAHAN DI SINI: MENAMBAHKAN BEBERAPA HEADER LAINNYA ***
            $response = Http::withHeaders([
                'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept'          => 'application/json, text/plain, */*', // Sesuaikan dengan browser Anda
                'Accept-Language' => 'en-US,en;q=0.9,id;q=0.8',
                'Connection'      => 'keep-alive',
                'Cache-Control'   => 'no-cache', // Mungkin membantu
                'Pragma'          => 'no-cache', // Mungkin membantu
                'Sec-Fetch-Site'  => 'cross-site', // Penting!
                'Sec-Fetch-Mode'  => 'cors',       // Penting!
                'Sec-Fetch-Dest'  => 'empty',      // Penting!
                'DNT'             => '1',
                // 'Referer'         => 'https://servers.fivem.net/', // Jika ada, tambahkan
                // Tambahkan header lain yang Anda temukan di browser request Anda
            ])->get($apiUrl);

            if ($response->successful()) {
                $data = $response->json();

                if (!isset($data['Data'])) {
                    Log::error('FiveM API response missing "Data" key.', [
                        'server_id' => $serverId,
                        'response'  => $data
                    ]);
                    return response()->json([
                        'status'  => 'failed',
                        'message' => 'Invalid data structure received from FiveM API (missing "Data" key).'
                    ], 500);
                }

                $serverData = $data['Data'];
                $vars       = $serverData['vars'] ?? [];

                $filteredData = [
                    'clients'          => $serverData['clients'] ?? 0,
                    'hostname'         => $serverData['hostname'] ?? 'N/A',
                    'max_clients'      => $serverData['sv_maxclients'] ?? $serverData['svMaxclients'] ?? 0,
                    'uptime'           => $vars['Uptime'] ?? 'N/A',
                    'queue'            => $vars['Queue'] ?? 'N/A',
                    'website'          => $vars['Website'] ?? 'N/A',
                    'discord'          => $vars['Discord'] ?? 'N/A',
                    'banner_connecting' => $vars['banner_connecting'] ?? null,
                    'banner_detail'    => $vars['banner_detail'] ?? null,
                    'players'          => collect($serverData['players'] ?? [])->map(function ($player) {
                        return [
                            'id'        => $player['id'] ?? null,
                            'name'      => $player['name'] ?? 'Unknown Player',
                            'endpoint'  => $player['endpoint'] ?? 'N/A',
                            'ping'      => $player['ping'] ?? 0,
                        ];
                    })->values()->toArray(),
                ];

                return response()->json([
                    'status'  => 'success',
                    'message' => 'Server data retrieved successfully.',
                    'data'    => $filteredData
                ]);

            } else {
                Log::error('Failed to fetch FiveM server data.', [
                    'server_id' => $serverId,
                    'status'    => $response->status(),
                    'response'  => $response->body()
                ]);

                return response()->json([
                    'status'  => 'failed',
                    'message' => 'Failed to retrieve data from FiveM API. Status: ' . $response->status() . '. Check logs for more details.'
                ], $response->status());
            }

        } catch (Exception $e) {
            Log::error('An unexpected error occurred while fetching FiveM server data: ' . $e->getMessage(), [
                'server_id' => $serverId,
                'exception' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'An internal server error occurred.'
            ], 500);
        }
    }
}
