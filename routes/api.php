<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProxyPreviewController;
use App\Http\Controllers\WhoisController;
use App\Http\Controllers\SmtpController;

Route::post('/preview-proxy', [ProxyPreviewController::class, 'preview']) ->middleware('throttle:5,1'); // Maks 5 request per menit;

Route::post('/whois', [WhoisController::class, 'check']) ->middleware('throttle:5,1'); // Maks 5 request per menit;
Route::post('/send-email', [SmtpController::class, 'send'])
    ->middleware('throttle:5,1'); // Maks 5 request per menit