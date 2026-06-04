<?php

use App\Http\Controllers\Auth\CustomerAuthController;
use App\Http\Controllers\BonusCodeController;
use App\Http\Controllers\CheckInController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DailyIncomeClaimController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RechargeController;
use App\Http\Controllers\SalaryController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\WithdrawController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [CustomerAuthController::class, 'showLogin'])
        ->name('login');

    Route::post('/login', [CustomerAuthController::class, 'login'])
        ->name('login.store');

    Route::get('/register', [CustomerAuthController::class, 'showRegister'])
        ->name('register');

    Route::post('/register', [CustomerAuthController::class, 'register'])
        ->name('register.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', DashboardController::class)
        ->name('dashboard');

    Route::get('/products', [ProductController::class, 'index'])
        ->name('products.index');

    Route::post('/products/{product}/purchase', [ProductController::class, 'purchase'])
        ->name('products.purchase');

    Route::post('/daily-income/{userProduct}/claim', [DailyIncomeClaimController::class, 'store'])
        ->name('daily-income.claim');

    Route::get('/recharge', [RechargeController::class, 'index'])
        ->name('recharge.index');

    Route::get('/recharge/create', [RechargeController::class, 'create'])
        ->name('recharge.create');

    Route::post('/recharge', [RechargeController::class, 'store'])
        ->name('recharge.store');

    Route::get('/recharge/{rechargeRequest}', [RechargeController::class, 'show'])
        ->name('recharge.show');

    Route::get('/withdraw', [WithdrawController::class, 'index'])
        ->name('withdraw.index');

    Route::get('/withdraw/create', [WithdrawController::class, 'create'])
        ->name('withdraw.create');

    Route::post('/withdraw', [WithdrawController::class, 'store'])
        ->name('withdraw.store');

    Route::get('/withdraw/{withdrawRequest}', [WithdrawController::class, 'show'])
        ->name('withdraw.show');

    Route::get('/team', TeamController::class)
        ->name('team.index');

    Route::get('/wallet', [WalletController::class, 'index'])
        ->name('wallet.index');

    Route::get('/wallet/transactions/{walletTransaction}', [WalletController::class, 'show'])
        ->name('wallet.transactions.show');


    Route::get('/bonus-codes', [BonusCodeController::class, 'index'])
        ->name('bonus-codes.index');

    Route::post('/bonus-codes/{bonusCode}/claim', [BonusCodeController::class, 'claim'])
        ->name('bonus-codes.claim');

    Route::get('/salary', [SalaryController::class, 'index'])
        ->name('salary.index');

    Route::post('/salary/{type}/claim', [SalaryController::class, 'claim'])
        ->whereIn('type', ['weekly', 'monthly', 'annual'])
        ->name('salary.claim');

    Route::get('/profile', [ProfileController::class, 'index'])
        ->name('profile.index');

    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])
        ->name('profile.avatar.update');

    Route::delete('/profile/avatar', [ProfileController::class, 'deleteAvatar'])
        ->name('profile.avatar.delete');
        
    Route::get('/check-in', [CheckInController::class, 'index'])
        ->name('check-in.index');

    Route::post('/check-in/claim', [CheckInController::class, 'claim'])
        ->name('check-in.claim');

    Route::post('/logout', [CustomerAuthController::class, 'logout'])
        ->name('logout');
});
Route::post('/locale', LocaleController::class)
    ->name('locale.update');

Route::get('/contact', ContactController::class)
    ->name('contact.index');