# Aplikasi Absensi Siswa — Laravel

## Koreksi absensi dan petugas laporan

- Rekap, cetak/PDF, dan Excel menyediakan Sakit, Izin, serta Alpa dalam hari dan sisa jam (8 JP = 1 hari).
- **Filter Tindakan** tersedia di Rekap & Laporan dan Pengaturan Cetak: Semua, Semua yang perlu tindakan (>=24 JP), Wali Kelas (24–47 JP), BP (48–71 JP), atau Pemanggilan Orang Tua (>=72 JP). Filter ikut dibawa ke cetak, PDF, dan Excel; total hanya menghitung siswa yang lolos filter.
- Untuk mingguan/bulanan, tindakan memakai Alpa semester sampai periode terpilih, termasuk siswa dengan catatan pada bulan sebelumnya tetapi belum ada input periode ini. Nilai periode yang belum diinput ditandai, bukan dianggap 0. Pada laporan tahunan, filter diperiksa per semester, tidak menjumlahkan Alpa dua semester untuk menentukan tindakan.

- Menu **Edit Absen** berada setelah Input Absen Bulanan di sidebar, dengan tautan tambahan dari halaman input dan Rekap & Laporan.
- Admin/operator dapat mengoreksi JP dan catatan mingguan, bulanan, atau saldo awal, termasuk catatan siswa yang sudah tidak aktif atau pindah kelas. Identitas, kelas, dan periode asli tidak diubah.
- Alasan koreksi wajib diisi. Angka sebelum/sesudah, akun, dan waktu dicatat di audit; perubahan bersamaan ditolak agar koreksi operator lain tidak tertimpa. Periksa dan cetak ulang laporan setelah koreksi.
- Di **Profil Madrasah**, isi Nama Petugas Input Siswa Putra dan/atau Putri. Cetak/PDF memakai nama sesuai siswa pada kelas laporan. Kelas campuran menampilkan kedua petugas; jika hanya satu nama diisi, nama itu digunakan untuk semua siswa. Jika keduanya kosong, dipakai nama akun yang mencetak.
- Pengaturan petugas memakai tabel `school_settings` yang sudah tersedia; tidak memerlukan migrasi tambahan.

**Aplikasi Laravel 13 + PHP 8.3+ + MySQL 8.4 + PhpSpreadsheet.**

Paket ini melanjutkan **Tahap 4**, menambahkan input JP bulanan, NIS opsional, kenaikan kelas/kelulusan/mutasi massal, pondok, riwayat siswa, pembinaan, alur status surat, dan reset data uji terlindungi.

**PENTING:** Aplikasi masih tahap uji lokal. PHP lint dan pemeriksaan rute/berkas sudah berhasil, tetapi tes PHPUnit dan pengujian MySQL Laragon harus dijalankan pada komputer yang memiliki dependensi Composer; jangan gunakan untuk data riil atau produksi online sebelum pengujian selesai.

## Memasang pada Laragon Windows

1. **Cadangkan dahulu:** backup database (menu Backup Database) dan folder `C:\laragon\www\lamalif_madrasah_laravel13` yang sekarang. Pastikan ZIP backup berhasil diunduh. Simpan `.env`, `storage` (termasuk logo), `vendor` dan `composer.lock` lama.
2. Hentikan terminal `php artisan serve` dengan **Ctrl+C**.
3. Ekstrak ZIP Tahap 5 ke `C:\laragon\www\` sehingga file `artisan` berada di `C:\laragon\www\lamalif_madrasah_laravel13\artisan`. Timpa **kode aplikasi saja**, jangan hapus `.env`, `vendor`, `composer.lock` dan `storage` dari instalasi aktif. **Jangan mengganti seluruh folder aktif jika belum menyelamatkan file-file tersebut.**
4. Buka **Terminal Laragon** dan jalankan *satu perintah per baris*:

   ```cmd
   cd C:\laragon\www\lamalif_madrasah_laravel13
   php artisan optimize:clear
   php artisan migrate
   php artisan test
   php artisan serve
   ```

5. Akses `http://127.0.0.1:8000`, login dengan akun admin yang sudah ada. **Tidak perlu mengulang `madrasah:admin` atau `migrate:fresh`.**

**Catatan migrasi:** `php artisan migrate` WAJIB pada Tahap 5 karena ada perubahan kolom NIS dan tiga tabel baru: `monthly_absence`, `student_status_history`, `disciplinary_actions` (migrasi juga menambahkan kolom informasi pondok/status siswa). **Jangan gunakan `migrate:fresh` karena menghapus tabel.**

## Akun login melalui seeder

