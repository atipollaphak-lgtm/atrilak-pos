<?php

namespace Tests\Unit\Customers;

use App\Http\Requests\Customers\ConfirmCustomerImportRequest;
use App\Http\Requests\Customers\PreviewCustomerImportRequest;
use Tests\TestCase;

class CustomerImportRouteAndRequestTest extends TestCase
{
    public function test_import_routes_are_protected_by_authentication_and_manager_role(): void
    {
        foreach ([
            'customers.import.index',
            'customers.import.template',
            'customers.import.preview',
            'customers.import.confirm',
            'customers.import.history',
            'customers.import.history.show',
            'customers.import.report',
            'customers.import.destroy',
        ] as $routeName) {
            $route = app('router')->getRoutes()->getByName($routeName);

            $this->assertNotNull($route, $routeName.' should be registered');
            $this->assertContains('auth', $route->gatherMiddleware(), $routeName);
            $this->assertContains('role:manager', $route->gatherMiddleware(), $routeName);
        }
    }

    public function test_preview_request_accepts_only_xlsx_with_configured_size_limit(): void
    {
        $rules = (new PreviewCustomerImportRequest)->rules();

        $this->assertSame(['required', 'file', 'max:'.config('customer_import.max_file_size_kb')], $rules['file']);
    }

    public function test_confirm_request_accepts_only_a_token_and_distinct_row_numbers(): void
    {
        $rules = (new ConfirmCustomerImportRequest)->rules();

        $this->assertSame(['required', 'uuid'], $rules['token']);
        $this->assertSame(['required', 'array', 'min:1'], $rules['selected_rows']);
        $this->assertSame(['integer', 'distinct', 'min:1'], $rules['selected_rows.*']);
    }
}
