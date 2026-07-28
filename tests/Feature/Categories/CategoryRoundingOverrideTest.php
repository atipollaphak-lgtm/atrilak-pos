<?php

namespace Tests\Feature\Categories;

use App\Http\Middleware\RoleMiddleware;
use App\Models\Category;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryRoundingOverrideTest extends TestCase
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

    public function test_category_rounding_override_defaults_to_zone_value(): void
    {
        $category = Category::query()->create(['name' => 'Override default']);

        $this->assertNull($category->fresh()->rounding_override);
    }

    public function test_category_rounding_override_accepts_supported_values(): void
    {
        foreach (['0.25', '0.50', '1.00', '5.00', '10.00'] as $value) {
            $response = $this->post(route('categories.store'), [
                'name' => 'Category '.$value,
                'rounding_override' => $value,
            ]);

            $response->assertRedirect();
        }

        $this->assertDatabaseCount('categories', 5);
        $this->assertDatabaseHas('categories', ['name' => 'Category 0.25', 'rounding_override' => '0.25']);
        $this->assertDatabaseHas('categories', ['name' => 'Category 10.00', 'rounding_override' => '10.00']);
    }

    public function test_category_rounding_override_rejects_unsupported_values(): void
    {
        $this->post(route('categories.store'), [
            'name' => 'Invalid override',
            'rounding_override' => '0.30',
        ])->assertSessionHasErrors('rounding_override');

        $this->assertDatabaseCount('categories', 0);
    }

    public function test_category_rounding_override_can_be_cleared(): void
    {
        $category = Category::query()->create([
            'name' => 'Clear override',
            'rounding_override' => '0.50',
        ]);

        $this->put(route('categories.update', $category), [
            'name' => $category->name,
            'rounding_override' => null,
        ])->assertRedirect();

        $this->assertNull($category->fresh()->rounding_override);
    }
}
