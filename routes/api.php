<?php

use App\Http\Controllers\Api\AccountPasswordController;
use App\Http\Controllers\Api\AppConfigController;
use App\Http\Controllers\Api\Auth\RegisterOtpController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BankAccountController;
use App\Http\Controllers\Api\BankAccountOtpController;
use App\Http\Controllers\Api\BonusCodeController;
use App\Http\Controllers\Api\CalculatorController;
use App\Http\Controllers\Api\CheckInController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DownlineProductController;
use App\Http\Controllers\Api\GatewayRechargeController;
use App\Http\Controllers\Api\Internal\ManagementDashboardController;
use App\Http\Controllers\Api\MyTeamController;
use App\Http\Controllers\Api\PointsController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RechargeController;
use App\Http\Controllers\Api\SalaryController;
use App\Http\Controllers\Api\SvipController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\WithdrawController;
use App\Http\Controllers\GoldenEggController;
use App\Http\Controllers\TreasureChestController;
use Illuminate\Support\Facades\Route;

Route::prefix('internal/v1')
    ->name('api.internal.v1.')
    ->middleware([
        'auth:sanctum',
        'abilities:dashboard:read',
        'dashboard.api-client',
        'throttle:management-dashboard',
    ])
    ->group(function (): void {
        Route::get('/management-dashboard', ManagementDashboardController::class)
            ->name('management-dashboard');
    });

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('/register/send-otp', [RegisterOtpController::class, 'send'])
        ->middleware(['otp.audit:register', 'throttle:otp-send', 'otp.challenge:register'])
        ->name('register.otp.send');
    Route::post('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::get('/app-config', AppConfigController::class)->name('app-config');
    Route::get('/contact', ContactController::class)->name('contact');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/me', [AuthController::class, 'me'])->name('me');

        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        Route::get('/golden-egg', [GoldenEggController::class, 'index'])->name('golden-egg.index');
        Route::get('/treasure-chest', [TreasureChestController::class, 'index'])->name('treasure-chest.index');
        Route::get('/treasure-chest/history', [TreasureChestController::class, 'history'])->name('treasure-chest.history');
        Route::post('/treasure-chest/claim', [TreasureChestController::class, 'claim'])
            ->middleware('throttle:30,1')->name('treasure-chest.claim');
        Route::post('/golden-egg/draw', [GoldenEggController::class, 'draw'])
            ->middleware('throttle:30,1')->name('golden-egg.draw');

        Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
        Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar.update');
        Route::delete('/profile/avatar', [ProfileController::class, 'deleteAvatar'])->name('profile.avatar.delete');
        Route::post('/account/password/send-otp', [AccountPasswordController::class, 'sendOtp'])
            ->middleware(['otp.audit:change_password', 'throttle:otp-send', 'otp.challenge:change_password'])
            ->name('account.password.otp.send');
        Route::post('/account/password', [AccountPasswordController::class, 'update'])->name('account.password.update');

        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::post('/products/{product}/purchase', [ProductController::class, 'purchase'])->name('products.purchase');
        Route::get('/user-products', [ProductController::class, 'userProducts'])->name('user-products.index');
        Route::post('/user-products/{userProduct}/claim', [ProductController::class, 'claim'])->name('user-products.claim');

        Route::get('/wallet', [WalletController::class, 'index'])->name('wallet.index');
        Route::get('/wallet/transactions', [WalletController::class, 'transactions'])->name('wallet.transactions');

        Route::get('/recharge', [RechargeController::class, 'index'])->name('recharge.index');
        Route::post('/recharge', [RechargeController::class, 'store'])->name('recharge.store');
        Route::post('/recharge/gateway', [GatewayRechargeController::class, 'store'])->name('recharge.gateway.store');
        Route::get('/recharge/{rechargeRequest}/status', [GatewayRechargeController::class, 'status'])->name('recharge.gateway.status');

        Route::get('/withdraw', [WithdrawController::class, 'index'])->name('withdraw.index');
        Route::post('/withdraw', [WithdrawController::class, 'store'])->name('withdraw.store');
        Route::post('/bank-account/send-otp', [BankAccountOtpController::class, 'send'])
            ->middleware(['otp.audit:bank_account', 'throttle:otp-send', 'otp.challenge:bank_account'])
            ->name('bank-account.otp.send');
        Route::post('/bank-account', [BankAccountController::class, 'store'])->name('bank-account.store');

        Route::get('/team', [MyTeamController::class, 'index'])->name('team.index');
        Route::get('/my-team', [MyTeamController::class, 'index'])->name('my-team.index');
        Route::get('/my-team/{level}', [MyTeamController::class, 'level'])
            ->whereIn('level', ['b', 'c', 'd'])
            ->name('my-team.level');

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
