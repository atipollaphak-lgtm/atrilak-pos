<?php

namespace Tests\Unit\Customers;

use App\Models\Customer;
use App\Services\Customers\CustomerCodeAllocator;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CustomerCodeAllocatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('customers', function ($table): void {
            $table->id();
            $table->string('code')->nullable();
            $table->string('name');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('customers');

        parent::tearDown();
    }

    public function test_reserves_a_contiguous_range_after_the_highest_valid_code(): void
    {
        Customer::query()->insert([
            ['code' => 'CUS-0001', 'name' => 'One', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'CUS-0009', 'name' => 'Nine', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'LEGACY', 'name' => 'Legacy', 'created_at' => now(), 'updated_at' => now()],
            ['code' => null, 'name' => 'No code', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $codes = app(CustomerCodeAllocator::class)->reserveCodes(3);

        $this->assertSame(['CUS-0010', 'CUS-0011', 'CUS-0012'], $codes);
    }

    public function test_non_positive_reservation_returns_no_codes(): void
    {
        $allocator = app(CustomerCodeAllocator::class);

        $this->assertSame([], $allocator->reserveCodes(0));
        $this->assertSame([], $allocator->reserveCodes(-1));
    }
}
