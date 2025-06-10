<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProxyPreviewController;
use App\Http\Controllers\IpLookupController;
use App\Http\Controllers\WhoisController;
use App\Http\Controllers\SmtpController;
use App\Http\Controllers\DataConverterController;

Route::post('/preview-proxy', [ProxyPreviewController::class, 'preview']) ->middleware('throttle:5,1'); // Maks 5 request per menit;
Route::post('/convert-data', [DataConverterController::class, 'convert']) ->middleware('throttle:5,1'); // Maks 5 request per menit;
Route::post('/iplookup', [IpLookupController::class, 'lookup']) ->middleware('throttle:5,1'); // Maks 5 request per menit;
Route::post('/whois', [WhoisController::class, 'check']) ->middleware('throttle:5,1'); // Maks 5 request per menit;
Route::post('/send-email', [SmtpController::class, 'send'])
    ->middleware('throttle:5,1'); // Maks 5 request per menit