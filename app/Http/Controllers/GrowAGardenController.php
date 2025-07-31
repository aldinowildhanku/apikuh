<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http; // Untuk melakukan request HTTP
use Illuminate\Support\Facades\Log;   // Untuk logging
use Illuminate\Support\Facades\Cache; // Untuk caching
use Exception;
use Carbon\Carbon;

class GrowAGardenController extends Controller
{
    // URL dasar API eksternal Grow a Garden
    protected $externalApiBaseUrl = 'https://growagarden.gg/api/';
    protected $timezone = 'America/New_York'; // Timezone yang digunakan di JS

    /**
     * Mengambil dan memformat data stok dari growagarden.gg.
     * Data akan di-cache untuk menghindari terlalu banyak request ke API eksternal.
     *
     * @return \Illuminate\Http\JsonResponse
     */

     public function getLiveStockData()
    {
        $cacheKey = 'grow_a_garden_stock_data';
        $cacheDurationInMinutes = 5; // Cache selama 5 menit

        try {
            $formattedData = Cache::remember($cacheKey, Carbon::now()->addMinutes($cacheDurationInMinutes), function () {
                $response = Http::withHeaders([
                    'accept'        => '*/*',
                    'accept-language' => 'en-US,en;q=0.9',
                    'content-type'  => 'application/json',
                    'priority'      => 'u=1, i',
                    'referer'       => 'https://growagarden.gg/stocks',
                    'trpc-accept'   => 'application/json',
                    'x-trpc-source' => 'gag',
                    'User-Agent'    => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                ])->get($this->externalApiBaseUrl . 'stock');

                if ($response->successful()) {
                    $rawData = $response->json();
                    return $this->formatStocks($rawData);
                } else {
                    Log::error('Failed to fetch Grow a Garden stock data from external API.', [
                        'status'   => $response->status(),
                        'response' => $response->body(),
                    ]);
                    throw new Exception('Failed to retrieve data from Grow a Garden stock API. Status: ' . $response->status());
                }
            });

            return response()->json([
                'status'  => 'success',
                'message' => 'Grow a Garden live stock data retrieved successfully.',
                'data'    => $formattedData
            ], 200);

        } catch (Exception $e) {
            Log::error('An error occurred while fetching Grow a Garden stock data: ' . $e->getMessage(), [
                'exception' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'status'  => 'error',
                'message' => 'An internal server error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mengambil dan memformat data statistik cuaca dari growagarden.gg.
     * Data akan di-cache untuk menghindari terlalu banyak request ke API eksternal.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getWeatherStats()
    {
        $cacheKey = 'grow_a_garden_weather_stats';
        $cacheDurationInMinutes = 5; // Cache selama 5 menit

        try {
            $weatherStats = Cache::remember($cacheKey, Carbon::now()->addMinutes($cacheDurationInMinutes), function () {
                $response = Http::withHeaders([
                    'accept'         => '*/*',
                    'accept-language' => 'en-US,en;q=0.9',
                    'priority'       => 'u=1, i',
                    'referer'        => 'https://growagarden.gg/weather',
                    'user-agent'     => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36 OPR/119.0.0.0',
                    'sec-fetch-dest' => 'empty',
                    'sec-fetch-mode' => 'cors',
                    'sec-fetch-site' => 'same-origin',
                ])->get($this->externalApiBaseUrl . 'weather/stats');

                if ($response->successful()) {
                    return $response->json();
                } else {
                    Log::error('Failed to fetch Grow a Garden weather stats from external API.', [
                        'status'   => $response->status(),
                        'response' => $response->body(),
                    ]);
                    throw new Exception('Failed to retrieve data from Grow a Garden weather API. Status: ' . $response->status());
                }
            });

            return response()->json([
                'status'  => 'success',
                'message' => 'Grow a Garden weather stats retrieved successfully.',
                'data'    => $weatherStats
            ], 200);

        } catch (Exception $e) {
            Log::error('An error occurred while fetching Grow a Garden weather stats: ' . $e->getMessage(), [
                'exception' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'status'  => 'error',
                'message' => 'An internal server error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menghitung dan mengembalikan waktu restock untuk berbagai item.
     * Berdasarkan logika JavaScript yang diberikan.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRestockTimers()
    {
        try {
            $now = Carbon::now($this->timezone);
            $today = $now->copy()->startOfDay(); // Mulai hari ini di timezone yang sama

            // Helper function to format time
            $formatTime = function ($timestamp) use ($now) {
                // Carbon::createFromTimestampMs() tidak tersedia di semua versi Carbon,
                // jadi kita gunakan fromTimestamp() dan kalikan/bagi untuk milidetik
                return Carbon::createFromTimestampMs($timestamp, $this->timezone)->format('h:i A');
            };

            // Helper function to calculate time since
            $timeSince = function ($timestamp) use ($now) {
                $diffInSeconds = $now->diffInSeconds(Carbon::createFromTimestampMs($timestamp, $this->timezone));

                if ($diffInSeconds < 60) {
                    return "{$diffInSeconds}s ago";
                }

                $minutes = floor($diffInSeconds / 60);
                if ($minutes < 60) {
                    return "{$minutes}m ago";
                }

                $hours = floor($minutes / 60);
                return "{$hours}h ago";
            };

            // Helper function to get reset times
            $getResetTimes = function ($intervalMs) use ($now, $today) {
                $timeSinceStartOfDayMs = $now->diffInMilliseconds($today);
                $lastResetMs = $today->getTimestampMs() + floor($timeSinceStartOfDayMs / $intervalMs) * $intervalMs;
                $nextResetMs = $today->getTimestampMs() + ceil($timeSinceStartOfDayMs / $intervalMs) * $intervalMs;

                return ['lastReset' => $lastResetMs, 'nextReset' => $nextResetMs];
            };

            // Define intervals in milliseconds
            $eggInterval = 30 * 60 * 1000;
            ['lastReset' => $eggLastReset, 'nextReset' => $eggNextReset] = $getResetTimes($eggInterval);
            $eggCountdownMs = $eggNextReset - $now->getTimestampMs();
            $eggCountdown = $this->formatMillisecondsToHMS($eggCountdownMs);

            $gearInterval = 5 * 60 * 1000;
            ['lastReset' => $gearLastReset, 'nextReset' => $gearNextReset] = $getResetTimes($gearInterval);
            $gearCountdownMs = $gearNextReset - $now->getTimestampMs();
            $gearCountdown = $this->formatMillisecondsToMS($gearCountdownMs); // Menit dan Detik

            $cosmeticInterval = 4 * 3600 * 1000;
            ['lastReset' => $cosmeticLastReset, 'nextReset' => $cosmeticNextReset] = $getResetTimes($cosmeticInterval);
            $cosmeticCountdownMs = $cosmeticNextReset - $now->getTimestampMs();
            $cosmeticCountdown = $this->formatMillisecondsToHMS($cosmeticCountdownMs);

            $nightInterval = 3600 * 1000;
            ['lastReset' => $nightLastReset, 'nextReset' => $nightNextReset] = $getResetTimes($nightInterval);
            $nightCountdownMs = $nightNextReset - $now->getTimestampMs();
            $nightCountdown = $this->formatMillisecondsToHMS($nightCountdownMs);

            $merchantInterval = 14400 * 1000;
            ['lastReset' => $merchantLastReset, 'nextReset' => $merchantNextReset] = $getResetTimes($merchantInterval);
            $merchantCountdownMs = $merchantNextReset - $now->getTimestampMs();
            $merchantCountdown = $this->formatMillisecondsToHMS($merchantCountdownMs);

            $restockTimes = [
                'egg' => [
                    'timestamp'          => $eggNextReset,
                    'countdown'          => $eggCountdown,
                    'LastRestock'        => $formatTime($eggLastReset),
                    'timeSinceLastRestock' => $timeSince($eggLastReset),
                ],
                'gear' => [
                    'timestamp'          => $gearNextReset,
                    'countdown'          => $gearCountdown,
                    'LastRestock'        => $formatTime($gearLastReset),
                    'timeSinceLastRestock' => $timeSince($gearLastReset),
                ],
                'seeds' => [ // Menggunakan interval gear untuk seeds, sesuai JS
                    'timestamp'          => $gearNextReset,
                    'countdown'          => $gearCountdown,
                    'LastRestock'        => $formatTime($gearLastReset),
                    'timeSinceLastRestock' => $timeSince($gearLastReset),
                ],
                'cosmetic' => [
                    'timestamp'          => $cosmeticNextReset,
                    'countdown'          => $cosmeticCountdown,
                    'LastRestock'        => $formatTime($cosmeticLastReset),
                    'timeSinceLastRestock' => $timeSince($cosmeticLastReset),
                ],
                'SummerHarvest' => [ // Menggunakan interval night untuk SummerHarvest, sesuai JS
                    'timestamp'          => $nightNextReset,
                    'countdown'          => $nightCountdown,
                    'LastRestock'        => $formatTime($nightLastReset),
                    'timeSinceLastRestock' => $timeSince($nightLastReset),
                ],
                'merchant' => [
                    'timestamp'          => $merchantNextReset,
                    'countdown'          => $merchantCountdown,
                    'LastRestock'        => $formatTime($merchantLastReset),
                    'timeSinceLastRestock' => $timeSince($merchantLastReset),
                ],
            ];

            return response()->json([
                'status'  => 'success',
                'message' => 'Grow a Garden restock timers calculated successfully.',
                'data'    => $restockTimes
            ], 200);

        } catch (Exception $e) {
            Log::error('An error occurred while calculating Grow a Garden restock timers: ' . $e->getMessage(), [
                'exception' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'status'  => 'error',
                'message' => 'An internal server error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper: Memformat milidetik menjadi HHh MMm SSs.
     */
    protected function formatMillisecondsToHMS(int $ms): string
    {
        $hours = floor($ms / 3.6e6);
        $minutes = floor(($ms % 3.6e6) / 6e4);
        $seconds = floor(($ms % 6e4) / 1000);
        return $this->pad($hours) . 'h ' . $this->pad($minutes) . 'm ' . $this->pad($seconds) . 's';
    }

    /**
     * Helper: Memformat milidetik menjadi MMm SSs.
     */
    protected function formatMillisecondsToMS(int $ms): string
    {
        $minutes = floor($ms / 6e4);
        $seconds = floor(($ms % 6e4) / 1000);
        return $this->pad($minutes) . 'm ' . $this->pad($seconds) . 's';
    }

    /**
     * Helper: Menambahkan padding nol di depan angka < 10.
     */
    protected function pad(int $n): string
    {
        return $n < 10 ? '0' . $n : (string)$n;
    }

    /**
     * Fungsi helper untuk memformat item stok.
     * Mirip dengan formatStockItems di JS.
     */
    protected function formatStockItems(array $items, array $imageData): array
    {
        if (empty($items)) {
            return [];
        }

        $formatted = [];
        foreach ($items as $item) {
            $name = $item['name'] ?? 'Unknown Item';
            $image = $imageData[$name] ?? null;

            $formattedItem = [
                'name'  => $name,
                'value' => $item['value'] ?? null,
            ];

            if ($image) {
                $formattedItem['image'] = $image;
            }
            $formatted[] = $formattedItem;
        }
        return $formatted;
    }

    /**
     * Fungsi helper untuk memformat item 'last seen'.
     * Mirip dengan formatLastSeenItems di JS.
     */
    protected function formatLastSeenItems(array $items, array $imageData): array
    {
        if (empty($items)) {
            return [];
        }

        $formatted = [];
        foreach ($items as $item) {
            $name = $item['name'] ?? 'Unknown';
            $emoji = $item['emoji'] ?? '❓';
            $seen = $item['seen'] ?? null;

            $image = $imageData[$name] ?? null;

            $formattedItem = [
                'name'  => $name,
                'emoji' => $emoji,
                'seen'  => $seen,
            ];

            if ($image) {
                $formattedItem['image'] = $image;
            }
            $formatted[] = $formattedItem;
        }
        return $formatted;
    }

    /**
     * Fungsi utama untuk memformat seluruh data stok.
     * Mirip dengan formatStocks di JS.
     */
    protected function formatStocks(array $stocks): array
    {
        $imageData = $stocks['imageData'] ?? [];

        return [
            'easterStock'    => $this->formatStockItems($stocks['easterStock'] ?? [], $imageData),
            'gearStock'      => $this->formatStockItems($stocks['gearStock'] ?? [], $imageData),
            'eggStock'       => $this->formatStockItems($stocks['eggStock'] ?? [], $imageData),
            'nightStock'     => $this->formatStockItems($stocks['nightStock'] ?? [], $imageData),
            'honeyStock'     => $this->formatStockItems($stocks['honeyStock'] ?? [], $imageData),
            'cosmeticsStock' => $this->formatStockItems($stocks['cosmeticsStock'] ?? [], $imageData),
            'seedsStock'     => $this->formatStockItems($stocks['seedsStock'] ?? [], $imageData),

            'lastSeen' => [
                'Seeds'   => $this->formatLastSeenItems($stocks['lastSeen']['Seeds'] ?? [], $imageData),
                'Gears'   => $this->formatLastSeenItems($stocks['lastSeen']['Gears'] ?? [], $imageData),
                'Weather' => $this->formatLastSeenItems($stocks['lastSeen']['Weather'] ?? [], $imageData),
                'Eggs'    => $this->formatLastSeenItems($stocks['lastSeen']['Eggs'] ?? [], $imageData),
                'Honey'   => $this->formatLastSeenItems($stocks['lastSeen']['Honey'] ?? [], $imageData),
            ],

            'restockTimers' => $stocks['restockTimers'] ?? [],
        ];
    }
}
