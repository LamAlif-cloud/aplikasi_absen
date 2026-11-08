<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Surat {{ $letter->letter_number }} — {{ $subject }}</title>
<style>
@page { size: A4 portrait; margin: 19mm 18mm 18mm 20mm; }
* { box-sizing: border-box; }
body { font-family: "DejaVu Serif", "Times New Roman", serif; font-size: 10.5pt; line-height: 1.55; color: #111; margin: 0; }
.sheet { max-width: 175mm; margin: 0 auto; }
.kop { width: 100%; border-bottom: 3px double #111; padding: 0 0 8px; margin: 0 0 18px; }
.kop-table { width: 100%; border-collapse: collapse; }
.kop-logo { width: 20mm; vertical-align: top; text-align: center; }
.kop-logo img { width: 18mm; max-height: 20mm; object-fit: contain; }
.kop-text { text-align: center; padding: 0 2mm; }
.kop-title { font-size: 13.5pt; font-weight: bold; text-transform: uppercase; line-height: 1.25; }
.kop-meta { font-size: 8.5pt; line-height: 1.4; }
.letter-name { text-align: center; font-size: 12pt; font-weight: bold; text-decoration: underline; text-transform: uppercase; margin: 0; }
.letter-number { text-align: center; font-size: 9pt; margin-bottom: 14px; }
.meta { width: 100%; border-collapse: collapse; }
.meta td { vertical-align: top; padding: 1px 0; }
.meta .key { width: 37mm; }
.prose { text-align: justify; margin: 9px 0; }
.rekap { margin: 9px 0 14px; width: 100%; border-collapse: collapse; font-size: 9.5pt; }
.rekap th, .rekap td { border: 1px solid #333; padding: 5px 7px; text-align: center; }
.rekap th { background: #f0f0f0; }
.note { font-size: 8.5pt; }
.citydate { margin-top: 18px; text-align: right; }
.signature-table { width: 100%; border-collapse: collapse; margin-top: 10px; page-break-inside: avoid; }
.signature-table td { width: 50%; text-align: center; vertical-align: top; }
.sign-gap { height: 60px; }
.sign-name { font-weight: bold; text-decoration: underline; }
.actions { margin: 0 0 15px; padding: 12px; background: #f4f7fb; border-radius: 8px; font: 13px Arial,sans-serif; }
.actions button, .actions a { background: #173d72; color: white; border: 0; border-radius: 5px; padding: 9px 15px; cursor: pointer; display: inline-block; text-decoration: none; }
.actions a { background: #3b536f; }
.warning { color: #8b4513; }
@media print { .actions { display: none; } .sheet { max-width: none; } }
</style>
</head>
<body>
@if(!($pdfMode ?? false))
<div class="actions">
<button type="button" onclick="window.print()">Cetak / Print sekarang</button>
<a href="{{ route('letters.pdf', $letter) }}">Unduh PDF</a>
<span>Pastikan nama penandatangan sudah lengkap pada Profil Madrasah.</span>
</div>
@endif
<div class="sheet">
<div class="kop">
<table class="kop-table"><tr>
<td class="kop-logo">@if($logoData)<img src="{{ $logoData }}" alt="Logo Madrasah">@endif</td>
<td class="kop-text">
<div class="kop-title">{{ ($school['school_name'] ?? '') ?: 'MADRASAH TSANAWIYAH' }}</div>
@if(!empty($school['school_address']))<div class="kop-meta">{{ $school['school_address'] }}</div>@endif
<div class="kop-meta">
@if(!empty($school['school_nsm']))NSM: {{ $school['school_nsm'] }} @endif
@if(!empty($school['school_npsn'])) &nbsp; NPSN: {{ $school['school_npsn'] }} @endif
@if(!empty($school['school_phone'])) &nbsp; Telp: {{ $school['school_phone'] }} @endif
</div>
</td>
<td class="kop-logo"></td>
</tr></table>
</div>
<p class="letter-name">{{ $subject }}</p>
<p class="letter-number">Nomor: {{ $letter->letter_number }}</p>
<p>Kepada Yth.<br><strong>{{ $recipient }}</strong><br>di Tempat</p>
<p>Assalamu'alaikum warahmatullahi wabarakatuh.</p>
<p class="prose">Berdasarkan rekapitulasi kehadiran Semester <strong>{{ $letter->semester }}</strong> Tahun Pelajaran <strong>{{ $letter->academic_year }}</strong>, berikut kami sampaikan data siswa:</p>
<table class="meta">
<tr><td class="key">Nama siswa</td><td>: <strong>{{ $letter->student->full_name }}</strong></td></tr>
<tr><td class="key">NIS</td><td>: {{ $letter->student->nis ?: '—' }}</td></tr>
<tr><td class="key">Kelas</td><td>: <strong>{{ $classLabel }}</strong></td></tr>
<tr><td class="key">Periode</td><td>: Semester {{ $letter->semester }} / {{ $letter->academic_year }}</td></tr>
</table>
<table class="rekap">
<thead><tr><th>Sakit (S)</th><th>Izin (I)</th><th>Alpa (A)</th><th>Alpa (Hari)</th></tr></thead>
<tbody><tr><td>{{ $sakitJp }} JP</td><td>{{ $izinJp }} JP</td><td><strong>{{ $alpaJp }} JP</strong></td><td><strong>{{ number_format($alpaJp / 8, 2, ',', '.') }} hari</strong></td></tr></tbody>
</table>
<p class="note"><em>Keterangan: 8 JP = 1 hari. Ambang tata tertib dihitung berdasarkan Alpa semester saja; Sakit dan Izin tidak termasuk Alpa.</em></p>
@if($level === 1)
<p class="prose">Akumulasi Alpa siswa telah mencapai tahap <strong>3 hari (24 JP)</strong>. Kami meminta Bapak/Ibu Wali Kelas memberikan <strong>peringatan serta pembinaan awal</strong>, berkoordinasi dengan bagian kesiswaan, dan mencatat hasil tindak lanjutnya.</p>
@elseif($level === 2)
<p class="prose">Akumulasi Alpa siswa telah mencapai tahap <strong>6 hari (48 JP)</strong>. Kami meminta bagian <strong>Bimbingan dan Penyuluhan (BP)</strong> melakukan penanganan dan pembinaan siswa, termasuk koordinasi dengan Wali Kelas dan pencatatan hasilnya.</p>
@else
<p class="prose">Akumulasi Alpa putra/putri Bapak/Ibu telah mencapai tahap <strong>9 hari (72 JP)</strong>. Sehubungan dengan hal tersebut, kami mengundang Bapak/Ibu datang ke madrasah untuk membicarakan kehadiran dan tindak lanjut pembinaan putra/putri Bapak/Ibu.</p>
@endif
@if($letter->meeting_date)<p>Hari/tanggal pertemuan: <strong>{{ $letter->meeting_date->translatedFormat('l, d F Y') }}</strong>. Waktu dan tempat dapat dikonfirmasi kepada petugas madrasah.</p>@endif
@if($letter->notes)<p class="prose">Keterangan tambahan: {{ $letter->notes }}</p>@endif
<p class="prose">Demikian surat ini disampaikan. Atas perhatian dan kerja sama Bapak/Ibu, kami mengucapkan terima kasih.</p>
<p>Wassalamu'alaikum warahmatullahi wabarakatuh.</p>
<div class="citydate">{{ $letter->letter_date->translatedFormat('d F Y') }}</div>
<table class="signature-table"><tr>
@if($level === 3)
<td>Mengetahui,<br><strong>Kepala Madrasah</strong><div class="sign-gap"></div><span class="sign-name">{{ $principalName ?: '....................................' }}</span></td>
<td><strong>{{ $senderTitle }}</strong><div class="sign-gap"></div><span class="sign-name">{{ $senderName ?: '....................................' }}</span></td>
@else
<td></td>
<td><strong>{{ $senderTitle }}</strong><div class="sign-gap"></div><span class="sign-name">{{ $senderName ?: '....................................' }}</span></td>
@endif
</tr></table>
</div>
</body></html>
