<?php

use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/products', [ProductController::class, 'index']);
Route::post('/products', [ProductController::class, 'store']);
Route::get('/products/search', [ProductController::class, 'search']);
Route::get('/products/dashboard', [ProductController::class, 'dashboard']);
Route::get('/products/sales-report', [ProductController::class, 'salesReport']);

Route::get('/orders', [OrderController::class, 'index']);
Route::post('/orders', [OrderController::class, 'store']);
Route::get('/orders/filter', [OrderController::class, 'filterByStatus']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/admin/products', [ProductController::class, 'index']);
    Route::post('/admin/orders', [OrderController::class, 'store']);
});
