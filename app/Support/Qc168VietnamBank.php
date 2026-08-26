<?php

namespace App\Support;

class Qc168VietnamBank
{
    public static function options(): array
    {
        return collect(config('vietnam_banks.banks', []))
            ->keys()
            ->mapWithKeys(fn (string $name): array => [$name => $name])
            ->all();
    }

    public static function codeForName(?string $name): ?string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        $codes = [
            ...config('vietnam_banks.banks', []),
            ...config('vietnam_banks.aliases', []),
        ];

        foreach ($codes as $knownName => $code) {
            if (mb_strtolower(trim((string) $knownName)) === mb_strtolower($name)) {
                return preg_match('/^\d+$/', (string) $code) ? (string) $code : null;
            }
        }

        return null;
    }
}
