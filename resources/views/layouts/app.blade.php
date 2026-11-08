<!doctype html>
<html lang="id">
<head>
 <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
 <meta name="csrf-token" content="{{ csrf_token() }}">
 <title>@yield('title', 'Dashboard') — Aplikasi Absensi Siswa</title>
 <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
</head>
<body>
 <div class="shell">
  <aside class="sidebar">
   <div class="brand"><span class="brand-mark">AS</span><div><strong>Aplikasi Absensi Siswa</strong><small>Administrasi Madrasah</small></div></div>
   <nav>
     @if(auth()->user()->isAnyRole('admin','petugas','bp','kepala'))
       <a class="{{ request()->routeIs('dashboard') ? 'current' : '' }}" href="{{ route('dashboard') }}">◫ &nbsp; Dashboard</a>
     @endif
     @if(auth()->user()->isAnyRole('admin','petugas'))
       <a class="{{ request()->routeIs('classes.*') ? 'current' : '' }}" href="{{ route('classes.index') }}">▦ &nbsp; Data Kelas</a>
       <a class="{{ request()->routeIs('teachers.*') ? 'current' : '' }}" href="{{ route('teachers.index') }}">♧ &nbsp; Wali Kelas</a>
       <a class="{{ request()->routeIs('students.*') ? 'current' : '' }}" href="{{ route('students.index') }}">♙ &nbsp; Data Siswa</a>
       <a class="{{ request()->routeIs('weekly.*') ? 'current' : '' }}" href="{{ route('weekly.index') }}">▣ &nbsp; Input Absen Mingguan</a>
       <a class="{{ request()->routeIs('monthly.*') ? 'current' : '' }}" href="{{ route('monthly.index') }}">▤ &nbsp; Input Absen Bulanan</a>
     @endif
     @if(auth()->user()->isAnyRole('admin','petugas'))
       <a class="{{ request()->routeIs('attendance-corrections.*') ? 'current' : '' }}" href="{{ route('attendance-corrections.index') }}">Edit Absen</a>
     @endif
     @if(auth()->user()->isAnyRole('admin'))
       <a class="{{ request()->routeIs('progression.*') ? 'current' : '' }}" href="{{ route('progression.index') }}">♧ &nbsp; Kenaikan Kelas & Kelulusan</a>
     @endif
     @if(auth()->user()->isAnyRole('admin','petugas','bp','kepala'))
       <a class="{{ request()->routeIs('reports.index','reports.excel') ? 'current' : '' }}" href="{{ route('reports.index') }}">▤ &nbsp; Rekap & Laporan</a>
       <a class="{{ request()->routeIs('reports.print*') ? 'current' : '' }}" href="{{ route('reports.print.setup') }}">▣ &nbsp; Cetak Laporan Wali Kelas</a>
     @endif
     @if(auth()->user()->isAnyRole('admin','bp'))
       <a class="{{ request()->routeIs('discipline.*') ? 'current' : '' }}" href="{{ route('discipline.index') }}">◈ &nbsp; Peringatan & Pembinaan</a>
       <a class="{{ request()->routeIs('letters.*') ? 'current' : '' }}" href="{{ route('letters.index') }}">✉ &nbsp; Surat Tata Tertib</a>
     @endif
     @if(auth()->user()->isAnyRole('admin'))
       <a class="{{ request()->routeIs('settings.school*') ? 'current' : '' }}" href="{{ route('settings.school') }}">⚙ &nbsp; Profil Madrasah</a>
       <a class="{{ request()->routeIs('users.*') ? 'current' : '' }}" href="{{ route('users.index') }}">♧ &nbsp; Pengaturan Pengguna</a>
       <a class="{{ request()->routeIs('backup.*') ? 'current' : '' }}" href="{{ route('backup.index') }}">◈ &nbsp; Backup Database</a>
       <a class="{{ request()->routeIs('reset.*') ? 'current' : '' }}" href="{{ route('reset.index') }}">⚠ &nbsp; Reset Data Uji</a>
       <a class="{{ request()->routeIs('imports.*') ? 'current' : '' }}" href="{{ route('imports.index') }}">⇪ &nbsp; Impor & Ekspor Excel</a>
       <a class="{{ request()->routeIs('import-issues.*') ? 'current' : '' }}" href="{{ route('import-issues.index') }}">Masalah Impor</a>
     @endif
   </nav>
   <div class="sidebar-foot"><small>Versi Laravel — Tahap 5</small><p>{{ auth()->user()->full_name }}<br><span>{{ strtoupper(str_replace('_', ' ', auth()->user()->role)) }}</span></p>
     <form method="POST" action="{{ route('logout') }}">@csrf <button type="submit" class="logout">Keluar aplikasi ↗</button></form>
   </div>
  </aside>
  <main class="content">
   <header class="topbar"><span class="top-label">SISTEM ADMINISTRASI SEKOLAH</span><div class="userchip"><span class="dot"></span> Login aman</div></header>
   @if(session('success'))<div class="alert success">✓ {{ session('success') }}</div>@endif
   @if(session('notice'))<div class="alert notice">{{ session('notice') }}</div>@endif
   @if($errors->any())<div class="alert danger"><strong>Periksa kembali data:</strong><ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
   @yield('content')
   <footer class="page-foot">© {{ date('Y') }} Aplikasi Absensi Siswa · Pengembangan lokal sebelum online</footer>
  </main>
 </div>
</body>
</html>
