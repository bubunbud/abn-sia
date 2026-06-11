<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Services\TrialBalanceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TrialBalanceController extends Controller
{
    public function __construct(private TrialBalanceService $trialBalanceService)
    {
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
}
