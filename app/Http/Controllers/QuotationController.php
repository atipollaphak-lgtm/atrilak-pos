<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Services\SaleService;
use App\Services\TransactionDocumentSnapshotService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class QuotationController extends Controller
{
    public function __construct(
        private SaleService $saleService,
        private TransactionDocumentSnapshotService $documentSnapshotService
    ) {}

    public function index()
    {
        $quotations = Quotation::with('customer')
            ->latest()
            ->take(30)
            ->get();

        return view(
            'quotations.index',
            compact('quotations')
        );
    }

    public function create()
    {
        $customers = Customer::where('active', true)
            ->orderBy('name')
            ->get();

        $products = Product::where('active', true)
            ->orderBy('name')
            ->get();

        return view(
            'quotations.create',
            compact(
                'customers',
                'products'
            )
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'quotation_date' => 'required|date',
            'product_id' => 'required|array',
            'qty' => 'required|array',
            'selling_price' => 'required|array',
        ]);

        DB::transaction(function () use ($request) {

            $date = $request->quotation_date;
            $productIds = collect($request->product_id)
                ->filter()
                ->map(fn ($productId) => (int) $productId)
                ->unique()
                ->values();
            $products = Product::query()
                ->whereIn('id', $productIds->all())
                ->get()
                ->keyBy('id');
            $itemSnapshots = $this->documentSnapshotService
                ->quotationItemSnapshots($products);

            $running = Quotation::whereDate(
                'quotation_date',
                $date
            )->count() + 1;

            $quotationNo = 'QT-'.
                date('Ymd', strtotime($date)).
                '-'.
                str_pad($running, 4, '0', STR_PAD_LEFT);

            $totalAmount = 0;

            foreach ($request->product_id as $index => $productId) {
                if (! $productId) {
                    continue;
                }

                $qty = (int) ($request->qty[$index] ?? 0);
                $price = (float) ($request->selling_price[$index] ?? 0);

                $totalAmount += $qty * $price;
            }

            $quotation = Quotation::create(array_merge([
                'quotation_no' => $quotationNo,
                'customer_id' => $request->customer_id,
                'quotation_date' => $date,
                'total_amount' => $totalAmount,
                'remark' => $request->remark,
                'status' => 'draft',
            ], $this->documentSnapshotService->quotationHeaderSnapshots(
                $request->customer_id === null || $request->customer_id === ''
                    ? null
                    : (int) $request->customer_id
            )));

            foreach ($request->product_id as $index => $productId) {
                if (! $productId) {
                    continue;
                }

                $qty = (int) ($request->qty[$index] ?? 0);
                $price = (float) ($request->selling_price[$index] ?? 0);
                $total = $qty * $price;

                QuotationItem::create(array_merge([
                    'quotation_id' => $quotation->id,
                    'product_id' => $productId,
                    'qty' => $qty,
                    'selling_price' => $price,
                    'total' => $total,
                ], $itemSnapshots[(int) $productId] ?? []));
            }
        });

        return redirect()
            ->route('quotations.index')
            ->with('success', 'สร้างใบเสนอราคาสำเร็จ');
    }

    public function show(Quotation $quotation)
    {
        $quotation->load([
            'customer',
            'items.product',
            'convertedSale',
        ]);

        return view(
            'quotations.show',
            compact('quotation')
        );
    }

    public function print(Quotation $quotation)
    {
        $quotation->load([
            'customer',
            'items.product',
        ]);

        return view(
            'quotations.print',
            compact('quotation')
        );
    }

    public function destroy(Quotation $quotation)
    {
        try {
            $this->saleService->deleteQuotation($quotation);
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return back()->with(
                'error',
                'ไม่สามารถลบใบเสนอราคาได้ กรุณาลองใหม่อีกครั้ง'
            );
        }

        return redirect()
            ->route('quotations.index')
            ->with('success', 'ลบใบเสนอราคาเรียบร้อย');
    }

    public function convertToSale(Quotation $quotation)
    {
        try {
            $sale = $this->saleService->createSaleFromQuotation($quotation);
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return back()->with(
                'error',
                'ไม่สามารถแปลงใบเสนอราคาเป็นใบขายได้ กรุณาลองใหม่อีกครั้ง'
            );
        }

        return redirect()
            ->route('sales.show', $sale)
            ->with(
                'success',
                'แปลงเป็นใบขายเรียบร้อย'
            );
    }
}
