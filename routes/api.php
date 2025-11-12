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
use App\Http\Controllers\GrowAGardenController;
use App\Http\Controllers\RedMServerController;
use App\Http\Controllers\PdfController;

Route::post('/preview-proxy', [ProxyPreviewController::class, 'preview']) ->middleware('throttle:5,1'); //request maks 5 kali per menit
Route::post('/convert-data', [DataConverterController::class, 'convert']) ->middleware('throttle:5,1'); 
Route::post('/iplookup', [IpLookupController::class, 'lookup']) ->middleware('throttle:5,1'); 
Route::post('/whois', [WhoisController::class, 'check']) ->middleware('throttle:5,1'); 
Route::post('/send-email', [SmtpController::class, 'send'])
    ->middleware('throttle:5,1'); 
Route::post('/split-bill', [SplitBillController::class, 'calculateAndStore'])->middleware('throttle:5,1');
Route::get('/split-bill/{invoice_id}', [SplitBillController::class, 'show']);
Route::get('/fivem/server-data/{serverName}', [FiveMServerController::class, 'getServerData']) ->middleware('throttle:5,1'); 
Route::get('/redm/server-data/{serverName}', [RedMServerController::class, 'getServerData']) 
    ->middleware('throttle:5,1');
Route::post('/tiktok-info', [TiktokController::class, 'getInfo'])->middleware('throttle:3,1');
Route::post('/secret-messages', [SecretMessageController::class, 'create']);
Route::get('/secret-messages/{uuid}', [SecretMessageController::class, 'show']);
Route::get('/growagarden/live-stock', [GrowAGardenController::class, 'getLiveStockData']) ->middleware('throttle:6,1');
Route::get('/growagarden/weather-stats', [GrowAGardenController::class, 'getWeatherStats']) ->middleware('throttle:6,1');
Route::get('/growagarden/restock-timers', [GrowAGardenController::class, 'getRestockTimers']) ->middleware('throttle:6,1');
Route::post('/generate-pdf', [PdfController::class, 'generate']);
Route::post('/merge-pdf', [PdfController::class, 'merge']);
