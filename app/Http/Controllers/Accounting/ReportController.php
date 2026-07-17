<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Services\BalanceSheetExportService;
use App\Services\BalanceSheetService;
use App\Services\BalanceSheetSummaryService;
use App\Services\ProfitLossDetailService;
use App\Services\ProfitLossExportService;
use App\Services\ProfitLossSummaryService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private BalanceSheetService $balanceSheetService,
        private BalanceSheetSummaryService $balanceSheetSummaryService,
        private BalanceSheetExportService $balanceSheetExportService,
        private ProfitLossDetailService $profitLossDetailService,
        private ProfitLossSummaryService $profitLossSummaryService,
        private ProfitLossExportService $profitLossExportService,
    ) {
    }

    public function balanceSheet(Request $request): View
    {
        $year = (int) $request->get('year', now()->year);
        $hideZero = $request->boolean('hide_zero');

        $report = $this->balanceSheetService->generate($year, $hideZero);

        return view('accounting.reports.balance-sheet', [
            'report' => $report,
            'year' => $year,
            'hideZero' => $hideZero,
            'yearOptions' => range(now()->year - 2, now()->year + 1),
            'breadcrumbs' => [
                ['label' => 'Accounting', 'url' => route('accounting.dashboard')],
                ['label' => 'Reports'],
                ['label' => 'Balance Sheet Detail'],
            ],
        ]);
    }

    public function balanceSheetSummary(Request $request): View
    {
        $year = (int) $request->get('year', now()->year);
        $hideZero = $request->boolean('hide_zero');

        $report = $this->balanceSheetSummaryService->generate($year, $hideZero);

        return view('accounting.reports.balance-sheet-summary', [
            'report' => $report,
            'year' => $year,
            'hideZero' => $hideZero,
            'yearOptions' => range(now()->year - 2, now()->year + 1),
            'breadcrumbs' => [
                ['label' => 'Accounting', 'url' => route('accounting.dashboard')],
                ['label' => 'Reports'],
                ['label' => 'Balance Sheet'],
            ],
        ]);
    }

    public function exportBalanceSheet(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'hide_zero' => ['nullable', 'boolean'],
        ]);

        return $this->balanceSheetExportService->downloadResponse(
            (int) $validated['year'],
            $request->boolean('hide_zero'),
        );
    }

    public function profitLoss(Request $request): View
    {
        $year = (int) $request->get('year', now()->year);
        $hideZero = $request->boolean('hide_zero');

        $report = $this->profitLossSummaryService->generate($year, $hideZero);

        return view('accounting.reports.profit-loss-summary', [
            'report' => $report,
            'year' => $year,
            'hideZero' => $hideZero,
            'yearOptions' => range(now()->year - 2, now()->year + 1),
            'breadcrumbs' => [
                ['label' => 'Accounting', 'url' => route('accounting.dashboard')],
                ['label' => 'Reports'],
                ['label' => 'Profit & Loss'],
            ],
        ]);
    }

    public function profitLossDetail(Request $request): View
    {
        $year = (int) $request->get('year', now()->year);
        $hideZero = $request->boolean('hide_zero');

        $report = $this->profitLossDetailService->generate($year, $hideZero);

        return view('accounting.reports.profit-loss-detail', [
            'report' => $report,
            'year' => $year,
            'hideZero' => $hideZero,
            'yearOptions' => range(now()->year - 2, now()->year + 1),
            'breadcrumbs' => [
                ['label' => 'Accounting', 'url' => route('accounting.dashboard')],
                ['label' => 'Reports'],
                ['label' => 'Profit & Loss Detail'],
            ],
        ]);
    }

    public function exportProfitLoss(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'hide_zero' => ['nullable', 'boolean'],
        ]);

        return $this->profitLossExportService->downloadResponse(
            (int) $validated['year'],
            $request->boolean('hide_zero'),
        );
    }
}
