@extends('layouts.app')
@section('title', 'Profil Madrasah')
@section('content')
<div class="heading"><div><p class="eyebrow">PENGATURAN LEMBAGA</p><h1>Profil Madrasah</h1><p class="muted">Identitas dan logo tersimpan untuk digunakan pada kop surat serta laporan berikutnya.</p></div><span class="tag">Administrator</span></div>
<section class="panel"><h2>Identitas Madrasah</h2>
<form method="POST" action="{{ route('settings.school.update') }}" enctype="multipart/form-data">@csrf @method('PUT')
<div class="form-grid">
<label>Nama Petugas Input Siswa Putra<input name="operator_male_name" maxlength="150" value="{{ old('operator_male_name',$settings['operator_male_name'] ?? '') }}"></label>
<label>Nama Petugas Input Siswa Putri<input name="operator_female_name" maxlength="150" value="{{ old('operator_female_name',$settings['operator_female_name'] ?? '') }}"></label>
<label>Nama Madrasah<input name="school_name" maxlength="180" required value="{{ old('school_name',$settings['school_name'] ?? '') }}"></label>
<label>Nama Kepala Madrasah<input name="principal_name" maxlength="150" value="{{ old('principal_name',$settings['principal_name'] ?? '') }}"></label>
<label>Nama Waka Kesiswaan (penandatangan surat Wali Kelas &amp; BP)<input name="vice_principal_name" maxlength="150" value="{{ old('vice_principal_name',$settings['vice_principal_name'] ?? '') }}" placeholder="Nama Waka Kesiswaan"></label>
<label>Nama Petugas/Koordinator BP (penandatangan panggilan wali)<input name="bp_name" maxlength="150" value="{{ old('bp_name',$settings['bp_name'] ?? '') }}" placeholder="Nama Petugas BP"></label>
<label>NPSN<input name="school_npsn" maxlength="30" value="{{ old('school_npsn',$settings['school_npsn'] ?? '') }}"></label>
<label>NSM<input name="school_nsm" maxlength="30" value="{{ old('school_nsm',$settings['school_nsm'] ?? '') }}"></label>
<label>No Telepon<input name="school_phone" maxlength="40" value="{{ old('school_phone',$settings['school_phone'] ?? '') }}"></label>
<label>Logo Madrasah (.png/.jpg/.webp, maksimal 1 MB)<input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp"></label>
</div>
<p class="small muted">Jika satu petugas menangani semua siswa, cukup isi salah satu nama. Jika keduanya kosong, laporan memakai nama akun yang sedang login. Untuk kelas campuran dengan dua petugas, kedua nama dicantumkan.</p>
<label>Alamat Madrasah<textarea rows="3" name="school_address" maxlength="500">{{ old('school_address',$settings['school_address'] ?? '') }}</textarea></label>
@if(!empty($settings['logo_path']))<p class="small muted">Logo telah diunggah: {{ basename($settings['logo_path']) }}</p>@endif
<div class="form-actions"><button class="btn primary" type="submit">Simpan Pengaturan Madrasah</button></div>
</form></section>
@endsection
