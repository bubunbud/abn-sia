<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\JournalEntry;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            'total_entries' => JournalEntry::count(),
            'draft_entries' => JournalEntry::where('status', 'draft')->count(),
            'posted_entries' => JournalEntry::where('status', 'posted')->count(),
        ];

        $recentEntries = JournalEntry::with('journalType')
            ->orderByDesc('entry_date')
            ->limit(10)
            ->get();

        return view('accounting.dashboard', [
            'stats' => $stats,
            'recentEntries' => $recentEntries,
            'breadcrumbs' => [
                ['label' => 'Accounting'],
                ['label' => 'Dashboard'],
            ],
        ]);
    }
}
