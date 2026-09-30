<?php

namespace App\Support\Api;

use App\Models\BankAccountReviewRequest;
use App\Models\BonusCode;
use App\Models\CheckInRecord;
use App\Models\Contact;
use App\Models\PointTransaction;
use App\Models\Product;
use App\Models\RechargeMethod;
use App\Models\RechargeRequest;
use App\Models\SalaryClaim;
use App\Models\SvipPackage;
use App\Models\SvipPurchase;
use App\Models\User;
use App\Models\UserBankAccount;
use App\Models\UserProduct;
use App\Models\VipLevel;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\WithdrawRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ApiFormatter
{
    public static function money(float|int|string|null $value): float
    {
        return round((float) ($value ?? 0), 2);
    }

    public static function formattedMoney(float|int|string|null $value): string
    {
        return config('app.currency').' '.number_format(self::money($value), 2);
    }

    public static function dateTime(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        return $value instanceof Carbon
            ? $value->toISOString()
            : Carbon::parse($value)->toISOString();
    }

    public static function user(User $user): array
    {
        $activeTeamSize = $user->activeTeamSize();
        $realVipLevel = VipLevel::query()
            ->where('status', 'active')
            ->where('required_accumulated_recharge', '<=', $user->accumulated_recharge)
            ->where('required_active_team_size', '<=', $activeTeamSize)
            ->orderByDesc('level')
            ->first();
        $displayVipLevel = $user->currentVipLevel() ?: $realVipLevel;
        $vipLevelValue = DisplayValue::resolve($user, 'display_vip_level', $realVipLevel?->level ?? 1);

        return [
            'id' => (int) $user->id,
            'username' => $user->username,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'phone_verified_at' => self::dateTime($user->phone_verified_at),
            'status' => $user->status,
            'is_virtual' => (bool) $user->is_virtual,
            'order_status' => (bool) $user->order_status,
            'withdraw_status' => (bool) $user->withdraw_status,
            'check_in_status' => (bool) $user->check_in_status,
            'referral_code' => $user->referral_code,
            'referrer_id' => $user->referrer_id ? (int) $user->referrer_id : null,
            'points_balance' => self::money($user->points_balance),
            'profile_photo_url' => $user->profile_photo_url,
            'current_vip_level_number' => (int) ($displayVipLevel?->level ?? 1),
            'current_vip_level_name' => $displayVipLevel?->name ?? 'LV1',
            'raw_current_vip_level_number' => (int) ($realVipLevel?->level ?? 1),
            'raw_current_vip_level_name' => $realVipLevel?->name ?? 'LV1',
            'is_vip_level_overridden' => $vipLevelValue['is_overridden'],
            ...DisplayValue::moneyFields($user, 'accumulated_recharge', 'display_accumulated_recharge', $user->accumulated_recharge),
            'activated_at' => self::dateTime($user->activated_at),
            'created_at' => self::dateTime($user->created_at),
            'updated_at' => self::dateTime($user->updated_at),
        ];
    }

    public static function wallet(?Wallet $wallet, string $type): array
    {
        $balance = self::money($wallet?->balance);
        $locked = self::money($wallet?->locked_balance);

        return [
            'type' => $type,
            'label' => self::walletTypeLabel($type),
            'balance' => $balance,
            'locked_balance' => $locked,
            'formatted_balance' => self::formattedMoney($balance),
            'formatted_locked_balance' => self::formattedMoney($locked),
        ];
    }

    public static function walletTypeLabel(string $type): string
    {
        return match ($type) {
            Wallet::TYPE_RECHARGE => __('filament.recharge_wallet'),
            Wallet::TYPE_WITHDRAWABLE => __('filament.withdrawable_wallet'),
            default => Str::of($type)->headline()->toString(),
        };
    }

    public static function product(Product $product, ?User $user = null): array
    {
        $price = self::money($product->price);
        $incomePerClaim = self::money($product->daily_income);
        $totalClaims = $product->effectiveTotalClaims();
        $totalIncome = self::money($incomePerClaim * $totalClaims);
        $rechargeBalance = $user ? (float) $user->recharge_wallet_balance : 0;
        $withdrawableBalance = $user ? (float) $user->withdrawable_wallet_balance : 0;
        $purchaseCount = $user
            ? UserProduct::query()
                ->where('user_id', $user->id)
                ->where('product_id', $product->id)
                ->whereIn('status', ['active', 'expired', 'completed'])
                ->count()
            : 0;
        $maxPurchase = $product->maxPurchasePerUser();
        $limitReached = $purchaseCount >= $maxPurchase;
        $isPurchasableNow = $product->isPurchasableNow();
        $meetsVipLevelRequirement = $product->meetsVipLevelRequirement($user);

        return [
            'id' => (int) $product->id,
            'code' => $product->code,
            'name' => $product->name,
            'type' => $product->product_type,
            'type_label' => self::productTypeLabel($product->product_type),
            'product_type' => $product->product_type,
            'product_type_label' => self::productTypeLabel($product->product_type),
            'price' => $price,
            'formatted_price' => self::formattedMoney($price),
            'daily_income' => $incomePerClaim,
            'income_per_claim' => $incomePerClaim,
            'duration_days' => (int) $product->duration_days,
            'claim_interval_hours' => $product->effectiveClaimIntervalHours(),
            'total_claims' => $totalClaims,
            'total_income' => $totalIncome,
            'profit' => self::money($totalIncome - $price),
            'roi' => $price > 0 ? round((($totalIncome - $price) / $price) * 100, 2) : 0,
            'extra_points' => (int) ($product->extra_points ?? 0),
            'available_days' => $product->available_days ?: [],
            'available_days_label' => self::availableDaysLabel($product),
            'available_from' => $product->available_from?->format('H:i:s'),
            'available_until' => $product->available_until?->format('H:i:s'),
            'available_until_label' => $product->available_until?->format('H:i'),
            'stock_quantity' => $product->stock_quantity,
            'daily_limit' => $product->daily_limit,
            'daily_limit_reached' => ! $product->hasDailyStock(),
            'sold_quantity' => (int) ($product->sold_quantity ?? 0),
            'remaining_stock' => $product->stock_quantity === null ? null : max(0, (int) $product->stock_quantity - (int) $product->sold_quantity),
            'image_url' => $product->image_url,
            'status' => $product->status,
            'is_purchasable_now' => $isPurchasableNow,
            'user_purchase_count' => $purchaseCount,
            'max_purchase_per_user' => $maxPurchase,
            'min_vip_level' => $product->min_vip_level,
            'meets_vip_level_requirement' => $meetsVipLevelRequirement,
            'limit_reached' => $limitReached,
            'can_afford_recharge' => $rechargeBalance >= $price,
            'can_afford_withdrawable' => $withdrawableBalance >= $price,
            'can_purchase' => $isPurchasableNow && ! $limitReached && $meetsVipLevelRequirement && (bool) $user?->canOrder(),
        ];
    }

    public static function userProduct(UserProduct $userProduct): array
    {
        $product = $userProduct->product;
        $incomePerClaim = self::money($userProduct->daily_income_snapshot);
        $totalClaims = $userProduct->effectiveTotalClaims();
        $claimed = (int) $userProduct->claimed_days;
        $missed = (int) $userProduct->missed_days;

        return [
            'id' => (int) $userProduct->id,
            'product_id' => (int) $userProduct->product_id,
            'product_name' => $product?->name ?? data_get($userProduct->meta, 'product_name_snapshot'),
            'product_code' => $product?->code ?? data_get($userProduct->meta, 'product_code_snapshot'),
            'status' => $userProduct->status,
            'price_snapshot' => self::money($userProduct->price_snapshot),
            'daily_income_snapshot' => $incomePerClaim,
            'income_per_claim' => $incomePerClaim,
            'duration_days_snapshot' => (int) $userProduct->duration_days_snapshot,
            'claim_interval_hours' => $userProduct->effectiveClaimIntervalHours(),
            'total_claims' => $totalClaims,
            'claimed_days' => $claimed,
            'claimed_count' => $claimed,
            'missed_days' => $missed,
            'remaining_claims' => max(0, $totalClaims - $claimed - $missed),
            'next_claim_at' => self::dateTime($userProduct->next_claim_at),
            'purchased_at' => self::dateTime($userProduct->purchased_at),
            'expires_at' => self::dateTime($userProduct->effectiveExpiresAt()),
            'is_claimable' => $userProduct->status === 'active'
                && $userProduct->next_claim_at
                && now()->greaterThanOrEqualTo($userProduct->next_claim_at)
                && now()->lt($userProduct->effectiveExpiresAt()),
            'is_completed' => ($claimed + $missed) >= $totalClaims,
            'meta' => $userProduct->meta,
        ];
    }

    public static function walletTransaction(WalletTransaction $transaction): array
    {
        $createdAt = $transaction->created_at
            ? $transaction->created_at->copy()->timezone(config('app.timezone'))
            : null;

        return [
            'id' => (int) $transaction->id,
            'wallet_type' => $transaction->wallet_type,
            'wallet_type_label' => self::walletTypeLabel($transaction->wallet_type),
            'type' => $transaction->type,
            'direction' => $transaction->direction,
            'amount' => self::money($transaction->amount),
            'formatted_amount' => self::formattedMoney($transaction->amount),
            'description' => $transaction->remarks,
            'balance_before' => self::money($transaction->balance_before),
            'balance_after' => self::money($transaction->balance_after),
            'remarks' => $transaction->remarks,
            'meta' => $transaction->meta,
            'created_at' => $createdAt?->toAtomString(),
            'created_at_label' => $createdAt?->format('d M Y, h:i A'),
        ];
    }

    public static function rechargeMethod(RechargeMethod $method): array
    {
        return [
            'id' => (int) $method->id,
            'name' => $method->name,
            'bank_name' => $method->bank_name,
            'account_name' => $method->account_name,
            'account_no' => $method->account_no,
            'qr_image_url' => $method->qr_image_url,
            'instructions' => $method->instructions,
        ];
    }

    public static function rechargeRequest(RechargeRequest $request): array
    {
        return [
            'id' => (int) $request->id,
            'amount' => self::money($request->amount),
            'formatted_amount' => self::formattedMoney($request->amount),
            'deposit_method' => $request->deposit_method ?: RechargeRequest::METHOD_MANUAL,
            'status' => $request->status,
            'approved' => $request->isApproved(),
            'gateway_provider' => $request->gateway_provider,
            'merchant_order_id' => $request->merchant_order_id,
            'gateway_status' => $request->gateway_payment_status,
            'payment_url' => $request->isGatewayDeposit() ? $request->gateway_payment_url : null,
            'reference_no' => $request->reference_no,
            'receipt_url' => $request->receipt_url,
            'payment_method_name' => $request->payment_method_name,
            'payment_bank_name' => $request->payment_bank_name,
            'payment_account_name' => $request->payment_account_name,
            'payment_account_no' => $request->payment_account_no,
            'admin_remark' => $request->admin_remark,
            'reviewed_at' => self::dateTime($request->reviewed_at),
            'created_at' => self::dateTime($request->created_at),
        ];
    }

    public static function withdrawRequest(WithdrawRequest $request): array
    {
        return [
            'id' => (int) $request->id,
            'amount' => self::money($request->amount),
            'fee_amount' => self::money($request->fee_amount),
            'net_amount' => self::money($request->net_amount),
            'status' => $request->status,
            'vip_level_snapshot' => $request->vip_level_snapshot,
            'min_withdraw_amount_snapshot' => self::money($request->min_withdraw_amount_snapshot),
            'withdraw_fee_percent_snapshot' => self::money($request->withdraw_fee_percent_snapshot),
            'bank_name' => $request->bank_name,
            'bank_code' => $request->bank_code,
            'bank_account_name' => $request->bank_account_name,
            'bank_account_no' => $request->bank_account_no,
            'payout_status' => $request->payout_status,
            'payout_status_label' => $request->payoutStatusLabel(),
            'gateway_release_at' => self::dateTime($request->gateway_release_at),
            'gateway_submitted_at' => self::dateTime($request->gateway_submitted_at),
            'gateway_completed_at' => self::dateTime($request->gateway_completed_at),
            'admin_remark' => $request->admin_remark,
            'reviewed_at' => self::dateTime($request->reviewed_at),
            'created_at' => self::dateTime($request->created_at),
        ];
    }

    public static function bankAccount(?UserBankAccount $account): ?array
    {
        if (! $account) {
            return null;
        }

        return [
            'id' => (int) $account->id,
            'bank_name' => $account->bank_name,
            'bank_code' => $account->bank_code,
            'bank_account_name' => $account->bank_account_name,
            'bank_account_no' => $account->bank_account_no,
            'masked_account_no' => $account->maskedAccountNo(),
            'phone' => $account->phone,
            'phone_verified_at' => self::dateTime($account->phone_verified_at),
            'status' => $account->status,
            'is_locked' => (bool) $account->is_locked,
            'bound_at' => self::dateTime($account->bound_at),
        ];
    }

    public static function bankAccountReview(?BankAccountReviewRequest $review): ?array
    {
        if (! $review) {
            return null;
        }

        return [
            'id' => (int) $review->id,
            'bank_account_name' => $review->bank_account_name,
            'bank_name' => $review->bank_name,
            'bank_code' => $review->bank_code,
            'bank_account_no_masked' => $review->maskedAccountNo(),
            'phone' => $review->phone,
            'id_photo_count' => count($review->id_photo_paths ?? []),
            'status' => $review->status,
            'admin_remark' => $review->admin_remark,
            'submitted_at' => self::dateTime($review->submitted_at),
            'reviewed_at' => self::dateTime($review->reviewed_at),
        ];
    }

    public static function bonusCode(BonusCode $code): array
    {
        return [
            'id' => (int) $code->id,
            'code' => $code->code,
            'type' => $code->type,
            'amount' => self::money($code->amount),
            'status' => $code->status,
            'remark' => $code->remark,
            'expires_at' => self::dateTime($code->expires_at),
            'used_at' => self::dateTime($code->used_at),
            'created_at' => self::dateTime($code->created_at),
        ];
    }

    public static function pointTransaction(PointTransaction $transaction): array
    {
        return [
            'id' => (int) $transaction->id,
            'direction' => $transaction->direction,
            'type' => $transaction->type,
            'points' => self::money($transaction->points),
            'balance_before' => self::money($transaction->balance_before),
            'balance_after' => self::money($transaction->balance_after),
            'remarks' => $transaction->remarks,
            'meta' => $transaction->meta,
            'created_at' => self::dateTime($transaction->created_at),
        ];
    }

    public static function svipPackage(SvipPackage $package): array
    {
        return [
            'id' => (int) $package->id,
            'name' => $package->name,
            'price' => self::money($package->price),
            'formatted_price' => self::formattedMoney($package->price),
            'duration_days' => $package->duration_days,
            'is_active' => (bool) $package->is_active,
            'description' => $package->description,
        ];
    }

    public static function svipPurchase(?SvipPurchase $purchase): ?array
    {
        if (! $purchase) {
            return null;
        }

        return [
            'id' => (int) $purchase->id,
            'svip_package_id' => (int) $purchase->svip_package_id,
            'package_name' => $purchase->package?->name ?? data_get($purchase->meta, 'package_name'),
            'amount' => self::money($purchase->amount),
            'status' => $purchase->status,
            'started_at' => self::dateTime($purchase->started_at),
            'expires_at' => self::dateTime($purchase->expires_at),
            'is_active' => $purchase->isActive(),
            'meta' => $purchase->meta,
        ];
    }

    public static function salaryClaim(SalaryClaim $claim): array
    {
        return [
            'id' => (int) $claim->id,
            'type' => $claim->type,
            'period_key' => $claim->period_key,
            'vip_level_snapshot' => $claim->vip_level_snapshot,
            'salary_amount' => self::money($claim->salary_amount),
            'team_subsidy_amount' => self::money($claim->team_subsidy_amount),
            'total_amount' => self::money($claim->total_amount),
            'claimed_at' => self::dateTime($claim->claimed_at),
            'meta' => $claim->meta,
        ];
    }

    public static function checkInRecord(CheckInRecord $record): array
    {
        return [
            'id' => (int) $record->id,
            'claim_date' => $record->claim_date?->toDateString() ?? (string) $record->claim_date,
            'vip_level_snapshot' => $record->vip_level_snapshot,
            'amount' => self::money($record->amount),
            'claimed_at' => self::dateTime($record->claimed_at),
            'meta' => $record->meta,
        ];
    }

    public static function contact(Contact $contact): array
    {
        return [
            'id' => (int) $contact->id,
            'name' => $contact->name,
            'type' => $contact->type,
            'link' => $contact->link,
            'description' => $contact->description,
            'sort_order' => (int) $contact->sort_order,
        ];
    }

    public static function vipLevel(?VipLevel $level): ?array
    {
        if (! $level) {
            return null;
        }

        return [
            'id' => (int) $level->id,
            'level' => (int) $level->level,
            'name' => $level->name,
            'position_name' => $level->position_name,
            'required_accumulated_recharge' => self::money($level->required_accumulated_recharge),
            'required_active_team_size' => (int) $level->required_active_team_size,
            'daily_check_in_amount' => self::money($level->daily_check_in_amount),
            'weekly_salary' => self::money($level->weekly_salary),
            'monthly_salary' => self::money($level->monthly_salary),
            'annual_salary' => self::money($level->annual_salary),
            'team_subsidy' => self::money($level->team_subsidy),
            'min_withdraw_amount' => self::money($level->min_withdraw_amount),
            'withdraw_fee_percent' => self::money($level->withdraw_fee_percent),
        ];
    }

    public static function productTypeLabel(?string $type): string
    {
        if (! $type) {
            return '';
        }

        return Product::typeLabel($type);
    }

    public static function availableDaysLabel(Product $product): string
    {
        $days = $product->available_days ?: [];

        if ($days === []) {
            return __('filament.everyday');
        }

        return collect($days)
            ->map(fn ($day): string => Product::weekdayOptions()[(int) $day] ?? (string) $day)
            ->join(', ');
    }
}
