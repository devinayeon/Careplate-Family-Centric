<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AhliGizi;
use App\Models\Pengguna;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class AdminUserController extends Controller
{
    /**
     * Tampilkan halaman daftar manajemen pengguna.
     */
    public function index(Request $request): Response
    {
        $search = $request->input('search');
        $roleFilter = $request->input('role', 'semua');

        $query = User::with(['roles', 'pengguna.keluarga', 'ahliGizi'])
            ->latest('id');

        // Filter pencarian nama atau email
        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter role
        if (! empty($roleFilter) && $roleFilter !== 'semua') {
            if ($roleFilter === 'customer' || $roleFilter === 'user') {
                $query->role('user');
            } elseif ($roleFilter === 'ahli_gizi' || $roleFilter === 'nutritionist') {
                $query->role(['ahli_gizi', 'nutritionist']);
            } elseif ($roleFilter === 'admin') {
                $query->role('admin');
            }
        }

        $usersPaginator = $query->paginate(8)->withQueryString();

        // Transformasi data untuk tampilan tabel di frontend
        $usersFormatted = $usersPaginator->through(function ($u) {
            $primaryRole = $u->roles->first()?->name ?? 'user';
            
            // Format label dan key peran
            if (in_array($primaryRole, ['ahli_gizi', 'nutritionist'])) {
                $roleLabel = 'Ahli Gizi';
                $roleKey = 'ahli_gizi';
            } elseif ($primaryRole === 'admin') {
                $roleLabel = 'Admin';
                $roleKey = 'admin';
            } else {
                $roleLabel = 'Customer';
                $roleKey = 'customer';
            }

            // Tentukan status keluarga
            if ($roleKey !== 'customer') {
                $statusKeluarga = '-';
            } else {
                $pengguna = $u->pengguna;
                if ($pengguna && $pengguna->isKepalaKeluarga()) {
                    $statusKeluarga = 'Kepala Keluarga';
                } elseif ($pengguna && ! is_null($pengguna->keluarga_id)) {
                    $statusKeluarga = 'Anggota';
                } else {
                    $statusKeluarga = 'Tanpa grup';
                }
            }

            // Inisial avatar
            $initials = collect(explode(' ', str_replace('dr. ', '', $u->name)))
                ->map(fn ($segment) => mb_substr($segment, 0, 1))
                ->take(2)
                ->join('');

            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $roleKey,
                'role_label' => $roleLabel,
                'status_keluarga' => $statusKeluarga,
                'terdaftar' => Carbon::parse($u->created_at)->translatedFormat('j M Y'),
                'initials' => strtoupper($initials ?: 'U'),
                'ahli_gizi' => $u->ahliGizi ? [
                    'spesialisasi' => $u->ahliGizi->spesialisasi,
                    'nomor_str' => $u->ahliGizi->nomor_str,
                    'jam_layanan' => $u->ahliGizi->jam_layanan,
                ] : null,
            ];
        });

        // Hitung metrik ringkasan
        $counts = [
            'semua' => User::count(),
            'customer' => User::role('user')->count(),
            'ahli_gizi' => User::role(['ahli_gizi', 'nutritionist'])->count(),
            'admin' => User::role('admin')->count(),
        ];

        return Inertia::render('Admin/Pengguna/Index', [
            'users' => $usersFormatted,
            'filters' => [
                'search' => $search ?? '',
                'role' => $roleFilter,
            ],
            'counts' => $counts,
        ]);
    }

    /**
     * Simpan pengguna baru dari form modal admin.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'string', 'in:customer,user,ahli_gizi,nutritionist,admin'],
            'password' => ['required', 'string', 'min:8'],
            'spesialisasi' => ['nullable', 'string', 'max:255'],
            'nomor_str' => ['nullable', 'string', 'max:255'],
            'jam_layanan' => ['nullable', 'string', 'max:255'],
        ], [
            'name.required' => 'Nama lengkap wajib diisi.',
            'email.required' => 'Alamat email wajib diisi.',
            'email.unique' => 'Alamat email ini sudah terdaftar.',
            'role.required' => 'Peran pengguna wajib dipilih.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'email_verified_at' => now(),
        ]);

        // Tetapkan role
        $roleName = match ($validated['role']) {
            'ahli_gizi', 'nutritionist' => 'ahli_gizi',
            'admin' => 'admin',
            default => 'user',
        };

        Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        $user->assignRole($roleName);

        // Jika ahli gizi, buat relasi one-to-one AhliGizi
        if ($roleName === 'ahli_gizi') {
            AhliGizi::create([
                'user_id' => $user->id,
                'spesialisasi' => $validated['spesialisasi'] ?? null,
                'nomor_str' => $validated['nomor_str'] ?? null,
                'jam_layanan' => $validated['jam_layanan'] ?? null,
            ]);
        } else {
            // Untuk customer/admin, buat profil pengguna default
            Pengguna::create([
                'user_id' => $user->id,
            ]);
        }

        return redirect()->back()->with('success', 'Pengguna berhasil ditambahkan.');
    }

    /**
     * Perbarui data pengguna.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role' => ['required', 'string', 'in:customer,user,ahli_gizi,nutritionist,admin'],
            'password' => ['nullable', 'string', 'min:8'],
            'spesialisasi' => ['nullable', 'string', 'max:255'],
            'nomor_str' => ['nullable', 'string', 'max:255'],
            'jam_layanan' => ['nullable', 'string', 'max:255'],
        ], [
            'name.required' => 'Nama lengkap wajib diisi.',
            'email.required' => 'Alamat email wajib diisi.',
            'email.unique' => 'Alamat email ini sudah terdaftar.',
            'password.min' => 'Kata sandi minimal 8 karakter jika ingin diubah.',
        ]);

        $updateData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
        ];

        if (! empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $user->update($updateData);

        // Update role
        $roleName = match ($validated['role']) {
            'ahli_gizi', 'nutritionist' => 'ahli_gizi',
            'admin' => 'admin',
            default => 'user',
        };

        Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        $user->syncRoles([$roleName]);

        // Update atau create profil ahli gizi jika rolenya ahli gizi
        if ($roleName === 'ahli_gizi') {
            AhliGizi::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'spesialisasi' => $validated['spesialisasi'] ?? null,
                    'nomor_str' => $validated['nomor_str'] ?? null,
                    'jam_layanan' => $validated['jam_layanan'] ?? null,
                ]
            );
        }

        return redirect()->back()->with('success', 'Data pengguna berhasil diperbarui.');
    }

    /**
     * Hapus data pengguna.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        // Cegah admin menghapus dirinya sendiri
        if ($request->user()->id === $user->id) {
            return redirect()->back()->with('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
        }

        $user->delete();

        return redirect()->back()->with('success', 'Pengguna berhasil dihapus.');
    }
}
