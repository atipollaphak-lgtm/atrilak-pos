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
        Schema::table('categories', function (Blueprint $table) {

            // กำไรของหมวดสินค้า (%)
            // null = ใช้ค่าทั้งระบบ
            $table->decimal('profit_percent', 8, 2)
                ->nullable()
                ->after('name');

            // วิธีปัดสตางค์
            // null = ใช้ค่าทั้งระบบ
            // none = ไม่ปัด
            // ceil_satang_10 = ปัดขึ้นทีละ 10 สตางค์
            // ceil_satang_25 = ปัดขึ้นทีละ 25 สตางค์
            // ceil_satang_50 = ปัดขึ้นทีละ 50 สตางค์
            $table->string('satang_rounding_mode')
                ->nullable()
                ->after('profit_percent');

            // วิธีปัดบาท
            // null = ใช้ค่าทั้งระบบ
            // none = ไม่ปัดบาท
            // ceil_baht = ปัดขึ้นเป็นบาท
            // ceil_5 = ปัดขึ้นทีละ 5 บาท
            // ceil_10 = ปัดขึ้นทีละ 10 บาท
            $table->string('baht_rounding_mode')
                ->nullable()
                ->after('satang_rounding_mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {

            $table->dropColumn([
                'profit_percent',
                'satang_rounding_mode',
                'baht_rounding_mode',
            ]);
        });
    }
};
