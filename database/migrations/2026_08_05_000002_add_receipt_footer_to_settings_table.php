<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('settings', 'receipt_footer')) {
            Schema::table('settings', function (Blueprint $table): void {
                $table->text('receipt_footer')->nullable()->after('qr_image');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('settings', 'receipt_footer')) {
            Schema::table('settings', function (Blueprint $table): void {
                $table->dropColumn('receipt_footer');
            });
        }
    }
};
