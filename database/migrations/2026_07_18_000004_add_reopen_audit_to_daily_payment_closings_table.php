<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_payment_closings', function (Blueprint $table) {
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reopened_at')->nullable();
            $table->text('reopen_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('daily_payment_closings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reopened_by');
            $table->dropColumn(['reopened_at', 'reopen_reason']);
        });
    }
};
