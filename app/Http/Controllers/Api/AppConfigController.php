<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Wallet;
use App\Support\Api\ApiFormatter;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

class AppConfigController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return ApiResponse::success([
            'app_name' => config('app.name'),
            'currency' => config('app.currency'),
            'locale' => app()->getLocale(),
            'supported_locales' => ['en', 'vi'],
            'wallet_types' => [
                Wallet::TYPE_RECHARGE => ApiFormatter::walletTypeLabel(Wallet::TYPE_RECHARGE),
                Wallet::TYPE_WITHDRAWABLE => ApiFormatter::walletTypeLabel(Wallet::TYPE_WITHDRAWABLE),
            ],
            'user_status_labels' => [
                'active' => __('filament.active'),
                'inactive' => __('filament.inactive'),
                'suspended' => __('filament.suspended'),
            ],
            'product_type_labels' => collect([
                Product::TYPE_NORMAL,
                Product::TYPE_UPGRADE_DAY,
                Product::TYPE_LIMITED_TIME,
                Product::TYPE_INVITE_DAY,
                Product::TYPE_WEEKEND_SPECIAL,
                Product::TYPE_HOURLY,
            ])->mapWithKeys(fn (string $type): array => [$type => ApiFormatter::productTypeLabel($type)]),
            'modules' => [
                'dashboard' => true,
                'products' => true,
                'wallet' => true,
                'recharge' => true,
                'withdraw' => true,
                'team' => true,
                'check_in' => true,
                'salary' => true,
                'bonus_codes' => true,
                'points' => true,
                'svip' => true,
            ],
        ]);
    }
}
