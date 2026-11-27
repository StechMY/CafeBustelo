<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ydpay_payout_return_evidence', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('withdraw_request_id')->unique()->constrained()->restrictOnDelete();
            $table->string('return_reference', 128)->nullable()->unique();
            $table->decimal('returned_amount', 18, 2)->nullable();
            $table->string('document_mime', 32)->nullable();
            $table->char('document_sha256', 64)->nullable()->unique();
            $table->mediumText('document_base64')->nullable();
            $table->timestamp('created_at');
        });

        Permission::findOrCreate('ConfirmYdPayReturn:Withdrawal', 'admin');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->where('name', 'ConfirmYdPayReturn:Withdrawal')->where('guard_name', 'admin')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Schema::dropIfExists('ydpay_payout_return_evidence');
    }
};
