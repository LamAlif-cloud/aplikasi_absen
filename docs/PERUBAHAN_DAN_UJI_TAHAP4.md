# LamAlif Laravel Tahap 4 — Excel Sekolah, Admin, Backup

## Perubahan

1. **Satu impor Kelas + Wali Kelas** pada lembar Excel yang sama: `KELAS, WALI KELAS`. Penugasan wali kelas juga bisa diperbarui melalui menu Wali Kelas, tetapi tidak perlu impor terpisah. Berkas impor lama dengan header `class_name, homeroom_teacher` tetap diterima. Jika WALI KELAS dikosongkan pada kelas yang sudah ada, nilai lama tidak dihapus tanpa sengaja; gunakan menu Wali Kelas untuk mengosongkan penugasan.
2. **Template siswa** mengikuti berkas administrasi sekolah secara persis: `NOMOR | NIS | NISN | NAMA | JENIS KELAMIN | KELAS`. Ini juga merupakan urutan ekspor siswa. Kolom NOMOR diabaikan saat impor (nomor urut). NIS dan NISN disimpan sebagai teks; L/P, Laki-laki/Perempuan diterima. Header teknis Inggris sebelumnya tetap kompatibel.
3. **Data absensi JP mingguan** tetap menggunakan sumber tunggal `weekly_absence`, tahun pelajaran Juli–Juni dan ambang tatib semester 24/48/72 JP.
4. **Pengaturan Pengguna:** buat akun, edit username/nama/peran, aktif/nonaktif, reset password, ganti password sendiri. Akun tidak bisa dihapus dari antarmuka. Admin aktif terakhir tidak bisa dinonaktifkan, administrator tidak bisa menurunkan peran sendiri.
5. **Profil Madrasah:** identitas sekolah dan unggah logo (PNG/JPG/WebP sampai 1 MB). Kop cetak surat/laporan membaca profil tersebut.
6. **Backup Database:** unduh ZIP berisi SQL lengkap untuk *seluruh tabel aplikasi* termasuk migrasi, pengguna, kelas, siswa, input mingguan, surat, dan audit. Mengharuskan login dan memasukkan password administrator. File .env, APP_KEY, dan file logo tidak disertakan; perlu cadangan file terpisah. Tidak ada mekanisme pemulihan/reset satu klik pada Tahap 4.

## Cara pembaruan Laragon (dari Tahap 3)

1. Hentikan `php artisan serve` dengan Ctrl+C.
2. Cadangkan dulu folder aplikasi, `.env`, dan bila dibutuhkan data database melalui phpMyAdmin.
3. Ekstrak ZIP Tahap 4, lalu **salin isi folder `lamalif_madrasah_laravel13`** ke `C:\laragon\www\lamalif_madrasah_laravel13`. Jangan buat folder bersarang. **Jangan hapus atau timpa `.env`, `vendor`, `composer.lock`, database, atau folder `storage` yang sudah berisi unggahan/log.** ZIP paket tidak menyertakan `.env`, `vendor`, `composer.lock`.
4. Gunakan Terminal Laragon, jalankan **satu baris per perintah**:

   ```cmd
   cd C:\laragon\www\lamalif_madrasah_laravel13
   php artisan optimize:clear
   php artisan route:list
   php artisan storage:link
   php artisan serve
   ```

5. Buka `http://127.0.0.1:8000` dan masuk dengan akun admin yang sudah digunakan sebelumnya. **Tidak perlu migrasi, composer install, atau membuat akun baru.** Struktur database tidak berubah.
6. Uji `Impor & Ekspor Excel`: unduh template Kelas, isi dua kolom KELAS dan WALI KELAS, lalu impor. Impor siswa dengan enam kolom sesuai urutan baru. Coba ekspor dan pastikan kolom sama persis dengan template.
7. Buka `Pengaturan Pengguna` dan buat akun petugas percobaan. Coba ubah peran, aktif/nonaktif, dan password; akun admin awal harus tetap aktif.
8. Buka `Backup Database`: masukkan ulang password admin, unduh ZIP, periksa apakah berisi `lamalif_database.sql` dan `PETUNJUK_BACKUP.txt`. **Jangan impor SQL tersebut ke database aktif hanya untuk uji**; uji restore pada database *lain* bila diperlukan.

## Uji khusus untuk programmer

- Periksa akun petugas tidak memiliki akses GET `/settings/users` atau POST `/settings/backup/download`.
- Pastikan satu-satunya admin aktif tidak dapat diturunkan peran atau dinonaktifkan.
- Impor kelas + wali dan siswa harus menampilkan jumlah **baru/diperbarui/dilewati/gagal** per baris dan alasannya.
- Excel siswa dari unduhan harus bisa diimpor kembali. Pastikan data NIS/NISN dengan nol di depan benar.
- Siswa yang baru diimpor harus terlihat pada `Input Rekap Absen` dengan kelas dan tahun pelajaran yang sama; JP tercermin konsisten di rekap, surat, dan ekspor.
- Periksa ambang semester: 23 JP pemantauan, 24 JP wali kelas, 48 JP BP, 72 JP pemanggilan orang tua.
- Uji print surat setelah mengisi profil sekolah dan menjalankan `storage:link`.
- Test otomatis `php artisan test` hanya dengan konfigurasi database **testing terpisah**. Jangan menjalankan test `RefreshDatabase` pada database aktif.

## Hal belum selesai

- Reset data dengan konfirmasi berlapis, dan *restore* backup lewat panel.
- Hak akses Wali Kelas berdasarkan kelas. Peran Wali Kelas belum ditawarkan pada menu pembuatan akun sampai otorisasi per kelas aman.
- Ekspor PDF/cetak A4 landscape dengan pengujian layout panjang, logo cetak seluruh laporan dan riwayat kelas tahun sebelumnya.
- Uji Laravel di Windows/MySQL Laragon nyata, keamanan produksi, dan pemulihan backup belum dilakukan dalam lingkungan pembuat kode.

**Status: paket pembaruan untuk diuji, belum final / belum layak online.**
