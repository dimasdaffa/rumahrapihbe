<?php

use App\Http\Controllers\Api\BookingTransactionController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\HomeServiceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/service/{homeService:slug}', [HomeServiceController::class, 'show']);

Route::apiResource('/services', HomeServiceController::class); // This line is likely redundant if the one above is for individual service lookup

Route::get('/category/{category:slug}', [CategoryController::class, 'show']);

Route::apiResource('/categories', CategoryController::class);

Route::post('/booking-transaction', [BookingTransactionController::class, 'store']);

Route::post('/check-booking', [BookingTransactionController::class, 'booking_details']);
