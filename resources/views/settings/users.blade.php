@extends('layouts.app')
@section('title', 'Pengaturan Pengguna')
@section('content')
<div class="heading"><div><p class="eyebrow">ADMINISTRASI AKUN</p><h1>Pengaturan Pengguna</h1><p class="muted">Tambah akun petugas dan kelola hak akses. Akun tidak dihapus agar riwayat penggunaan tetap tercatat.</p></div><span class="tag">Akses Admin</span></div>
<div class="two-cols uneven">
<section class="panel"><h2>Daftar Pengguna</h2><div class="table-wrap"><table><thead><tr><th>Nama</th><th>Username</th><th>Peran</th><th>Status</th><th>Tindakan</th></tr></thead><tbody>
@foreach($users as $account)<tr><td><strong>{{ $account->full_name }}</strong></td><td>{{ $account->username }}</td><td>{{ strtoupper(str_replace('_',' ',$account->role)) }}</td><td>{{ $account->active ? 'Aktif' : 'Nonaktif' }}</td><td><a class="btn btn-sm" href="{{ route('users.index') }}?edit_user={{ $account->id }}#edit-user">Edit / Reset Password</a></td></tr>@endforeach
</tbody></table></div></section>
<section class="panel"><h2>Tambah Pengguna</h2><form method="POST" action="{{ route('users.store') }}">@csrf
<label>Nama lengkap<input name="full_name" maxlength="120" required value="{{ old('full_name') }}"></label>
<label>Username<input name="username" maxlength="60" required pattern="[A-Za-z0-9_.-]+" value="{{ old('username') }}"></label>
<label>Peran<select name="role">@foreach($roles as $role)<option value="{{ $role }}">{{ strtoupper(str_replace('_',' ',$role)) }}</option>@endforeach</select></label>
<label>Password (minimal 12 karakter)<input type="password" name="password" minlength="12" autocomplete="new-password" required></label>
<label>Ulangi Password<input type="password" name="password_confirmation" minlength="12" autocomplete="new-password" required></label>
<div class="form-actions"><button type="submit" class="btn primary">Buat Akun</button></div>
</form></section>
</div>
@php($edit = $users->firstWhere('id', (int)request('edit_user')))
@if($edit)
<section class="panel" id="edit-user"><div class="panel-head"><div><h2>Edit Pengguna: {{ $edit->username }}</h2><p class="muted">Kosongkan password jika tidak ingin menggantinya.</p></div><a href="{{ route('users.index') }}" class="btn">Batalkan</a></div>
<form method="POST" action="{{ route('users.update',$edit) }}">@csrf @method('PUT')<div class="form-grid">
<label>Nama lengkap<input name="full_name" maxlength="120" required value="{{ $edit->full_name }}"></label>
<label>Username<input name="username" maxlength="60" required value="{{ $edit->username }}"></label>
<label>Peran<select name="role">@foreach($roles as $role)<option value="{{ $role }}" @selected($edit->role===$role)>{{ strtoupper(str_replace('_',' ',$role)) }}</option>@endforeach</select></label>
<label>Status Akun<select name="active"><option value="1" @selected($edit->active)>Aktif</option><option value="0" @selected(!$edit->active)>Nonaktif</option></select></label>
<label>Password baru (opsional)<input type="password" name="password" minlength="12" autocomplete="new-password"></label>
<label>Konfirmasi password baru<input type="password" name="password_confirmation" minlength="12" autocomplete="new-password"></label>
</div><div class="form-actions"><button class="btn primary" type="submit">Simpan Perubahan</button></div></form></section>
@endif
<section class="panel"><h2>Ganti Password Akun Saya</h2><p class="muted">Untuk keamanan, penggantian password akun sendiri membutuhkan password lama.</p>
<form method="POST" action="{{ route('users.password') }}" class="form-grid">@csrf
<label>Password saat ini<input type="password" name="current_password" autocomplete="current-password" required></label>
<label>Password baru<input type="password" name="password" minlength="12" autocomplete="new-password" required></label>
<label>Konfirmasi password baru<input type="password" name="password_confirmation" minlength="12" autocomplete="new-password" required></label>
<div class="form-actions"><button class="btn primary" type="submit">Ganti Password Saya</button></div>
</form></section>
<div class="callout">Peran Wali Kelas sudah tersedia untuk akun, tetapi pembatasan akses berdasarkan kelas masing-masing belum selesai. Jangan memberikan akses ke data seluruh siswa kepada akun Wali Kelas sebelum pembatasannya diaudit.</div>
@endsection
