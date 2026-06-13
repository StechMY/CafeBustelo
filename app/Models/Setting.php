<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $value = static::query()
            ->where('key', $key)
            ->value('value');

        return $value !== null ? $value : $default;
    }

    public static function setValue(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value]
        );
    }

    public static function bool(string $key, bool $default = false): bool
    {
        return filter_var(static::getValue($key, $default ? '1' : '0'), FILTER_VALIDATE_BOOLEAN);
    }

    public static function decimal(string $key, float $default = 0): float
    {
        return (float) static::getValue($key, $default);
    }
}