<?php

namespace App\Services;

use App\Models\AdminUser;
use App\Models\BankAccountReviewRequest;
use App\Models\User;
use App\Models\UserBankAccount;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class BankAccountReviewService
{
    /**
     * @param  array<int, UploadedFile>  $idPhotos
     */
    public function submit(User $user, array $data, array $idPhotos = []): BankAccountReviewRequest
    {
        $newPaths = $this->storeIdPhotos($user, $idPhotos);
        $oldPaths = [];

        try {
            $reviewRequest = DB::transaction(function () use ($user, $data, $newPaths, &$oldPaths): BankAccountReviewRequest {
                $lockedUser = User::query()
                    ->whereKey($user->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedUser->bankAccount()->where('status', UserBankAccount::STATUS_ACTIVE)->exists()) {
                    throw new RuntimeException(__('bank_account.already_bound'));
                }

                $latestReview = BankAccountReviewRequest::query()
                    ->where('user_id', $lockedUser->id)
                    ->latest('id')
                    ->lockForUpdate()
                    ->first();

                if ($latestReview?->isPending()) {
                    throw new RuntimeException(__('bank_account.under_review'));
                }

                if ($latestReview?->isApproved()) {
                    throw new RuntimeException(__('bank_account.already_bound'));
                }

                if ($newPaths === [] && ! $latestReview?->isRejected()) {
                    throw new RuntimeException(__('bank_account.id_photos_required'));
                }

                $paths = $newPaths;

                if ($latestReview?->isRejected()) {
                    $oldPaths = $latestReview->id_photo_paths ?? [];
                    $paths = $newPaths !== [] ? $newPaths : $oldPaths;

                    $latestReview->forceFill([
                        ...$this->reviewData($data, $paths),
                        'status' => BankAccountReviewRequest::STATUS_PENDING,
                        'submitted_at' => now(),
                        'reviewed_by' => null,
                        'reviewed_at' => null,
                        'admin_remark' => null,
                    ])->save();

                    return $latestReview->fresh();
                }

                return BankAccountReviewRequest::query()->create([
                    'user_id' => $lockedUser->id,
                    ...$this->reviewData($data, $paths),
                    'status' => BankAccountReviewRequest::STATUS_PENDING,
                    'submitted_at' => now(),
                ]);
            });
        } catch (Throwable $exception) {
            $this->deleteIdPhotos($newPaths);

            throw $exception;
        }

        if ($newPaths !== []) {
            $this->deleteIdPhotos($oldPaths);
        }

        return $reviewRequest;
    }

    public function approve(
        BankAccountReviewRequest $reviewRequest,
        ?AdminUser $adminUser,
        ?string $adminRemark = null,
    ): UserBankAccount {
        return DB::transaction(function () use ($reviewRequest, $adminUser, $adminRemark): UserBankAccount {
            $lockedRequest = BankAccountReviewRequest::query()
                ->whereKey($reviewRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedRequest->isPending()) {
                throw new RuntimeException(__('bank_account.only_pending_can_be_reviewed'));
            }

            $user = $lockedRequest->user()
                ->lockForUpdate()
                ->firstOrFail();

            if ($user->bankAccount()->where('status', UserBankAccount::STATUS_ACTIVE)->exists()) {
                throw new RuntimeException(__('bank_account.already_bound'));
            }

            if (UserBankAccount::query()->where('bank_account_no', $lockedRequest->bank_account_no)->exists()) {
                throw new RuntimeException(__('bank_account.account_no_already_bound'));
            }

            $bankAccount = UserBankAccount::query()->create([
                'user_id' => $user->id,
                'bank_account_name' => $lockedRequest->bank_account_name,
                'bank_name' => $lockedRequest->bank_name,
                'bank_account_no' => $lockedRequest->bank_account_no,
                'phone' => $lockedRequest->phone,
                'phone_verified_at' => $lockedRequest->phone_verified_at,
                'status' => UserBankAccount::STATUS_ACTIVE,
                'is_locked' => true,
                'bound_at' => now(),
                'meta' => [
                    'source' => 'bank_account_review',
                    'bank_account_review_request_id' => $lockedRequest->id,
                    'approved_by' => $adminUser?->id,
                ],
            ]);

            $lockedRequest->forceFill([
                'status' => BankAccountReviewRequest::STATUS_APPROVED,
                'reviewed_by' => $adminUser?->id,
                'reviewed_at' => now(),
                'admin_remark' => $adminRemark,
            ])->save();

            return $bankAccount;
        });
    }

    public function reject(
        BankAccountReviewRequest $reviewRequest,
        ?AdminUser $adminUser,
        ?string $adminRemark = null,
    ): BankAccountReviewRequest {
        return DB::transaction(function () use ($reviewRequest, $adminUser, $adminRemark): BankAccountReviewRequest {
            $lockedRequest = BankAccountReviewRequest::query()
                ->whereKey($reviewRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedRequest->isPending()) {
                throw new RuntimeException(__('bank_account.only_pending_can_be_reviewed'));
            }

            $lockedRequest->forceFill([
                'status' => BankAccountReviewRequest::STATUS_REJECTED,
                'reviewed_by' => $adminUser?->id,
                'reviewed_at' => now(),
                'admin_remark' => $adminRemark,
            ])->save();

            return $lockedRequest;
        });
    }

    private function reviewData(array $data, array $paths): array
    {
        return [
            'bank_account_name' => $data['bank_account_name'],
            'bank_name' => $data['bank_name'],
            'bank_account_no' => $data['bank_account_no'],
            'phone' => $data['phone'],
            'phone_verified_at' => $data['phone_verified_at'] ?? now(),
            'id_photo_paths' => $paths,
            'meta' => $data['meta'] ?? null,
        ];
    }

    /**
     * @param  array<int, UploadedFile>  $idPhotos
     * @return array<int, string>
     */
    private function storeIdPhotos(User $user, array $idPhotos): array
    {
        return collect($idPhotos)
            ->map(fn (UploadedFile $photo): string => $photo->store(
                'bank-account-review-ids/'.$user->id,
                'local',
            ))
            ->all();
    }

    /** @param array<int, string> $paths */
    private function deleteIdPhotos(array $paths): void
    {
        if ($paths !== []) {
            Storage::disk('local')->delete($paths);
        }
    }
}
