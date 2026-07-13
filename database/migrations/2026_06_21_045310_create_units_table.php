<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {

            $table->id();

            $table->string('code',30)->unique();

            $table->string('name',100);

            $table->string('short_name',20);

            $table->boolean('active')->default(true);

            $table->integer('sort_order')->default(0);

            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
