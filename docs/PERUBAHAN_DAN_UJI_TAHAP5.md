# Tahap 5 — Daftar Perubahan dan Kendali Uji

## I. Fungsi yang dimasukkan / diperbaiki

1. Input Rekap Absen: tersedia **Mingguan** dan **Bulanan**; kelas dipilih berdasarkan tahun pelajaran, siswa aktif tampil dalam kedua mode.
2. Database: tabel `monthly_absence` dan validasi larangan input tumpang tindih per siswa dan bulan.
3. Laporan, Dashboard dan Surat: laporan semester/tahunan mengambil akumulasi dari **dua sumber** melalui `AbsenceEntries` dan `AbsenceReport`; status ganjil/genap terpisah, batas 24/48/72 JP.
4. Import & Export Excel: `.xlsx/.xls` (masukan) dan `.xlsx` (keluaran), kelas+wali digabung; siswa 6 kolom tetap; NIS tidak wajib; JP Mingguan/Bulanan mempunyai format tersendiri.
5. Data Siswa: NIS nullable dalam database, kolom status mondok/pondok, tanggal dan catatan status.
6. Kenaikan kelas/pelulusan/mutasi massal: memilih siswa, memindah kelas ke tahun berikutnya atau mengubah status menjadi tidak aktif; menyimpan riwayat tanpa menimpa transaksi lama.
7. Riwayat per siswa: riwayat kelas, status, absensi, surat, pembinaan.
8. Pembinaan: petugas admin/BP menyimpan status proses pada ambang 3–6–9 hari.
9. Surat: alur draf → terbit → selesai dengan audit log; mencetak tetap memerlukan keputusan petugas.
10. Reset data uji: **admin saja**, wajib password, frasa konfirmasi, pengakuan telah menguji backup, dan konfirmasi akhir. Hapus absensi/surat saja atau semua data siswa & kelas. Akun, konfigurasi, migrasi dan audit log dipertahankan.

## II. Pemasangan

```
cd C:\laragon\www\lamalif_madrasah_laravel13
php artisan optimize:clear
php artisan migrate
php artisan test
php artisan serve
```

Jangan menyatukan baris perintah. Jika `php artisan migrate` gagal, hentikan dan kirim **seluruh teks error** (jangan reset database). Pastikan backup ada sebelum mencoba migrasi.

## III. Pengujian wajib di Laragon sebelum data asli

- [ ] Masuk admin lama; Data Kelas, Data Siswa, Wali Kelas, Edit dan Hapus aman.
- [ ] Buat kelas VII A dan satu siswa **tanpa NIS** dengan NISN; tampil dalam Input Mingguan dan Bulanan.
- [ ] Unduh template Siswa: NOMOR, NIS, NISN, NAMA, JENIS KELAMIN, KELAS. Impor dengan NIS kosong; lihat jumlah sukses/gagal dan alasan per baris.
- [ ] Unduh template Kelas + Wali: satu file dua kolom, nama wali tampil di Data Kelas dan Wali Kelas.
- [ ] Input bulanan Oktober 2026 untuk satu siswa: Alpa 24 JP. Status Semester Ganjil menjadi **Peringatan Wali Kelas**.
- [ ] Coba input Mingguan Oktober 2026 untuk siswa yang sama: **ditolak** tanpa mengubah total.
- [ ] Input Mingguan November 2026 untuk siswa tadi: Alpa 24 JP; total semester 48 JP → **Penanganan BP**.
- [ ] Input bulanan Desember 2026 Alpa 24 JP; total semester 72 JP → **Pemanggilan Orang Tua**. Periksa Surat dan Dashboard.
- [ ] Input bulanan Februari 2027 Alpa 24 JP; Semester Genap berstatus **Peringatan Wali Kelas**, tidak meneruskan 72 JP semester Ganjil.
- [ ] Ekspor `.xlsx` kelas, siswa, minggu/bulan, rekap semester/tahunan; bandingkan angka pada layar.
- [ ] Catat pembinaan tahap 1, 2, 3; terbitkan draf surat, tandai terbit dan selesai; periksa di Riwayat Siswa.
- [ ] Buat kelas VIII A 2027/2028, naikkan siswa dari VII A 2026/2027. Input periode lama **tetap mengikuti kelas VII A**; kelas baru memuat siswa aktif tersebut.
- [ ] Luluskan siswa pada tahun yang sesuai; siswa hilang dari input rutin, tetapi tetap terlihat di Riwayat dan laporan lama.
- [ ] Jalankan `php artisan test` dan pastikan tidak ada test gagal.
- [ ] Buat ZIP backup; **pulihkan ke database uji terpisah** sebelum memakai tombol Reset. Uji login akun admin setelah reset (hanya terhadap data percobaan).

## IV. Aturan dan batasan penting

- Saat pergantian bulan dan input mingguan, minggu dikelompokkan berdasarkan tanggal **Senin** dalam `week_start`. Jika kebijakan madrasah membutuhkan pemecahan minggu lintas bulan, diperlukan model input tambahan (jangan otomatis menebak tanggal).
- Input bulanan tidak dapat dicampur dengan mingguan untuk siswa yang sama dalam bulan yang sama. Belum tersedia tombol konversi yang aman dari mingguan menjadi bulanan untuk bulan yang sudah terisi.
- Dari kode ini saja belum bisa mengonfirmasi semua alur MySQL berjalan di komputer operator. PHPUnit dan pemeriksaan browser perlu dijalankan sendiri di Laragon.
- Backup memuat informasi sensitif. Jangan unggah ke publik. `.env`, `APP_KEY`, dan aset gambar belum tercakup; buat cadangan terpisah.
- Reset belum memiliki fitur restore melalui UI. Pemulihan hanya lewat administrasi database terpisah.
- Hak akses `wali_kelas` berbasis pemetaan pengguna-ke-kelas belum tuntas. Jangan deploy untuk penggunaan banyak akun secara online sebelum hak akses dan keamanan diuji.

## V. Lampiran status pemeriksaan pengembang

**Sudah diperiksa di lingkungan pembuatan paket:** lint seluruh file PHP, isi arsip ZIP, rute yang dirujuk view, keberadaan file Blade dan controller, referensi migrasi dan sumber agregasi JP.

**Belum diperiksa secara langsung:** menjalankan Composer dan PHPUnit pada Laravel lengkap, MySQL 8.4 Laragon, pengujian integrasi melalui browser atau pemulihan backup; jika ada masalah, kirim teks error yang lengkap.
