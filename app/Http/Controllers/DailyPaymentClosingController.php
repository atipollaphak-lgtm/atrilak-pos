<?php

namespace App\Http\Controllers;

use App\Http\Requests\DailyPaymentClosings\FinalizeDailyPaymentClosingRequest;
use App\Http\Requests\DailyPaymentClosings\ReopenDailyPaymentClosingRequest;
use App\Http\Requests\DailyPaymentClosings\StoreDailyPaymentClosingRequest;
use App\Http\Requests\DailyPaymentClosings\UpdateDailyPaymentClosingRequest;
use App\Models\DailyPaymentClosing;
use App\Models\Setting;
use App\Services\Sales\DailyPaymentClosingDriftService;
use App\Services\Sales\DailyPaymentClosingService;
use App\Services\Sales\DailyPaymentSummaryService;
use App\Services\Sales\SaleDecimalService;
use DomainException;
use Illuminate\Http\Request;

class DailyPaymentClosingController extends Controller
{
    public function index(DailyPaymentClosingDriftService $driftService)
    {
        $closings = DailyPaymentClosing::query()
            ->with(['finalizedBy', 'sales'])
            ->orderByDesc('business_date')
            ->paginate(20);

        $drifts = $driftService->compareMany($closings->getCollection());

        return view('daily-payment-closings.index', compact('closings', 'drifts'));
    }

    public function create(Request $request, DailyPaymentClosingService $service)
    {
        $businessDate = $request->validate([
            'business_date' => ['nullable', 'date_format:Y-m-d'],
        ])['business_date'] ?? now()->toDateString();
        [$closing, $created] = $service->open($businessDate, $request->user());

        if (! $created) {
            return redirect()->route('daily-payment-closings.edit', $closing);
        }

        return redirect()->route('daily-payment-closings.edit', $closing)
            ->with('success', 'เปิดรายการปิดยอดประจำวันแล้ว');
    }

    public function edit(
        DailyPaymentClosing $dailyPaymentClosing,
        DailyPaymentSummaryService $summaryService,
        SaleDecimalService $decimalService
    ) {
        if ($dailyPaymentClosing->status !== DailyPaymentClosing::STATUS_OPEN) {
            return redirect()->route('daily-payment-closings.show', $dailyPaymentClosing);
        }

        $summary = $summaryService->forBusinessDate((string) $dailyPaymentClosing->business_date);
        $hasSavedActualAmounts = $dailyPaymentClosing->actual_cash_amount !== null
            && $dailyPaymentClosing->actual_promptpay_amount !== null;
        $actualCash = $dailyPaymentClosing->actual_cash_amount ?: '0.00';
        $actualPromptpay = $dailyPaymentClosing->actual_promptpay_amount ?: '0.00';
        $cashVariancePreview = $decimalService->subtractMoney(
            $actualCash,
            $summary['cash_total']
        );
        $promptpayVariancePreview = $decimalService->subtractMoney(
            $actualPromptpay,
            $summary['promptpay_total']
        );

        $dailyPaymentClosing->setAttribute('actual_cash_amount', $actualCash);
        $dailyPaymentClosing->setAttribute('actual_promptpay_amount', $actualPromptpay);

        return view('daily-payment-closings.form', compact(
            'dailyPaymentClosing',
            'summary',
            'hasSavedActualAmounts',
            'cashVariancePreview',
            'promptpayVariancePreview'
        ));
    }

    public function show(DailyPaymentClosing $dailyPaymentClosing, DailyPaymentClosingDriftService $driftService)
    {
        $dailyPaymentClosing->load(['openedBy', 'finalizedBy', 'reopenedBy', 'sales.sale']);
        $drift = $driftService->compare($dailyPaymentClosing);

        return view('daily-payment-closings.show', compact('dailyPaymentClosing', 'drift'));
    }

    public function print(DailyPaymentClosing $dailyPaymentClosing, DailyPaymentClosingDriftService $driftService)
    {
        abort_unless($dailyPaymentClosing->status === DailyPaymentClosing::STATUS_FINALIZED, 404);

        $dailyPaymentClosing->load(['openedBy', 'finalizedBy', 'reopenedBy', 'sales.sale']);
        $setting = Setting::query()->first();
        $drift = $driftService->compare($dailyPaymentClosing);

        return view('daily-payment-closings.print', compact('dailyPaymentClosing', 'setting', 'drift'));
    }

    public function store(StoreDailyPaymentClosingRequest $request, DailyPaymentClosingService $service)
    {
        [$closing, $created] = $service->open($request->validated('business_date'), $request->user());

        return response()->json(['data' => $closing], $created ? 201 : 200);
    }

    public function update(UpdateDailyPaymentClosingRequest $request, DailyPaymentClosing $dailyPaymentClosing, DailyPaymentClosingService $service)
    {
        $operation = fn () => $service->update(
            $dailyPaymentClosing,
            $request->validated('actual_cash_amount'),
            $request->validated('actual_promptpay_amount'),
            $request->validated('notes'),
            $request->integer('revision')
        );

        if ($request->expectsJson()) {
            return $this->respond($operation);
        }

        return $this->redirectAfterOperation($operation, 'daily-payment-closings.edit', $dailyPaymentClosing, 'บันทึกยอดตรวจนับแล้ว');
    }

    public function finalize(FinalizeDailyPaymentClosingRequest $request, DailyPaymentClosing $dailyPaymentClosing, DailyPaymentClosingService $service)
    {
        $operation = fn () => $service->finalize(
            $dailyPaymentClosing,
            $request->integer('revision'),
            $request->user()
        );

        if ($request->expectsJson()) {
            return $this->respond($operation);
        }

        return $this->redirectAfterOperation($operation, 'daily-payment-closings.show', $dailyPaymentClosing, 'ปิดยอดประจำวันแล้ว');
    }

    public function reopen(ReopenDailyPaymentClosingRequest $request, DailyPaymentClosing $dailyPaymentClosing, DailyPaymentClosingService $service)
    {
        $operation = fn () => $service->reopen(
            $dailyPaymentClosing,
            $request->validated('reason'),
            $request->integer('revision'),
            $request->user()
        );

        if ($request->expectsJson()) {
            return $this->respond($operation);
        }

        return $this->redirectAfterOperation($operation, 'daily-payment-closings.show', $dailyPaymentClosing, 'เปิดรายการปิดยอดใหม่แล้ว');
    }

    private function respond(callable $operation)
    {
        try {
            return response()->json(['data' => $operation()]);
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->getCode() ?: 422);
        }
    }

    private function redirectAfterOperation(callable $operation, string $route, DailyPaymentClosing $closing, string $successMessage)
    {
        try {
            $operation();

            return redirect()->route($route, $closing)->with('success', $successMessage);
        } catch (DomainException $exception) {
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        }
    }
}
