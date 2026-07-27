<?php

namespace Tests\Feature\Categories;

use App\Http\Middleware\RoleMiddleware;
use App\Models\Category;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryPrefixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            Authenticate::class,
            RoleMiddleware::class,
            ValidateCsrfToken::class,
        ]);
    }

    public function test_category_prefixes_are_optional_for_existing_categories_but_validated_when_present(): void
    {
        $this->post(route('categories.store'), [
            'name' => 'Cement',
            'code_prefix' => 'cem',
            'barcode_prefix' => '01',
        ])->assertSessionHasErrors(['code_prefix', 'barcode_prefix']);

        $this->assertDatabaseCount('categories', 0);

        $this->post(route('categories.store'), [
            'name' => 'Legacy Category',
        ])->assertRedirect();

        $this->assertDatabaseHas('categories', [
            'name' => 'Legacy Category',
            'code_prefix' => null,
            'barcode_prefix' => null,
        ]);
    }

    public function test_category_prefixes_must_be_unique(): void
    {
        Category::query()->create([
            'name' => 'Cement',
            'code_prefix' => 'CEM',
            'barcode_prefix' => '001',
        ]);

        $this->post(route('categories.store'), [
            'name' => 'Another Cement',
            'code_prefix' => 'CEM',
            'barcode_prefix' => '001',
        ])->assertSessionHasErrors(['code_prefix', 'barcode_prefix']);
    }
}
