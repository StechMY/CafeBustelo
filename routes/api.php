<?php

use App\Http\Controllers\Api\AppConfigController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BonusCodeController;
use App\Http\Controllers\Api\CalculatorController;
use App\Http\Controllers\Api\CheckInController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DownlineProductController;
use App\Http\Controllers\Api\MyTeamController;
use App\Http\Controllers\Api\PointsController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RechargeController;
use App\Http\Controllers\Api\SalaryController;
use App\Http\Controllers\Api\SvipController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\WithdrawController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::get('/app-config', AppConfigController::class)->name('app-config');
    Route::get('/contact', ContactController::class)->name('contact');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/me', [AuthController::class, 'me'])->name('me');

        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
        Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar.update');
        Route::delete('/profile/avatar', [ProfileController::class, 'deleteAvatar'])->name('profile.avatar.delete');

        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::post('/products/{product}/purchase', [ProductController::class, 'purchase'])->name('products.purchase');
        Route::get('/user-products', [ProductController::class, 'userProducts'])->name('user-products.index');
        Route::post('/user-products/{userProduct}/claim', [ProductController::class, 'claim'])->name('user-products.claim');

        Route::get('/wallet', [WalletController::class, 'index'])->name('wallet.index');
        Route::get('/wallet/transactions', [WalletController::class, 'transactions'])->name('wallet.transactions');

        Route::get('/recharge', [RechargeController::class, 'index'])->name('recharge.index');
        Route::post('/recharge', [RechargeController::class, 'store'])->name('recharge.store');

        Route::get('/withdraw', [WithdrawController::class, 'index'])->name('withdraw.index');
        Route::post('/withdraw', [WithdrawController::class, 'store'])->name('withdraw.store');

        Route::get('/team', [MyTeamController::class, 'index'])->name('team.index');

        Route::get('/check-in', [CheckInController::class, 'index'])->name('check-in.index');
        Route::post('/check-in', [CheckInController::class, 'claim'])->name('check-in.claim');

        Route::get('/salary', [SalaryController::class, 'index'])->name('salary.index');
        Route::post('/salary/claim', [SalaryController::class, 'claim'])->name('salary.claim');

        Route::get('/bonus-codes', [BonusCodeController::class, 'index'])->name('bonus-codes.index');
        Route::post('/bonus-codes/redeem', [BonusCodeController::class, 'redeem'])->name('bonus-codes.redeem');

        Route::get('/calculator', CalculatorController::class)->name('calculator');

        Route::get('/points', [PointsController::class, 'index'])->name('points.index');
        Route::post('/points/redeem', [PointsController::class, 'redeem'])->name('points.redeem');

        Route::get('/downline-products', [DownlineProductController::class, 'index'])->name('downline-products.index');

        Route::get('/svip', [SvipController::class, 'index'])->name('svip.index');
        Route::post('/svip/{svipPackage}/purchase', [SvipController::class, 'purchase'])->name('svip.purchase');
    });
});
