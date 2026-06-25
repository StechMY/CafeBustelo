<?php

namespace App\Support\Api;

use Illuminate\Database\Eloquent\Model;

class DisplayValue
{
    public static function resolve(?Model $model, string $overrideField, mixed $rawValue): array
    {
        $overrideValue = $model?->getAttribute($overrideField);
        $isOverridden = $overrideValue !== null && $overrideValue !== '';

        return [
            'raw' => $rawValue,
            'display' => $isOverridden ? $overrideValue : $rawValue,
            'is_overridden' => $isOverridden,
            'override_source' => $overrideField,
        ];
    }

    public static function moneyFields(Model $model, string $key, string $overrideField, mixed $rawValue): array
    {
        $value = self::resolve($model, $overrideField, ApiFormatter::money($rawValue));
        $display = ApiFormatter::money($value['display']);

        return [
            $key => ApiFormatter::money($value['raw']),
            'display_'.$key => $display,
            'is_'.$key.'_overridden' => $value['is_overridden'],
            'formatted_'.$key => ApiFormatter::formattedMoney($display),
        ];
    }

    public static function integerFields(Model $model, string $key, string $overrideField, mixed $rawValue): array
    {
        $value = self::resolve($model, $overrideField, (int) $rawValue);

        return [
            $key => (int) $value['raw'],
            'display_'.$key => (int) $value['display'],
            'is_'.$key.'_overridden' => $value['is_overridden'],
        ];
    }
}
