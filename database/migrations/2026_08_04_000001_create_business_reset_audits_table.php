<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_reset_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('database_name')->nullable();
            $table->json('business_counts_before')->nullable();
            $table->json('business_counts_after')->nullable();
            $table->json('protected_counts_before')->nullable();
            $table->json('protected_counts_after')->nullable();
            $table->string('backup_file')->nullable();
            $table->string('backup_sha256', 64)->nullable();
            $table->unsignedBigInteger('backup_bytes')->nullable();
            $table->string('status', 32);
            $table->string('error_code')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_reset_audits');
    }
};
