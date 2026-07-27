<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('status', 20)
                ->default('active')
                ->index('sales_status_index');
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('void_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voided_by');
            $table->dropIndex('sales_status_index');
            $table->dropColumn(['status', 'voided_at', 'void_reason']);
        });
    }
};
