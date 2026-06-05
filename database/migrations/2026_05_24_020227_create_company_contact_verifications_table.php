<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCompanyContactVerificationsTable extends Migration
{
    public function up()
    {
        Schema::create('company_contact_verifications', function (Blueprint $table) {
            $table->id();

            $table->string('visitor_id', 100)->index();
            $table->string('fingerprint_hash', 128)->nullable()->index();

            $table->ipAddress('ip')->nullable()->index();
            $table->string('user_agent', 1024)->nullable();

            $table->unsignedSmallInteger('risk_score')->default(100);
            $table->string('decision', 30)->default('unknown');
            $table->json('reasons')->nullable();
            $table->json('signals')->nullable();

            $table->string('remember_token_hash', 128)->nullable()->index();
            $table->timestamp('remember_expires_at')->nullable()->index();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            $table->index(['visitor_id', 'decision']);
            $table->index(['ip', 'created_at']);
            $table->index(['fingerprint_hash', 'decision']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('company_contact_verifications');
    }
}