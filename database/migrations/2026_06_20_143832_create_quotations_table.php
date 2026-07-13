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
    Schema::create('quotations', function (Blueprint $table) {
        $table->id();

        $table->string('quotation_no')->nullable();

        $table->foreignId('customer_id')
            ->nullable()
            ->constrained()
            ->nullOnDelete();

        $table->date('quotation_date');

        $table->decimal('total_amount', 15, 2)->default(0);

        $table->text('remark')->nullable();

        $table->string('status')->default('draft');

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotations');
    }
};
