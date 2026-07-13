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
    Schema::table('products', function (Blueprint $table) {

        // เปิด/ปิด Auto Pricing รายสินค้า
        $table->boolean('auto_price_enabled')
            ->default(true)
            ->after('selling_price');

        // Override กำไร (%)
        // null = ใช้ค่าจากหมวดสินค้า/ระบบ
        $table->decimal('profit_percent', 8, 2)
            ->nullable()
            ->after('auto_price_enabled');

        // Override วิธีปัดสตางค์
        // null = ใช้ค่าจากหมวดสินค้า/ระบบ
        $table->string('satang_rounding_mode')
            ->nullable()
            ->after('profit_percent');

        // Override วิธีปัดบาท
        // null = ใช้ค่าจากหมวดสินค้า/ระบบ
        $table->string('baht_rounding_mode')
            ->nullable()
            ->after('satang_rounding_mode');

        // ล็อกราคาขาย
        // ถ้าเปิด ระบบจะไม่เปลี่ยนราคาขายอัตโนมัติ
        $table->boolean('price_lock')
            ->default(false)
            ->after('baht_rounding_mode');

    });
}

    /**
     * Reverse the migrations.
     */
public function down(): void
{
    Schema::table('products', function (Blueprint $table) {

        $table->dropColumn([
            'auto_price_enabled',
            'profit_percent',
            'satang_rounding_mode',
            'baht_rounding_mode',
            'price_lock',
        ]);

    });
}
};
