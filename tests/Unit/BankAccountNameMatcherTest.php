<?php

namespace Tests\Unit;

use App\Support\BankAccountNameMatcher;
use PHPUnit\Framework\TestCase;

class BankAccountNameMatcherTest extends TestCase
{
    public function test_spacing_punctuation_case_and_vietnamese_accents_are_ignored(): void
    {
        $expected = BankAccountNameMatcher::matchHash('Nguyen hoa sim');

        $this->assertSame($expected, BankAccountNameMatcher::matchHash('Nguyenhoasim'));
        $this->assertSame($expected, BankAccountNameMatcher::matchHash('NGUYEN HOA SIM.'));
        $this->assertSame($expected, BankAccountNameMatcher::matchHash('Nguyễn Hòa Sim'));
    }

    public function test_different_names_are_not_treated_as_similar(): void
    {
        $this->assertNotSame(
            BankAccountNameMatcher::matchHash('Nguyen hoa sim'),
            BankAccountNameMatcher::matchHash('Nguyễn Thị Hoa'),
        );
    }

    public function test_legacy_duplicates_keep_the_same_match_hash_but_get_distinct_unique_hashes(): void
    {
        $matchHash = BankAccountNameMatcher::matchHash('Nguyen hoa sim');

        $this->assertNotSame($matchHash, BankAccountNameMatcher::legacyUniqueHash(2, $matchHash));
        $this->assertNotSame(
            BankAccountNameMatcher::legacyUniqueHash(2, $matchHash),
            BankAccountNameMatcher::legacyUniqueHash(3, $matchHash),
        );
    }
}
