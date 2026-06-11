<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StorePartnerRequest;
use App\Http\Requests\Accounting\UpdatePartnerRequest;
use App\Models\Partner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PartnerController extends Controller
{
    public function search(Request $request)
    {
        $query = trim($request->string('q')->toString());

        if ($query === '') {
            return response()->json([]);
        }

        $partners = Partner::query()
            ->where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('code', 'like', "%{$query}%")
                    ->orWhere('name', 'like', "%{$query}%");
            })
            ->orderBy('code')
            ->limit(20)
            ->get();

        return response()->json(
            $partners->map(fn (Partner $partner) => [
                'id' => $partner->id,
                'code' => $partner->code,
                'name' => $partner->name,
                'label' => $partner->displayName(),
                'type_label' => $partner->status_label ?? $partner->typeLabel(),
            ])
        );
    }

    public function index(Request $request): View
    {
        $partners = Partner::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->orderBy('code')
            ->paginate(50)
            ->withQueryString();

        return view('accounting.partners.index', [
            'partners' => $partners,
            'typeOptions' => Partner::typeOptions(),
            'breadcrumbs' => [
                ['label' => 'Accounting', 'url' => route('accounting.dashboard')],
                ['label' => 'Pihak Kedua'],
            ],
        ]);
    }

    public function create(): View
    {
        return view('accounting.partners.form', [
            'partner' => new Partner([
                'type' => 'customer',
                'region' => 'Lokal',
                'is_active' => true,
            ]),
            'typeOptions' => Partner::typeOptions(),
            'breadcrumbs' => [
                ['label' => 'Accounting', 'url' => route('accounting.dashboard')],
                ['label' => 'Pihak Kedua', 'url' => route('accounting.partners.index')],
                ['label' => 'Tambah'],
            ],
        ]);
    }

    public function store(StorePartnerRequest $request): RedirectResponse
    {
        Partner::create($this->buildData($request->validated()));

        return redirect()
            ->route('accounting.partners.index')
            ->with('success', 'Pihak Kedua berhasil ditambahkan.');
    }

    public function edit(Partner $partner): View
    {
        return view('accounting.partners.form', [
            'partner' => $partner,
            'typeOptions' => Partner::typeOptions(),
            'breadcrumbs' => [
                ['label' => 'Accounting', 'url' => route('accounting.dashboard')],
                ['label' => 'Pihak Kedua', 'url' => route('accounting.partners.index')],
                ['label' => 'Edit ' . $partner->code],
            ],
        ]);
    }

    public function update(UpdatePartnerRequest $request, Partner $partner): RedirectResponse
    {
        $partner->update($this->buildData($request->validated()));

        return redirect()
            ->route('accounting.partners.index')
            ->with('success', 'Pihak Kedua berhasil diperbarui.');
    }

    private function buildData(array $validated): array
    {
        $type = $validated['type'];

        return [
            'code' => $validated['code'],
            'name' => $validated['name'],
            'type' => $type,
            'region' => $validated['region'] ?? null,
            'status_label' => $validated['status_label'] ?? Partner::typeOptions()[$type] ?? null,
            'is_active' => filter_var($validated['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
        ];
    }
}
