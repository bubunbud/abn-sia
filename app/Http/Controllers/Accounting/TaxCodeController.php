<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class TaxCodeController extends Controller
{
    public function index(): View
    {
        return view('accounting.placeholder', [
            'title' => 'Tax Codes',
            'breadcrumbs' => [
                ['label' => 'Accounting', 'url' => route('accounting.dashboard')],
                ['label' => 'Tax'],
                ['label' => 'Tax Codes'],
            ],
        ]);
    }
}
