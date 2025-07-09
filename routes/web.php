<?php

use App\Http\Controllers\ProxyPreviewController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SecretMessageController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/preview-content', [ProxyPreviewController::class, 'previewContent']);
Route::get('/preview-asset', [ProxyPreviewController::class, 'proxyAsset']);
Route::get('/message/{uuid}', [SecretMessageController::class, 'show']);