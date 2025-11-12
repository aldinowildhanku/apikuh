<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log; 
use Exception;
use Illuminate\Support\Collection; 

class RedMServerController extends Controller
{
    /**
     *
     * @param string 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getServerData(string $serverName)
    {

        $redmServers = config('redm_servers.servers');
        $baseUrl = config('redm_servers.base_url');

        if (!isset($redmServers[$serverName])) {
            return response()->json([
                'status'  => 'failed',
                'message' => 'Server not found or not configured in redm_servers config.'
            ], 404);
        }

        $serverId = $redmServers[$serverName];
        $apiUrl   = $baseUrl . $serverId;

        try {

            $response = Http::withHeaders([
                'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept'          => 'application/json, text/plain, */*', 
                'Accept-Language' => 'en-US,en;q=0.9,id;q=0.8',
                'Connection'      => 'keep-alive',
                'Cache-Control'   => 'no-cache',
                'Pragma'          => 'no-cache', 
                'Sec-Fetch-Site'  => 'cross-site', 
                'Sec-Fetch-Mode'  => 'cors',      
                'Sec-Fetch-Dest'  => 'empty',     
                'DNT'             => '1',

            ])->get($apiUrl);

            if ($response->successful()) {
                $data = $response->json();

                if (!isset($data['Data'])) {
                    Log::error('RedM API response missing "Data" key.', [
                        'server_id' => $serverId,
                        'response'  => $data
                    ]);
                    return response()->json([
                        'status'  => 'failed',
                        'message' => 'Invalid data structure received from RedM API (missing "Data" key).'
                    ], 500);
                }

                $serverData = $data['Data'];
                $vars       = $serverData['vars'] ?? [];

                $uptime = $vars['Uptime'] ?? 'N/A';
                $website = $vars['Website'] ?? 'N/A';

                $filteredData = [
                    'clients'          => $serverData['clients'] ?? 0,
                    'hostname'         => $serverData['hostname'] ?? 'N/A',
                    'max_clients'      => $serverData['sv_maxclients'] ?? $serverData['svMaxclients'] ?? 0, 
                    'uptime'           => $uptime,
                    'queue'            => $vars['Queue'] ?? 'N/A',
                    'website'          => $website,
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
                    'message' => 'RedM server data retrieved successfully.',
                    'data'    => $filteredData
                ]);

            } else {
                Log::error('Failed to fetch RedM server data.', [
                    'server_id' => $serverId,
                    'status'    => $response->status(),
                    'response'  => $response->body()
                ]);

                return response()->json([
                    'status'  => 'failed',
                    'message' => 'Failed to retrieve data from RedM API. Status: ' . $response->status() . '. Check logs for more details.'
                ], $response->status());
            }

        } catch (Exception $e) {
            Log::error('An unexpected error occurred while fetching RedM server data: ' . $e->getMessage(), [
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