<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Cetak Rekap Absensi {{ $period }} — {{ $year }}</title>
<style>
@page{size:A4 landscape;margin:10mm 10mm 11mm}
*{box-sizing:border-box}
body{font:9pt/1.36 "DejaVu Sans",Arial,sans-serif;color:#172327;background:white;margin:0}
.print-toolbar{background:#f0f4f3;border-bottom:1px solid #cbd8d5;padding:11px 16px;font:13px Arial,sans-serif;display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.print-toolbar a,.print-toolbar button{border:1px solid #bacac7;background:white;border-radius:5px;color:#164943;padding:9px 13px;cursor:pointer;text-decoration:none;font-weight:bold}
.print-toolbar button{background:#1c6059;color:white}
.print-toolbar small{color:#536e68}
.sheet{width:100%;max-width:278mm;margin:0 auto;page-break-after:always}
.sheet:last-of-type{page-break-after:auto}
.kop{width:100%;border-collapse:collapse;border-bottom:3px double #242c2c;margin-bottom:9px}
.kop td{border:0;vertical-align:middle;padding:0 3px 8px}
.logo{width:19mm;text-align:center}
.logo img{width:17mm;max-height:19mm;object-fit:contain}
.kop-title{font:bold 14pt/1.22 "DejaVu Sans",Arial,sans-serif;text-align:center;text-transform:uppercase}
.kop-meta{font:8pt/1.35 "DejaVu Sans",Arial,sans-serif;text-align:center}
.report-title{text-align:center;font:bold 11pt/1.25 "DejaVu Sans",Arial,sans-serif;text-decoration:underline;margin:7px 0 3px}
.report-meta{text-align:center;font-size:8pt;margin:0 0 8px}
.info{width:100%;border-collapse:collapse;margin:4px 0 8px}
.info td{padding:2px 0;vertical-align:top;border:0}
.info .key{width:27mm}
.info .sep{width:3mm}
.report-table{border-collapse:collapse;width:100%;table-layout:fixed;font-size:7.5pt}
.report-table th,.report-table td{border:1px solid #5b6966;padding:4px 4px;vertical-align:top;overflow-wrap:break-word}
.report-table th{font-weight:700;text-align:center;background:#eaeff0}
.report-table td.center,.report-table td.numeric{text-align:center}
.report-table td.numeric{white-space:nowrap}
.report-table td.duration{white-space:normal;text-align:center}
.report-table td.name{font-weight:600}
.report-table td.none{text-align:center;color:#697471;font-style:italic}
.report-table tfoot td{font-weight:bold;background:#f2f5f3}
.report-table thead{display:table-header-group}
.report-table tr{page-break-inside:avoid}
.summary{margin:7px 0 6px;font-size:7.8pt}
.caution{margin-top:5px;font-size:7.4pt;color:#444;line-height:1.4}
.signatures{width:100%;border-collapse:collapse;margin-top:11mm;page-break-inside:avoid}
.signatures td{border:none;width:50%;text-align:center;vertical-align:top;font-size:8pt}
.sign-gap{height:18mm}
.sign-name{font-weight:bold;text-decoration:underline}
.date{text-align:right;margin:5px 3px 0;font-size:8pt}
.empty-title{text-align:center;font-size:12pt;margin-top:40px}
@media screen{body{background:#f0f3f2;padding:0}.sheet{background:#fff;box-shadow:0 5px 24px #243e3a25;margin:16px auto;padding:12mm 11mm;min-height:185mm}.sheet:last-of-type{margin-bottom:25px}}
@media print{body{margin:0;background:#fff}.print-toolbar{display:none}.sheet{padding:0!important;box-shadow:none;margin:0;max-width:none;min-height:0}}
</style></head><body>
@if(!($pdfMode ?? false))
<div class="print-toolbar">
<button type="button" onclick="window.print()">Cetak / Print</button>
<a href="{{ route('reports.print.pdf',request()->only(['academic_year','period','class_id','week_start','month','action'])) }}">Unduh PDF</a>
<a href="{{ route('reports.print.setup',request()->only(['academic_year','period','class_id','week_start','month','action'])) }}">Ubah Filter</a>
<small>Disusun untuk diserahkan kepada wali kelas masing-masing. Cetak A4 Landscape.</small>
</div>
@endif
@if(empty($sections))
<div class="sheet"><h2 class="empty-title">{{ $action !== 'all' ? 'Tidak ada siswa yang memenuhi filter tindakan.' : 'Belum ada kelas untuk tahun pelajaran '.$year.'.' }}</h2>
<p style="text-align:center">Silakan kembali ke menu Cetak Laporan untuk memilih tahun pelajaran yang sudah memiliki data kelas.</p></div>
@endif
@foreach($sections as $section)
@php($room=$section['class'])
<div class="sheet">
<table class="kop"><tr><td class="logo">@if($logoData)<img src="{{ $logoData }}" alt="Logo Madrasah">@endif</td>
<td>
<div class="kop-title">{{ ($school['school_name'] ?? '') ?: 'MADRASAH TSANAWIYAH' }}</div>
@if(!empty($school['school_address']))<div class="kop-meta">{{ $school['school_address'] }}</div>@endif
<div class="kop-meta">@if(!empty($school['school_nsm']))NSM: {{ $school['school_nsm'] }} @endif @if(!empty($school['school_npsn'])) &nbsp; NPSN: {{ $school['school_npsn'] }} @endif @if(!empty($school['school_phone'])) &nbsp; Telepon: {{ $school['school_phone'] }} @endif</div>
</td><td class="logo"></td></tr></table>
<div class="report-title">LAPORAN REKAPITULASI KETIDAKHADIRAN SISWA</div>
@if(!empty($openingNotice))<p class="caution" style="padding:5px;border:1px solid #a4a4a4"><strong>SALDO AWAL:</strong> {{ $openingNotice }}</p>@endif
<p class="report-meta">{{ $rangeLabel }} &nbsp;|&nbsp; Tahun Pelajaran {{ $year }}</p>
<p class="report-meta"><strong>Filter: {{ $actionLabel }}</strong>@if($action!=='all') · Berdasarkan Alpa kumulatif per semester; laporan tahunan memakai masing-masing semester.@endif</p>
<table class="info"><tr><td class="key">Kelas</td><td class="sep">:</td><td><strong>{{ $room->class_name }}</strong></td><td class="key">Wali Kelas</td><td class="sep">:</td><td><strong>{{ $room->homeroom_teacher ?: '....................................' }}</strong></td></tr>
<tr><td class="key">Metode input</td><td class="sep">:</td><td colspan="4">Rekap JP mingguan / bulanan &nbsp; (8 JP = 1 hari)</td></tr></table>
<table class="report-table">
<thead><tr>
<th style="width:3%">No</th><th style="width:16%">Nama Siswa</th><th style="width:8%">NIS</th>
<th style="width:4%">S<br>JP</th><th style="width:4%">I<br>JP</th><th style="width:4%">A<br>JP</th><th style="width:10%">Sakit<br>Hari</th><th style="width:10%">Izin<br>Hari</th><th style="width:10%">Alpa<br>Hari</th>
@if($isAnnual)<th style="width:4%">A Ganjil<br>JP</th><th style="width:11%">Tatib Ganjil</th><th style="width:4%">A Genap<br>JP</th><th style="width:12%">Tatib Genap</th>
@elseif($isShort)<th style="width:10%">Alpa Sem.<br>s.d. Periode (JP)</th><th style="width:21%">Status Tatib Semester</th>
@else<th style="width:31%">Status Tatib Semester</th>
@endif
</tr></thead>
<tbody>
@forelse($section['rows'] as $idx=>$r)
<tr><td class="center">{{ $idx+1 }}</td><td class="name">{{ $r->full_name }}@if($r->recorded && !(int)$r->period_entry_count)<br><small>Belum diinput untuk periode ini; tindakan dari akumulasi semester.</small>@endif</td><td class="center">{{ $r->nis ?: '—' }}</td>
@if($r->recorded)
@foreach(['sakit_jp','izin_jp','alpa_jp'] as $quantity)<td class="numeric">{{ (int)$r->period_entry_count ? (int)$r->{$quantity} : '—' }}</td>@endforeach
@foreach(['sakit_jp','izin_jp','alpa_jp'] as $quantity)<td class="duration">{{ (int)$r->period_entry_count ? \App\Services\AbsenceQuantity::readable((int)$r->{$quantity}) : '—' }}</td>@endforeach
@if($isAnnual)
<td class="numeric">{{ (int)$r->alpa_ganjil_jp }}</td><td>{{ \App\Services\Discipline::label((int)$r->alpa_ganjil_jp) }}</td>
<td class="numeric">{{ (int)$r->alpa_genap_jp }}</td><td>{{ \App\Services\Discipline::label((int)$r->alpa_genap_jp) }}</td>
@elseif($isShort)
<td class="numeric">{{ (int)$r->semester_alpa_jp }}</td><td>{{ \App\Services\Discipline::label((int)$r->semester_alpa_jp) }}</td>
@else
<td>{{ \App\Services\Discipline::label((int)$r->alpa_jp) }}</td>
@endif
@else
<td class="none" colspan="{{ $isAnnual?10:($isShort?8:7) }}">Belum diinput untuk periode ini</td>
@endif
</tr>
@empty<tr><td colspan="{{ $isAnnual?13:($isShort?11:10) }}" class="none">Belum ada siswa dengan catatan/keanggotaan kelas pada periode ini.</td></tr>
@endforelse
</tbody>
<tfoot><tr><td colspan="3">JUMLAH TERCATAT</td>
<td class="numeric">{{ $section['totals']['sakit'] }}</td><td class="numeric">{{ $section['totals']['izin'] }}</td><td class="numeric">{{ $section['totals']['alpa'] }}</td>
@foreach(['sakit','izin','alpa'] as $quantity)<td class="duration">{{ \App\Services\AbsenceQuantity::readable((int)$section['totals'][$quantity]) }}</td>@endforeach<td colspan="{{ $isAnnual?4:($isShort?2:1) }}"></td></tr></tfoot>
</table>
<p class="summary">Jumlah siswa pada daftar: <strong>{{ count($section['rows']) }}</strong> &nbsp; | &nbsp; Sudah ada catatan pada periode ini: <strong>{{ $section['recordedCount'] }}</strong> &nbsp; | &nbsp; Belum ada input: <strong>{{ $section['missingCount'] }}</strong></p>
<p class="caution">Keterangan: S = Sakit, I = Izin, A = Alpa. Angka dalam JP; 8 JP = 1 hari. Status tata tertib ditentukan oleh <strong>Alpa kumulatif per semester</strong> (24/48/72 JP), bukan gabungan S+I+A. Baris bertuliskan “Belum diinput” bukan berarti 0 JP. Untuk rekap mingguan, minggu dicatat menurut tanggal Senin; rekap bulanan mengikuti bulan tanggal Senin.</p>
<div class="date">Tanggal cetak: {{ $printDate }}</div>
<table class="signatures"><tr><td>Diserahkan oleh,<br>@foreach($section['operators'] as $operator)<strong>{{ $operator['label'] }}</strong><div class="sign-gap"></div><span class="sign-name">{{ $operator['name'] ?: '...................................' }}</span>@if(!$loop->last)<br><br>@endif @endforeach</td>
<td>Diterima oleh,<br><strong>Wali Kelas {{ $room->class_name }}</strong><div class="sign-gap"></div><span class="sign-name">{{ $room->homeroom_teacher ?: '...................................' }}</span></td></tr></table>
</div>
@endforeach
</body></html>
