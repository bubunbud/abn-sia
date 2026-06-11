<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Services\JournalImportService;
use App\Services\JournalImportTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class JournalImportController extends Controller
{
    public function __construct(
        private JournalImportService $importService,
        private JournalImportTemplateService $templateService,
    ) {
    }

    public function downloadTemplate(): StreamedResponse
    {
        return $this->templateService->downloadResponse();
    }

    public function create(): View
    {
        return view('accounting.journal-entries.import', [
            'breadcrumbs' => [
                ['label' => 'Accounting', 'url' => route('accounting.dashboard')],
                ['label' => 'Journal Entries', 'url' => route('accounting.journal-entries.index')],
                ['label' => 'Import Historis'],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:20480'],
            'replace' => ['nullable', 'boolean'],
            'force' => ['nullable', 'boolean'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $path = $request->file('file')->store('imports');

        try {
            $stats = $this->importService->import(
                storage_path('app/' . $path),
                replace: $request->boolean('replace'),
                skipExisting: ! $request->boolean('force'),
                dryRun: $request->boolean('dry_run'),
            );

            if ($request->boolean('dry_run')) {
                $message = sprintf(
                    'Validasi berhasil: %d jurnal siap diimpor, %d gagal validasi.',
                    $stats['entries_ready'] ?? 0,
                    $stats['entries_failed'] ?? 0,
                );
            } else {
                $message = sprintf(
                    'Import selesai: %d jurnal, %d baris detail. Dilewati: %d. Gagal: %d.',
                    $stats['entries_imported'],
                    $stats['lines_imported'],
                    $stats['entries_skipped'],
                    $stats['entries_failed'],
                );
            }

            $redirect = redirect()
                ->route('accounting.journal-entries.import.create')
                ->with('import_stats', $stats)
                ->with($stats['entries_failed'] > 0 ? 'warning' : 'success', $message);

            if (! empty($stats['errors'])) {
                $redirect->with('import_errors', array_slice($stats['errors'], 0, 30));
            }

            return $redirect;
        } catch (\Throwable $e) {
            return back()
                ->withInput()
                ->with('error', 'Import gagal: ' . $e->getMessage());
        }
    }
}
