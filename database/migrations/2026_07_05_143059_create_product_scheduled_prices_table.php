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
        Schema::create('product_scheduled_prices', function (Blueprint $table) {

            $table->id();

            $table->foreignId('product_id')
                ->constrained()
                ->cascadeOnDelete();

            // ราคาที่จะมีผล
            $table->decimal('selling_price', 12, 2);

            // วันที่เริ่มใช้ราคา
            $table->date('effective_date');

            // ใช้งานแล้วหรือยัง
            $table->boolean('is_applied')
                ->default(false);

            // วันที่ระบบนำไปใช้จริง
            $table->timestamp('applied_at')
                ->nullable();

            // หมายเหตุ
            // สาเหตุที่สร้างรายการนี้
            // manual
            // auto_pricing
            // promotion
            // import
            // api
            $table->string('created_from')
                ->default('manual');

            // หมายเหตุ
            $table->text('remark')
                ->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_scheduled_prices');
    }
};
