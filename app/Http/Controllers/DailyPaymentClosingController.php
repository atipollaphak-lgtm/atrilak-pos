<?php

namespace App\Http\Controllers;

use App\Http\Requests\DailyPaymentClosings\FinalizeDailyPaymentClosingRequest;
use App\Http\Requests\DailyPaymentClosings\ReopenDailyPaymentClosingRequest;
use App\Http\Requests\DailyPaymentClosings\StoreDailyPaymentClosingRequest;
use App\Http\Requests\DailyPaymentClosings\UpdateDailyPaymentClosingRequest;
use App\Models\DailyPaymentClosing;
use App\Services\Sales\DailyPaymentClosingService;
use DomainException;

class DailyPaymentClosingController extends Controller
{
    public function store(StoreDailyPaymentClosingRequest $request, DailyPaymentClosingService $service)
    {
        [$closing, $created] = $service->open($request->validated('business_date'), $request->user());

        return response()->json(['data' => $closing], $created ? 201 : 200);
    }

    public function update(UpdateDailyPaymentClosingRequest $request, DailyPaymentClosing $dailyPaymentClosing, DailyPaymentClosingService $service)
    {
        return $this->respond(fn () => $service->update(
            $dailyPaymentClosing,
            $request->validated('actual_cash_amount'),
            $request->validated('actual_promptpay_amount'),
            $request->validated('notes'),
            $request->integer('revision')
        ));
    }

    public function finalize(FinalizeDailyPaymentClosingRequest $request, DailyPaymentClosing $dailyPaymentClosing, DailyPaymentClosingService $service)
    {
        return $this->respond(fn () => $service->finalize(
            $dailyPaymentClosing,
            $request->integer('revision'),
            $request->user()
        ));
    }

    public function reopen(ReopenDailyPaymentClosingRequest $request, DailyPaymentClosing $dailyPaymentClosing, DailyPaymentClosingService $service)
    {
        return $this->respond(fn () => $service->reopen(
            $dailyPaymentClosing,
            $request->validated('reason'),
            $request->integer('revision'),
            $request->user()
        ));
    }

    private function respond(callable $operation)
    {
        try {
            return response()->json(['data' => $operation()]);
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->getCode() ?: 422);
        }
    }
}
