<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_bank_accounts', function (Blueprint $table): void {
            $table->dropUnique('user_bank_accounts_bank_account_name_unique');
            $table->dropIndex('bank_account_name_match_hash_idx');
            $table->dropUnique('bank_account_name_unique_hash_unique');
            $table->dropColumn([
                'bank_account_name_match_hash',
                'bank_account_name_unique_hash',
            ]);
        });

        Schema::create('bank_account_review_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('bank_account_name', 150);
            $table->string('bank_name', 100);
            $table->string('bank_account_no', 100);
            $table->string('phone', 40);
            $table->timestamp('phone_verified_at')->nullable();
            $table->json('id_photo_paths');
            $table->string('status', 32)->default('pending');
            $table->timestamp('submitted_at');
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('admin_users')
                ->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('admin_remark')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'submitted_at']);
            $table->index('bank_account_no');
        });

        $permissionIds = collect([
            'ViewAny:BankAccountReviewRequest',
            'View:BankAccountReviewRequest',
            'Update:BankAccountReviewRequest',
        ])->map(function (string $name): int {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name, 'guard_name' => 'admin'],
                ['updated_at' => now(), 'created_at' => now()],
            );

            return (int) DB::table('permissions')
                ->where('name', $name)
                ->where('guard_name', 'admin')
                ->value('id');
        });

        $superAdminRoleId = DB::table('roles')
            ->where('name', 'super_admin')
            ->where('guard_name', 'admin')
            ->value('id');

        if ($superAdminRoleId) {
            DB::table('role_has_permissions')->insertOrIgnore(
                $permissionIds->map(fn (int $permissionId): array => [
                    'permission_id' => $permissionId,
                    'role_id' => $superAdminRoleId,
                ])->all(),
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_account_review_requests');

        DB::table('permissions')
            ->where('guard_name', 'admin')
            ->whereIn('name', [
                'ViewAny:BankAccountReviewRequest',
                'View:BankAccountReviewRequest',
                'Update:BankAccountReviewRequest',
            ])
            ->delete();
    }
};
