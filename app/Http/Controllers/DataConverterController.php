<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DataConverterController extends Controller
{
    public function convert(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'value' => 'required|numeric|min:0', 
            'unit_from' => 'required|string|in:mb,gb', // Unit asal harus 'mb' atau 'gb'
            'unit_to' => 'required|string|in:mb,gb',   // Unit tujuan harus 'mb' atau 'gb'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422); // Unprocessable Entity
        }

        $value = $request->input('value');
        $unitFrom = strtolower($request->input('unit_from')); // Pastikan lowercase
        $unitTo = strtolower($request->input('unit_to'));     // Pastikan lowercase

        // Konversi dasar: 1 GB = 1024 MB
        $conversionRate = 1024;
        $result = 0;

        // 2. Lakukan Konversi
        try {
            if ($unitFrom === $unitTo) {
                // Jika unit asal dan tujuan sama, tidak perlu konversi
                $result = $value;
                $message = "No conversion needed, units are the same.";
            } elseif ($unitFrom === 'mb' && $unitTo === 'gb') {
                // MB ke GB
                $result = $value / $conversionRate;
                $message = "Successfully converted MB to GB.";
            } elseif ($unitFrom === 'gb' && $unitTo === 'mb') {
                // GB ke MB
                $result = $value * $conversionRate;
                $message = "Successfully converted GB to MB.";
            } else {
                // Seharusnya tidak tercapai karena validasi `in`
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Invalid unit combination.',
                ], 400); // Bad Request
            }

            return response()->json([
                'status' => 'success',
                'message' => $message,
                'original_value' => $value,
                'original_unit' => $unitFrom,
                'converted_value' => round($result, 4), // Bulatkan untuk hasil yang lebih bersih
                'converted_unit' => $unitTo
            ]);

        } catch (\Exception $e) {
            // Tangani error yang tidak terduga
            return response()->json([
                'status' => 'failed',
                'message' => 'An unexpected error occurred during conversion: ' . $e->getMessage()
            ], 500); // Internal Server Error
        }
    }
}
