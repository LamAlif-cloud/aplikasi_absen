<?php
namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController
{
    public function form(): View { return view('auth.login'); }
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['username' => ['required', 'string', 'max:60'], 'password' => ['required', 'string']]);
        $credentials['active'] = true;
        if (!Auth::attempt($credentials)) {
            return back()->withErrors(['username' => 'Username atau kata sandi tidak sesuai.'])->onlyInput('username');
        }
        $request->session()->regenerate();
        AuditLog::create(['user_id' => Auth::id(), 'action' => 'login', 'detail' => 'Login berhasil', 'ip_address' => $request->ip()]);
        return redirect()->intended(route('dashboard'));
    }
    public function logout(Request $request): RedirectResponse
    {
        if (Auth::check()) AuditLog::create(['user_id' => Auth::id(), 'action' => 'logout', 'detail' => 'Keluar aplikasi', 'ip_address' => $request->ip()]);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
