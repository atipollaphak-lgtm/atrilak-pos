<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('categories', 'sort_order')) {
            Schema::table('categories', function (Blueprint $table): void {
                $table->integer('sort_order')->default(0)->after('name');
            });
        }

        $categoryIds = DB::table('categories')
            ->orderBy('id')
            ->pluck('id');

        foreach ($categoryIds as $index => $categoryId) {
            DB::table('categories')
                ->where('id', $categoryId)
                ->update(['sort_order' => $index]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('categories', 'sort_order')) {
            Schema::table('categories', function (Blueprint $table): void {
                $table->dropColumn('sort_order');
            });
        }
    }
};
