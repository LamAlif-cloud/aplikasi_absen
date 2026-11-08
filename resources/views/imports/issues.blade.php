@extends('layouts.app')
@section('title', 'Masalah Impor')
@section('content')
<div class="heading"><div><h1>Masalah Impor</h1><p class="muted">Baris yang gagal disimpan dapat diperiksa dan diperbaiki satu per satu.</p></div><a class="btn" href="{{ route('imports.index') }}">Unggah Excel</a></div>
<div class="callout">Daftar ini menyimpan kegagalan sejak fitur diaktifkan. Untuk upload sebelumnya, unggah kembali file sumber agar baris bermasalah tercatat. Data lama tetap digunakan sampai perbaikan berhasil disimpan.</div>
<p><a href="{{ route('import-issues.index') }}">Belum selesai</a> · <a href="{{ route('import-issues.index', ['status'=>'resolved']) }}">Sudah diperbaiki</a></p>
<section class="panel"><h2>{{ $resolved ? 'Sudah diperbaiki' : 'Perlu diperbaiki' }} ({{ $issues->total() }})</h2>
<div class="table-wrap"><table><thead><tr><th>File / Baris</th><th>Siswa / Kelas</th><th>Periode / Satuan</th><th>Sakit / Izin / Alpa</th><th>Alasan gagal</th><th>Aksi</th></tr></thead><tbody>
@forelse($issues as $issue)
@php($r=$issue->row_data)
<tr><td>{{ $issue->filename }}<br>Baris {{ $issue->row_number }}<br>{{ $issue->created_at }}</td>
<td>{{ $r['full_name'] ?? $r['nis'] ?? $r['nisn'] ?? '—' }}<br>{{ $r['class_name'] ?? '—' }}</td>
<td>{{ $issue->academic_year }}<br>{{ $r['month'] ?? $r['week_start'] ?? $issue->type }}<br>{{ $issue->input_unit === 'days' ? 'Hari (×8 JP)' : 'JP' }}</td>
<td>{{ $r['sakit_jp'] ?? '—' }} / {{ $r['izin_jp'] ?? '—' }} / {{ $r['alpa_jp'] ?? '—' }}</td><td>{{ $issue->error }}</td>
<td>@if($issue->resolved_at)Selesai @else<a class="btn" href="{{ route('import-issues.edit', $issue) }}">Periksa &amp; Edit</a>@endif</td></tr>
@empty<tr><td colspan="6">Tidak ada masalah impor pada daftar ini.</td></tr>@endforelse
</tbody></table></div>{{ $issues->links() }}</section>
@endsection
