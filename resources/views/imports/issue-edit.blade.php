@extends('layouts.app')
@section('title', 'Perbaiki Baris Impor')
@section('content')
<div class="heading"><div><h1>Perbaiki Baris {{ $issue->row_number }}</h1><p>{{ $issue->filename }} · {{ $issue->academic_year }}</p></div></div>
<div class="alert danger">{{ $issue->error }}</div>
<section class="panel"><p>Periksa angka berdasarkan sumber. Menyimpan perbaikan akan membuat atau memperbarui data utama untuk siswa dan periode yang diisi. Sakit tetap dicatat sebagai Sakit. Batas jumlah dan pemeriksaan data ganda tetap berlaku.</p>
@if($issue->resolved_at)<p>Masalah ini sudah diperbaiki.</p>
@else
<form method="POST" action="{{ route('import-issues.update', $issue) }}">@csrf @method('PUT')
<label>Satuan angka<select name="input_unit"><option value="jp" @selected(old('input_unit',$issue->input_unit)==='jp')>JP</option><option value="days" @selected(old('input_unit',$issue->input_unit)==='days')>Hari (1 hari = 8 JP)</option></select></label>
@foreach($issue->row_data as $field=>$value)
<label>{{ ['full_name'=>'Nama siswa','class_name'=>'Kelas','month'=>'Bulan (YYYY-MM)','week_start'=>'Senin (YYYY-MM-DD)','sakit_jp'=>'Sakit','izin_jp'=>'Izin','alpa_jp'=>'Alpa','nis'=>'NIS','nisn'=>'NISN','gender'=>'Jenis kelamin','note'=>'Catatan','homeroom_teacher'=>'Wali kelas'][$field] ?? $field }}<input name="row[{{ $field }}]" value="{{ old('row.'.$field,$value) }}" maxlength="500"></label>
@endforeach
<div class="form-actions"><button class="btn primary" type="submit">Simpan Perbaikan ke Data Utama</button></div>
</form>@endif
<p><a href="{{ route('import-issues.index') }}">Kembali ke daftar masalah</a></p></section>
@endsection
