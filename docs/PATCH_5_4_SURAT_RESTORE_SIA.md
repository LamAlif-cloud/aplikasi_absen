# LamAlif Patch 5.4 — Surat resmi, Restore lokal, dan kolom S–I–A

## Apa yang diperbarui

1. **Backup & Restore Database** (hanya MySQL di Laragon lokal): dua tahap, Unggah & Periksa kemudian Konfirmasi Restore. Parser hanya menerima backup LamAlif sesuai struktur database aktif; tidak menjalankan SQL unggahan sebagai perintah bebas. Meminta password admin, konfirmasi, dan membuat backup otomatis sebelum pemulihan. Restore mengganti seluruh isi data pada tabel yang sama (termasuk pengguna) dan akan mengeluarkan pengguna dari sesi login setelah berhasil.
2. **Input Absensi**: urutan kolom **Sakit–Izin–Alpa (S–I–A)** pada input mingguan/bulanan dan template/ekspor Excel (kode internal kolom database tetap `sakit_jp`, `izin_jp`, `alpa_jp`; angka tidak diubah).
3. **Surat Tata Tertib**: tiga tujuan dan penandatangan: (1) Wali Kelas — Waka Kesiswaan; (2) BP — Waka Kesiswaan; (3) wali murid — petugas BP dan diketahui Kepala Madrasah. Surat menampilkan nama siswa, NIS jika ada, kelas riwayat periode surat, S/I/A semester (JP), Alpa dalam hari (8 JP/hari), nomor/tanggal, tanggal pertemuan bila diisi, kop/logo NSM NPSN alamat telepon Profil Madrasah.
4. **Cetak/PDF**: tombol **Cetak/Print** membuka halaman HTML siap cetak A4; **Unduh PDF** memakai `dompdf/dompdf` dan teks/kop/signature yang sama. Surat berstatus draft menampilkan tanda DRAF.
5. **Profil Madrasah**: dua isian baru, Waka Kesiswaan dan Petugas/Koordinator BP, tanpa menambah tabel atau menghapus data.

## Cara memasang di Windows Laragon

1. Sebelum memasang: unduh backup ZIP dari LamAlif (jika tersedia) dan **juga** salin proyek sekarang sebagai cadangan. Jangan menguji restore memakai database utama terlebih dahulu.
2. Hentikan server `php artisan serve` dengan `Ctrl+C`.
3. Ekstrak arsip Patch 5.4 dan salin isi folder `lamalif_madrasah_laravel13` ke `C:\laragon\www\lamalif_madrasah_laravel13`, timpa file yang sama. **Jangan hapus `.env`, `vendor`, `composer.lock`, atau `storage`**.
4. Dari Terminal Laragon di folder proyek, jalankan (satu-satu):

   ```cmd
   cd C:\laragon\www\lamalif_madrasah_laravel13
   composer require dompdf/dompdf
   php artisan optimize:clear
   .\vendor\bin\phpunit.bat --display-errors
   php artisan serve
   ```

   Perintah `composer require` harus dilakukan **di dalam folder proyek**, bukan `C:\laragon\www`.

5. Di browser, buka `http://127.0.0.1:8000`. Sebelum membuat surat, isi Profil Madrasah: **nama Waka Kesiswaan, nama petugas BP, dan Kepala Madrasah**, serta logo dan identitas sekolah. Jika penandatangan kosong, surat menggunakan garis kosong dan perlu dilengkapi dulu sebelum ditandatangani.
6. Di Surat Tata Tertib, pilih semester/tahun, buat draf, lalu uji **Cetak/Print** dan **Unduh PDF**. Surat baru dapat dibuat untuk tingkat yang ambangnya sudah dicapai.

## Ketentuan cetak & data

- Level 1: Alpa >=24 JP semester; surat kepada Wali Kelas, dari Waka Kesiswaan.
- Level 2: Alpa >=48 JP semester; surat kepada BP, dari Waka Kesiswaan.
- Level 3: Alpa >=72 JP semester; surat kepada orang tua/wali, dari BP, diketahui Kepala Madrasah.
- S dan I tetap ditampilkan sebagai rekap, **tidak** digunakan dalam ambang sanksi. Jumlah JP adalah hasil **data yang tersimpan pada saat cetak**, bukan angka dibekukan dalam arsip surat; jika catatan absensi dikoreksi kemudian, PDF yang dicetak ulang ikut menampilkan rekap terbaru.
- Surat draf tidak otomatis terbit; status diubah oleh petugas melalui menu Surat.
- Restore SQL **tidak** sama dengan Impor Excel. Hanya backup LamAlif dengan **struktur tabel identik** yang dapat dipulihkan. Tidak mendukung SQL bebas atau backup PHP Native yang lama. **Restore via web dimatikan saat APP_ENV bukan local**. Backup tidak mencakup `.env` dan berkas logo; cadangkan keduanya secara terpisah.
- Sebelum melakukan restore terhadap data penting, uji pemulihan pada **salinan database terpisah**. Jangan menjalankan fitur restore saat pengguna lain sedang memakai aplikasi. Jika restore berhasil, akun dan password mengikuti isi backup.
- Tidak diperlukan `php artisan migrate` untuk pembaruan ini. Jangan menggunakan `migrate:fresh`, reset, atau impor ulang data siswa.

## Pengujian minimal

- Data siswa, kelas, dan wali tetap sama sebelum/sesudah kode di-copy.
- Input mingguan dan bulanan berurutan **S–I–A** dan Excel tetap sesuai.
- 24 JP -> surat ke Wali Kelas dari Waka; 48 JP -> surat BP dari Waka; 72 JP -> orang tua dari BP mengetahui Kepala.
- PDF memuat nama siswa, kelas, rekap semester, logo (jika ada), penandatangan dan tanggal yang sesuai.
- PHPUnit + pengujian browser dan MySQL Laragon harus dilakukan; lint PHP saja tidak membuktikan fitur bekerja.

