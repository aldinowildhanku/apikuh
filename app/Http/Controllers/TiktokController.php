<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TiktokController extends Controller
{
     public function getInfo(Request $request)
    {
        $url = $request->input('url');

        if (!$url) {
            return response()->json(['error' => 'URL is required'], 400);
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0'
            ])->withoutRedirecting()->get($url);

            $realUrl = $response->header('Location') ?? (string) $response->effectiveUri();

            if (!$realUrl || !str_contains($realUrl, 'tiktok.com')) {
                return response()->json(['error' => 'Invalid TikTok URL'], 400);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to resolve URL', 'message' => $e->getMessage()], 500);
        }

        try {

            $oembedResponse = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0'
            ])->get("https://www.tiktok.com/oembed", [
                'url' => $realUrl
            ]);

            if (!$oembedResponse->successful()) {
                return response()->json(['error' => 'Failed to fetch oEmbed info'], 500);
            }

            $videoInfo = $oembedResponse->json();
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch oEmbed info', 'message' => $e->getMessage()], 500);
        }

        try {

            $tikwmApi = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0',
                'Referer' => 'https://www.tikwm.com/',
            ])->get('https://www.tikwm.com/api/', [
                'url' => $realUrl,
                'hd' => 1
            ]);

            $videoDownloadUrl = null;

            if ($tikwmApi->successful()) {
                $data = $tikwmApi->json();

                if (isset($data['data']['play'])) {
                    $videoDownloadUrl = $data['data']['play'];
                } else {
                    return response()->json(['error' => 'Failed to extract video play URL'], 500);
                }
            } else {
                return response()->json(['error' => 'Failed to fetch TikWM data'], 500);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch TikWM video', 'message' => $e->getMessage()], 500);
        }

        // Final response
        return response()->json([
            'original_url' => $url,
            'resolved_url' => $realUrl,
            'video_info' => $videoInfo,
            'play' => $videoDownloadUrl
        ]);
    }
}
