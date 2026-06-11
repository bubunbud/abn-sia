<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\FiscalPeriod;
use App\Services\PeriodClosingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PeriodClosingController extends Controller
{
    public function __construct(private PeriodClosingService $periodClosingService)
    {
    }

    public function index(Request $request): View
    {
        $year = (int) $request->get('year', now()->year);
        $periods = $this->periodClosingService->listForYear($year);

        $summary = [
            'total' => $periods->count(),
            'open' => $periods->filter(fn ($row) => $row['period']->isOpen())->count(),
            'closed' => $periods->filter(fn ($row) => $row['period']->isClosed())->count(),
        ];

        return view('accounting.period-closing.index', [
            'year' => $year,
            'periods' => $periods,
            'summary' => $summary,
            'yearOptions' => range(now()->year - 2, now()->year + 1),
            'breadcrumbs' => [
                ['label' => 'Accounting', 'url' => route('accounting.dashboard')],
                ['label' => 'Period Closing'],
            ],
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        $year = (int) $request->input('year', now()->year);
        $created = $this->periodClosingService->generateYear($year);

        return redirect()
            ->route('accounting.period-closing.index', ['year' => $year])
            ->with('success', "Periode fiskal {$year} siap. {$created} periode baru dibuat.");
    }

    public function close(Request $request, FiscalPeriod $fiscalPeriod): RedirectResponse
    {
        try {
            $this->periodClosingService->close(
                $fiscalPeriod,
                $request->input('notes')
            );

            return redirect()
                ->route('accounting.period-closing.index', ['year' => $fiscalPeriod->year])
                ->with('success', "Periode {$fiscalPeriod->name} berhasil ditutup.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reopen(FiscalPeriod $fiscalPeriod): RedirectResponse
    {
        try {
            $this->periodClosingService->reopen($fiscalPeriod);

            return redirect()
                ->route('accounting.period-closing.index', ['year' => $fiscalPeriod->year])
                ->with('success', "Periode {$fiscalPeriod->name} berhasil dibuka kembali.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
