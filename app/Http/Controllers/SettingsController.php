<?php
namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/** Profil sekolah tersimpan pada tabel school_settings yang sudah ada. */
class SettingsController
{
    private const FIELDS = [
        'school_name', 'school_address', 'principal_name', 'vice_principal_name', 'bp_name', 'school_nsm', 'school_npsn', 'school_phone',
        'operator_male_name', 'operator_female_name',
    ];

    public function index(): View
    {
        return view('settings.school', ['settings' => DB::table('school_settings')->pluck('value', 'key')->all()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'school_name' => ['required', 'string', 'max:180'],
            'operator_male_name' => ['nullable', 'string', 'max:150'],
            'operator_female_name' => ['nullable', 'string', 'max:150'],
            'school_address' => ['nullable', 'string', 'max:500'],
            'principal_name' => ['nullable', 'string', 'max:150'],
            'vice_principal_name' => ['nullable', 'string', 'max:150'],
            'bp_name' => ['nullable', 'string', 'max:150'],
            'school_nsm' => ['nullable', 'string', 'max:30'],
            'school_npsn' => ['nullable', 'string', 'max:30'],
            'school_phone' => ['nullable', 'string', 'max:40'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
        ]);
        $oldLogo = DB::table('school_settings')->where('key', 'logo_path')->value('value');
        $newLogo = null;
        if ($request->hasFile('logo')) $newLogo = $request->file('logo')->store('madrasah-logo', 'public');
        try {
            DB::transaction(function () use ($request, $data, $newLogo): void {
                foreach (self::FIELDS as $field) {
                    DB::table('school_settings')->updateOrInsert(['key' => $field], ['value' => trim((string) ($data[$field] ?? ''))]);
                }
                if ($newLogo !== null) DB::table('school_settings')->updateOrInsert(['key' => 'logo_path'], ['value' => $newLogo]);
                AuditLog::create(['user_id' => Auth::id(), 'action' => 'school_settings_updated', 'detail' => 'Profil madrasah diperbarui', 'ip_address' => $request->ip()]);
            });
        } catch (\Throwable $e) {
            if ($newLogo) Storage::disk('public')->delete($newLogo);
            throw $e;
        }
        if ($newLogo && $oldLogo && $oldLogo !== $newLogo) Storage::disk('public')->delete($oldLogo);
        return back()->with('success', 'Profil madrasah berhasil disimpan.');
    }
}
