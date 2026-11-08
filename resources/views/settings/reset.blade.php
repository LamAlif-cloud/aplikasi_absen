@extends('layouts.app')
@section('title','Reset Data Percobaan')
@section('content')
<div class="heading"><div><p class="eyebrow">ADMINISTRATOR SAJA</p><h1>Reset Data Percobaan</h1><p class="muted">Penghapusan tidak dapat dibatalkan tanpa backup database yang telah diuji pemulihannya.</p></div></div>
<div class="alert danger"><strong>PERINGATAN KERAS:</strong> Reset ini benar-benar menghapus data di database yang sedang dipakai aplikasi. Jika database berisi data asli, jangan gunakan menu ini. Unduh backup terlebih dahulu dan pastikan hasilnya dapat dipulihkan pada database terpisah.</div>
<section class="panel"><h2>Persiapan sebelum reset</h2><p><a class="btn" href="{{ route('backup.index') }}">1. Buka Backup Database →</a></p><p class="small muted">Backup berisi struktur dan data SQL, bukan file konfigurasi .env atau gambar logo. Lakukan uji pemulihan di lingkungan terpisah.</p></section>
<section class="panel"><form method="POST" action="{{ route('reset.execute') }}" onsubmit="return confirm('PERINGATAN AKHIR: Hapus data database sekarang?')">@csrf
<label>Lingkup reset<select name="scope" required><option value="attendance">Hapus absensi, pembinaan dan surat saja (siswa/kelas tetap)</option><option value="all">Hapus juga siswa, kelas, dan riwayat akademik (AKUN ADMIN TETAP)</option></select></label>
<label>Ketikan wajib: HAPUS DATA UJI<input name="confirmation" autocomplete="off" required placeholder="HAPUS DATA UJI"></label>
<label>Password admin saat ini<input type="password" name="password" autocomplete="current-password" required></label>
<label><input type="checkbox" name="backup_acknowledged" value="1" required> Saya sudah membuat dan menguji pemulihan backup; saya memahami risiko data terhapus permanen.</label>
<div class="form-actions"><button type="submit" class="btn danger-outline">Konfirmasi dan Reset Data</button></div></form></section>
@endsection
