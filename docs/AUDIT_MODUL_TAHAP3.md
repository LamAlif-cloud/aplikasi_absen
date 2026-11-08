# Audit fungsional LamAlif Laravel — Tahap 3

**Tanggal review:** 20 Oktober 2026. **Lingkup sumber:** kode Tahap 2 yang telah diunggah dalam percakapan dan dikembangkan menjadi Tahap 3. **Bukan pengujian pada komputer pengguna.**

## Temuan dari pembacaan kode

| Modul | Temuan | Perbaikan Tahap 3 | Harus diverifikasi di Laragon |
|---|---|---|---|
| Login / logout | Pengaman CSRF, `Auth::attempt`, regenerasi sesi, throttle login | Dipertahankan | Login admin, logout, salah password, expired session |
| Data Kelas | Hanya tambah dan lihat; tak ada edit/hapus | Edit kelas dan wali, hapus kelas kosong dengan proteksi transaksi dan riwayat | Tambah, ganti nama kelas, duplikat, hapus terpakai/masih kosong |
| Wali Kelas | Hanya teks di tabel kelas; tak punya halaman kelola | Menu Wali Kelas, simpan/edit penugasan, hapus *penugasan* tanpa menghapus kelas | Edit/hapus wali dan impor/ekspor Excel |
| Data Siswa | Hanya tambah dan lihat; tak ada edit/hapus | Edit NIS, NISN, nama, jenis kelamin, kelas, status; riwayat mutasi; hapus siswa tanpa transaksi | Tambah siswa 1 orang, edit, nonaktif, hapus terlindungi |
| Input JP Mingguan | Query siswa aktif ditapis `class_id`; tahun/kelas salah menjadi daftar kosong; kelas pertama bisa tidak berisi siswa | Memprioritaskan kelas dengan siswa, penjelasan diagnostik, tautan langsung dari daftar siswa, tanggal diselaraskan tahun | Klik Tampilkan setelah tambah siswa; ganti tahun; simpan JP, ubah JP |
| Perpindahan kelas | Tabel riwayat tersedia tetapi belum dipakai di form | Edit siswa mencatat old/new class & tahun pelajaran; data JP lalu tetap pada kelas historis | Pindah kelas dan cek laporan historis |
| Rekap minggu/bulan/semester/tahun | Berbasis agregasi `AbsenceReport`, laporan tahun memisahkan semester | Penyesuaian pilihan periode dan tanggal untuk tahun yang bukan tahun aktif | Cocokkan Alpa 24/48/72 JP lintas menu & ekspor Excel |
| Dashboard | Sumber JP mingguan, ringkasan siswa aktif awalnya lintas tahun | Siswa aktif dihitung hanya di tahun saat ini | Jumlah siswa dan kelas konsisten dengan filter |
| Surat & tindak lanjut | Draf surat setelah ambang 24/48/72 JP, riwayat draf/cetak | Tidak ada perubahan besar | Terbit draf dan periksa level, nomor, cetakan |
| Excel impor | Sudah menerima `.xlsx/.xls`, per baris ada hasil dan alasan gagal | Dipertahankan; pemilihan tahun dasar diselaraskan | Impor kelas/siswa/JP, alasan gagal, duplikat, data 0 di depan NIS |
| Excel ekspor/template | Sudah memakai `.xlsx`, bukan CSV | Dipertahankan | Unduh template, isi, impor ulang, ekspor dan bandingkan |
| Hak akses | Admin/petugas/BP/kepala sebagian tersedia; penugasan akses khusus wali kelas belum dipetakan | Proteksi modifikasi master admin dipertahankan | Uji 403 bagi petugas, lanjutkan kebijakan wali_kelas |
| Pengaturan/Backup/Reset | Belum lengkap dari Tahap 2 | Belum ditambahkan pada Tahap 3 | Masuk pekerjaan berikutnya setelah alur inti stabil |

## Sumber data baku dan aturan

