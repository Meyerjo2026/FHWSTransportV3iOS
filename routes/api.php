<?php

use App\Http\Controllers\Api\AdminApiController;
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

    // Admin
    Route::prefix('admin')->middleware('api.admin')->group(function () {
        Route::get('/dashboard', [AdminApiController::class, 'dashboard']);
        Route::get('/review', [AdminApiController::class, 'review']);
        Route::post('/review/bulk', [AdminApiController::class, 'bulkStatus']);
        Route::get('/journeys', [AdminApiController::class, 'journeys']);
        Route::post('/journeys', [AdminApiController::class, 'storeJourney']);
        Route::delete('/journeys/{journey}', [AdminApiController::class, 'destroyJourney']);
        Route::get('/quotes', [AdminApiController::class, 'quotes']);
        Route::post('/quotes', [AdminApiController::class, 'storeQuote']);
        Route::get('/quotes/{quote}', [AdminApiController::class, 'showQuote']);
        Route::get('/sites', [AdminApiController::class, 'sites']);
        Route::post('/sites', [AdminApiController::class, 'storeSite']);
        Route::post('/sites/{site}', [AdminApiController::class, 'updateSite']);
        Route::get('/staff', [AdminApiController::class, 'staff']);
        Route::post('/staff', [AdminApiController::class, 'storeStaff']);
        Route::post('/staff/{staff}/reset-password', [AdminApiController::class, 'resetStaffPassword']);
        Route::post('/staff/{staff}/toggle', [AdminApiController::class, 'toggleStaff']);
        Route::delete('/staff/{staff}', [AdminApiController::class, 'destroyStaff']);
    });
});
