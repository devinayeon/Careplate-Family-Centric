<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Pengguna;
use App\Services\Profile\ProfileProgress;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request, ProfileProgress $progress): Response|RedirectResponse
    {
        $user = $request->user();
        $props = ['mustVerifyEmail' => $user instanceof MustVerifyEmail, 'status' => session('status')];

        // The admin has its own profile screen; roles without a Figma screen keep the classic page.
        if ($user->hasRole('admin')) {
            return Redirect::route('admin.profile.edit');
        }
        if (! $user->hasRole('user')) {
            return Inertia::render('Profile/Edit', $props);
        }

        $pengguna = Pengguna::query()->where('user_id', $user->id)->first();

        return Inertia::render('User/Profil', $props + [
            'profil' => [
                'name' => $user->name,
                'email' => $user->email,
                'peran' => $pengguna?->keluarga_id === null ? null : ($pengguna->isKepalaKeluarga() ? 'Kepala Keluarga' : 'Anggota Keluarga'),
                'usia' => $pengguna?->usia,
                'keluarga' => $pengguna?->keluarga?->nama_keluarga,
            ],
        ] + $progress->forUser($user, $pengguna, CarbonImmutable::today()));
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
