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
        Schema::create('pricing_settings', function (Blueprint $table) {
            $table->id();

            // เปิด/ปิดการคำนวณราคาอัตโนมัติ
            $table->boolean('auto_pricing_enabled')->default(true);

            // กำไรเริ่มต้น (%)
            $table->decimal('default_profit_percent', 8, 2)->default(20);

            // วิธีปัดราคา
            // none = ไม่ปัด
            // baht = ปัดขึ้นเป็นบาท
            // 5 = ปัดขึ้นทีละ 5 บาท
            // 10 = ปัดขึ้นทีละ 10 บาท
            // วิธีปัดสตางค์เริ่มต้น
$table->string('default_satang_rounding_mode')
    ->default('ceil_satang_50');

// วิธีปัดบาทเริ่มต้น
$table->string('default_baht_rounding_mode')
    ->default('ceil_5');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pricing_settings');
    }
};
