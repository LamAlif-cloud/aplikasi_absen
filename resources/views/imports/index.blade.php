@extends('layouts.app')
@section('title', 'Impor & Ekspor Excel')
@section('content')
<p><a class="btn" href="{{ route('import-issues.index') }}">Daftar Masalah Impor &amp; Perbaikan →</a></p>
<div class="heading"><div><p class="eyebrow">PENGELOLAAN DATA MADRASAH</p><h1>Impor & Ekspor Excel</h1><p class="muted">Format Excel langsung (.xlsx / .xls untuk impor; .xlsx untuk ekspor). Tanpa CSV.</p></div><span class="tag">Impor Excel · format sekolah 5.7</span></div>
@if(session('import_summary'))
 @php($s=session('import_summary'))
 <section class="panel"><h2>Hasil Impor Excel</h2><div class="metrics">
 <div class="metric"><span>Data baru</span><strong>{{ $s['baru'] }}</strong></div>
 <div class="metric"><span>Diperbarui</span><strong>{{ $s['diperbarui'] }}</strong></div>
 <div class="metric"><span>Dilewati</span><strong>{{ $s['dilewati'] }}</strong></div>
 <div class="metric"><span>Gagal</span><strong>{{ $s['gagal'] }}</strong></div>
 </div>
 @if(count($s['errors']))<h3>Alasan kegagalan (maksimal 100 pertama)</h3><div class="error-list">@foreach($s['errors'] as $reason)<p>{{ $reason }}</p>@endforeach</div>@endif
 </section>
@endif
<div class="two-cols uneven">
 <section class="panel"><h2>Unggah Data Excel</h2>
 <p class="small muted"><strong>Petunjuk satuan:</strong> untuk kolom JUMLAH pada rekap bulanan sumber gunakan JP; untuk kolom TOTAL HARI gunakan Hari termasuk angka bulat (contoh 12 hari = 96 JP). Jangan gunakan mode Campuran untuk total semester karena bisa menyebabkan salah tahap tata tertib.</p>
