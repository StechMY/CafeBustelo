<?php

namespace App\Filament\Resources\BonusCodes\Pages;

use App\Filament\Resources\BonusCodes\BonusCodeResource;
use App\Models\BonusCode;
use App\Models\User;
use App\Services\BonusCodeService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateBonusCode extends CreateRecord
{
    protected static string $resource = BonusCodeResource::class;

    protected array $createdBonusCodeIds = [];

    protected function handleRecordCreation(array $data): Model
    {
        $ownerUserIds = $this->form->getRawState()['owner_user_ids'] ?? [];

        if (empty($ownerUserIds)) {
            throw new \RuntimeException(__('filament.please_select_at_least_one_user'));
        }

        $adminUser = Filament::auth()->user();

        return DB::transaction(function () use ($data, $ownerUserIds, $adminUser): BonusCode {
            $firstBonusCode = null;

            foreach ($ownerUserIds as $ownerUserId) {
                $owner = User::query()->findOrFail($ownerUserId);

                $code = blank($data['code'] ?? null)
                    ? app(BonusCodeService::class)->generateUniqueCode($data['type'] ?? BonusCode::TYPE_ADMIN)
                    : strtoupper(trim($data['code']));

                if (count($ownerUserIds) > 1 && filled($data['code'] ?? null)) {
                    $code = app(BonusCodeService::class)->generateUniqueCode($data['type'] ?? BonusCode::TYPE_ADMIN);
                }

                $bonusCode = BonusCode::query()->create([
                    'code' => $code,
                    'owner_user_id' => $owner->id,
                    'issued_by_admin_user_id' => $adminUser?->id,
                    'type' => $data['type'] ?? BonusCode::TYPE_ADMIN,
                    'amount' => $data['amount'],
                    'status' => $data['status'] ?? BonusCode::STATUS_UNUSED,
                    'expires_at' => $data['expires_at'] ?? null,
                    'remark' => $data['remark'] ?? null,
                    'meta' => $data['meta'] ?? null,
                ]);

                $this->createdBonusCodeIds[] = $bonusCode->id;

                if (! $firstBonusCode) {
                    $firstBonusCode = $bonusCode;
                }
            }

            return $firstBonusCode;
        });
    }

    protected function afterCreate(): void
    {
        Notification::make()
            ->title(__('filament.bonus_codes_distributed'))
            ->body(__('filament.bonus_codes_created_successfully', ['count' => count($this->createdBonusCodeIds)]))
            ->success()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
