<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\Sales\SaleDecimalService;
use App\Services\Sales\SaleFinancialSnapshotService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        private readonly SaleFinancialSnapshotService $financialSnapshots,
        private readonly SaleDecimalService $decimalService
    ) {}

    public function sales()
    {
        return view('reports.sales');
    }

    public function profit()
    {
        return view('reports.profit');
    }

    public function stock()
    {
        return view('reports.stock');
    }

    public function dailyProfit(Request $request)
    {
        $date = $request->input('date', date('Y-m-d'));

        $sales = Sale::with(['customer', 'items'])
            ->whereDate('sale_date', $date)
            ->orderBy('sale_date')
            ->get();

        $financialsBySaleId = $this->financialSnapshots->summariesBySale($sales);
        $totalSales = $this->financialSnapshots->sumRevenue($sales);
        $totalCost = $this->financialSnapshots->sumCost($sales);
        $totalProfit = $this->financialSnapshots->sumProfit($sales);
        $paymentTotals = Sale::query()
            ->whereDate('sale_date', $date)
            ->selectRaw("COALESCE(SUM(CASE WHEN payment_method IN ('cash', 'promptpay', 'mixed') THEN cash_amount ELSE 0 END), 0) as cash_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN payment_method IN ('cash', 'promptpay', 'mixed') THEN promptpay_amount ELSE 0 END), 0) as promptpay_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN payment_method IN ('cash', 'promptpay', 'mixed') THEN cash_amount + promptpay_amount ELSE 0 END), 0) as recorded_total")
            ->selectRaw("SUM(CASE WHEN payment_method = 'cash' THEN 1 ELSE 0 END) as cash_count")
            ->selectRaw("SUM(CASE WHEN payment_method = 'promptpay' THEN 1 ELSE 0 END) as promptpay_count")
            ->selectRaw("SUM(CASE WHEN payment_method = 'mixed' THEN 1 ELSE 0 END) as mixed_count")
            ->selectRaw('SUM(CASE WHEN payment_method IS NULL THEN 1 ELSE 0 END) as unrecorded_count')
            ->first();
        $paymentSummary = [
            'cash_total' => $this->decimalService->money((string) $paymentTotals->cash_total),
            'promptpay_total' => $this->decimalService->money((string) $paymentTotals->promptpay_total),
            'recorded_total' => $this->decimalService->money((string) $paymentTotals->recorded_total),
            'cash_count' => (int) $paymentTotals->cash_count,
            'promptpay_count' => (int) $paymentTotals->promptpay_count,
            'mixed_count' => (int) $paymentTotals->mixed_count,
            'unrecorded_count' => (int) $paymentTotals->unrecorded_count,
        ];

        return view('reports.daily-profit', compact(
            'date',
            'sales',
            'totalSales',
            'totalCost',
            'totalProfit',
            'financialsBySaleId',
            'paymentSummary'
        ));
    }

    public function monthlyProfit(Request $request)
    {
        $month = $request->input('month', date('m'));
        $year = $request->input('year', date('Y'));

        $sales = Sale::with(['customer', 'items'])
            ->whereMonth('sale_date', $month)
            ->whereYear('sale_date', $year)
            ->orderBy('sale_date')
            ->get();

        $financialsBySaleId = $this->financialSnapshots->summariesBySale($sales);
        $totalSales = $this->financialSnapshots->sumRevenue($sales);
        $totalCost = $this->financialSnapshots->sumCost($sales);
        $totalProfit = $this->financialSnapshots->sumProfit($sales);

        return view('reports.monthly-profit', compact(
            'month',
            'year',
            'sales',
            'totalSales',
            'totalCost',
            'totalProfit',
            'financialsBySaleId'
        ));
    }

    public function yearlyProfit(Request $request)
    {
        $year = $request->input('year', date('Y'));

        $sales = Sale::with(['customer', 'items'])
            ->whereYear('sale_date', $year)
            ->orderBy('sale_date')
            ->get();

        $financialsBySaleId = $this->financialSnapshots->summariesBySale($sales);
        $totalSales = $this->financialSnapshots->sumRevenue($sales);
        $totalCost = $this->financialSnapshots->sumCost($sales);
        $totalProfit = $this->financialSnapshots->sumProfit($sales);

        return view('reports.yearly-profit', compact(
            'year',
            'sales',
            'totalSales',
            'totalCost',
            'totalProfit',
            'financialsBySaleId'
        ));
    }

    public function productSales()
    {
        $month = request('month', date('m'));
        $year = request('year', date('Y'));

        $items = SaleItem::with('product', 'sale')
            ->whereHas('sale', function ($query) use ($month, $year) {
                $query->whereMonth('sale_date', $month)
                    ->whereYear('sale_date', $year);
            })
            ->get();

        $products = [];

        foreach ($items as $item) {
            $productId = $item->product_id;

            if (! isset($products[$productId])) {
                $products[$productId] = [
                    'name' => $item->product->name ?? '-',
                    'unit' => $item->product->unit ?? '',
                    'qty' => 0,
                    'sales' => '0.00',
                    'cost' => '0.00',
                    'profit' => '0.00',
                ];
            }

            $products[$productId]['qty'] += $item->qty;
            $products[$productId]['sales'] = $this->decimalService->addMoney(
                $products[$productId]['sales'],
                $this->financialSnapshots->itemRevenue($item)
            );
            $products[$productId]['cost'] = $this->decimalService->addMoney(
                $products[$productId]['cost'],
                $this->financialSnapshots->itemCost($item)
            );
            $products[$productId]['profit'] = $this->decimalService->addMoney(
                $products[$productId]['profit'],
                $this->financialSnapshots->itemProfit($item)
            );
        }

        return view('reports.product_sales', compact(
            'month',
            'year',
            'products'
        ));
    }

    public function bestSeller()
    {
        $month = request('month', date('m'));
        $year = request('year', date('Y'));

        $items = SaleItem::with('product', 'sale')
            ->whereHas('sale', function ($query) use ($month, $year) {
                $query->whereMonth('sale_date', $month)
                    ->whereYear('sale_date', $year);
            })
            ->get();

        $products = [];

        foreach ($items as $item) {

            $productId = $item->product_id;

            if (! isset($products[$productId])) {

                $products[$productId] = [
                    'name' => $item->product->name ?? '-',
                    'qty' => 0,
                ];
            }

            $products[$productId]['qty'] += $item->qty;
        }

        usort($products, function ($a, $b) {
            return $b['qty'] <=> $a['qty'];
        });

        $products = array_slice($products, 0, 10);

        return view(
            'reports.best_seller',
            compact(
                'month',
                'year',
                'products'
            )
        );
    }

    public function exportDailyProfit(Request $request)
    {
        $date = $request->input('date', date('Y-m-d'));

        $sales = Sale::with(['customer', 'items.product'])
            ->whereDate('sale_date', $date)
            ->orderBy('sale_date')
            ->get();

        $fileName = 'daily-profit-'.$date.'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ];

        $callback = function () use ($sales) {
            $file = fopen('php://output', 'w');

            // UTF-8 BOM สำหรับ Excel ภาษาไทย
            fwrite($file, "\xEF\xBB\xBF");

            fputcsv($file, [
                'เลขที่บิล',
                'วันที่ขาย',
                'ลูกค้า',
                'สินค้า',
                'จำนวน',
                'ราคาขาย',
                'ต้นทุนรวม',
                'ยอดขาย',
                'กำไร',
            ]);

            foreach ($sales as $sale) {
                foreach ($sale->items as $item) {
                    $sellingPrice = $item->selling_price ?? 0;
                    $qty = $item->qty ?? 0;
                    $total = $this->financialSnapshots->itemRevenue($item);
                    $profit = $this->financialSnapshots->itemProfit($item);
                    $totalCost = $this->financialSnapshots->itemCost($item);

                    fputcsv($file, [
                        $sale->sale_no,
                        $sale->sale_date,
                        $sale->customer->name ?? 'ลูกค้าทั่วไป',
                        $item->product->name ?? '-',
                        $qty,
                        $sellingPrice,
                        $totalCost,
                        $total,
                        $profit,
                    ]);
                }
            }

            fclose($file);
        };

        return response()->stream(
            $callback,
            200,
            $headers
        );
    }

    public function exportMonthlyProfit(Request $request)
    {
        $month = $request->input('month', date('m'));
        $year = $request->input('year', date('Y'));

        $sales = Sale::with(['customer', 'items.product'])
            ->whereMonth('sale_date', $month)
            ->whereYear('sale_date', $year)
            ->orderBy('sale_date')
            ->get();

        $fileName = 'monthly-profit-'.$year.'-'.$month.'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ];

        $callback = function () use ($sales) {
            $file = fopen('php://output', 'w');

            fwrite($file, "\xEF\xBB\xBF");

            fputcsv($file, [
                'เลขที่บิล',
                'วันที่ขาย',
                'ลูกค้า',
                'สินค้า',
                'จำนวน',
                'ราคาขาย',
                'ต้นทุนรวม',
                'ยอดขาย',
                'กำไร',
            ]);

            foreach ($sales as $sale) {
                foreach ($sale->items as $item) {
                    $sellingPrice = $item->selling_price ?? 0;
                    $qty = $item->qty ?? 0;
                    $total = $this->financialSnapshots->itemRevenue($item);
                    $profit = $this->financialSnapshots->itemProfit($item);
                    $totalCost = $this->financialSnapshots->itemCost($item);

                    fputcsv($file, [
                        $sale->sale_no,
                        $sale->sale_date,
                        $sale->customer->name ?? 'ลูกค้าทั่วไป',
                        $item->product->name ?? '-',
                        $qty,
                        $sellingPrice,
                        $totalCost,
                        $total,
                        $profit,
                    ]);
                }
            }

            fclose($file);
        };

        return response()->stream(
            $callback,
            200,
            $headers
        );
    }

    public function exportYearlyProfit(Request $request)
    {
        $year = $request->input('year', date('Y'));

        $sales = Sale::with(['customer', 'items.product'])
            ->whereYear('sale_date', $year)
            ->orderBy('sale_date')
            ->get();

        $fileName = 'yearly-profit-'.$year.'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ];

        $callback = function () use ($sales) {

            $file = fopen('php://output', 'w');

            fwrite($file, "\xEF\xBB\xBF");

            fputcsv($file, [
                'เลขที่บิล',
                'วันที่ขาย',
                'ลูกค้า',
                'สินค้า',
                'จำนวน',
                'ราคาขาย',
                'ต้นทุนรวม',
                'ยอดขาย',
                'กำไร',
            ]);

            foreach ($sales as $sale) {

                foreach ($sale->items as $item) {

                    $sellingPrice = $item->selling_price ?? 0;
                    $qty = $item->qty ?? 0;
                    $total = $this->financialSnapshots->itemRevenue($item);
                    $profit = $this->financialSnapshots->itemProfit($item);
                    $totalCost = $this->financialSnapshots->itemCost($item);

                    fputcsv($file, [
                        $sale->sale_no,
                        $sale->sale_date,
                        $sale->customer->name ?? 'ลูกค้าทั่วไป',
                        $item->product->name ?? '-',
                        $qty,
                        $sellingPrice,
                        $totalCost,
                        $total,
                        $profit,
                    ]);
                }
            }

            fclose($file);
        };

        return response()->stream(
            $callback,
            200,
            $headers
        );
    }

    public function exportProductSales(Request $request)
    {
        $month = $request->input('month', date('m'));
        $year = $request->input('year', date('Y'));

        $items = SaleItem::with('product', 'sale')
            ->whereHas('sale', function ($query) use ($month, $year) {
                $query->whereMonth('sale_date', $month)
                    ->whereYear('sale_date', $year);
            })
            ->get();

        $products = [];

        foreach ($items as $item) {
            $productId = $item->product_id;

            if (! isset($products[$productId])) {
                $products[$productId] = [
                    'name' => $item->product->name ?? '-',
                    'unit' => $item->product->unit ?? '',
                    'qty' => 0,
                    'sales' => '0.00',
                    'cost' => '0.00',
                    'profit' => '0.00',
                ];
            }

            $products[$productId]['qty'] += $item->qty;
            $products[$productId]['sales'] = $this->decimalService->addMoney(
                $products[$productId]['sales'],
                $this->financialSnapshots->itemRevenue($item)
            );
            $products[$productId]['cost'] = $this->decimalService->addMoney(
                $products[$productId]['cost'],
                $this->financialSnapshots->itemCost($item)
            );
            $products[$productId]['profit'] = $this->decimalService->addMoney(
                $products[$productId]['profit'],
                $this->financialSnapshots->itemProfit($item)
            );
        }

        $fileName = 'product-sales-'.$year.'-'.$month.'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ];

        $callback = function () use ($products) {
            $file = fopen('php://output', 'w');

            fwrite($file, "\xEF\xBB\xBF");

            fputcsv($file, [
                'สินค้า',
                'หน่วย',
                'จำนวนขาย',
                'ยอดขายรวม',
            ]);

            foreach ($products as $product) {
                fputcsv($file, [
                    $product['name'],
                    $product['unit'],
                    $product['qty'],
                    $product['sales'],
                ]);
            }

            fclose($file);
        };

        return response()->stream(
            $callback,
            200,
            $headers
        );
    }

    public function exportBestSeller(Request $request)
    {
        $month = $request->input('month', date('m'));
        $year = $request->input('year', date('Y'));

        $items = SaleItem::with('product', 'sale')
            ->whereHas('sale', function ($query) use ($month, $year) {
                $query->whereMonth('sale_date', $month)
                    ->whereYear('sale_date', $year);
            })
            ->get();

        $products = [];

        foreach ($items as $item) {
            $productId = $item->product_id;

            if (! isset($products[$productId])) {
                $products[$productId] = [
                    'name' => $item->product->name ?? '-',
                    'unit' => $item->product->unit ?? '',
                    'qty' => 0,
                ];
            }

            $products[$productId]['qty'] += $item->qty;
        }

        usort($products, function ($a, $b) {
            return $b['qty'] <=> $a['qty'];
        });

        $products = array_slice($products, 0, 10);

        $fileName = 'best-seller-'.$year.'-'.$month.'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ];

        $callback = function () use ($products) {
            $file = fopen('php://output', 'w');

            fwrite($file, "\xEF\xBB\xBF");

            fputcsv($file, [
                'อันดับ',
                'สินค้า',
                'หน่วย',
                'จำนวนขาย',
            ]);

            $rank = 1;

            foreach ($products as $product) {
                fputcsv($file, [
                    $rank,
                    $product['name'],
                    $product['unit'],
                    $product['qty'],
                ]);

                $rank++;
            }

            fclose($file);
        };

        return response()->stream(
            $callback,
            200,
            $headers
        );
    }
}
