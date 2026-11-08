# LamAlif Patch 5.8 — Cetak laporan untuk wali kelas

## Fitur
- Menu **Cetak Laporan Wali Kelas** baru pada sidebar, juga tombol dari Rekap & Laporan.
- Filter Mingguan (tanggal Senin), Bulanan, Semester Ganjil, Semester Genap, dan Tahun Pelajaran Juli–Juni.
- Pilihan **satu kelas** atau **semua kelas**; Semua Kelas disusun per kelas dengan lembar/kop dan ruang penyerahan masing-masing.
- Cetak HTML (browser, A4 landscape) dan unduh PDF memakai Dompdf (jika sudah terpasang).
- Kop, logo, identitas madrasah dari Profil; wali kelas dari Data Kelas; operator dari akun yang login.
- Kolom Sakit–Izin–Alpa dalam JP, konversi Alpa ke hari, tata tertib semester 24/48/72 JP.
- Rekap tahunan mempertahankan **status tata tertib Ganjil dan Genap terpisah**.
- Data tanpa input JP dilabeli **Belum diinput**, bukan 0 JP palsu.
- Perhitungan sumber data yang sama dengan `AbsenceReport::query` (mingguan + bulanan, tanpa penghitungan ganda).

## Pemasangan setelah Patch 5.7
1. Cadangkan proyek dan database Laragon. Stop `php artisan serve` (Ctrl+C).
2. Ekstrak ZIP. Salin folder `lamalif_madrasah_laravel13` ke `C:\laragon\www\lamalif_madrasah_laravel13` dan **gabungkan/timpa hanya file patch**.
3. Jangan hapus `.env`, `vendor`, `storage`, `composer.lock`, atau data MySQL.
4. Di Terminal Laragon, ketik *satu perintah per baris*:
   ```cmd
   cd C:\laragon\www\lamalif_madrasah_laravel13
   php artisan optimize:clear
   .\vendor\bin\phpunit.bat --display-errors
   php artisan serve
   ```
5. Login. Klik **Cetak Laporan Wali Kelas**. Pilih tahun/periode/kelas, lalu **Pratinjau & Cetak** atau **Unduh PDF A4 Landscape**.
6. Jika PDF gagal dengan pesan Dompdf tidak tersedia, Stop server, kemudian `composer require dompdf/dompdf` di folder proyek; jalankan kembali.

## Checklist uji dengan data aktual
- [ ] Uji satu kelas/mingguan: total SIA sama dengan Rekap & Laporan untuk minggu sama.
- [ ] Uji satu kelas/bulanan: siswa dari input bulanan dan mingguan di bulan yang berbeda tercatat benar.
- [ ] Uji semester: status Wali Kelas/BP/Orang Tua pada 24/48/72 JP Alpa.
- [ ] Uji tahunan: label tata tertib Ganjil dan Genap terpisah.
- [ ] Uji semua kelas: tiap kelas memperoleh lembar, wali kelas, dan total sendiri.
- [ ] Uji riwayat siswa yang naik kelas: laporan lama berdasarkan `class_id` transaksi.
- [ ] Uji siswa tanpa data input: tampil 'Belum diinput', bukan 0 JP.
- [ ] Cetak ke printer atau PDF, pastikan orientasi A4 landscape, kop, tabel, tanda tangan tidak terpotong.
- [ ] Bandingkan PDF dan browser dengan data sama.

## Catatan audit
- Patch ini hanya menambah/mengubah kode pelaporan. **Tidak ada migrasi atau penghapusan data**.
- Tahun pelajaran lama hanya dapat mencetak siswa yang tercatat di riwayat transaksi atau yang saat ini masih terhubung ke kelas; siswa yang sama sekali tidak memiliki transaksi dan telah pindah bisa tidak terlihat pada laporan historis.
- Rekap mingguan mengelompokkan data sesuai **tanggal Senin**; minggu lintas bulan mengikuti bulan hari Senin, sama dengan versi sebelumnya.
- Lulus lint PHP dan pemeriksaan statis. Pengujian Laravel+MySQL dengan data pengguna masih diperlukan sebelum dinyatakan final.
