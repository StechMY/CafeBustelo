<?php

namespace App\Support;

use Illuminate\Support\Str;

class BankAccountNameMatcher
{
    public static function normalize(string $name): string
    {
        $asciiName = Str::lower(Str::ascii(Str::squish($name)));
        $normalized = preg_replace('/[^a-z0-9]+/', '', $asciiName) ?? '';

        if ($normalized !== '') {
            return $normalized;
        }

        return preg_replace('/[^\p{L}\p{N}]+/u', '', Str::lower(Str::squish($name))) ?? '';
    }

    public static function matchHash(string $name): string
    {
        return hash('sha256', self::normalize($name));
    }

    public static function legacyUniqueHash(int $accountId, string $matchHash): string
    {
        return hash('sha256', "legacy-duplicate:{$accountId}:{$matchHash}");
    }
}
