<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\OAuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\Admin\AdminProductController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;

/*
|--------------------------------------------------------------------------
| 前台
|--------------------------------------------------------------------------
*/
/*
|--------------------------------------------------------------------------
|-- 登入/登出/註冊功能(含第三方登入)
|--------------------------------------------------------------------------
*/
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'registerUser']);

Route::prefix('auth')->group(function () {
    Route::get('/{provider}/callback', [OAuthController::class, 'handleProviderCallback']);
    Route::post('/register-oauth', [OAuthController::class, 'registerOAuthUser']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
});


/*
|--------------------------------------------------------------------------
|-- 產品顯示/訂單確認功能
|--------------------------------------------------------------------------
*/
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {    
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel']);
    Route::post('/checkout', [CheckoutController::class, 'checkout']);
});

/*
|--------------------------------------------------------------------------
|-- 付款功能
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/payment/ecpay/{order}', [PaymentController::class, 'payWithEcpay']);
    Route::post('/payment/linepay/{order}', [PaymentController::class, 'payWithLinePay']);
});

Route::post('/payment/ecpay/callback', [PaymentController::class, 'ecpayCallback']); 
Route::post('/payment/ecpay/order_result', [PaymentController::class, 'ecpayCallback']); 
Route::get('/payment/linepay/confirm', [PaymentController::class, 'linePayConfirm'])->name('linepay.confirm');
Route::get('/payment/linepay/cancel', [PaymentController::class, 'linePayCancel'])->name('linepay.cancel');

/*
|--------------------------------------------------------------------------
| 後台
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'role:admin,merchant'])->prefix('admin')->group(function () {

    Route::get('/products', [AdminProductController::class, 'index']);
    Route::post('/products', [AdminProductController::class, 'store']);
    Route::put('/products/{id}', [AdminProductController::class, 'update']);
    Route::patch('/products/{id}/toggle', [AdminProductController::class, 'toggle']);
    Route::delete('/products/{id}', [AdminProductController::class, 'destroy']);
});

