<?php

namespace App\Http\Controllers\Accounting;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StoreUserRequest;
use App\Http\Requests\Auth\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->role))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('accounting.users.index', [
            'users' => $users,
            'roleOptions' => UserRole::options(),
            'breadcrumbs' => [
                ['label' => 'Accounting', 'url' => route('accounting.dashboard')],
                ['label' => 'Pengguna'],
            ],
        ]);
    }

    public function create(): View
    {
        return view('accounting.users.form', [
            'user' => new User([
                'role' => UserRole::User,
                'is_active' => true,
            ]),
            'roleOptions' => UserRole::options(),
            'breadcrumbs' => [
                ['label' => 'Accounting', 'url' => route('accounting.dashboard')],
                ['label' => 'Pengguna', 'url' => route('accounting.users.index')],
                ['label' => 'Tambah'],
            ],
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
            'role' => $request->role,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('accounting.users.index')
            ->with('success', 'Pengguna berhasil ditambahkan.');
    }

    public function edit(User $user): View
    {
        return view('accounting.users.form', [
            'user' => $user,
            'roleOptions' => UserRole::options(),
            'breadcrumbs' => [
                ['label' => 'Accounting', 'url' => route('accounting.dashboard')],
                ['label' => 'Pengguna', 'url' => route('accounting.users.index')],
                ['label' => $user->name],
            ],
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
            'is_active' => $request->boolean('is_active', false),
        ];

        if ($request->filled('password')) {
            $data['password'] = $request->password;
        }

        $user->update($data);

        return redirect()
            ->route('accounting.users.index')
            ->with('success', 'Pengguna berhasil diperbarui.');
    }
}
