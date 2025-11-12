<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ProxyPreviewController extends Controller
{

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

    $html = preg_replace('/<base[^>]+>/i', '', $html);


    $parsedHost = parse_url($finalUrl, PHP_URL_HOST);

    $html = preg_replace_callback(
        '/\b(src|href|action)=["\']([^"\']+)["\']/i',
        function ($matches) use ($host, $ip, $parsedHost) {
            $attr = $matches[1];
            $url = $matches[2];

            if (preg_match('#^(data:|mailto:|tel:|\#|javascript:)#i', $url)) {
                return $matches[0];
            }

            if (preg_match('#^https?://#i', $url)) {
                $urlHost = parse_url($url, PHP_URL_HOST);

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

                return $matches[0];
            }

            $cleanPath = $url[0] === '/' ? $url : '/' . $url;

            $queryParams = [
                'host' => $host,
                'path' => $cleanPath,
            ];
            if ($ip) $queryParams['ip'] = $ip;

            $proxyUrl = url('/preview-asset?' . http_build_query($queryParams));
            return "$attr=\"$proxyUrl\"";
        },
        $html
    );

    return response($html, $response->status() === 403 ? 200 : $response->status())
        ->header('Content-Type', 'text/html');
}

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

}
