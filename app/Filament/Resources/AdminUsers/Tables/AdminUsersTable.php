<?php

namespace App\Filament\Resources\AdminUsers\Tables;

use App\Filament\Resources\AdminUsers\AdminUserResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\AdminUser;
use App\Models\RechargeRequest;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use App\Models\User;
use App\Models\Wallet;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament.name'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('username')
                    ->label(__('filament.username'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('roles.name')
                    ->label(__('filament.roles'))
                    ->badge()
                    ->separator(',')
                    ->searchable(),

                TextColumn::make('managedUser.username')
                    ->label(__('filament.managed_referral_user'))
                    ->placeholder('-')
                    ->searchable()
                    ->toggleable(),

                ToggleColumn::make('status')
                    ->label(__('filament.active'))
                    ->getStateUsing(fn(AdminUser $record): bool => $record->status === 'active')
                    ->updateStateUsing(function (AdminUser $record, bool $state): void {
                        if ($record->hasRole('super_admin') && !auth('admin')->user()?->hasRole('super_admin')) {
                            Notification::make()
                                ->title(__('filament.you_cannot_update_super_admin_status'))
                                ->danger()
                                ->send();

                            return;
                        }

                        if ($record->id === auth('admin')->id() && !$state) {
                            Notification::make()
                                ->title(__('filament.you_cannot_disable_your_own_account'))
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->update([
                            'status' => $state ? 'active' : 'inactive',
                        ]);

                        Notification::make()
                            ->title(__('filament.admin_status_updated'))
                            ->success()
                            ->send();
                    }),

                TextColumn::make('last_login_ip')
                    ->label(__('filament.last_login_ip'))
                    ->placeholder('-')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('last_login_at')
                    ->label(__('filament.last_login_at'))
                    ->dateTime()
                    ->sortable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label(__('filament.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('createUserAccess')
                    ->label(__('filament.create_user_access'))
                    ->icon('heroicon-o-link')
                    ->color('success')
                    ->visible(function (AdminUser $record): bool {
                        return auth('admin')->user()?->hasRole('super_admin')
                            && !$record->hasRole('super_admin');
                    })
                    ->form([
                        TextInput::make('password')
                            ->label(__('filament.user_password'))
                            ->password()
                            ->revealable()
                            ->helperText(__('filament.leave_blank_to_generate_password'))
                            ->maxLength(255),

                        TextInput::make('password_confirmation')
                            ->label(__('filament.password_confirmation'))
                            ->password()
                            ->revealable()
                            ->same('password')
                            ->dehydrated(false),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading(__('filament.create_user_access'))
                    ->modalDescription(__('filament.create_user_access_desc'))
                    ->action(function (AdminUser $record, array $data): void {
                        $created = false;
                        $temporaryPassword = null;

                        DB::transaction(function () use ($record, $data, &$created, &$temporaryPassword): void {
                            $user = User::query()
                                ->where('username', $record->username)
                                ->lockForUpdate()
                                ->first();

                            if (!$user) {
                                $temporaryPassword = filled($data['password'] ?? null)
                                    ? $data['password']
                                    : Str::password(12);

                                $user = User::query()->create([
                                    'name' => $record->name ?: $record->username,
                                    'username' => $record->username,
                                    'password' => Hash::make($temporaryPassword),
                                    'referral_code' => self::generateUniqueReferralCode(),
                                    'status' => 'active',
                                    'is_virtual' => false,
                                    'order_status' => true,
                                    'withdraw_status' => true,
                                    'weekly_salary_status' => true,
                                    'monthly_salary_status' => true,
                                    'annual_salary_status' => true,
                                    'check_in_status' => true,
                                    'accumulated_recharge' => 0,
                                ]);

                                Wallet::query()->firstOrCreate([
                                    'user_id' => $user->id,
                                    'type' => Wallet::TYPE_RECHARGE,
                                ], [
                                    'balance' => 0,
                                    'locked_balance' => 0,
                                ]);

                                Wallet::query()->firstOrCreate([
                                    'user_id' => $user->id,
                                    'type' => Wallet::TYPE_WITHDRAWABLE,
                                ], [
                                    'balance' => 0,
                                    'locked_balance' => 0,
                                ]);

                                $created = true;
                            }

                            $record->update([
                                'managed_user_id' => $user->id,
                            ]);
                        });

                        if ($created) {
                            Notification::make()
                                ->title(__('filament.user_access_created_and_linked'))
                                ->body(__('filament.temporary_user_password') . ': ' . $temporaryPassword)
                                ->success()
                                ->persistent()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title(__('filament.existing_user_access_linked'))
                            ->success()
                            ->send();
                    }),
                Action::make('viewTeam')
                    ->label(__('filament.team'))
                    ->icon('heroicon-o-users')
                    ->visible(fn (AdminUser $record): bool => self::canViewTeam($record))
                    ->modalHeading(fn (AdminUser $record): string => __('filament.team_of') . $record->username)
                    ->modalWidth('7xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('filament.close'))
                    ->modalContent(function (AdminUser $record) {
                        abort_unless(self::canViewTeam($record), 403);

                        return self::teamView($record->managedUser);
                    }),
                ViewAction::make(),

                EditAction::make()
                    ->visible(function (AdminUser $record): bool {
                        if (auth('admin')->user()?->hasRole('super_admin')) {
                            return true;
                        }

                        return !$record->hasRole('super_admin');
                    }),

                DeleteAction::make()
                    ->visible(function (AdminUser $record): bool {
                        if ($record->id === auth('admin')->id()) {
                            return false;
                        }

                        if ($record->hasRole('super_admin')) {
                            return false;
                        }

                        return auth('admin')->user()?->hasRole('super_admin') ?? false;
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn(): bool => auth('admin')->user()?->hasRole('super_admin') ?? false),
                ]),
            ]);
    }

    private static function canViewTeam(AdminUser $record): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof AdminUser
            && $record->managedUser !== null
            && AdminUserResource::canView($record)
            && UserResource::canViewAny()
            && UserResource::canView($record->managedUser)
            && (! $admin->hasDownlineRestriction()
                || (int) $admin->managed_user_id === (int) $record->managed_user_id);
    }

    private static function teamView(User $root): \Illuminate\Contracts\View\View
    {
        $firstRecharge = RechargeRequest::query()
            ->whereColumn('user_id', 'users.id')
            ->where('status', 'approved')
            ->orderByRaw('reviewed_at IS NULL')
            ->orderBy('reviewed_at')
            ->orderBy('id')
            ->limit(1);

        $query = User::query()
            ->select(['id', 'referrer_id', 'username', 'name', 'status', 'is_virtual'])
            ->addSelect([
                'first_recharge_amount' => (clone $firstRecharge)->select('amount'),
                'first_recharge_at' => (clone $firstRecharge)->select('reviewed_at'),
            ]);
        $root = (clone $query)->findOrFail($root->id);
        $members = collect();
        $visited = [$root->id];
        $parents = [$root->id];

        // Query one level at a time, retaining unpaid parents and guarding cycles.
        while ($parents !== []) {
            $children = (clone $query)->whereIn('referrer_id', $parents)
                ->whereNotIn('id', $visited)->orderBy('username')->orderBy('id')->get();
            $parents = $children->modelKeys();
            array_push($visited, ...$parents);
            $members = $members->concat($children);
        }

        return view('filament.resources.admin-users.team-tree', [
            'root' => $root,
            'childrenByParent' => $members->groupBy('referrer_id'),
            'memberCount' => $members->count(),
            'rechargedCount' => $members->whereNotNull('first_recharge_amount')->count(),
        ]);
    }

    private static function generateUniqueReferralCode(): string
    {
        do {
            $code = strtoupper(\Illuminate\Support\Str::random(8));
        } while (\App\Models\User::query()->where('referral_code', $code)->exists());

        return $code;
    }
}
