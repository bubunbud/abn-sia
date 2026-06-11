<?php

namespace App\Http\Controllers\Accounting;

use App\Enums\AccountCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StoreAccountRequest;
use App\Http\Requests\Accounting\UpdateAccountRequest;
use App\Models\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function search(Request $request)
    {
        $query = trim($request->string('q')->toString());

        if ($query === '') {
            return response()->json([]);
        }

        $accounts = Account::query()
            ->where('is_active', true)
            ->where('is_header', false)
            ->where(function ($q) use ($query) {
                $q->where('code', 'like', "%{$query}%")
                    ->orWhere('name', 'like', "%{$query}%");
            })
            ->orderBy('code')
            ->limit(20)
            ->get();

        return response()->json(
            $accounts->map(fn (Account $account) => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'label' => $account->displayName(),
            ])
        );
    }

    public function index(Request $request): View
    {
        $accounts = Account::query()
            ->with('parent')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('group_name', 'like', "%{$search}%");
                });
            })
            ->orderBy('code')
            ->get();

        return view('accounting.accounts.index', [
            'accounts' => $accounts,
            'breadcrumbs' => [
                ['label' => 'Accounting', 'url' => route('accounting.dashboard')],
                ['label' => 'Chart of Accounts'],
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $isHeader = $request->string('type')->toString() !== 'detail';

        return view('accounting.accounts.form', [
            'account' => new Account([
                'is_header' => $isHeader,
                'normal_balance' => 'debit',
                'is_active' => true,
                'parent_id' => $request->integer('parent_id') ?: null,
            ]),
            'headerAccounts' => $this->headerAccounts(),
            'breadcrumbs' => [
                ['label' => 'Accounting', 'url' => route('accounting.dashboard')],
                ['label' => 'Chart of Accounts', 'url' => route('accounting.accounts.index')],
                ['label' => $isHeader ? 'Tambah Header' : 'Tambah Detail'],
            ],
        ]);
    }

    public function store(StoreAccountRequest $request): RedirectResponse
    {
        $data = $this->buildAccountData($request->validated());

        Account::create($data);

        return redirect()
            ->route('accounting.accounts.index')
            ->with('success', 'Akun berhasil ditambahkan.');
    }

    public function edit(Account $account): View
    {
        return view('accounting.accounts.form', [
            'account' => $account,
            'headerAccounts' => $this->headerAccounts($account->id),
            'breadcrumbs' => [
                ['label' => 'Accounting', 'url' => route('accounting.dashboard')],
                ['label' => 'Chart of Accounts', 'url' => route('accounting.accounts.index')],
                ['label' => 'Edit ' . $account->code],
            ],
        ]);
    }

    public function update(UpdateAccountRequest $request, Account $account): RedirectResponse
    {
        $data = $this->buildAccountData($request->validated());

        $account->update($data);

        return redirect()
            ->route('accounting.accounts.index')
            ->with('success', 'Akun berhasil diperbarui.');
    }

    private function headerAccounts(?int $excludeId = null)
    {
        return Account::query()
            ->where('is_header', true)
            ->where('is_active', true)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->orderBy('code')
            ->get();
    }

    private function buildAccountData(array $validated): array
    {
        $parts = explode('.', $validated['code']);
        $level = $this->resolveLevel($parts);

        return [
            'code' => $validated['code'],
            'name' => $validated['name'],
            'group_name' => $validated['group_name'] ?? null,
            'account_category' => AccountCategory::fromCode($validated['code']),
            'normal_balance' => $validated['normal_balance'],
            'is_header' => $validated['is_header'],
            'parent_id' => $validated['parent_id'] ?? null,
            'level' => $level,
            'is_active' => filter_var($validated['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    private function resolveLevel(array $parts): int
    {
        if (($parts[1] ?? '') === '000' && ($parts[2] ?? '') === '000') {
            return 1;
        }

        if (($parts[2] ?? '') === '000') {
            return 2;
        }

        return 3;
    }
}
