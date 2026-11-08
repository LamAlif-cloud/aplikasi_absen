# Audit & Perbaikan Saldo Awal Semester

**Lingkup:** 149 rekap hasil Excel enam kelas Juli–Oktober 2026.

- Total sumber: S=4.786 JP, I=830 JP, A=2.736 JP.
- Total SQL sebelum koreksi: S=663 JP, I=291 JP, A=2.043 JP.
- 121 siswa berbeda nilainya; 10 siswa dengan status tata tertib terlampau ringan.
- Rekap sumber periode Juli–Oktober disimpan sebagai **Saldo Awal Ganjil per 31-10-2026**, bukan input bulanan Oktober biasa.
- Input minggu/bulan normal mulai November 2026, dan mulai Juli pada setiap tahun pelajaran baru.

## Pengamanan data

Proses `madrasah:saldo-awal-audit` beroperasi dalam mode pemeriksaan/read-only secara default. Penerapan koreksi membutuhkan parameter `--apply --confirm=PINDAHKAN_SALDO_149 --backup-verified`, koneksi MySQL lokal, dan seluruh isi data aktif harus sama dengan snapshot backup yang diaudit. Satu perubahan angka atau identitas membuat seluruh operasi dibatalkan, tanpa menimpa transaksi yang mungkin sudah disunting petugas.

Patch tidak mengubah data pengguna, kelas, wali kelas, pondok, maupun catatan status siswa, dan tidak menghapus surat tersimpan. Surat yang telah dibuat tetapi tidak memenuhi ambang sekarang **diblokir dari cetak, PDF, dan penerbitan**.

## Aturan tata tertib

- 24 JP Alpa semester: peringatan wali kelas
- 48 JP: penanganan BP
- 72 JP: pemanggilan orang tua

Angka dihitung langsung dari JP bulat, bukan dari angka hari Excel yang dibulatkan menjadi dua desimal.

## Kejelasan laporan

Periode Ganjil/Tahunan memuat Saldo Awal + transaksi rutin. Laporan Bulanan Oktober 2026 yang mengandung Saldo Awal diberi peringatan karena rekap yang dimasukkan tersebut mencakup Juli–Oktober. Periode mingguan Oktober tidak menerima saldo awal yang tidak memiliki rincian minggu. Bulan Juli–September tidak direkayasa seolah memiliki transaksi rinci.

## Uji yang harus lulus di Laragon

`php artisan migrate`, `php artisan madrasah:saldo-awal-audit` (harus 149 cocok), `phpunit`, kemudian setelah admin menyetujui penerapan: audit status 149 siswa, cetak PDF Wali Kelas, ekspor Excel, penguncian bulan Oktober, input minggu pertama November, dan input tahun pelajaran 2027/2028 pada Juli.

## Perubahan keamanan import/ekspor

Impor Excel kini hanya menerima mode **JP** atau **Hari** yang dinyatakan jelas. Mode Campuran pada form dinonaktifkan karena besaran bulat seperti `12,00` pada JUMLAH TOTAL bisa berarti 12 hari (96 JP), bukan 12 JP. Nilai Saldo Awal juga bisa melampaui batas JP bulanan normal, sehingga tidak boleh diproses sebagai satu bulan biasa.

Ada tombol **Ekspor Saldo Awal .xlsx** pada Rekap & Laporan. Ekspor transaksi bulanan reguler tetap hanya memuat input bulanan sungguhan, tidak mengekspor Saldo Awal sebagai transaksi Oktober normal.
