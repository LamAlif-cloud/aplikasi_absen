# Checklist pengujian setelah memasang di Laragon

1. Pastikan tidak menghapus `.env`, `vendor` dan akun administrator.
2. `composer update phpoffice/phpspreadsheet --with-all-dependencies` dan `php artisan optimize:clear`.
3. Cek `php artisan route:list`, `php artisan test`.
4. Di Impor & Ekspor Excel, unduh template Kelas. Isi VII A dan wali kelas, hapus baris contoh. Impor. Hasil: baru 1.
5. Unduh template Siswa, isi NIS bertipe teks `00123`, nama, L/P, VII A, NISN. Impor: baru 1, NIS tetap `00123`.
6. Impor siswa dengan kelas VII Z: gagal dan alasan kelas tidak ditemukan; data berhasil lain tidak dibatalkan.
7. Uji impor ulang dengan opsi **Lewati** dan **Perbarui**. Periksa angka summary.
8. Input absensi Senin pada kelas VII A: Alpa 23 JP. Status semester: pemantauan. Koreksi input minggu yang sama menjadi 24 JP: tidak menggandakan.
9. Nilai 24 JP -> peringatan wali kelas; 47 JP -> wali kelas; 48 JP -> BP; 71 JP -> BP; 72/80 JP -> panggil orang tua.
10. Impor JP mingguan menggunakan template, kemudian periksa hasilnya di Input Absen, Rekap, Dashboard dan Surat.
11. Mingguan: agregasi JP hanya pada satu tanggal Senin. Bulanan: tanggal Senin yang jatuh pada bulan tersebut. Tata tertib kedua laporan wajib memakai akumulasi semester sampai periode yang dipilih.
12. Semester Ganjil: Juli–Desember, semester Genap: Januari–Juni tahun berikutnya. Tidak boleh tercampur.
13. Tahunan: penjumlahan JP Ganjil+Genap, tetapi kolom status tatib menampilkan **dua status terpisah**.
14. Pada Rekap tampilkan satu kelas, ekspor Excel, cocokkan baris jumlah S/I/A dan nama siswa dengan halaman cetak.
15. Coba mengekspor dan mengimpor kembali hasil ekspor Excel untuk Kelas/Siswa/JP (tanpa duplikasi).
16. Kelas bernama sama pada tahun berbeda tidak boleh bercampur di dropdown atau hasil laporan.
17. Login role petugas tidak boleh masuk impor data; admin boleh. Surat hanya admin/BP.
18. Ukur kegagalan akses online, unggahan berkas tak valid, percobaan formula Excel, file terlalu besar, dan akses tanpa login.

**Catatan:** pengujian PHP sintaks tanpa dependensi/MySQL tidak menjamin halaman berjalan. Laporkan error beserta alamat menu dan teks errornya.
