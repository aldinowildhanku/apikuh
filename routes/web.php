<?php

use App\Http\Controllers\ProxyPreviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/preview-content', [ProxyPreviewController::class, 'previewContent']);
Route::get('/preview-asset', [ProxyPreviewController::class, 'proxyAsset']);