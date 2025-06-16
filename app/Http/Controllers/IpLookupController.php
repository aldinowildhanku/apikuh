<?php

namespace App\Http\Controllers;
use GuzzleHttp\Client;
use Illuminate\Http\Request;

class IpLookupController extends Controller
{
    public function lookup(Request $request)
    {
        // Mendapatkan IP address dari request body (POST) atau query string (GET)
        $ip = $request->input('ip');

        // Jika tidak ada IP yang diberikan di request, kembalikan error
        if (empty($ip)) {
            return response()->json([
                'status'  => 'failed',
                'message' => 'IP address is required. Please provide an "ip" parameter in the request body or query string.'
            ], 400); // Bad Request
        }

        // Validasi IP address: Pastikan formatnya valid dan bukan IP pribadi/dicadangkan
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return response()->json([
                'status'  => 'failed',
                'message' => 'The provided IP address is invalid, a private, or reserved range and cannot be geolocated. Please provide a valid public IP address.'
            ], 400); // Bad Request
        }

        $client = new Client();
        try {
            $response = $client->get("http://ip-api.com/json/{$ip}?fields=status,message,country,countryCode,region,regionName,city,zip,lat,lon,timezone,isp,org,as,hosting,query");
            $data = json_decode($response->getBody()->getContents(), true);

            if ($data['status'] === 'success') {
                return response()->json($data);
            } elseif ($data['status'] === 'fail') {
                return response()->json([
                    'status'  => 'failed',
                    'message' => $data['message'] ?? 'Failed to retrieve IP information from the external API.'
                ], 500);
            } else {
                return response()->json([
                    'status'  => 'failed',
                    'message' => 'An unexpected response was received from the IP lookup service.'
                ], 500);
            }
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            return response()->json([
                'status'  => 'failed',
                'message' => 'Failed to connect to IP lookup service: ' . $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'failed',
                'message' => 'An unexpected error occurred: ' . $e->getMessage()
            ], 500);
        }
    }
}
