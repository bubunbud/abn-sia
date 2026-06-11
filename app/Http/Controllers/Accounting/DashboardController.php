<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $dashboardService)
    {
    }

    public function index(): View
    {
        $data = $this->dashboardService->build();

        return view('accounting.dashboard', [
            'stats' => $data['stats'],
            'current' => $data['current'],
            'fiscalOverview' => $data['fiscal_overview'],
            'alerts' => $data['alerts'],
            'recentEntries' => $data['recent_entries'],
            'breadcrumbs' => [
                ['label' => 'Accounting'],
                ['label' => 'Dashboard'],
            ],
        ]);
    }
}
