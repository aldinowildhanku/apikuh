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
            'unit_from' => 'required|string|in:mb,gb',
            'unit_to' => 'required|string|in:mb,gb', 
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422); // Unprocessable Entity
        }

        $value = $request->input('value');
        $unitFrom = strtolower($request->input('unit_from')); 
        $unitTo = strtolower($request->input('unit_to')); 
        $conversionRate = 1024;
        $result = 0;

        try {
            if ($unitFrom === $unitTo) {

                $result = $value;
                $message = "No conversion needed, units are the same.";
            } elseif ($unitFrom === 'mb' && $unitTo === 'gb') {

                $result = $value / $conversionRate;
                $message = "Successfully converted MB to GB.";
            } elseif ($unitFrom === 'gb' && $unitTo === 'mb') {

                $result = $value * $conversionRate;
                $message = "Successfully converted GB to MB.";
            } else {

                return response()->json([
                    'status' => 'failed',
                    'message' => 'Invalid unit combination.',
                ], 400);
            }

            return response()->json([
                'status' => 'success',
                'message' => $message,
                'original_value' => $value,
                'original_unit' => $unitFrom,
                'converted_value' => round($result, 4), 
                'converted_unit' => $unitTo
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'failed',
                'message' => 'An unexpected error occurred during conversion: ' . $e->getMessage()
            ], 500); 
        }
    }
}
