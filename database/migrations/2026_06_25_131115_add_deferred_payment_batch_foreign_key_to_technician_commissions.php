<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONSTRAINT = 'technician_commissions_payment_batch_id_deferred_foreign';

    public function up(): void
    {
        if (! Schema::hasTable('technician_commissions')
            || ! Schema::hasTable('technician_payment_batches')
            || ! Schema::hasColumn('technician_commissions', 'payment_batch_id')) {
            return;
        }

        if ($this->hasPaymentBatchForeignKey()) {
            return;
        }

        Schema::table('technician_commissions', function (Blueprint $table) {
            $table->foreign('payment_batch_id', self::CONSTRAINT)
                ->references('id')
                ->on('technician_payment_batches')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('technician_commissions')) {
            return;
        }

        $hasDeferredConstraint = collect(Schema::getForeignKeys('technician_commissions'))
            ->contains(fn (array $foreignKey): bool => $foreignKey['name'] === self::CONSTRAINT);

        if (! $hasDeferredConstraint) {
            return;
        }

        Schema::table('technician_commissions', function (Blueprint $table) {
            $table->dropForeign(self::CONSTRAINT);
        });
    }

    private function hasPaymentBatchForeignKey(): bool
    {
        return collect(Schema::getForeignKeys('technician_commissions'))
            ->contains(function (array $foreignKey): bool {
                return $foreignKey['columns'] === ['payment_batch_id']
                    && $foreignKey['foreign_table'] === 'technician_payment_batches'
                    && $foreignKey['foreign_columns'] === ['id'];
            });
    }
};
