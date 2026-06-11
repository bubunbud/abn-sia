<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Services\BalanceSheetService;
use App\Services\BalanceSheetSummaryService;
use App\Services\ProfitLossDetailService;
use App\Services\ProfitLossSummaryService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(
        private BalanceSheetService $balanceSheetService,
        private BalanceSheetSummaryService $balanceSheetSummaryService,
        private ProfitLossDetailService $profitLossDetailService,
        private ProfitLossSummaryService $profitLossSummaryService,
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
}
