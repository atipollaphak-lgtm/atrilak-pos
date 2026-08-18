<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'sort_order')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->integer('sort_order')->default(0)->after('category_id');
                $table->index(['category_id', 'sort_order']);
            });
        }

        DB::transaction(function (): void {
            DB::table('categories')
                ->orderBy('id')
                ->pluck('id')
                ->each(function ($categoryId): void {
                    DB::table('products')
                        ->where('category_id', $categoryId)
                        ->orderBy('name')
                        ->orderBy('id')
                        ->pluck('id')
                        ->each(function ($productId, int $sortOrder): void {
                            DB::table('products')
                                ->where('id', $productId)
                                ->update(['sort_order' => $sortOrder]);
                        });
                });
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'sort_order')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->dropIndex(['category_id', 'sort_order']);
                $table->dropColumn('sort_order');
            });
        }
    }
};
