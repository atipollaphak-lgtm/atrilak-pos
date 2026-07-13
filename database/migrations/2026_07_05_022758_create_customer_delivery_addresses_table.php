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
        Schema::create('customer_delivery_addresses', function (Blueprint $table) {

            $table->id();

            // ลูกค้า
            $table->foreignId('customer_id')
                ->constrained()
                ->cascadeOnDelete();

            // ชื่อสถานที่ เช่น บ้าน / ร้าน / ไซต์งาน
            $table->string('name');

            // ผู้รับ
            $table->string('receiver_name')->nullable();
            $table->string('receiver_phone', 30)->nullable();

            // ที่อยู่
            $table->text('address');

            // จุดสังเกต
            $table->text('landmark')->nullable();

            // โซนจัดส่ง
            $table->foreignId('delivery_zone_id')
                ->nullable()
                ->constrained('delivery_zones')
                ->nullOnDelete();

            // พิกัด
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // ค่าเริ่มต้น
            $table->boolean('is_default')->default(false);

            // หมายเหตุ
            $table->text('remark')->nullable();

            $table->timestamps();

            $table->index('customer_id');
            $table->index('delivery_zone_id');
            $table->index(['customer_id', 'is_default']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_delivery_addresses');
    }
};
