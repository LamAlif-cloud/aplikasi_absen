@extends('layouts.app')
@section('title', 'Cetak Laporan Wali Kelas')
@section('content')
<div class="heading"><div><p class="eyebrow">PELAPORAN OPERATOR</p><h1>Cetak Laporan untuk Wali Kelas</h1><p class="muted">Siapkan laporan absensi JP mingguan, bulanan, semester, atau tahun pelajaran. Satu kelas satu dokumen penyerahan.</p></div></div>
@if($filterNotice)<div class="alert notice">{{ $filterNotice }}</div>@endif
<section class="panel">
    <h2>Pilih Periode dan Kelas</h2>
    <form method="GET" action="{{ route('reports.print') }}" target="_blank" id="print-report-form">
        <div class="form-grid">
            @include('reports.action-filter')
            <label>Tahun pelajaran
                <select name="academic_year" id="print-year">
                    @foreach($years as $y)<option value="{{ $y }}" @selected($year===$y)>{{ $y }}</option>@endforeach
                </select>
            </label>
            <label>Periode laporan
                <select name="period" id="print-period">
                    @foreach(['Mingguan'=>'Mingguan','Bulanan'=>'Bulanan','Ganjil'=>'Semester Ganjil','Genap'=>'Semester Genap','Tahunan'=>'Satu Tahun Pelajaran'] as $k=>$v)
                        <option value="{{ $k }}" @selected($period===$k)>{{ $v }}</option>
                    @endforeach
                </select>
            </label>
            <label>Kelas
                <select name="class_id" id="print-class">
                    <option value="0">Semua kelas — lembar terpisah per wali kelas</option>
                    @foreach($classes as $c)
                        <option value="{{ $c->id }}" @selected($classId===$c->id)>{{ $c->class_name }} — {{ $c->homeroom_teacher ?: 'Wali belum diisi' }}</option>
                    @endforeach
                </select>
            </label>
            <label id="print-week">Minggu dimulai Senin<input type="date" name="week_start" value="{{ $week }}"></label>
            <label id="print-month">Bulan<input type="month" name="month" value="{{ $month }}"></label>
        </div>
        <div class="file-actions">
            <button class="btn primary" type="submit" formtarget="_blank">Pratinjau & Cetak</button>
            <button class="btn" type="submit" formaction="{{ route('reports.print.pdf') }}" formtarget="_blank">Unduh PDF A4 Landscape</button>
        </div>
    </form>
    <p class="muted small">Jika memilih <strong>Semua kelas</strong>, setiap kelas memiliki kop, tabel, total, dan ruang tanda tangan sendiri. Cetak menggunakan kertas A4 mendatar. <strong>Versi fitur cetak: 5.8</strong>. Semua angka dari sumber JP yang sama dengan Rekap & Laporan.</p>
    <p class="muted small">Jika belum ada catatan input untuk siswa, laporan menuliskan <strong>Belum diinput</strong>, bukan menganggapnya 0 JP.</p>
    <p class="muted small">Pilih Filter Tindakan untuk mencetak hanya siswa yang perlu peringatan Wali Kelas, penanganan BP, atau pemanggilan orang tua. Tindakan berdasarkan Alpa kumulatif semester, bukan Sakit + Izin + Alpa.</p>
</section>
<script>
(function(){
 const form=document.getElementById('print-report-form'), period=document.getElementById('print-period');
 const weekly=document.getElementById('print-week'),monthly=document.getElementById('print-month');
 function toggle(){weekly.style.display=period.value==='Mingguan'?'block':'none';monthly.style.display=period.value==='Bulanan'?'block':'none';}
 period.addEventListener('change',toggle);toggle();
 document.getElementById('print-year').addEventListener('change',function(){
    const params=new URLSearchParams(new FormData(form));params.set('class_id','0');
    window.location.href='{{ route('reports.print.setup') }}?'+params.toString();
 });
})();
</script>
@endsection
