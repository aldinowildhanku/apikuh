<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProxyPreviewController;
use App\Http\Controllers\IpLookupController;
use App\Http\Controllers\WhoisController;
use App\Http\Controllers\SmtpController;
use App\Http\Controllers\DataConverterController;
use App\Http\Controllers\SplitBillController;
use App\Http\Controllers\FiveMServerController;
use App\Http\Controllers\TiktokController;
use App\Http\Controllers\SecretMessageController;

Route::post('/preview-proxy', [ProxyPreviewController::class, 'preview']) ->middleware('throttle:5,1'); // Maks 5 request per menit;
Route::post('/convert-data', [DataConverterController::class, 'convert']) ->middleware('throttle:5,1'); // Maks 5 request per menit;
Route::post('/iplookup', [IpLookupController::class, 'lookup']) ->middleware('throttle:5,1'); // Maks 5 request per menit;
Route::post('/whois', [WhoisController::class, 'check']) ->middleware('throttle:5,1'); // Maks 5 request per menit;
Route::post('/send-email', [SmtpController::class, 'send'])
    ->middleware('throttle:5,1'); // Maks 5 request per menit
Route::post('/split-bill', [SplitBillController::class, 'calculateAndStore'])->middleware('throttle:5,1');
Route::get('/split-bill/{invoice_id}', [SplitBillController::class, 'show']);
Route::get('/fivem/server-data/{serverName}', [FiveMServerController::class, 'getServerData']) ->middleware('throttle:5,1'); // Maks 5 request per menit;
Route::post('/tiktok-info', [TiktokController::class, 'getInfo'])->middleware('throttle:3,1');
Route::post('/secret', [SecretMessageController::class, 'create']);
