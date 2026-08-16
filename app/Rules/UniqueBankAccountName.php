<?php

namespace App\Rules;

use App\Models\UserBankAccount;
use App\Support\BankAccountNameMatcher;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueBankAccountName implements ValidationRule
{
    public function __construct(
        private readonly ?int $ignoreAccountId = null,
        private readonly ?string $currentMatchHash = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $matchHash = BankAccountNameMatcher::matchHash((string) $value);

        if ($this->currentMatchHash !== null && hash_equals($this->currentMatchHash, $matchHash)) {
            return;
        }

        $exists = UserBankAccount::query()
            ->where('bank_account_name_match_hash', $matchHash)
            ->when(
                $this->ignoreAccountId !== null,
                fn ($query) => $query->whereKeyNot($this->ignoreAccountId),
            )
            ->exists();

        if ($exists) {
            $fail(__('bank_account.name_already_bound'));
        }
    }
}
