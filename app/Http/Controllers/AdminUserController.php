<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

final class AdminUserController extends Controller
{
    public function __construct(private UserService $users)
    {
    }

    public function index(Request $request): View
    {
        $query = User::query()->orderBy('username');

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
                $builder->where('username', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('email', 'like', $like);
            });
        }

        $role = (string) $request->query('role', '');
        if (in_array($role, User::ROLES, true)) {
            $query->where('role', $role);
        }

        $active = (string) $request->query('aktivni', '');
        if ($active === '1' || $active === '0') {
            $query->where('is_active', $active === '1');
        }

        return view('admin.users.index', [
            'users' => $query->paginate(20)->withQueryString(),
            'search' => $search,
            'role' => $role,
            'active' => $active,
            'activeAdminCount' => $this->users->activeAdminCount(),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.form', $this->formData(null));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->users->create($this->validated($request, true));

        return redirect()->route('admin.users.index')->with('status', 'Uživatel byl vytvořen.');
    }

    public function edit(User $user): View
    {
        return view('admin.users.form', $this->formData($user));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->users->update($user, $this->validated($request, false));

        return redirect()->route('admin.users.index')->with('status', 'Uživatel byl upraven.');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [], ['password' => 'nové heslo']);

        $this->users->resetPassword($user, $validated['password']);

        return back()->with('status', 'Heslo bylo změněno a existující relace byly ukončeny.');
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        $this->users->deactivate($user, $request->user());

        return back()->with('status', 'Uživatel byl deaktivován.');
    }

    public function activate(User $user): RedirectResponse
    {
        $this->users->activate($user);

        return back()->with('status', 'Uživatel byl aktivován.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->users->delete($user, $request->user());

        return redirect()->route('admin.users.index')->with('status', 'Uživatel byl smazán.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?User $user): array
    {
        return [
            'user' => $user,
            'username' => old('username', $user?->username ?? ''),
            'name' => old('name', $user?->name ?? ''),
            'email' => old('email', $user?->email ?? ''),
            'role' => old('role', $user?->role ?? User::ROLE_USER),
            'roles' => User::ROLES,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $creating): array
    {
        $userId = $creating ? null : $request->route('user')?->id;

        return Validator::make($request->all(), [
            'username' => $creating
                ? ['required', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:users,username']
                : ['prohibited'],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:254', Rule::unique('users', 'email')->ignore($userId)],
            'role' => ['required', Rule::in(User::ROLES)],
            'password' => $creating
                ? ['required', 'string', 'confirmed', Password::min(8)->letters()->numbers()]
                : ['prohibited'],
        ], [
            'username' => 'uživatelské jméno',
            'name' => 'jméno',
            'email' => 'e-mail',
            'role' => 'role',
            'password' => 'heslo',
        ])->validate();
    }
}
