@extends('layouts.app')
@section('title','Input Rekap Absen Bulanan')
@section('content')
<p><a class="btn" href="{{ route('attendance-corrections.index',['academic_year'=>$year,'class_id'=>$classId,'mode'=>'monthly']) }}">Edit Absen Bulanan yang Sudah Diinput</a></p>
<div class="heading"><div><p class="eyebrow">PENCATATAN JP</p><h1>Input Rekap Absen Bulanan</h1><p class="muted">Rekap satu kali sebulan. Untuk siswa dan bulan yang sama, pilih bulanan atau mingguan agar JP tidak terhitung dua kali.</p></div><div class="file-actions no-print"><span class="tag">8 JP = 1 hari</span><a class="btn" href="{{ route('weekly.index',['academic_year'=>$year,'class_id'=>$classId]) }}">Input Mingguan</a><a class="btn" href="{{ route('excel.export',['type'=>'monthly','academic_year'=>$year,'class_id'=>$classId,'month'=>$month]) }}">Ekspor Excel Bulanan</a></div></div>
<p class="small muted no-print"><strong>Masukkan jumlah JP bulat.</strong> Sistem menampilkan ekuivalen hari di bawah setiap angka: 8 JP = 1 hari; 25 JP = 3 hari 1 JP (3,125 hari). Pecahan desimal Hari hanya digunakan pada impor Excel jika satuan Hari dipilih.</p>
<section class="panel no-print"><form method="GET" action="{{ route('monthly.index') }}" class="toolbar"><label>Tahun pelajaran<select name="academic_year" onchange="this.form.elements['class_id'].value='0';this.form.submit()">@foreach($years as $y)<option value="{{ $y }}" @selected($year===$y)>{{ $y }}</option>@endforeach</select></label><label>Kelas<select name="class_id"><option value="0">— Pilih kelas —</option>@foreach($classes as $c)<option value="{{ $c->id }}" @selected($classId===$c->id)>{{ $c->class_name }} ({{ $c->active_students_count }} aktif)</option>@endforeach</select></label><label>Bulan<input type="month" name="month" value="{{ $month }}" required></label><button class="btn primary" type="submit">Tampilkan</button></form></section>
<section class="panel"><div class="panel-head"><h2>Daftar input — {{ $classes->firstWhere('id',$classId)?->class_name ?? '—' }}</h2><span class="tag">{{ $students->count() }} siswa</span></div>
@if(!$classId)<div class="alert notice">Belum tersedia kelas di tahun ini.</div>
@elseif($students->isEmpty())<div class="alert notice">Tidak ada siswa aktif di kelas ini. Periksa data siswa dan tahun pelajaran.</div>
@elseif(($openingCount ?? 0)>0)<div class="alert danger">Ada {{ $openingCount }} siswa dengan Saldo Awal terverifikasi sampai bulan ini. Formulir bulan tersebut dikunci agar akumulasi lama tidak tertimpa. Input normal dapat dilanjutkan pada bulan berikutnya.</div>
@elseif(count($weeklyStudentIds))<div class="alert danger">Ada {{ count($weeklyStudentIds) }} siswa yang sudah memiliki rekap mingguan untuk {{ $month }}. Penyimpanan bulanan ditolak agar tidak menghitung JP dua kali. Koreksi lewat Input Mingguan atau lakukan pengelolaan data terdahulu secara aman.</div>
@endif
@if($students->isNotEmpty())<form method="POST" action="{{ route('monthly.store') }}">@csrf<input type="hidden" name="academic_year" value="{{ $year }}"><input type="hidden" name="class_id" value="{{ $classId }}"><input type="hidden" name="month" value="{{ $month }}">
<div class="table-wrap"><table><thead><tr><th>No.</th><th>NIS</th><th>Nama siswa</th><th>Sakit JP</th><th>Izin JP</th><th>Alpa JP</th><th>Catatan</th></tr></thead><tbody>@foreach($students as $i=>$s)@php($existing=$data->get($s->id))<tr><td>{{ $i+1 }}<input type="hidden" name="rows[{{ $i }}][student_id]" value="{{ $s->id }}"></td><td>{{ $s->nis ?: '—' }}</td><td><strong>{{ $s->full_name }}</strong></td>@foreach(['sakit_jp','izin_jp','alpa_jp'] as $field)<td><input class="jp" type="number" name="rows[{{ $i }}][{{ $field }}]" value="{{ old('rows.'.$i.'.'.$field,$existing?->{$field} ?? 0) }}" min="0" max="248" step="1" required><small class="muted jp-converted" data-unit-preview>—</small></td>@endforeach<td><input class="note" name="rows[{{ $i }}][note]" value="{{ old('rows.'.$i.'.note',$existing?->note ?? '') }}" maxlength="255"></td></tr>@endforeach</tbody></table></div><div class="form-actions"><button class="btn primary" type="submit" @disabled(count($weeklyStudentIds)>0 || ($openingCount ?? 0)>0)>Simpan Rekap Bulanan</button></div></form>@endif
</section>
<script>
(function(){
  function formatJp(jp) {
    if (!Number.isInteger(jp) || jp < 0) return 'Masukkan JP bulat';
    const hari=Math.floor(jp/8), sisa=jp%8;
    const bagian=hari ? hari+' hari'+(sisa?' '+sisa+' JP':'') : sisa+' JP';
    const desimal=(jp/8).toLocaleString('id-ID',{maximumFractionDigits:3});
    return bagian+' ('+desimal+' hari)';
  }
  document.querySelectorAll('input.jp').forEach(function(input){
    const label=input.parentElement.querySelector('[data-unit-preview]');
    if (!label) return;
    const draw=function(){ const value=input.value.trim(); label.textContent=value===''?'—':formatJp(Number(value)); };
    input.addEventListener('input',draw);draw();
  });
})();
</script>
@endsection
