<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ProxyPreviewController extends Controller
{
    // API JSON untuk dapatkan url preview
    public function preview(Request $request)
    {
        $host = $request->input('host');
        $ip = $request->input('ip');

        if (!$host || !$ip) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Missing host or IP.',
            ]);
        }

        return response()->json([
            'status' => 'ok',
            'message' => 'Preview loaded successfully.',
            'preview_url' => url("/preview-content?host=$host&ip=$ip&path=/"),
        ]);
    }
    
public function previewContent(Request $request)
{
    $host = $request->query('host');
    $path = $request->query('path', '/');
    $ip = $request->query('ip');

    if (!$host) {
        return response()->json([
            'status' => false,
            'message' => 'Missing host.',
        ], 400);
    }

    $schemeList = ['https', 'http'];
    $response = null;
    $finalUrl = null;

    foreach ($schemeList as $scheme) {
        $base = $ip ? "$scheme://$ip" : "$scheme://$host";
        $url = rtrim($base, '/') . '/' . ltrim($path, '/');

        try {
            $http = Http::withHeaders([
                'Host' => $host,
                'User-Agent' => 'Mozilla/5.0',
            ])->timeout(15)->withoutVerifying();

            $response = $http->get($url);

            if ($response->successful() || $response->status() === 403) {
                $finalUrl = $url;
                break;
            }
        } catch (\Exception $e) {
            continue;
        }
    }

    if (!$response || !$finalUrl) {
        return response()->json([
            'status' => false,
            'message' => 'Preview failed. Domain may not be reachable.',
        ], 500);
    }

    $html = $response->body();

    // Hapus <base> tag agar tidak override base URL
    $html = preg_replace('/<base[^>]+>/i', '', $html);

    // Parse host dari URL final untuk rewrite URL absolut
    $parsedHost = parse_url($finalUrl, PHP_URL_HOST);

    $html = preg_replace_callback(
        '/\b(src|href|action)=["\']([^"\']+)["\']/i',
        function ($matches) use ($host, $ip, $parsedHost) {
            $attr = $matches[1];
            $url = $matches[2];

            // Jika url adalah data URI, mailto, tel, hash (#), javascript: biarkan
            if (preg_match('#^(data:|mailto:|tel:|\#|javascript:)#i', $url)) {
                return $matches[0];
            }

            // Jika url adalah absolute
            if (preg_match('#^https?://#i', $url)) {
                $urlHost = parse_url($url, PHP_URL_HOST);

                // Jika domain sama dengan host target, rewrite ke proxy Laravel
                if ($urlHost === $host || $urlHost === $parsedHost) {
                    $path = parse_url($url, PHP_URL_PATH) ?: '/';
                    $query = parse_url($url, PHP_URL_QUERY);
                    if ($query) $path .= '?' . $query;

                    $queryParams = [
                        'host' => $host,
                        'path' => $path,
                    ];
                    if ($ip) $queryParams['ip'] = $ip;

                    $proxyUrl = url('/preview-asset?' . http_build_query($queryParams));
                    return "$attr=\"$proxyUrl\"";
                }

                // Kalau bukan domain target, biarkan langsung (misal CDN)
                return $matches[0];
            }

            // Jika relative path (tidak dimulai http atau /)
            // Pastikan mulai dengan slash
            $cleanPath = $url[0] === '/' ? $url : '/' . $url;

            $queryParams = [
                'host' => $host,
                'path' => $cleanPath,
            ];
            if ($ip) $queryParams['ip'] = $ip;

            // Semua relative path diarahkan ke proxy asset Laravel
            $proxyUrl = url('/preview-asset?' . http_build_query($queryParams));
            return "$attr=\"$proxyUrl\"";
        },
        $html
    );

    return response($html, $response->status() === 403 ? 200 : $response->status())
        ->header('Content-Type', 'text/html');
}




    // Proxy asset CSS/JS/image/font dll
public function proxyAsset(Request $request)
{
    $host = $request->query('host');
    $path = $request->query('path');
    $ip = $request->query('ip');

    if (!$host || !$path) {
        return response('Missing parameter', 400);
    }

    $schemes = ['https', 'http'];

    foreach ($schemes as $scheme) {
        $base = $ip ? "$scheme://$ip" : "$scheme://$host";
        $url = rtrim($base, '/') . '/' . ltrim($path, '/');

        try {
            $response = Http::withHeaders([
                'Host' => $host,
                'User-Agent' => 'Mozilla/5.0',
            ])->timeout(15)->withoutVerifying()->get($url);

            return response($response->body(), $response->status())
                ->withHeaders($response->headers());
        } catch (\Exception $e) {
            continue;
        }
    }

    return response('Asset fetch failed.', 500);
}


//     public function proxyAsset(Request $request)
// {
//     $host = $request->query('host');
//     $ip = $request->query('ip');
//     $path = $request->query('path');

//     if (!$host || !$ip || !$path) {
//         return response()->json([
//             'status' => false,
//             'message' => 'Missing parameter',
//         ], 400);
//     }

//     try {
//         $response = Http::withHeaders([
//             'Host' => $host,
//         ])->timeout(15)->get("http://$ip/" . ltrim($path, '/'));

//         $contentType = $response->header('Content-Type', 'application/octet-stream');

//         return response($response->body(), $response->status())
//     ->withHeaders([
//         'Content-Type' => $contentType,
//         'Access-Control-Allow-Origin' => '*', // penting
//         'Access-Control-Allow-Headers' => '*',
//         'Access-Control-Allow-Methods' => 'GET, OPTIONS',
//     ]);
//     } catch (\Throwable $e) {
//         return response()->json([
//             'status' => false,
//             'message' => 'Asset fetch failed',
//         ], 500);
//     }
// }

}
