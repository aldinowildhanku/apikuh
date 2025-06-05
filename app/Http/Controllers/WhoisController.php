<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WhoisController extends Controller
{
     public function check(Request $request)
    {
        $request->validate([
            'domain' => 'required|string'
        ]);

        $domain = strtolower($request->input('domain'));
        $tld = substr(strrchr($domain, '.'), 1); // ambil TLD terakhir

        // Tentukan URL RDAP berdasarkan TLD
        $rdapUrl = match ($tld) {
            'id', 'my.id' => "https://rdap.pandi.id/rdap/domain/{$domain}",
            'com' => "https://rdap.verisign.com/com/v1/domain/{$domain}",
            default => null
        };

        if (!$rdapUrl) {
            return response()->json([
                'status' => 'failed',
                'domain' => 'unsupported TLD'
            ], 400);
        }

        try {
            $response = Http::timeout(10)->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36'
            ])->get($rdapUrl);

            if ($response->failed() || $response->status() === 404 || isset($response['errorCode'])) {
                return response()->json([
                    'status' => 'failed',
                    'domain' => 'not found'
                ]);
            }

            $data = $response->json();

            // Ambil event date berdasarkan eventAction
            $events = collect($data['events'] ?? []);
            $getEventDate = fn($action) => optional($events->firstWhere('eventAction', $action))['eventDate'] ?? 'not available';

            // Ambil entity berdasarkan role
            $getEntityByRole = function (array $entities, string $role) {
                foreach ($entities as $entity) {
                    if (in_array($role, $entity['roles'] ?? [])) {
                        return $entity;
                    }
                }
                return null;
            };

            $entities = $data['entities'] ?? [];
            $registrar = $getEntityByRole($entities, 'registrar');
            $abuse = $getEntityByRole($entities, 'abuse');

            // Ambil vcard array
            $getVcardArray = fn($entity) => collect($entity['vcardArray'][1] ?? []);

            // Extract value dari vcard key tertentu
            $extractVcardValue = function ($vcard, $key) {
                $item = $vcard->firstWhere(fn($i) => $i[0] === $key);
                if (!$item) return null;

                // Untuk tel, cek tipe data (uri / text)
                if ($key === 'tel') {
                    return $item[3] ?? null;
                }

                // Untuk adr biasanya array, kita akan gabungkan
                if ($key === 'adr' && is_array($item[3])) {
                    return implode(', ', array_filter($item[3]));
                }

                return $item[3] ?? null;
            };

            $registrarVcard = $getVcardArray($registrar);
            $abuseVcard = $getVcardArray($abuse);

            // Cari field dari registrar dulu, kalau tidak ada coba dari abuse
            $getField = fn($key) => 
                $extractVcardValue($registrarVcard, $key) 
                ?? $extractVcardValue($abuseVcard, $key) 
                ?? 'not available';

            // Ambil status pertama jika ada
            $status_code = $data['status'][0] ?? 'not available';

            // Ambil nameservers list
            $nameservers = collect($data['nameservers'] ?? [])->pluck('ldhName')->toArray();

            return response()->json([
                'status' => 'ok',
                'domain' => $data['ldhName'] ?? $domain,
                'expired' => $getEventDate('expiration'),
                'registered' => $getEventDate('registration'),
                'updated' => $getEventDate('last changed'),
                'registrar' => $getField('fn'),
                'email' => $getField('email'),
                'telephone' => $getField('tel'),
                'address' => $getField('adr'),
                'status_code' => $status_code,
                'nameserver' => $nameservers,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'failed',
                'domain' => 'request error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
