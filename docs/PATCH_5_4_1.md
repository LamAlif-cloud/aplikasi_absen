LAMALIF PATCH 5.4.1 — SURAT TANPA TULISAN DRAF

Perubahan:
- Teks DRAF tidak lagi dicetak pada surat atau ditampilkan dalam PDF.
- Form menjadi "Buat surat" / "Simpan surat"; status pada riwayat memakai istilah Indonesia.
- STATUS INTERNAL (draft/issued/completed) TETAP DIPERTAHANKAN sebagai kontrol alur administrasi, sehingga riwayat tidak terganggu.
- Tambahan tes untuk memastikan tulisan D R A F tidak muncul pada halaman cetak.

TEMPAT INPUT NAMA PENANDATANGAN:
Menu "Profil Madrasah" atau alamat lokal http://127.0.0.1:8000/settings/school
  - Nama Waka Kesiswaan: surat untuk Wali Kelas & BP
  - Nama Petugas/Koordinator BP: surat ke Orang Tua/Wali
  - Nama Kepala Madrasah: pihak yang mengetahui surat ke Orang Tua/Wali
  - Logo, nama madrasah, alamat, NSM/NPSN untuk kop
Simpan Pengaturan Madrasah.

TEMPAT IMPOR BACKUP SQL:
Menu "Backup Database" => halaman "Backup & Restore Database" atau
http://127.0.0.1:8000/settings/backup
Bagian 2 "Unggah Backup SQL untuk Pemulihan" => Unggah & Periksa Backup.
Hanya admin, dan restore via web hanya di lingkungan APP_ENV=local dengan MySQL aktif.
Restore memerlukan konfirmasi lagi, dapat mengganti data dan akun. Uji dulu pada database salinan.

INSTALASI:
1. Cadangkan database dan salin folder proyek Laravel.
2. Hentikan php artisan serve (Ctrl+C).
3. Salin 3 file aplikasi dari lamalif_madrasah_laravel13 ke folder proyek yang sama, timpa berkas terkait.
   File tests opsional untuk pengujian otomatis.
4. Jangan mengganti .env, vendor, storage, composer.lock; tidak perlu migrasi DB.
5. php artisan optimize:clear
6. .\vendor\bin\phpunit.bat --display-errors
7. php artisan serve
8. Cek surat cetak/PDF dan halaman Profil Madrasah/Backup & Restore.

Paket patch ini hanya menyasar tulisan DRAF; tidak mengubah metode restore atau isi database.
