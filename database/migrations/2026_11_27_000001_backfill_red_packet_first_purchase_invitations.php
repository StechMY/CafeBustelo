<?php

use App\Services\RedPacketService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        app(RedPacketService::class)->backfillFirstPurchaseInvitations();
    }

    public function down(): void
    {
        // Awarded chances may already have been used for draws.
    }
};
