<?php
namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Classroom;
use App\Services\YearOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceCorrectionController
{
    private const SOURCES = [
        'weekly' => ['weekly_absence', 'week_start', 'Mingguan', 80],
        'monthly' => ['monthly_absence', 'month_start', 'Bulanan', 248],
        'opening' => ['opening_absence', 'as_of_date', 'Saldo Awal', 65535],
    ];

    public function index(Request $request)
    {
        $year = YearOptions::selected($request->query('academic_year'));
        $classes = Classroom::forYear($year)->orderBy('class_name')->get();
        $classId = $request->integer('class_id');
        $mode = (string) $request->query('mode', '');
        $search = trim((string) $request->query('q', ''));
        $union = null;
        foreach (self::SOURCES as $key => [$table, $date, $label]) {
            $query = DB::table($table)->select('id', 'student_id', 'class_id', 'academic_year', 'sakit_jp', 'izin_jp', 'alpa_jp', 'updated_at')
                ->selectRaw($date.' as entry_date, ? as mode, ? as mode_label', [$key, $label]);
            $union = $union ? $union->unionAll($query) : $query;
        }
        $query = DB::query()->fromSub($union, 'a')->join('students as s', 's.id', '=', 'a.student_id')
            ->join('classes as c', 'c.id', '=', 'a.class_id')->where('a.academic_year', $year)
            ->select('a.*', 's.full_name', 's.nis', 'c.class_name');
        if ($classId) $query->where('a.class_id', $classId);
        if (isset(self::SOURCES[$mode])) $query->where('a.mode', $mode);
        if ($search !== '') $query->where(fn ($q) => $q->where('s.full_name', 'like', '%'.$search.'%')->orWhere('s.nis', 'like', '%'.$search.'%'));
        $rows = $query->orderByDesc('a.entry_date')->orderBy('s.full_name')->orderBy('a.mode')->orderBy('a.id')->paginate(40)->withQueryString();
        return view('attendance-corrections.index', compact('year', 'classes', 'classId', 'mode', 'search', 'rows') + ['years' => YearOptions::all()]);
    }

    public function edit(string $mode, int $id)
    {
        [$table, $date, $label, $limit] = $this->source($mode);
        $entry = DB::table($table)->find($id);
        abort_unless($entry, 404);
        $student = DB::table('students')->find($entry->student_id);
        $class = DB::table('classes')->find($entry->class_id);
        return view('attendance-corrections.edit', compact('entry', 'student', 'class', 'mode', 'date', 'label', 'limit') + ['version' => $this->version($entry)]);
    }

    public function update(Request $request, string $mode, int $id)
    {
        [$table, , , $limit] = $this->source($mode);
        $data = $request->validate([
            'sakit_jp' => ['required', 'integer', 'between:0,'.$limit],
            'izin_jp' => ['required', 'integer', 'between:0,'.$limit],
            'alpa_jp' => ['required', 'integer', 'between:0,'.$limit],
            'note' => ['nullable', 'string', 'max:255'],
            'reason' => ['required', 'string', 'max:120'],
            'version' => ['required', 'string', 'size:64'],
        ]);
        if (trim($data['reason']) === '') throw ValidationException::withMessages(['reason' => 'Alasan koreksi wajib diisi.']);
        if (array_sum(array_intersect_key($data, array_flip(['sakit_jp', 'izin_jp', 'alpa_jp']))) > $limit) {
            throw ValidationException::withMessages(['alpa_jp' => 'Jumlah Sakit + Izin + Alpa melebihi batas '.$limit.' JP.']);
        }
        $year = DB::transaction(function () use ($request, $table, $mode, $id, $data) {
            $entry = DB::table($table)->where('id', $id)->lockForUpdate()->first();
            abort_unless($entry, 404);
            if (!hash_equals($this->version($entry), $data['version'])) {
                throw ValidationException::withMessages(['version' => 'Catatan sudah berubah. Muat ulang halaman edit sebelum menyimpan koreksi.']);
            }
            $before = [(int) $entry->sakit_jp, (int) $entry->izin_jp, (int) $entry->alpa_jp];
            DB::table($table)->where('id', $id)->update([
                'sakit_jp' => $data['sakit_jp'], 'izin_jp' => $data['izin_jp'], 'alpa_jp' => $data['alpa_jp'],
                'note' => $data['note'] ?? null, 'updated_at' => now(),
            ]);
            AuditLog::create(['user_id' => $request->user()->id, 'action' => 'attendance_corrected',
                'detail' => json_encode(['mode' => $mode, 'id' => $id, 'before_sia' => $before,
                    'after_sia' => [(int) $data['sakit_jp'], (int) $data['izin_jp'], (int) $data['alpa_jp']],
                    'reason' => trim($data['reason'])], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'ip_address' => $request->ip()]);
            return $entry->academic_year;
        }, 3);
        return redirect()->route('attendance-corrections.index', ['academic_year' => $year, 'mode' => $mode])
            ->with('success', 'Koreksi tersimpan. Rekap, cetak, dan PDF menggunakan angka terbaru. Surat yang sudah diserahkan perlu diperiksa ulang.');
    }

    private function source(string $mode): array
    {
        abort_unless(isset(self::SOURCES[$mode]), 404);
        return self::SOURCES[$mode];
    }

    private function version(object $entry): string
    {
        return hash('sha256', json_encode($entry));
    }
}
