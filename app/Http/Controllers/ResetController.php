<?php
namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Development data reset only. Roles/auth/settings/migrations remain intact. */
class ResetController
{
    public function index(): View
    {
        $this->ensureLocalOperation();
        return view('settings.reset');
    }
    public function execute(Request $request): RedirectResponse
    {
        $this->ensureLocalOperation();
        $data = $request->validate([
            'scope' => ['required', Rule::in(['attendance','all'])],
            'confirmation' => ['required','string','in:HAPUS DATA UJI'],
            'backup_acknowledged' => ['accepted'],
            'password' => ['required','string'],
        ]);
        if (!Hash::check($data['password'], Auth::user()->password)) {
            throw ValidationException::withMessages(['password' => 'Password administrator tidak sesuai.']);
        }
        DB::transaction(function () use ($data,$request):void {
            // Pending import rows must not restore operational data after a reset.
            DB::table('import_issues')->delete();
            // All child records are removed before their parents to keep foreign keys valid.
            foreach (['disciplinary_actions','disciplinary_letters','opening_absence','monthly_absence','weekly_absence'] as $table) DB::table($table)->delete();
            if ($data['scope']==='all') {
                DB::table('student_status_history')->delete();
                DB::table('student_class_history')->delete();
                DB::table('students')->delete();
                DB::table('classes')->delete();
            }
            AuditLog::create(['user_id'=>Auth::id(),'action'=>'admin_reset_'.$data['scope'],
                'detail'=>'Reset manual disetujui admin. Data operasional dihapus (tanpa akun, konfigurasi, dan log).',
                'ip_address'=>$request->ip()]);
        },3);
        return redirect()->route('reset.index')->with('success', 'Reset selesai. Akun administrator, profil madrasah, dan audit log tetap tersimpan.');
    }

    private function ensureLocalOperation(): void
    {
        // Testing PHPUnit menggunakan SQLite :memory: dan hanya data tes.
        if (app()->environment('testing')) return;
        abort_unless(app()->environment('local')
            && in_array(request()->ip(), ['127.0.0.1', '::1'], true)
            && in_array(request()->getHost(), ['127.0.0.1', 'localhost', '::1'], true),
            403, 'Reset data dinonaktifkan pada server publik / jaringan non-lokal.');
    }
}
