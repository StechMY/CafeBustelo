<?php

use App\Http\Controllers\AccountPasswordController;
use App\Http\Controllers\Admin\ImpersonateUserController;
use App\Http\Controllers\Auth\CustomerAuthController;
use App\Http\Controllers\Auth\RegisterOtpController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\BankAccountOtpController;
use App\Http\Controllers\BankAccountReviewDocumentController;
use App\Http\Controllers\BonusCodeController;
use App\Http\Controllers\CalculatorController;
use App\Http\Controllers\CheckInController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DailyIncomeClaimController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DownlineProductController;
use App\Http\Controllers\GatewayRechargeController;
use App\Http\Controllers\GoldenEggController;
use App\Http\Controllers\HumanGateController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MyTeamController;
use App\Http\Controllers\PaymentGateway\Qc168CallbackController;
use App\Http\Controllers\PaymentGateway\Qc168WithdrawalCallbackController;
use App\Http\Controllers\PointClaimController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RechargeController;
use App\Http\Controllers\SalaryController;
use App\Http\Controllers\SvipController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\WithdrawController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

// Route::match(['GET', 'POST'], '/debug/request-info', function (Request $request) {
//     $data = [
//         // Raw TCP/source IP seen by PHP/web server
//         'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null,

//         // Laravel interpreted IP
//         'laravel_ip' => $request->ip(),

//         // Laravel IP chain
//         'laravel_ips' => $request->ips(),

//         // Headers that may leak/provide IP information
//         'x_forwarded_for' => $request->header('X-Forwarded-For'),
//         'x_real_ip' => $request->header('X-Real-IP'),
//         'forwarded' => $request->header('Forwarded'),
//         'via' => $request->header('Via'),
//         'cf_connecting_ip' => $request->header('CF-Connecting-IP'),
//         'true_client_ip' => $request->header('True-Client-IP'),

//         'user_agent' => $request->userAgent(),

//         'all_headers' => $request->headers->all(),
//     ];

//     Log::info('DEBUG INCOMING REQUEST', $data);

//     return response()->json($data);
// });

Route::redirect('/', '/dashboard');

Route::get('/otp/challenge/{purpose}', function (string $purpose) {
    return response()->view('otp.app-challenge', compact('purpose'))
        ->header('Cache-Control', 'no-store');
})->whereIn('purpose', ['register', 'bank_account', 'change_password'])
    ->name('otp.app-challenge');

Route::post('/payment-gateway/qc168/callback', Qc168CallbackController::class)
    ->middleware('throttle:120,1')
    ->name('payment-gateway.qc168.callback');

Route::post('/payment-gateway/qc168/withdrawal/callback', Qc168WithdrawalCallbackController::class)
    ->middleware('throttle:120,1')
    ->name('payment-gateway.qc168.withdrawal.callback');

Route::get('/human-gate', [HumanGateController::class, 'show'])
    ->name('human.gate')
    ->middleware(['web', 'throttle:60,1']);

Route::post('/human-gate/verify', [HumanGateController::class, 'verify'])
    ->name('human.gate.verify')
    ->middleware(['web', 'throttle:30,1']);

Route::post('/register/send-otp', [RegisterOtpController::class, 'send'])
    ->middleware(['otp.audit:register', 'throttle:otp-send', 'otp.challenge:register'])
    ->name('register.otp.send');

Route::post('/bank-account/send-otp', [BankAccountOtpController::class, 'send'])
    ->middleware(['auth', 'otp.audit:bank_account', 'throttle:otp-send', 'otp.challenge:bank_account'])
    ->name('bank-account.otp.send');

Route::middleware(['guest', 'web', 'human.only'])->group(function (): void {
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

    Route::post('/dashboard/account-policy/agree', [DashboardController::class, 'agreeAccountPolicy'])
        ->name('dashboard.account-policy.agree');

    Route::get('/products', [ProductController::class, 'index'])
        ->name('products.index');

    Route::get('/golden-egg', [GoldenEggController::class, 'index'])->name('golden-egg.index');
    Route::post('/golden-egg/draw', [GoldenEggController::class, 'draw'])
        ->middleware('throttle:30,1')->name('golden-egg.draw');

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

    Route::post('/recharge/gateway', [GatewayRechargeController::class, 'store'])
        ->name('recharge.gateway.store');

    Route::get('/recharge/gateway/return/{rechargeRequest}', [GatewayRechargeController::class, 'showReturn'])
        ->name('recharge.gateway.return');

    Route::get('/recharge/gateway/{rechargeRequest}/status', [GatewayRechargeController::class, 'status'])
        ->name('recharge.gateway.status');

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

    // Route::get('/team', TeamController::class)
    //     ->name('team.index');

    Route::get('/my-team', [MyTeamController::class, 'index'])
        ->name('my-team.index');

    Route::get('/my-team/{level}', [MyTeamController::class, 'level'])
        ->whereIn('level', ['b', 'c', 'd'])
        ->name('my-team.level');

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

    Route::get('/settings/password', [AccountPasswordController::class, 'edit'])
        ->name('settings.password.edit');

    Route::post('/settings/password/send-otp', [AccountPasswordController::class, 'sendOtp'])
        ->middleware(['otp.audit:change_password', 'throttle:otp-send', 'otp.challenge:change_password'])
        ->name('settings.password.otp.send');

    Route::post('/settings/password', [AccountPasswordController::class, 'update'])
        ->name('settings.password.update');

    Route::get('/check-in', [CheckInController::class, 'index'])
        ->name('check-in.index');

    Route::post('/check-in/claim', [CheckInController::class, 'claim'])
        ->name('check-in.claim');

    Route::post('/svip/{package}/purchase', [SvipController::class, 'purchase'])
        ->name('svip.purchase');

    Route::get('/calculator', CalculatorController::class)
        ->name('calculator.index');

    Route::get('/downline-products', [DownlineProductController::class, 'index'])
        ->name('downline-products.index');

    Route::get('/points', [PointClaimController::class, 'index'])->name('points.index');
    Route::post('/points/redeem', [PointClaimController::class, 'redeem'])->name('points.redeem');

    Route::get('/bank-account/bind', [BankAccountController::class, 'create'])
        ->name('bank-account.create');

    Route::post('/bank-account/bind', [BankAccountController::class, 'store'])
        ->name('bank-account.store');

    Route::post('/logout', [CustomerAuthController::class, 'logout'])
        ->name('logout');
});
Route::post('/locale', LocaleController::class)
    ->name('locale.update');

Route::middleware(['web', 'human.only'])->group(function (): void {
    Route::get('/contact', ContactController::class)
        ->name('contact.index');
});

Route::middleware(['web', 'signed:relative'])
    ->get('/admin/users/{user}/impersonate', ImpersonateUserController::class)
    ->name('admin.users.impersonate');

Route::middleware(['web', 'auth:admin'])
    ->get(
        '/admin/bank-account-review-requests/{bankAccountReviewRequest}/id-photos/{photo}',
        BankAccountReviewDocumentController::class,
    )
    ->whereNumber('photo')
    ->name('admin.bank-account-review-requests.id-photo');
