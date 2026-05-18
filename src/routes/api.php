<?php

use App\Http\Controllers\NotificationController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/notifications/send', [NotificationController::class, 'send'])
    ->middleware('throttle:60,1');

Route::get('/notifications/subscribers/{subscriber_id}', [NotificationController::class, 'forSubscriber']);

Route::post('/webhooks/delivery', [WebhookController::class, 'delivery']);
