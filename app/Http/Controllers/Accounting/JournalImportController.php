<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Services\JournalImportService;
use App\Services\JournalImportSetupService;
use App\Services\JournalImportTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JournalImportController extends Controller
{
    public function __construct(
        private JournalImportService $importService,
        private JournalImportTemplateService $templateService,
        private JournalImportSetupService $setupService,
    ) {
    }

    public function downloadTemplate(): StreamedResponse
    {
        return $this->templateService->downloadResponse();
    }

    public function create(): View
    {
        $prerequisites = $this->setupService->prerequisites();
        $defaultFile = $this->setupService->resolveDefaultFile();

        return view('accounting.journal-entries.import', [
            'prerequisites' => $prerequisites,
            'defaultFile' => $defaultFile,
            'readyToImport' => $prerequisites['accounts'] > 0,
            'breadcrumbs' => [
                ['label' => 'Accounting', 'url' => route('accounting.dashboard')],
                ['label' => 'Journal Entries', 'url' => route('accounting.journal-entries.index')],
                ['label' => 'Import Jurnal'],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:51200'],
            'replace' => ['nullable', 'boolean'],
            'force' => ['nullable', 'boolean'],
            'dry_run' => ['nullable', 'boolean'],
            'generate_periods' => ['nullable', 'boolean'],
        ]);

        $path = $request->file('file')->store('imports');

        try {
            $stats = $this->importService->import(
                storage_path('app/' . $path),
                replace: $request->boolean('replace'),
                skipExisting: ! $request->boolean('force'),
                dryRun: $request->boolean('dry_run'),
                generatePeriods: $request->boolean('generate_periods'),
            );

            if ($request->boolean('dry_run')) {
                $message = sprintf(
                    'Validasi berhasil: %d jurnal (%d baris) siap diimpor. Gagal validasi: %d.',
                    $stats['entries_ready'] ?? 0,
                    $stats['lines_ready'] ?? 0,
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

                if (($stats['periods_generated'] ?? 0) > 0) {
                    $message .= ' Periode fiskal baru: ' . $stats['periods_generated'] . '.';
                }
            }

            $redirect = redirect()
                ->route('accounting.journal-entries.import.create')
                ->with('import_stats', $stats)
                ->with($stats['entries_failed'] > 0 ? 'warning' : 'success', $message);

            if (! empty($stats['errors'])) {
                $redirect->with('import_errors', array_slice($stats['errors'], 0, 50));
            }

            return $redirect;
        } catch (\Throwable $e) {
            return back()
                ->withInput()
                ->with('error', 'Import gagal: ' . $e->getMessage());
        }
    }
}
