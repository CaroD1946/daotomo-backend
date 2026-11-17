<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;
use App\Http\Controllers\Api\Payment\SepayWebhookController;

// ─── SePay Webhook Root Aliases ───────────────────────────────────────────────
Route::match(['GET', 'POST'], '/webhooks/sepay', [SepayWebhookController::class, 'handleWebhook']);
Route::match(['GET', 'POST'], '/sepay/webhook', [SepayWebhookController::class, 'handleWebhook']);

Route::get('/', function () {
    $indexPath = public_path('index.html');
    if (File::exists($indexPath)) {
        return response()->file($indexPath);
    }
    return view('welcome');
});

Route::get('/index.html', function () {
    return response()->file(public_path('index.html'));
});

// API Fallback for unmapped API routes across all HTTP methods
Route::any('/api/{any}', function () {
    return response()->json(['success' => false, 'message' => 'API route not found'], 404);
})->where('any', '.*');

// Fallback for HTML pages or SPA routes
Route::fallback(function () {
    $uri = request()->path();

    $filePath = public_path($uri);
    if (File::exists($filePath) && is_file($filePath)) {
        return response()->file($filePath);
    }

    $indexPath = public_path('index.html');
    if (File::exists($indexPath)) {
        return response()->file($indexPath);
    }

    return response('Page not found', 404);
});
