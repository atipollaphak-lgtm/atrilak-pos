<?php

namespace Tests\Feature\Categories;

use App\Http\Middleware\RoleMiddleware;
use App\Models\Category;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryOrderingTest extends TestCase
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

    public function test_complete_category_order_is_persisted(): void
    {
        $first = Category::query()->create(['name' => 'First']);
        $second = Category::query()->create(['name' => 'Second']);
        $third = Category::query()->create(['name' => 'Third']);

        $this->putJson(route('categories.order'), [
            'category_ids' => [$third->id, $first->id, $second->id],
        ])->assertOk();

        $this->assertSame(
            [$third->id, $first->id, $second->id],
            Category::query()->orderBy('sort_order')->orderBy('id')->pluck('id')->all(),
        );
    }

    public function test_new_category_is_appended_after_existing_categories(): void
    {
        $first = Category::query()->create(['name' => 'First']);
        $second = Category::query()->create(['name' => 'Second']);

        $response = $this->postJson(route('categories.store'), ['name' => 'Third']);

        $response->assertCreated();
        $created = Category::query()->where('name', 'Third')->sole();

        $this->assertGreaterThan(
            max($first->sort_order ?? 0, $second->sort_order ?? 0),
            $created->sort_order,
        );
    }

    public function test_duplicate_sort_order_uses_category_id_as_deterministic_tie_breaker(): void
    {
        $first = Category::query()->create(['name' => 'First', 'sort_order' => 5]);
        $second = Category::query()->create(['name' => 'Second', 'sort_order' => 5]);

        $this->assertSame(
            [$first->id, $second->id],
            Category::query()->orderBy('sort_order')->orderBy('id')->pluck('id')->all(),
        );
    }

    public function test_order_request_rejects_an_incomplete_category_list(): void
    {
        $first = Category::query()->create(['name' => 'First']);
        Category::query()->create(['name' => 'Second']);

        $this->putJson(route('categories.order'), [
            'category_ids' => [$first->id],
        ])->assertUnprocessable();
    }
}
