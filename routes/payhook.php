<?php

use Illuminate\Support\Facades\Route;
use PayHook\Laravel\Http\Controllers\HeartbeatController;
use PayHook\Laravel\Http\Controllers\WebhookController;

Route::group([
    'prefix' => config('payhook.routes.prefix', 'payhook'),
    'middleware' => (array) config('payhook.routes.middleware', ['api']),
], function () {
    Route::post(config('payhook.routes.webhook_path', 'webhook'), WebhookController::class)
        ->middleware('payhook.verify')
        ->name('payhook.webhook');
    Route::post(config('payhook.routes.heartbeat_path', 'heartbeat'), HeartbeatController::class)
        ->middleware('payhook.verify:heartbeat')
        ->name('payhook.heartbeat');
});
