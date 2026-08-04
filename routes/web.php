<?php

use App\Http\Controllers\BackupController;
use App\Http\Controllers\BarcodeController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerDeliveryAddressController;
use App\Http\Controllers\DailyPaymentClosingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryZoneController;
use App\Http\Controllers\HoldBillController;
use App\Http\Controllers\PriceController;
use App\Http\Controllers\PricingManagementController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductImportController;
use App\Http\Controllers\ProductPriceTierController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ReceiveStockController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SaleV3Controller;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StockCountController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TechnicianCommissionController;
use App\Http\Controllers\TechnicianCommissionRuleController;
use App\Http\Controllers\TechnicianController;
use App\Http\Controllers\TechnicianPaymentBatchController;
use App\Http\Controllers\TechnicianPaymentController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get(
    '/dashboard',
    [DashboardController::class, 'index']
)->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    Route::get('/home', function () {
        return redirect('/dashboard');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->name('profile.destroy');

    // Cashier ขึ้นไป
    Route::middleware(['role:cashier'])->group(function () {

        Route::post(
            '/sales/check-profit',
            [SaleController::class, 'checkProfit']
        )->name('sales.check-profit');

        Route::get('/sales-history', [SaleController::class, 'history'])
            ->name('sales.history');

        Route::get('/sales-v2', [SaleController::class, 'indexV2'])
            ->name('sales.v2');

        Route::get('/sales-v3', [SaleV3Controller::class, 'index'])
            ->name('sales.v3');

        Route::get('/sales-v3/hold-bills', [HoldBillController::class, 'index'])
            ->name('sales.v3.hold-bills.index');
        Route::get('/sales-v3/hold-bills/{holdBill}', [HoldBillController::class, 'show'])
            ->name('sales.v3.hold-bills.show');
        Route::post('/sales-v3/hold-bills', [HoldBillController::class, 'store'])
            ->name('sales.v3.hold-bills.store');
        Route::delete('/sales-v3/hold-bills/{holdBill}', [HoldBillController::class, 'destroy'])
            ->name('sales.v3.hold-bills.destroy');

        Route::post(
            '/sales-v2/store',
            [SaleController::class, 'storeV2']
        )->name('sales.v2.store');

        Route::post(
            '/sales-v3/store',
            [SaleV3Controller::class, 'store']
        )->name('sales.v3.store');

        Route::resource('sales', SaleController::class)->except('destroy');

        Route::get('/sales/{sale}/invoice', [SaleController::class, 'invoice'])
            ->name('sales.invoice');

        Route::get('/sales/{sale}/invoice-v2', [SaleController::class, 'invoiceV2'])
            ->name('sales.invoice-v2');

        Route::get('/sales/{sale}/print', [SaleController::class, 'print'])
            ->name('sales.print');

        Route::resource('customers', CustomerController::class);

        Route::post(
            '/customers/{customer}/restore',
            [CustomerController::class, 'restore']
        )->name('customers.restore');

        Route::get(
            '/technician-payments',
            [TechnicianPaymentController::class, 'index']
        )->name('technician-payments.index');

        Route::resource(
            'technicians',
            TechnicianController::class
        )->only([
            'index',
            'store',
            'update',
            'destroy',
        ]);

        Route::get('/technician-commissions', [TechnicianCommissionController::class, 'index'])
            ->name('technician-commissions.index');

        Route::post(
            '/api/price/calculate',
            [PriceController::class, 'calculate']
        )->name('api.price.calculate');

        Route::get(
            '/sales-v2/customers/{customer}/delivery-addresses-json',
            [
                CustomerDeliveryAddressController::class,
                'getByCustomer',
            ]
        )->name('sales.v2.customer-delivery-addresses.json');

        Route::get(
            '/sales-v3/customers/{customer}/delivery-addresses-json',
            [CustomerDeliveryAddressController::class, 'getByCustomer']
        )->name('sales.v3.customer-delivery-addresses.json');
    });

    // Manager ขึ้นไป
    Route::middleware(['role:manager'])->group(function () {
        Route::get('/daily-payment-closings', [DailyPaymentClosingController::class, 'index'])
            ->name('daily-payment-closings.index');
        Route::get('/daily-payment-closings/create', [DailyPaymentClosingController::class, 'create'])
            ->name('daily-payment-closings.create');
        Route::get('/daily-payment-closings/{dailyPaymentClosing}/edit', [DailyPaymentClosingController::class, 'edit'])
            ->name('daily-payment-closings.edit');
        Route::get('/daily-payment-closings/{dailyPaymentClosing}/print', [DailyPaymentClosingController::class, 'print'])
            ->name('daily-payment-closings.print');
        Route::get('/daily-payment-closings/{dailyPaymentClosing}', [DailyPaymentClosingController::class, 'show'])
            ->name('daily-payment-closings.show');
        Route::post('/daily-payment-closings', [DailyPaymentClosingController::class, 'store'])
            ->name('daily-payment-closings.store');
        Route::put('/daily-payment-closings/{dailyPaymentClosing}', [DailyPaymentClosingController::class, 'update'])
            ->name('daily-payment-closings.update');
        Route::post('/daily-payment-closings/{dailyPaymentClosing}/finalize', [DailyPaymentClosingController::class, 'finalize'])
            ->name('daily-payment-closings.finalize');
        Route::post('/sales/{sale}/void', [SaleController::class, 'void'])
            ->name('sales.void');

        Route::prefix('products/import')->name('products.import.')->group(function () {
            Route::get('/', [ProductImportController::class, 'index'])->name('index');
            Route::get('/template', [ProductImportController::class, 'template'])->name('template');
            Route::post('/preview', [ProductImportController::class, 'preview'])->name('preview');
            Route::post('/confirm', [ProductImportController::class, 'confirm'])->name('confirm');
            Route::get('/errors/{token}', [ProductImportController::class, 'errors'])->name('errors');
            Route::delete('/{token}', [ProductImportController::class, 'destroy'])->name('destroy');
        });

        Route::resource('products', ProductController::class)->except('show');
        Route::put('/products/{product}/cost', [ProductController::class, 'updateCost'])
            ->name('products.cost.update');
        Route::post(
            '/products/{product}/restore',
            [ProductController::class, 'restore']
        )->name('products.restore');

        Route::post('/products/{product}/units', [ProductController::class, 'storeUnit'])
            ->name('products.units.store');

        Route::put('/products/{product}/units/{productUnit}', [ProductController::class, 'updateUnit'])
            ->name('products.units.update');

        Route::delete('/products/{product}/units/{productUnit}', [ProductController::class, 'destroyUnit'])
            ->name('products.units.destroy');

        Route::post(
            '/products/{product}/barcodes',
            [ProductController::class, 'storeBarcode']
        )->name('products.barcodes.store');

        Route::put(
            '/products/{product}/barcodes/{productBarcode}',
            [ProductController::class, 'updateBarcode']
        )->name('products.barcodes.update');

        Route::delete(
            '/products/{product}/barcodes/{productBarcode}',
            [ProductController::class, 'destroyBarcode']
        )->name('products.barcodes.destroy');

        Route::post(
            '/products/{product}/units/{productUnit}/price-tiers',
            [ProductPriceTierController::class, 'store']
        )->name('products.price-tiers.store');

        Route::put(
            '/products/{product}/units/{productUnit}/price-tiers/{productPriceTier}',
            [ProductPriceTierController::class, 'update']
        )->name('products.price-tiers.update');

        Route::delete(
            '/products/{product}/units/{productUnit}/price-tiers/{productPriceTier}',
            [
                ProductPriceTierController::class,
                'destroy',
            ]
        )->name('products.price-tiers.destroy');

        Route::get(
            '/product-price-tiers',
            [ProductPriceTierController::class, 'index']
        )->name('product-price-tiers.index');

        Route::get(
            '/product-price-tiers/bulk-copy-data',
            [ProductPriceTierController::class, 'bulkCopyData']
        )->name('product-price-tiers.bulk-copy-data');

        Route::post(
            '/product-price-tiers/bulk-copy',
            [ProductPriceTierController::class, 'bulkCopy']
        )->name('product-price-tiers.bulk-copy');

        Route::post(
            '/product-price-tiers/store',
            [ProductPriceTierController::class, 'storeFromManagement']
        )->name('product-price-tiers.store');

        Route::put(
            '/product-price-tiers/{productPriceTier}',
            [ProductPriceTierController::class, 'updateFromManagement']
        )->name('product-price-tiers.update');

        Route::delete(
            '/product-price-tiers/{productPriceTier}',
            [ProductPriceTierController::class, 'destroyFromManagement']
        )->name('product-price-tiers.destroy');

        Route::resource('categories', CategoryController::class);
        Route::resource('suppliers', SupplierController::class);
        Route::resource('purchases', PurchaseController::class);
        Route::prefix('receivings')->name('receivings.')->controller(ReceiveStockController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::get('/products/search', 'search')->name('products.search');
            Route::post('/preview', 'preview')->name('preview.store');
            Route::get('/preview/{token}', 'previewPage')->name('preview');
            Route::post('/confirm', 'confirm')->name('confirm');
            Route::get('/{receiving}', 'show')->name('show');
        });
        Route::get(
            '/purchases/{purchase}/print',
            [PurchaseController::class, 'print']
        )->name('purchases.print');

        Route::get('/stock-movements', [StockMovementController::class, 'index'])
            ->name('stock-movements.index');

        Route::get('/stock-counts', [StockCountController::class, 'index'])
            ->name('stock-counts.index');

        Route::post('/stock-counts', [StockCountController::class, 'store'])
            ->name('stock-counts.store');

        Route::get('/barcodes', [BarcodeController::class, 'index'])
            ->name('barcodes.index');

        Route::resource('quotations', QuotationController::class);

        Route::get('/quotations/{quotation}/print', [QuotationController::class, 'print'])
            ->name('quotations.print');

        Route::post(
            '/quotations/{quotation}/convert',
            [QuotationController::class, 'convertToSale']
        )->name('quotations.convert');

        Route::resource(
            'units',
            UnitController::class
        );

        Route::post('/units/seed', [UnitController::class, 'seed'])
            ->name('units.seed');

        Route::post('/units/merge', [UnitController::class, 'merge'])
            ->name('units.merge');

        Route::resource(
            'technician-commission-rules',
            TechnicianCommissionRuleController::class
        );
        Route::get('/technician-payments', [TechnicianPaymentController::class, 'index'])
            ->name('technician-payments.index');

        Route::post('/technician-payments/pay', [TechnicianPaymentController::class, 'pay'])
            ->name('technician-payments.pay');

        Route::get('/technician-payment-batches', [TechnicianPaymentBatchController::class, 'index'])
            ->name('technician-payment-batches.index');

        Route::get('/technician-payment-batches/create', [TechnicianPaymentBatchController::class, 'create'])
            ->name('technician-payment-batches.create');

        Route::post('/technician-payment-batches/preview', [TechnicianPaymentBatchController::class, 'preview'])
            ->name('technician-payment-batches.preview');

        Route::post('/technician-payment-batches', [TechnicianPaymentBatchController::class, 'store'])
            ->name('technician-payment-batches.store');

        Route::get('/technician-payment-batches/preview', function () {
            return redirect()->route('technician-payment-batches.create');
        });

        Route::get('/technician-payment-batches/{batch}', [TechnicianPaymentBatchController::class, 'show'])
            ->name('technician-payment-batches.show');

        Route::get('/technician-payment-batches/{batch}/print', [TechnicianPaymentBatchController::class, 'print'])
            ->name('technician-payment-batches.print');

        Route::resource(
            'delivery-zones',
            DeliveryZoneController::class
        );

        Route::resource(
            'customers.delivery-addresses',
            CustomerDeliveryAddressController::class
        );

        Route::post(
            '/customers/{customer}/delivery-addresses/{deliveryAddress}/set-primary',
            [CustomerDeliveryAddressController::class, 'setPrimary']
        )->name('customers.delivery-addresses.set-primary');
    });

    // Owner เท่านั้น
    Route::middleware(['role:owner'])->group(function () {
        Route::post('/daily-payment-closings/{dailyPaymentClosing}/reopen', [DailyPaymentClosingController::class, 'reopen'])
            ->name('daily-payment-closings.reopen');
        Route::resource('users', UserController::class);

        Route::post('/users/{user}/update-role', [UserController::class, 'updateRole'])
            ->name('users.update-role');

        Route::get('/settings', [SettingController::class, 'index'])
            ->name('settings.index');

        Route::post('/settings', [SettingController::class, 'update'])
            ->name('settings.update');

        Route::get('/backups', [BackupController::class, 'index'])
            ->name('backups.index');

        Route::post('/backups/create', [BackupController::class, 'createBackup'])
            ->name('backups.create');

        Route::post('/backups/reset-business-data', [BackupController::class, 'resetBusinessData'])
            ->middleware('throttle:3,1')
            ->name('backups.reset-business-data');

        Route::get('/backups/{fileName}/download', [BackupController::class, 'downloadFile'])
            ->name('backups.download');

        Route::get('/reports/daily-profit', [ReportController::class, 'dailyProfit'])
            ->name('reports.daily-profit');

        Route::get(
            'reports/daily-profit/export',
            [ReportController::class, 'exportDailyProfit']
        )->name('reports.daily-profit.export');

        Route::get('/reports/monthly-profit', [ReportController::class, 'monthlyProfit'])
            ->name('reports.monthly-profit');

        Route::get(
            '/reports/monthly-profit/export',
            [ReportController::class, 'exportMonthlyProfit']
        )->name('reports.monthly-profit.export');

        Route::get('/reports/yearly-profit', [ReportController::class, 'yearlyProfit'])
            ->name('reports.yearly-profit');

        Route::get(
            '/reports/yearly-profit/export',
            [ReportController::class, 'exportYearlyProfit']
        )->name('reports.yearly-profit.export');

        Route::get('/reports/product-sales', [ReportController::class, 'productSales'])
            ->name('reports.product-sales');
        Route::get(
            '/reports/product-sales/export',
            [ReportController::class, 'exportProductSales']
        )->name('reports.product-sales.export');

        Route::get('/reports/best-seller', [ReportController::class, 'bestSeller'])
            ->name('reports.best-seller');

        Route::get(
            '/reports/best-seller/export',
            [ReportController::class, 'exportBestSeller']
        )->name('reports.best-seller.export');

        Route::get(
            '/pricing-management',
            [PricingManagementController::class, 'index']
        )->name('pricing-management.index');

        Route::get(
            '/pricing-management/history',
            [PricingManagementController::class, 'history']
        )->name('pricing-management.history');

        Route::get(
            '/pricing-management/category-rules',
            [PricingManagementController::class, 'categoryRules']
        )->name('pricing-management.category-rules');

        Route::post(
            '/pricing-management/category-rules',
            [PricingManagementController::class, 'storeCategoryRule']
        )->name('pricing-management.category-rules.store');

        Route::put(
            '/pricing-management/category-rules/{categoryPricingRule}',
            [PricingManagementController::class, 'updateCategoryRule']
        )->name('pricing-management.category-rules.update');

        Route::delete(
            '/pricing-management/category-rules/{categoryPricingRule}',
            [PricingManagementController::class, 'destroyCategoryRule']
        )->name('pricing-management.category-rules.destroy');

        Route::get(
            '/pricing-management/{product}',
            [PricingManagementController::class, 'show']
        )->name('pricing-management.show');

        Route::put(
            '/pricing-management/{product}',
            [PricingManagementController::class, 'update']
        )->name('pricing-management.update');

        Route::post(
            '/pricing-management/history/{history}/rollback',
            [PricingManagementController::class, 'rollback']
        )->name('pricing-management.rollback');
    });
});

require __DIR__.'/auth.php';
