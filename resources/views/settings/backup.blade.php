@extends('layouts.app')
@section('title', 'Backup & Restore Database')
@section('content')
<div class="heading"><div><p class="eyebrow">KEAMANAN DATA</p><h1>Backup & Restore Database</h1><p class="muted">Cadangkan atau pulihkan data Aplikasi Absensi Siswa. Restore tidak sama dengan impor Excel.</p></div><span class="tag">Hanya Administrator</span></div>
<div class="two-cols uneven">
<section class="panel">
<h2>1. Unduh Backup Lengkap</h2>
<div class="callout"><strong>Penting:</strong> File ZIP berisi data siswa dan hash password. Simpan di tempat aman. File <code>.env</code> dan logo tidak ikut di dalam backup.</div>
<form method="POST" action="{{ route('backup.download') }}" onsubmit="return confirm('Buat dan unduh backup database sekarang?')">@csrf
<label>Password administrator<input type="password" name="password" autocomplete="current-password" required></label>
<div class="form-actions"><button type="submit" class="btn primary">Unduh Backup (.zip)</button></div></form>
</section>
<section class="panel">
<h2>Informasi Database</h2>
<p><strong>Nama database:</strong> {{ $database }}</p>
<p><strong>Jumlah tabel/objek:</strong> {{ count($tables) }}</p>
<p class="small muted">Restore hanya mengembalikan isi tabel pada skema yang identik. Tidak mengubah struktur tabel, tidak menjalankan perintah SQL arbitrer, dan tidak mengimpor database PHP Native/XAMPP lama.</p>
@if($recoveryFiles)<h3>Cadangan otomatis sebelum Restore</h3>
<div class="table-wrap"><table><thead><tr><th>Tanggal</th><th>Ukuran</th><th>Arsip</th></tr></thead><tbody>@foreach($recoveryFiles as $item)<tr><td>{{ $item['date'] }}</td><td>{{ number_format($item['bytes']/1024,0,',','.') }} KB</td><td><a href="{{ route('backup.recovery',['filename'=>$item['name']]) }}">Unduh ZIP</a></td></tr>@endforeach</tbody></table></div>@endif
</section>
</div>
<section class="panel" id="restore">
<h2>2. Unggah Backup SQL untuk Pemulihan</h2>
@if(!$restoreEnabled)
<div class="alert danger">Pemulihan dari browser dinonaktifkan. Untuk keamanan, fitur ini hanya tersedia saat <code>APP_ENV=local</code> dan koneksi MySQL aktif. Server online menggunakan prosedur restore administrator server.</div>
@else
<div class="alert danger"><strong>PERINGATAN PEMULIHAN:</strong> Jika Anda menyelesaikan Restore, seluruh data database aktif akan diganti dengan data dari backup. Siswa, kelas, absensi, akun pengguna, surat, dan riwayat dapat kembali ke keadaan lama. Backup kondisi sekarang akan dibuat otomatis lebih dahulu. Jangan jalankan Restore ketika petugas lain sedang memakai aplikasi.</div>
<form method="POST" action="{{ route('backup.restore.preview') }}" enctype="multipart/form-data">@csrf
<label>Pilih backup SQL atau ZIP buatan Aplikasi Absensi Siswa (maksimum 25 MB)<input type="file" name="restore_file" accept=".sql,.zip" required></label>
<div class="form-actions"><button class="btn" type="submit">Unggah & Periksa Backup</button></div>
<p class="small muted">Tahap ini hanya memvalidasi format, jumlah data, dan kecocokan struktur; belum memodifikasi database.</p>
</form>
@if($pendingRestore)
<div class="callout" style="margin-top:1rem"><strong>Pratinjau berhasil:</strong> {{ $pendingRestore['tables'] }} tabel, {{ number_format($pendingRestore['rows'],0,',','.') }} baris data. Backup cocok dengan skema database yang sekarang. Pratinjau berlaku 30 menit.</div>
<div class="table-wrap"><table><thead><tr><th>Tabel</th><th>Baris yang akan dipulihkan</th></tr></thead><tbody>
@foreach($pendingRestore['counts'] as $name=>$total)<tr><td>{{ $name }}</td><td>{{ number_format($total,0,',','.') }}</td></tr>@endforeach
</tbody></table></div>
<form method="POST" action="{{ route('backup.restore.confirm') }}" onsubmit="return confirm('KONFIRMASI TERAKHIR: Semua data aktif akan diganti sesuai backup. Lanjutkan Restore?')">@csrf
<label><input type="checkbox" name="understand_replace" value="1" required> Saya memahami bahwa seluruh isi database aktif akan diganti dengan isi backup.</label>
<label>Untuk konfirmasi, ketik PULIHKAN DATABASE<input name="confirmation" required autocomplete="off" placeholder="PULIHKAN DATABASE"></label>
<label>Password administrator<input type="password" name="password" required autocomplete="current-password"></label>
<div class="form-actions"><button class="btn danger-outline" type="submit">Konfirmasi & Pulihkan Database</button></div>
</form>
@endif
@endif
</section>
@endsection
