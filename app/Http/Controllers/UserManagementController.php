<?php
namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Pengelolaan akun. Tidak ada penghapusan akun agar riwayat audit tetap utuh. */
class UserManagementController
{
    private const ROLES = ['admin', 'petugas', 'bp', 'kepala']; // Wali kelas menunggu pembatasan hak akses per kelas

    public function index(): View
    {
        return view('settings.users', [
            'users' => User::query()->orderBy('full_name')->get(),
            'roles' => self::ROLES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'min:3', 'max:60', 'regex:/^[a-zA-Z0-9_.-]+$/', Rule::unique('users', 'username')],
            'role' => ['required', Rule::in(self::ROLES)],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);
        DB::transaction(function () use ($request, $data): void {
            $user = User::create([
                'full_name' => trim($data['full_name']),
                'username' => $data['username'],
                'role' => $data['role'],
                'password' => $data['password'], // User casts password => hashed
                'active' => true,
            ]);
            $this->log($request, 'user_created', 'Akun '.$user->id.' ('.$user->username.')');
        });
        return back()->with('success', 'Akun pengguna berhasil dibuat.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'min:3', 'max:60', 'regex:/^[a-zA-Z0-9_.-]+$/', Rule::unique('users', 'username')->ignore($user->id)],
            'role' => ['required', Rule::in(self::ROLES)],
            'active' => ['required', 'boolean'],
            'password' => ['nullable', 'string', 'min:12', 'confirmed'],
        ]);
        DB::transaction(function () use ($request, $user, $data): void {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $willBeAdmin = $data['role'] === 'admin' && (bool) $data['active'];
            $currentlyAdmin = $user->role === 'admin' && $user->active;
            if ($currentlyAdmin && !$willBeAdmin && User::where('role', 'admin')->where('active', true)->lockForUpdate()->get()->count() <= 1) {
                throw ValidationException::withMessages(['user' => 'Minimal satu administrator aktif wajib dipertahankan.']);
            }
            if ($user->id === Auth::id() && (!$data['active'] || $data['role'] !== 'admin')) {
                throw ValidationException::withMessages(['user' => 'Administrator tidak dapat menonaktifkan atau menurunkan peran akun sendiri.']);
            }
            $payload = [
                'full_name' => trim($data['full_name']), 'username' => $data['username'],
                'role' => $data['role'], 'active' => (bool) $data['active'],
            ];
            if (!empty($data['password'])) $payload['password'] = $data['password'];
            $user->update($payload);
            $this->log($request, 'user_updated', 'Akun '.$user->id.' ('.$user->username.') diperbarui'.(isset($payload['password']) ? ', password diganti' : ''));
        });
        return back()->with('success', 'Pengaturan pengguna berhasil disimpan.');
    }

    public function changeOwnPassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:12', 'confirmed', 'different:current_password'],
        ]);
        $user = $request->user();
        if (!Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'Password lama tidak cocok.']);
        }
        DB::transaction(function () use ($request, $user, $data): void {
            $user->update(['password' => $data['password']]);
            $this->log($request, 'own_password_changed', 'Akun '.$user->id.' mengganti password sendiri');
        });
        return back()->with('success', 'Password Anda berhasil diganti.');
    }

    private function log(Request $request, string $action, string $detail): void
    {
        AuditLog::create(['user_id' => Auth::id(), 'action' => $action, 'detail' => $detail, 'ip_address' => $request->ip()]);
    }
}
