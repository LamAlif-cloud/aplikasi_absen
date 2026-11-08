@extends('layouts.app')
@section('title', 'Rekap & Laporan')
@section('content')
<div class="heading"><div><p class="eyebrow">LAPORAN TERPADU</p><h1>Rekap Absensi</h1><p class="muted">Sumber terpadu: input JP mingguan atau bulanan. Keterangan tata tertib memakai Alpa semester (24/48/72 JP).</p></div><a class="btn primary" target="_blank" rel="noopener" href="{{ route('reports.print',['academic_year'=>$year,'period'=>$period,'class_id'=>$classId,'week_start'=>$week,'month'=>$month,'action'=>$action]) }}">Cetak untuk Wali Kelas</a></div>
@if($filterNotice)<div class="alert notice no-print">{{ $filterNotice }}</div>@endif
@if($openingNotice)<div class="alert notice"><strong>SALDO AWAL:</strong> {{ $openingNotice }}</div>@endif
<section class="panel no-print">
@if(auth()->user()->isAnyRole('admin','petugas'))<p><a class="btn" href="{{ route('attendance-corrections.index',['academic_year'=>$year,'class_id'=>$classId]) }}">Periksa / Edit Absen Sebelum Cetak</a></p>@endif
<form method="GET" class="toolbar" id="report-filter">
@include('reports.action-filter')
<label>Tahun pelajaran<select name="academic_year" onchange="this.form.submit()">@foreach($years as $y)<option value="{{ $y }}" @selected($year===$y)>{{ $y }}</option>@endforeach</select></label>
<label>Periode<select name="period" id="period-selector" onchange="toggleReportFields()">
@foreach(['Mingguan'=>'Mingguan','Bulanan'=>'Bulanan','Ganjil'=>'Semester Ganjil','Genap'=>'Semester Genap','Tahunan'=>'Satu Tahun Pelajaran'] as $k=>$v)<option value="{{ $k }}" @selected($period===$k)>{{ $v }}</option>@endforeach
</select></label>
<label id="week-label">Minggu dimulai (Senin)<input type="date" name="week_start" value="{{ $week }}"></label>
<label id="month-label">Bulan<input type="month" name="month" value="{{ $month }}"></label>
<label>Kelas<select name="class_id"><option value="0">Semua kelas</option>@foreach($classes as $c)<option value="{{ $c->id }}" @selected($classId===$c->id)>{{ $c->class_name }}</option>@endforeach</select></label>
<button class="btn primary" type="submit">Tampilkan</button>
<a class="btn" href="{{ route('reports.excel',['academic_year'=>$year,'period'=>$period,'class_id'=>$classId,'week_start'=>$week,'month'=>$month,'action'=>$action]) }}">Ekspor Excel .xlsx</a>
<a class="btn" href="{{ route('excel.export',['type'=>'opening','academic_year'=>$year,'class_id'=>$classId]) }}">Ekspor Saldo Awal .xlsx</a>
<a class="btn" href="{{ route('reports.print.setup',['academic_year'=>$year,'period'=>$period,'class_id'=>$classId,'week_start'=>$week,'month'=>$month,'action'=>$action]) }}">Pengaturan Cetak</a>
</form><p class="muted small">{{ $classes->count() }} kelas tersedia untuk tahun {{ $year }}. Rekap bulanan mencakup input bulanan dan minggu bertanggal Senin dalam bulan tersebut; status tata tertib hanya dinilai dari akumulasi semester.</p></section>
<section class="panel">
@php($school=\Illuminate\Support\Facades\DB::table('school_settings')->pluck('value','key'))
<div class="report-kop">
@if(!empty($school['logo_path']))<img src="{{ asset('storage/'.$school['logo_path']) }}" alt="Logo">@endif
<div><strong>{{ $school['school_name'] ?? 'APLIKASI ABSENSI SISWA' }}</strong><small>{{ $school['school_address'] ?? '' }}</small></div>
</div>
<div class="panel-head"><div><h2>Hasil Rekap {{ $period }} — {{ $year }}</h2><p class="muted">{{ $rows->count() }} siswa memiliki catatan absensi JP</p></div></div>
<p class="muted"><strong>Filter: {{ $actionLabel }}</strong>. Tindakan memakai Alpa kumulatif semester; izin dan sakit tidak menaikkan tahap. Pada laporan tahunan, siswa dipilih jika salah satu semester memenuhi tahap, tanpa menjumlahkan kedua semester.</p>
<div class="table-wrap"><table><thead><tr><th>NIS</th><th>Nama siswa</th><th>Riwayat kelas</th><th>Sakit JP</th><th>Izin JP</th><th>Alpa JP</th><th>Sakit Hari</th><th>Izin Hari</th><th>Alpa Hari</th>@if($period==='Tahunan')<th>Ganjil JP</th><th>Tatib Ganjil</th><th>Genap JP</th><th>Tatib Genap</th>@elseif(in_array($period,['Mingguan','Bulanan'],true))<th>Alpa semester s.d. periode (JP)</th><th>Tata tertib semester</th>@else<th>Tata tertib</th>@endif</tr></thead><tbody>
@forelse($rows as $r)<tr>
<td>{{ $r->nis }}</td><td><strong>{{ $r->full_name }}</strong>@if(!(int)$r->period_entry_count)<small>Belum diinput untuk periode ini; tindakan dari akumulasi semester.</small>@endif</td><td>{{ $r->class_label }}</td>
@foreach(['sakit_jp','izin_jp','alpa_jp'] as $quantity)<td>{{ (int)$r->period_entry_count ? $r->{$quantity} : '—' }}</td>@endforeach
@foreach(['sakit_jp','izin_jp','alpa_jp'] as $quantity)<td>{{ (int)$r->period_entry_count ? \App\Services\AbsenceQuantity::readable((int)$r->{$quantity}) : '—' }}</td>@endforeach
@if($period==='Tahunan')
<td>{{ $r->alpa_ganjil_jp }}</td><td>{{ \App\Services\Discipline::label((int)$r->alpa_ganjil_jp) }}</td><td>{{ $r->alpa_genap_jp }}</td><td>{{ \App\Services\Discipline::label((int)$r->alpa_genap_jp) }}</td>
@elseif(in_array($period,['Ganjil','Genap'],true))<td><span class="status status-{{ \App\Services\Discipline::level((int)$r->alpa_jp) }}">{{ \App\Services\Discipline::label((int)$r->alpa_jp) }}</span></td>
@else<td>{{ $r->semester_alpa_jp }}</td><td><span class="status status-{{ \App\Services\Discipline::level((int)$r->semester_alpa_jp) }}">{{ \App\Services\Discipline::label((int)$r->semester_alpa_jp) }}</span></td>@endif
</tr>@empty<tr><td colspan="{{ $period==='Tahunan'?13:(in_array($period,['Mingguan','Bulanan'],true)?11:10) }}" class="empty">Tidak ada siswa sesuai periode, kelas, dan filter tindakan.</td></tr>@endforelse
</tbody></table></div>
<p class="summary"><strong>Total siswa sesuai filter:</strong> @foreach(['sakit_jp'=>'Sakit','izin_jp'=>'Izin','alpa_jp'=>'Alpa'] as $quantity=>$label) {{ $label }} {{ \App\Services\AbsenceQuantity::readable((int)$rows->sum($quantity)) }}@if(!$loop->last) · @endif @endforeach</p>
</section>
<script>
function toggleReportFields(){const p=document.getElementById('period-selector').value;document.getElementById('week-label').style.display=p==='Mingguan'?'block':'none';document.getElementById('month-label').style.display=p==='Bulanan'?'block':'none';}
toggleReportFields();
</script>
@endsection
