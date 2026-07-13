<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up(): void
{
    Schema::table('settings', function (Blueprint $table) {

        $table->string('branch_type')
            ->default('head_office')
            ->after('tax_number');

        $table->string('branch_number')
            ->nullable()
            ->after('branch_type');

    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
{
    Schema::table('settings', function (Blueprint $table) {

        $table->dropColumn([
            'branch_type',
            'branch_number',
        ]);

    });
}
};
