# Daftar masalah impor

Menu administrator **Masalah Impor** menyimpan baris Excel yang gagal, nama file,
nomor baris, tahun pelajaran, satuan dan alasan kegagalan. Tombol **Periksa & Edit**
membuka nilai sumber untuk diperbaiki. Penyimpanan ulang menggunakan pemeriksaan
impor yang sama dan memperbarui data utama secara atomik bersama status selesai
dan audit log. Daftar selesai tersedia melalui tautan **Sudah diperbaiki**.

Jalankan `php artisan migrate` sebelum menggunakan fitur. Riwayat kegagalan sebelum
migrasi tidak memiliki isi baris lengkap; unggah ulang file sumber untuk mencatatnya.
Baris yang masih gagal tidak mengganti data sebelumnya. Satuan harus sesuai file;
batas mingguan/bulanan dan larangan tumpang tindih tetap berlaku.

Reset data uji juga membersihkan daftar masalah agar data yang sudah direset tidak
dimasukkan kembali secara tidak sengaja. Backup database mencakup tabel baru.

Pengujian mencakup kegagalan tersimpan, tampilan daftar dan edit, penolakan angka
di luar batas, penyimpanan perbaikan, larangan menyimpan ulang masalah selesai,
dan pembatasan akses administrator.
