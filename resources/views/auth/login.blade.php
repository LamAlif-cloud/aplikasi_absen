<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Masuk — Aplikasi Absensi Siswa</title><link rel="stylesheet" href="{{ asset('assets/app.css') }}"></head>
<body class="login-body"><div class="login-card"><div class="login-logo">AS</div><p class="eyebrow">SISTEM ADMINISTRASI SEKOLAH</p><h1>Selamat datang</h1><p class="muted">Masuk menggunakan akun resmi yang diberikan administrator.</p>
@if($errors->any())<div class="alert danger">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('login.submit') }}">@csrf
<label>Username<input type="text" name="username" value="{{ old('username') }}" autocomplete="username" maxlength="60" required autofocus></label>
<label>Kata sandi<input type="password" name="password" autocomplete="current-password" required></label>
<button class="btn primary block" type="submit">Masuk ke aplikasi →</button></form><p class="small muted">Akun pertama dibuat melalui perintah <code>php artisan madrasah:admin</code>.</p></div></body></html>
