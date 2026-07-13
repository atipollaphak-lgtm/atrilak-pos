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
        Schema::table('customers', function (Blueprint $table) {

            $table->string('tax_number')
                ->nullable()
                ->after('address');

            $table->enum('branch_type', [
                'สำนักงานใหญ่',
                'สาขา',
            ])
                ->default('สำนักงานใหญ่')
                ->after('tax_number');

            $table->string('branch_number', 5)
                ->nullable()
                ->after('branch_type');

        });
    }

    /**
     * Reverse the migrations.
     */
        public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {

            $table->dropColumn([
                'tax_number',
                'branch_type',
                'branch_number',
            ]);

        });
    }
};
