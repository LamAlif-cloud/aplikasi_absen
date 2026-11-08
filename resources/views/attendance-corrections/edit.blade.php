@extends('layouts.app')
@section('title', 'Koreksi Absen')
@section('content')
<div class="heading"><div><h1>Koreksi Absen {{ $label }}</h1><p>{{ $student->full_name }} · {{ $class->class_name }} · {{ $entry->academic_year }} · {{ $entry->{$date} }}</p></div></div>
<section class="panel">
@if($mode==='opening')<p class="alert notice">Ini saldo awal kumulatif semester, bukan absensi satu bulan. Koreksi berdasarkan rekap sumber yang telah diverifikasi.</p>@endif
<p class="muted">Ubah jumlah JP yang salah. Identitas siswa, kelas, dan periode tetap mengikuti catatan asli. 8 JP = 1 hari.</p>
<form method="POST" action="{{ route('attendance-corrections.update',[$mode,$entry->id]) }}">@csrf @method('PUT')
<input type="hidden" name="version" value="{{ $version }}">
<div class="form-grid">@foreach(['sakit_jp'=>'Sakit','izin_jp'=>'Izin','alpa_jp'=>'Alpa'] as $field=>$name)<label>{{ $name }} (JP)<input type="number" name="{{ $field }}" min="0" max="{{ $limit }}" step="1" required value="{{ old($field,$entry->{$field}) }}"></label>@endforeach</div>
<label>Catatan<input name="note" maxlength="255" value="{{ old('note',$entry->note) }}"></label>
<label>Alasan koreksi (wajib)<input name="reason" maxlength="120" required value="{{ old('reason') }}" placeholder="Contoh: alpa seharusnya izin berdasarkan surat wali"></label>
<p class="small muted">Angka sebelum/sesudah, alasan, waktu, dan akun pengoreksi dicatat dalam audit. Periksa dan cetak ulang laporan setelah koreksi.</p>
<div class="form-actions"><button class="btn primary">Simpan koreksi</button><a class="btn" href="{{ route('attendance-corrections.index',['academic_year'=>$entry->academic_year,'class_id'=>$entry->class_id,'mode'=>$mode]) }}">Kembali</a></div>
</form></section>
@endsection
