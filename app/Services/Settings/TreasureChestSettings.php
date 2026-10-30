<?php

namespace App\Services\Settings;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TreasureChestSettings
{
    public const RULES = 'treasure_chest_rules';
    public const SHOW_REWARDS = 'treasure_chest_show_rewards';

    public function rules(): array
    {
        $saved = Setting::getValue(self::RULES);
        if ($saved !== null) {
            return json_decode($saved, true, 512, JSON_THROW_ON_ERROR);
        }

        return [
            'prizes' => config('treasure_chest.prizes'),
            'products' => array_map(fn (array $product): array => $product + [
                'match_weekend_price' => str_contains($product['code'], '-'),
            ], config('treasure_chest.products')),
        ];
    }

    public function formData(): array
    {
        return $this->rules() + ['show_rewards' => Setting::bool(self::SHOW_REWARDS)];
    }

    public function save(array $data): void
    {
        $validator = Validator::make(['data' => $data], [
            'data.show_rewards' => ['required', 'boolean'],
            'data.prizes' => ['required', 'array:1,2,3,4,5,6,7,8,9', 'size:9'],
            'data.prizes.*' => ['required', 'array:threshold,amount,keys,discount_percent'],
            'data.prizes.*.threshold' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'data.prizes.*.amount' => ['required', 'numeric', 'min:0', 'max:99999999999999.99', 'decimal:0,2'],
            'data.prizes.*.keys' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'data.prizes.*.discount_percent' => ['required', 'numeric', 'between:0,100', 'decimal:0,2'],
            'data.products' => ['present', 'array', 'max:100'],
            'data.products.*' => ['required', 'array:code,price,keys,match_weekend_price'],
            'data.products.*.code' => ['required', 'string', 'max:100'],
            'data.products.*.price' => ['required', 'numeric', 'min:0', 'max:99999999999999.99', 'decimal:0,2'],
            'data.products.*.keys' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'data.products.*.match_weekend_price' => ['required', 'boolean'],
        ]);
        $validator->after(function ($validator) use ($data): void {
            $codes = [];
            $prices = [];
            foreach ($data['products'] ?? [] as $index => $product) {
                $code = strtoupper(str_replace('-', '', trim($product['code'] ?? '')));
                if (in_array($code, $codes, true)) {
                    $validator->errors()->add("data.products.$index.code", __('treasure_chest.duplicate_product'));
                }
                $codes[] = $code;
                if ($product['match_weekend_price'] ?? false) {
                    $price = (float) ($product['price'] ?? 0);
                    if (in_array($price, $prices, true)) {
                        $validator->errors()->add("data.products.$index.price", __('treasure_chest.duplicate_price'));
                    }
                    $prices[] = $price;
                }
            }
            foreach ($data['prizes'] ?? [] as $number => $prize) {
                if ((float) ($prize['amount'] ?? 0) <= 0 && (int) ($prize['keys'] ?? 0) <= 0 && (float) ($prize['discount_percent'] ?? 0) <= 0) {
                    $validator->errors()->add("data.prizes.$number.amount", __('treasure_chest.reward_required'));
                }
            }
        });
        $validated = $validator->validate()['data'];
        $rules = [
            'prizes' => array_map(fn (array $prize): array => [
                'threshold' => (int) $prize['threshold'], 'amount' => $prize['amount'],
                'keys' => (int) $prize['keys'], 'discount_percent' => $prize['discount_percent'],
            ], $validated['prizes']),
            'products' => array_values(array_map(fn (array $product): array => [
                'code' => trim($product['code']), 'price' => $product['price'],
                'keys' => (int) $product['keys'], 'match_weekend_price' => (bool) $product['match_weekend_price'],
            ], $validated['products'])),
        ];
        DB::transaction(function () use ($rules, $validated): void {
            Setting::setValue(self::RULES, json_encode($rules, JSON_THROW_ON_ERROR));
            Setting::setValue(self::SHOW_REWARDS, (bool) $validated['show_rewards']);
        });
    }
}
