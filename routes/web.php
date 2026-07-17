<?php

use App\Http\Controllers\Accounting\AccountController;
use App\Http\Controllers\Accounting\PartnerController;
use App\Http\Controllers\Accounting\DashboardController;
use App\Http\Controllers\Accounting\GeneralLedgerController;
use App\Http\Controllers\Accounting\JournalEntryController;
use App\Http\Controllers\Accounting\JournalImportController;
use App\Http\Controllers\Accounting\PeriodClosingController;
use App\Http\Controllers\Accounting\ReportController;
use App\Http\Controllers\Accounting\TaxCodeController;
use App\Http\Controllers\Accounting\TrialBalanceController;
use App\Http\Controllers\Accounting\UserController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/accounting');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

Route::prefix('accounting')->name('accounting.')->middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/accounts/search', [AccountController::class, 'search'])->name('accounts.search');
    Route::get('/accounts', [AccountController::class, 'index'])->name('accounts.index');
    Route::get('/accounts/create', [AccountController::class, 'create'])->name('accounts.create');
    Route::post('/accounts', [AccountController::class, 'store'])->name('accounts.store');
    Route::get('/accounts/{account}/edit', [AccountController::class, 'edit'])->name('accounts.edit');
    Route::put('/accounts/{account}', [AccountController::class, 'update'])->name('accounts.update');

    Route::get('/partners/search', [PartnerController::class, 'search'])->name('partners.search');
    Route::get('/partners', [PartnerController::class, 'index'])->name('partners.index');
    Route::get('/partners/create', [PartnerController::class, 'create'])->name('partners.create');
    Route::post('/partners', [PartnerController::class, 'store'])->name('partners.store');
    Route::get('/partners/{partner}/edit', [PartnerController::class, 'edit'])->name('partners.edit');
    Route::put('/partners/{partner}', [PartnerController::class, 'update'])->name('partners.update');

    Route::get('/journal-entries/preview-number', [JournalEntryController::class, 'previewNumber'])
        ->name('journal-entries.preview-number');
    Route::get('/journal-entries/export', [JournalEntryController::class, 'export'])
        ->name('journal-entries.export');
    Route::get('/journal-entries/import', [JournalImportController::class, 'create'])
        ->name('journal-entries.import.create');
    Route::get('/journal-entries/import/template', [JournalImportController::class, 'downloadTemplate'])
        ->name('journal-entries.import.template');
    Route::post('/journal-entries/import', [JournalImportController::class, 'store'])
        ->name('journal-entries.import.store');
    Route::post('/journal-entries/{journal_entry}/post', [JournalEntryController::class, 'post'])
        ->name('journal-entries.post');
    Route::post('/journal-entries/{journal_entry}/unpost', [JournalEntryController::class, 'unpost'])
        ->name('journal-entries.unpost');
    Route::resource('journal-entries', JournalEntryController::class);

    Route::get('/general-ledger', [GeneralLedgerController::class, 'index'])->name('general-ledger.index');
    Route::get('/general-ledger/export', [GeneralLedgerController::class, 'export'])->name('general-ledger.export');
    Route::get('/general-ledger/summary', [GeneralLedgerController::class, 'summary'])->name('general-ledger.summary');
    Route::get('/general-ledger/line/{line}', [GeneralLedgerController::class, 'fromJournalLine'])
        ->name('general-ledger.from-line');

    Route::get('/trial-balance', [TrialBalanceController::class, 'index'])->name('trial-balance.index');
    Route::get('/trial-balance/export', [TrialBalanceController::class, 'export'])->name('trial-balance.export');

    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/balance-sheet/export', [ReportController::class, 'exportBalanceSheet'])->name('balance-sheet.export');
        Route::get('/balance-sheet', [ReportController::class, 'balanceSheetSummary'])->name('balance-sheet');
        Route::get('/balance-sheet-detail', [ReportController::class, 'balanceSheet'])->name('balance-sheet-detail');
        Route::get('/profit-loss/export', [ReportController::class, 'exportProfitLoss'])->name('profit-loss.export');
        Route::get('/profit-loss', [ReportController::class, 'profitLoss'])->name('profit-loss');
        Route::get('/profit-loss-detail', [ReportController::class, 'profitLossDetail'])->name('profit-loss-detail');
    });

    Route::get('/period-closing', [PeriodClosingController::class, 'index'])->name('period-closing.index');
    Route::post('/period-closing/generate', [PeriodClosingController::class, 'generate'])->name('period-closing.generate');
    Route::post('/period-closing/{fiscalPeriod}/close', [PeriodClosingController::class, 'close'])->name('period-closing.close');
    Route::post('/period-closing/{fiscalPeriod}/reopen', [PeriodClosingController::class, 'reopen'])->name('period-closing.reopen');

    Route::get('/tax-codes', [TaxCodeController::class, 'index'])->name('tax-codes.index');

    Route::middleware('admin')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    });
});