- Sumber digital resmi: `weekly_absence` (input JP per minggu), **bukan** `attendance` harian dari proyek lama.
- `8 JP = 1 hari` untuk konversi tampilan. Pemicu sanksi hanya jumlah **Alpa** per semester.
- `24 JP` Wali Kelas, `48 JP` BP, `72 JP` Pemanggilan Orang Tua.
- Tahun pelajaran Juli–Juni; semester Ganjil Juli–Desember dan Genap Januari–Juni.
- Hapus kelas/siswa secara permanen **ditolak** jika sudah memiliki data transaksi/riwayat; nonaktifkan siswa jika perlu.

## Skenario uji minimum di komputer pengguna

1. Kelas 2026/2027: buat `VII A`; buat siswa NIS `TEST-001` status **Aktif**. Klik link **Input JP kelas ini** pada baris siswa. Pilih Senin, misalnya 2026-10-12. Harus muncul satu baris siswa.
2. Simpan Alpa `24 JP`, Izin `0`, Sakit `0`; muat ulang Input Mingguan. Angka `24` harus bertahan. Pada Rekap Ganjil pilih 2026/2027: **Peringatan Wali Kelas**.
3. Ubah `24` ke `48` dan kemudian `72` pada minggu yang sama (data pengujian): laporan dan Surat Tata Tertib masing-masing menampilkan Penanganan BP dan Pemanggilan Orang Tua.
4. Data Kelas → Edit wali / nama kelas; pastikan Data Siswa, dropdown Input, dan rekap ikut menampilkan nama baru. Coba hapus kelas: harus **ditolak** jika ada siswa atau JP.
5. Data Siswa → Edit NIS/Nama/Status; ubah `active` tidak aktif. Data Siswa tetap mencantumkan siswa, input JP tidak mencantumkan, laporan lama tetap mempunyai riwayat.
6. Data Wali Kelas → Ubah nama, lalu Hapus Wali: nama menjadi kosong tetapi kelas dan siswa tidak hilang.
7. Impor Excel dengan baris siswa pada kelas yang tidak ada dan NIS ganda; cek jumlah dan alasan kesalahan. Impor Excel sah, unduh laporan XLSX dan cocokkan.
8. Ganti tahun pelajaran: dropdown kelas mengikuti tahun; minggu otomatis berada dalam tahun yang dipilih, bukan tanggal tahun lain.
9. Dashboard, Rekap, Surat dan Excel mengacu data JP yang sama; tidak menjumlahkan sakit dan izin untuk pemicu surat.
10. Coba login sebagai **petugas** (jika akunnya tersedia): rute Edit/Hapus data induk harus memberi **403**.

### Pengujian otomatis Laravel pada Laragon

Jalankan perintah dari direktori aplikasi **`C:\laragon\www\lamalif_madrasah_laravel13`** (bukan `C:\laragon\www`):

```cmd
php artisan optimize:clear
php artisan route:list
php artisan test
```

`php artisan test` membutuhkan konfigurasi database pengujian yang terpisah. Pengujian menggunakan `RefreshDatabase`, jadi **jangan diarahkan ke database operasional**. Lakukan salinan cadangan dan gunakan koneksi pengujian SQLite khusus; pastikan `pdo_sqlite` aktif jika menggunakannya.

## Batasan yang belum diselesaikan

- **Belum ada pengujian aktual dengan vendor Laravel dan database MySQL Laragon pengguna**. Lint PHP, pemeriksaan rute statis, dan tes logika JP tidak setara dengan uji alur browser.
- Penugasan role `wali_kelas` ke kelas tertentu, kontrol per kelas, pengaturan pengguna, backup/reset penuh, kop/logo, surat lanjutan, dan mutasi massal tahun pelajaran masih membutuhkan pengembangan.
- Catatan JP siswa pindah kelas sengaja tetap pada kelas lama. Untuk mengubah/menyesuaikan entri historis setelah mutasi diperlukan alur admin khusus; sistem melindungi agar data lama tidak diam-diam berpindah.
- Laporan mungkin hanya menampilkan siswa yang sudah memiliki **catatan input JP**, bukan seluruh siswa tanpa pencatatan. Ini **bukan** bukti semua siswa yang tak muncul hadir.
