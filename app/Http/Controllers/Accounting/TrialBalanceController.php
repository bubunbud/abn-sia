<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Services\TrialBalanceExportService;
use App\Services\TrialBalanceService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TrialBalanceController extends Controller
{
    public function __construct(
        private TrialBalanceService $trialBalanceService,
        private TrialBalanceExportService $exportService,
    ) {
    }

    public function index(Request $request): View
    {
        $year = (int) $request->get('year', now()->year);
        $period = (int) $request->get('period', now()->month);
        $hideZero = $request->boolean('hide_zero');

        $period = max(1, min(12, $period));

        $report = $this->trialBalanceService->generate($year, $period, $hideZero);

        return view('accounting.trial-balance.index', [
            'report' => $report,
            'year' => $year,
            'period' => $period,
            'hideZero' => $hideZero,
            'yearOptions' => range(now()->year - 2, now()->year + 1),
            'periodOptions' => range(1, 12),
            'breadcrumbs' => [
                ['label' => 'Accounting', 'url' => route('accounting.dashboard')],
                ['label' => 'Trial Balance'],
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'hide_zero' => ['nullable', 'boolean'],
        ]);

        return $this->exportService->downloadResponse(
            (int) $validated['year'],
            $request->boolean('hide_zero'),
        );
    }
}
