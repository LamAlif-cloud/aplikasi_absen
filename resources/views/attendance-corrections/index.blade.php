@extends('layouts.app')
@section('title', 'Edit Absen')
@section('content')
<div class="heading"><div><p class="eyebrow">PEMERIKSAAN SEBELUM PENYERAHAN</p><h1>Edit Absen</h1><p class="muted">Cari catatan yang sudah diinput, lalu koreksi sebelum laporan diserahkan. Kelas dan periode mengikuti catatan asli, termasuk siswa tidak aktif atau yang sudah pindah kelas.</p></div></div>
<section class="panel"><form method="GET" class="toolbar">
<label>Tahun pelajaran<select name="academic_year">@foreach($years as $y)<option @selected($year===$y)>{{ $y }}</option>@endforeach</select></label>
<label>Kelas asal catatan<select name="class_id"><option value="0">Semua kelas</option>@foreach($classes as $c)<option value="{{ $c->id }}" @selected($classId===$c->id)>{{ $c->class_name }}</option>@endforeach</select></label>
<label>Metode<select name="mode"><option value="">Semua metode</option>@foreach(['weekly'=>'Mingguan','monthly'=>'Bulanan','opening'=>'Saldo Awal'] as $key=>$name)<option value="{{ $key }}" @selected($mode===$key)>{{ $name }}</option>@endforeach</select></label>
<label>Nama / NIS<input name="q" value="{{ $search }}"></label><button class="btn primary">Cari catatan</button>
</form></section>
<section class="panel"><div class="table-wrap"><table><thead><tr><th>Siswa</th><th>Kelas</th><th>Metode / Tanggal</th><th>Sakit JP</th><th>Izin JP</th><th>Alpa JP</th><th>Alpa Hari</th><th>Aksi</th></tr></thead><tbody>
@forelse($rows as $r)<tr><td><strong>{{ $r->full_name }}</strong><small>{{ $r->nis }}</small></td><td>{{ $r->class_name }}</td><td>{{ $r->mode_label }}<small>{{ $r->entry_date }}</small></td><td>{{ $r->sakit_jp }}</td><td>{{ $r->izin_jp }}</td><td>{{ $r->alpa_jp }}</td><td>{{ \App\Services\AbsenceQuantity::readable((int)$r->alpa_jp) }}</td><td><a class="btn" href="{{ route('attendance-corrections.edit',[$r->mode,$r->id]) }}">Edit absen</a></td></tr>
@empty<tr><td colspan="8" class="empty">Belum ada catatan sesuai filter.</td></tr>@endforelse
</tbody></table></div>{{ $rows->links() }}</section>
@endsection
