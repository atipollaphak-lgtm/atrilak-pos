<?php

namespace Tests\Feature\Products;

use App\Http\Middleware\RoleMiddleware;
use App\Models\User;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductImportBrowserContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_index_exposes_bulk_import_button_and_routes_are_available_to_manager(): void
    {
        $this->withoutMiddleware([
            Authenticate::class,
            RoleMiddleware::class,
            ValidateCsrfToken::class,
        ]);

        $this->get(route('products.index'))
            ->assertOk()
            ->assertSee('นำเข้าจาก Excel');

        foreach ([
            'products.import.index',
            'products.import.template',
            'products.import.preview',
            'products.import.confirm',
            'products.import.errors',
            'products.import.destroy',
        ] as $routeName) {
            $this->assertNotNull(app('router')->getRoutes()->getByName($routeName));
        }
    }

    public function test_cashier_cannot_access_bulk_product_import(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'cashier']))
            ->get('/products/import')
            ->assertForbidden();
    }
}
