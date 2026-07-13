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
    Schema::create('delivery_zones', function (Blueprint $table) {
        $table->id();

        $table->string('name'); // ชื่อโซน เช่น คลองแม่ลาย
        $table->unsignedInteger('sort_order')->default(0); // ลำดับการแสดงผล
        $table->decimal('base_delivery_fee', 12, 2)->default(0); // ค่าส่งพื้นฐาน
        $table->decimal('free_delivery_min_amount', 12, 2)->nullable(); // ยอดถึงเท่าไหร่ส่งฟรี
        $table->boolean('active')->default(true); // เปิด/ปิดใช้งาน
        $table->text('remark')->nullable(); // หมายเหตุ

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_zones');
    }
};