Jalankan `php artisan db:seed` untuk membuat akun admin dengan username
`admin@gmail.com` dan password `passsword`. Login menggunakan kolom Username.
Seeder menggunakan kolom `full_name` dan `username` sesuai skema aplikasi,
bukan kolom bawaan Laravel `name` dan `email`.
Menjalankan ulang seeder memperbarui akun tersebut dan mengatur ulang passwordnya,
tanpa membuat akun duplikat. Tidak perlu menjalankan `migrate:fresh`.

## Aturan Input & Perhitungan

- **Mingguan:** input A/I/S berupa total JP per siswa untuk minggu yang dimulai hari Senin.
- **Bulanan:** input A/I/S berupa total JP satu bulan (format YYYY-MM), sekali input per siswa.
- **Siswa dan bulan yang sama tidak boleh memiliki input mingguan sekaligus bulanan.** Aplikasi menolak penyimpanan konflik dan memperlihatkan peringatan. Nilai JP **tidak diakumulasi dua kali**.
- **Rekap bulanan** menggabungkan input bulanan dan input minggu yang Senin-nya di bulan tersebut. Data minggu menggunakan tanggal Senin sebagai penentu bulan.
- **Rekap mingguan** menampilkan input mingguan saja; hasil bulanan dapat dilihat pada Rekap Bulanan.
- **Rekap semester/tahunan dan Surat** menghitung JP dari kedua metode melalui satu layanan agregasi. Konversi **8 JP = 1 hari**, tata tertib **24/48/72 JP Alpa per semester**.
- Setelah input mingguan/bulanan untuk suatu bulan, **belum ada konversi otomatis metode input bulan yang sudah terisi**. Koreksi data yang tersimpan dilakukan lewat metode input semula. Untuk mengganti metode pada bulan terisi, perlu alur migrasi data khusus di versi lanjutan; jangan menghapus tabel langsung.

## Identitas siswa tanpa NIS

- Kolom **NIS** tersedia dalam Excel tetapi **boleh dikosongkan**. Kolom **NISN** juga boleh kosong, tetapi identitas yang tidak unik bisa menyebabkan impor ditolak (nomor baris dan alasan ditampilkan).
- Pencocokan saat impor: NIS → NISN → nama+jenis kelamin+kelas (hanya bila tidak ambigu). Dua entri dengan identitas identik tanpa NIS/NISN akan dilaporkan bermasalah, bukan sengaja dibuat duplikat.
- Impor/ekspor Excel siswa mengikuti format **NOMOR, NIS, NISN, NAMA, JENIS KELAMIN, KELAS**. **KELAS dan WALI KELAS** berada dalam satu template.
- Impor/ekspor absensi Excel menyediakan kolom NIS, NISN, NAMA untuk mengenali siswa ketika NIS tidak ada. Semua impor/ekspor operasional adalah Excel (`.xlsx`, impor juga menerima `.xls`), **bukan CSV**.

## Kenaikan kelas, kelulusan, mutasi

- Admin membuat kelas tujuan pada tahun pelajaran berikutnya dan memilih siswa untuk dipindah sekaligus.
- Perpindahan **hanya mengubah kelas siswa saat ini**; baris absensi dan kelas pada periode sebelumnya tetap mengikuti kelas asal.
- Status Lulus, Mutasi atau Berhenti membuat siswa tidak aktif untuk input berikutnya tanpa menghapus riwayat.
- Menu **Riwayat Siswa** menampilkan periode kelas, absensi, status dan surat.

## Status fitur

| Modul | Ada kode | Pengujian Laravel/MySQL nyata |
|---|---|---|
| Login, pengguna, profil, logo | Ya (Tahap 4) | Belum pada paket ini |
| Input mingguan dan bulanan, cegah data ganda | Ya | Belum |
| Impor/ekspor Excel dan NIS opsional | Ya | Belum |
| Data siswa, kelas, wali, mondok, CRUD | Ya | Belum |
| Kenaikan kelas, kelulusan, mutasi, riwayat | Ya | Belum |
| Rekap dan peringatan semester | Ya | Belum |
| Surat dan status penanganan BP | Ya | Belum |
| Backup dan reset dua tingkat | Ya | Pemulihan backup belum diverifikasi |
| Restore database otomatis | **Belum ada** | — |
| Akses Wali Kelas dibatasi hanya pada kelas sendiri | **Belum lengkap** | — |
| Audit keamanan siap online | **Belum selesai** | — |

Baca [`docs/PERUBAHAN_DAN_UJI_TAHAP5.md`](docs/PERUBAHAN_DAN_UJI_TAHAP5.md) untuk skenario uji dan tindakan bila muncul error.