<form method="POST" action="{{ route('imports.store') }}" enctype="multipart/form-data">@csrf
 <label>Jenis data
 <select name="type" required id="excel-type" onchange="updateExcelLinks(true)">
 <option value="classes" @selected(old('type')==='classes')>Kelas &amp; Wali Kelas (satu Excel)</option>
 <option value="students" @selected(old('type')==='students')>Data Siswa</option>
 <option value="weekly" @selected(old('type')==='weekly')>Rekap Absensi JP Mingguan</option>
 <option value="monthly" @selected(old('type')==='monthly')>Rekap Absensi JP Bulanan</option>
 </select></label>
 <label>Tahun pelajaran<input id="excel-year" name="academic_year" value="{{ old('academic_year',\App\Services\YearOptions::selected(null)) }}" required pattern="[0-9]{4}/[0-9]{4}" maxlength="9" oninput="updateExcelLinks()"></label>
 <label>Jika data sudah ada<select name="existing_action" required><option value="update">Perbarui data sesuai Excel</option><option value="skip">Lewati data yang sudah ada</option></select></label>
 <label id="excel-unit-container">Satuan angka Sakit, Izin, Alpa dalam file Excel
 <select name="input_unit" id="excel-unit">
 <option value="" @selected(!in_array(old('input_unit'), ['jp', 'days'], true))>Pilih satuan angka dalam file</option>
 <option value="jp" @selected(old('input_unit')==='jp')>JP — semua nilai merupakan Jam Pelajaran bulat (kolom Jumlah per bulan)</option>
 <option value="days" @selected(old('input_unit')==='days')>Hari — semua nilai merupakan hari; termasuk angka bulat dikalikan 8 (kolom JUMLAH TOTAL)</option>
 </select><small><strong>Wajib memilih satuan secara eksplisit:</strong> JP untuk kolom Jumlah S–I–A bulanan; Hari untuk JUMLAH TOTAL S–I–A (termasuk 12,00 hari = 96 JP). Mode Campuran dinonaktifkan karena dapat menyebabkan tindakan tata tertib yang salah.</small></label>
 <label>Pilih file Excel (maksimal 5 MB)<input type="file" name="file" accept=".xlsx,.xls" required></label>
 <div class="form-actions"><button class="btn primary" type="submit">Proses impor & periksa hasil</button></div>
 </form><div class="callout">Sebaiknya impor berurutan: <strong>Kelas + Wali Kelas → Siswa → Absensi JP</strong>. Urutan kolom siswa sesuai berkas sekolah (NOMOR, NIS, NISN, NAMA, JENIS KELAMIN, KELAS, STATUS PONDOK, NAMA PONDOK). <strong>Enam kolom awal tidak berubah; dua kolom baru STATUS PONDOK dan NAMA PONDOK ditambahkan di kanan.</strong> <strong>NOMOR hanya nomor urut dan tidak disimpan</strong>. NIS <strong>tidak wajib diisi</strong>. Jika NIS kosong, gunakan NISN untuk kecocokan. Jika keduanya kosong, nama harus unik dalam satu kelas; jika ambigu akan ditolak. NIS/NISN yang diisi harus berformat <strong>teks</strong> agar nol di depan tidak hilang. <strong>Untuk melengkapi pondok siswa yang sudah diimpor:</strong> pilih Siswa, unduh <em>Ekspor Data .xlsx</em>, isi dua kolom pondok, lalu impor dengan opsi <em>Perbarui data sesuai Excel</em>. Gunakan NIS atau NISN sebagai identitas jika ada; jika tidak ada keduanya, nama/kelas/jenis kelamin harus unik dan persis cocok. Kolom pondok yang kosong tidak menghapus data pondok yang sudah tersimpan. Mingguan menggunakan tanggal Senin YYYY-MM-DD; bulanan menggunakan BULAN YYYY-MM. Tidak boleh ada input mingguan dan bulanan untuk siswa pada bulan yang sama. <strong>Gunakan nilai dari kolom bulanan Jumlah dalam JP. Jangan campur kolom bulanan JP dengan JUMLAH TOTAL hari dalam satu file.</strong> Tampilan Excel berbentuk 4,00 atau 0,00 tidak berarti ada pecahan JP; aplikasi membaca nilai aslinya. Dalam mode Semua Hari, seluruh angka dikali 8, termasuk angka bulat; semua nilai tetap satuan yang dipilih. Kolom S/I/A wajib angka saja: bukan teks seperti "1 hari 25 JP". Untuk 1 hari + 25 JP masukkan 33 JP bila memilih satuan JP.</div>
 </section>
 <section class="panel"><h2>Template & Ekspor Excel</h2><p class="muted">Unduh template untuk mengisi data atau ekspor data yang sudah tersimpan. Pilihan jenis dan tahun pelajaran mengikuti form di sebelah.</p>
 <div class="file-actions"><a class="btn primary" id="excel-template-link" href="{{ route('excel.template',['type'=>'classes']) }}">Unduh Template .xlsx</a><a class="btn" id="excel-export-link" href="{{ route('excel.export',['type'=>'classes','academic_year'=>\App\Services\YearOptions::selected(null)]) }}">Ekspor Data .xlsx</a></div>
 <p class="small muted">Tersedia 4 jenis: Kelas beserta Wali Kelas, Siswa, serta Absensi Mingguan/Bulanan. Laporan semester/tahunan dapat diekspor dari menu Rekap & Laporan.</p>
 <h3>Format kolom per jenis</h3><div class="table-wrap"><table><thead><tr><th>Jenis</th><th>Nama kolom (header)</th></tr></thead><tbody>
 <tr><td>Kelas + Wali Kelas</td><td>KELAS, WALI KELAS</td></tr>
 <tr><td>Siswa</td><td><strong>NOMOR, NIS, NISN, NAMA, JENIS KELAMIN, KELAS, STATUS PONDOK, NAMA PONDOK</strong></td></tr>
 <tr><td>Absensi JP Mingguan</td><td>NIS, NISN, NAMA, KELAS, MINGGU DIMULAI, SAKIT JP, IZIN JP, ALPA JP, CATATAN</td></tr>
 <tr><td>Absensi JP Bulanan</td><td>NIS, NISN, NAMA, KELAS, BULAN, SAKIT JP, IZIN JP, ALPA JP, CATATAN</td></tr>
 </tbody></table></div>
 </section>
</div>
<script>
function updateExcelLinks(typeChanged=false){
  const type=document.getElementById('excel-type').value;
  const attendance = ['weekly','monthly'].includes(type);
  const unit = document.getElementById('excel-unit');
  document.getElementById('excel-unit-container').style.display = attendance ? '' : 'none';
  unit.required = attendance;
  unit.disabled = !attendance;
  const year=document.getElementById('excel-year').value;
  const template=new URL(@json(route('excel.template')),window.location.href);
  template.searchParams.set('type',type);
  const exported=new URL(@json(route('excel.export')),window.location.href);
  exported.searchParams.set('type',type);exported.searchParams.set('academic_year',year);
  document.getElementById('excel-template-link').href=template.toString();
  document.getElementById('excel-export-link').href=exported.toString();
}
updateExcelLinks();
</script>
@endsection
