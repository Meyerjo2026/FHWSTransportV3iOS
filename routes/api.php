<?php

use App\Http\Controllers\Api\ApiController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [ApiController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [ApiController::class, 'logout']);
    Route::get('/me', [ApiController::class, 'me']);
    Route::post('/password', [ApiController::class, 'changePassword']);

    // Student
    Route::get('/options', [ApiController::class, 'options']);
    Route::get('/requests', [ApiController::class, 'myRequests']);
    Route::post('/requests', [ApiController::class, 'storeRequest']);

    // Staff + admin
    Route::get('/approvals', [ApiController::class, 'approvals']);
    Route::post('/requests/{tripRequest}/status', [ApiController::class, 'setStatus']);
});
