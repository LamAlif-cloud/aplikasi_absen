@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
<div class="heading"><div><p class="eyebrow">RINGKASAN OPERASIONAL</p><h1>Dashboard Absensi</h1><p class="muted">Tahun pelajaran {{ $year }} · Semester {{ $semester }}</p></div><span class="tag">Data JP mingguan dan bulanan</span></div>
@if(($openingSummary->siswa ?? 0) > 0)<div class="callout"><strong>Saldo Awal Semester {{ $semester }}:</strong> {{ $openingSummary->siswa }} siswa telah memiliki rekap periode sebelum operasional rutin (S: {{ $openingSummary->sakit }} JP, I: {{ $openingSummary->izin }} JP, A: {{ $openingSummary->alpa }} JP). Saldo ini masuk ke pemantauan tata tertib, tidak dianggap ketidakhadiran minggu ini.</div>@endif
<div class="metrics">
 <div class="metric"><span>Kelas Tahun Ini</span><strong>{{ $totalClasses }}</strong></div>
 <div class="metric"><span>Siswa Aktif</span><strong>{{ $totalStudents }}</strong></div>
 <div class="metric"><span>Perlu Tindak Lanjut</span><strong>{{ $warnings->count() }}</strong></div>
 <div class="metric"><span>Dicatat Minggu Ini</span><strong>{{ $thisWeek->count() }}</strong><small>Siswa dengan ketidakhadiran</small></div>
</div>
<div class="callout"><strong>Catatan akurasi:</strong> Data petugas dapat dimasukkan seminggu sekali atau bulanan. Karena sumbernya total JP periode, aplikasi <strong>tidak menyebut daftar ini sebagai absensi hari ini</strong>. Tanggal kejadian harian tetap berasal dari hard copy madrasah.</div>
<div class="two-cols">
 <section class="panel"><div class="panel-head"><div><h2>Minggu berjalan</h2><p class="muted">Mulai {{ $currentWeek }}</p></div><span class="tag">{{ $thisWeek->count() }} siswa</span></div>
 <div class="table-wrap"><table><thead><tr><th>Siswa</th><th>Kelas</th><th>S</th><th>I</th><th>A</th></tr></thead><tbody>@forelse($thisWeek as $r)<tr><td>{{ $r->full_name }}</td><td>{{ $r->class_name }}</td><td>{{ $r->sakit_jp }}</td><td>{{ $r->izin_jp }}</td><td>{{ $r->alpa_jp }}</td></tr>@empty<tr><td colspan="5" class="empty">Belum ada input ketidakhadiran minggu ini.</td></tr>@endforelse</tbody></table></div></section>
 <section class="panel"><div class="panel-head"><div><h2>Minggu sebelumnya</h2><p class="muted">Mulai {{ $previousWeek }}</p></div><span class="tag">{{ $lastWeek->count() }} siswa</span></div>
 <div class="table-wrap"><table><thead><tr><th>Siswa</th><th>Kelas</th><th>S</th><th>I</th><th>A</th></tr></thead><tbody>@forelse($lastWeek as $r)<tr><td>{{ $r->full_name }}</td><td>{{ $r->class_name }}</td><td>{{ $r->sakit_jp }}</td><td>{{ $r->izin_jp }}</td><td>{{ $r->alpa_jp }}</td></tr>@empty<tr><td colspan="5" class="empty">Tidak ada catatan ketidakhadiran pada minggu sebelumnya.</td></tr>@endforelse</tbody></table></div></section>
</div>
<section class="panel"><div class="panel-head"><div><h2>Rekap Input Bulanan — {{ $currentMonth }}</h2><p class="small muted">Data yang diinput operator dengan pilihan Bulanan; tidak ditampilkan sebagai kehadiran harian.</p></div><a class="link" href="{{ route('monthly.index') }}">Input Bulanan →</a></div>
<div class="table-wrap"><table><thead><tr><th>Nama siswa</th><th>Kelas</th><th>Sakit JP</th><th>Izin JP</th><th>Alpa JP</th></tr></thead><tbody>@forelse($monthlyRows as $r)<tr><td>{{ $r->full_name }}</td><td>{{ $r->class_name }}</td><td>{{ $r->sakit_jp }}</td><td>{{ $r->izin_jp }}</td><td>{{ $r->alpa_jp }}</td></tr>@empty<tr><td colspan="5">Belum ada rekap bulanan dengan ketidakhadiran untuk bulan ini.</td></tr>@endforelse</tbody></table></div></section>
<section class="panel"><div class="panel-head"><div><h2>Pemantauan Tata Tertib</h2><p class="muted">Akumulasi Alpa semester {{ $semester }} (8 JP = 1 hari)</p></div><a class="link" href="{{ route('reports.index') }}">Seluruh rekap →</a></div>
<div class="table-wrap"><table><thead><tr><th>Nama siswa</th><th>Riwayat kelas</th><th>Alpa JP</th><th>Setara hari</th><th>Tindak lanjut</th></tr></thead><tbody>@forelse($warnings as $r)<tr><td>{{ $r->full_name }}</td><td>{{ $r->class_label }}</td><td><strong>{{ $r->alpa_jp }}</strong></td><td>{{ \App\Services\AbsenceQuantity::readable((int)$r->alpa_jp) }}</td><td><span class="status status-{{ $r->level }}">{{ $r->label }}</span></td></tr>@empty<tr><td class="empty" colspan="5">Belum ada siswa mencapai 24 JP Alpa pada semester ini.</td></tr>@endforelse</tbody></table></div></section>
@endsection
