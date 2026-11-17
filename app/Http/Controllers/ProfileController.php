<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Venue;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

final class ProfileController extends Controller
{
    public function __construct(private UserService $users)
    {
    }

    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = Validator::make($request->all(), [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:254', 'unique:users,email,' . $request->user()->id],
        ], [], [
            'name' => 'jméno a příjmení',
            'email' => 'e-mail',
        ])->validate();

        $this->users->updateProfile($request->user(), $validated['name'], $validated['email']);

        return back()->with('status', 'Profil byl uložen.');
    }

    public function password(Request $request): RedirectResponse
    {
        $validated = Validator::make($request->all(), [
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [], [
            'current_password' => 'současné heslo',
            'new_password' => 'nové heslo',
        ])->validate();

        $this->users->changePassword($request->user(), $validated['current_password'], $validated['new_password']);

        $request->session()->put('auth_version', $request->user()->fresh()->auth_version);

        return back()->with('status', 'Heslo bylo změněno.');
    }

    public function proposals(Request $request): View
    {
        $userId = $request->user()->id;

        return view('profile.proposals', [
            'venues' => Venue::query()->where('proposed_by', $userId)->orderByDesc('created_at')->get(),
            'categories' => Category::query()->where('proposed_by', $userId)->orderByDesc('created_at')->get(),
        ]);
    }
}
