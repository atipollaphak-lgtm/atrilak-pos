<?php

namespace Tests\Unit\Customers;

use App\Models\Customer;
use App\Models\CustomerExternalReference;
use App\Models\CustomerImportBatch;
use App\Models\CustomerImportRow;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\TestCase;

class CustomerImportModelContractTest extends TestCase
{
    public function test_batch_exposes_history_fields_and_relations(): void
    {
        $model = new CustomerImportBatch;

        $this->assertSame('customer_import_batches', $model->getTable());
        $this->assertContains('source_system', $model->getFillable());
        $this->assertContains('created_by', $model->getFillable());
        $this->assertContains('status', $model->getFillable());
        $this->assertSame('integer', $model->getCasts()['total_parsed']);
        $this->assertSame('array', $model->getCasts()['counts']);
        $this->assertInstanceOf(HasMany::class, $model->rows());
        $this->assertInstanceOf(BelongsTo::class, $model->creator());
    }

    public function test_row_exposes_imported_customer_fields_and_relations(): void
    {
        $model = new CustomerImportRow;

        $this->assertSame('customer_import_rows', $model->getTable());
        $this->assertContains('external_id', $model->getFillable());
        $this->assertContains('original_values', $model->getFillable());
        $this->assertSame('array', $model->getCasts()['reasons']);
        $this->assertSame('array', $model->getCasts()['warnings']);
        $this->assertInstanceOf(BelongsTo::class, $model->batch());
        $this->assertInstanceOf(BelongsTo::class, $model->customer());
    }

    public function test_external_reference_links_source_identity_to_customer(): void
    {
        $model = new CustomerExternalReference;

        $this->assertSame('customer_external_references', $model->getTable());
        $this->assertSame(
            ['customer_id', 'batch_id', 'source_system', 'external_id'],
            $model->getFillable(),
        );
        $this->assertInstanceOf(BelongsTo::class, $model->customer());
        $this->assertInstanceOf(BelongsTo::class, $model->batch());
    }

    public function test_customer_exposes_import_history_relations(): void
    {
        $model = new Customer;

        $this->assertInstanceOf(HasMany::class, $model->importRows());
        $this->assertInstanceOf(HasMany::class, $model->externalReferences());
    }
}
