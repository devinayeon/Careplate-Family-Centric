<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        if ($user) {
            $user->loadMissing(['pengguna.keluarga']);
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? array_merge($user->toArray(), [
                    'roles' => $user->getRoleNames(),
                    'permissions' => $user->getAllPermissions()->pluck('name'),
                    'pengguna' => $user->pengguna,
                    'keluarga' => $user->pengguna?->keluarga,
                ]) : null,
            ],
            'flash' => ['success' => fn () => $request->session()->get('success'), 'error' => fn () => $request->session()->get('error')],
            'pendingVerificationCount' => fn () => $user ? \App\Models\Makanan::where('status', 'menunggu')->count() : 0,
        ];
    }
}
