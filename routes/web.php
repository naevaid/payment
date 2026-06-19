<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => 'payment.naeva.id',
        'service' => 'centralized_payment_gateway',
        'status' => 'bootstrapped',
        'ssl_required' => true,
    ]);
});

Route::get('/healthz', function () {
    return response()->json([
        'ok' => true,
        'service' => 'payment',
    ]);
});
