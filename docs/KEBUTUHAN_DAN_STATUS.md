# Kebutuhan LamAlif — hasil penyelarasan permintaan dari akun sebelumnya

**Ini bukan daftar fitur yang semuanya sudah selesai.** Centang hanya untuk yang memiliki kode pada paket ini; verifikasi browser/MySQL Laragon masih harus dilakukan.

## Ketentuan yang wajib dipertahankan

- [x] Sumber absensi resmi digital: input **JP per minggu**, petugas input seminggu sekali; hard copy harian di luar sistem.
- [x] Klasifikasi Sakit, Izin, Alpa; **8 JP = 1 hari**.
- [x] Tata tertib **Alpa kumulatif PER SEMESTER**: **24 JP peringatan Wali Kelas, 48 JP BP, 72 JP pemanggilan orang tua**. Sakit dan Izin **bukan** pemicu sanksi.
- [x] Tahun pelajaran Juli–Juni, semester ganjil Juli–Desember, genap Januari–Juni. Minggu bertumpu tanggal Senin.
- [x] Database JP mingguan merupakan sumber perhitungan rekap, dashboard pemantauan, surat, dan ekspor; absensi harian tidak dijumlahkan lagi.
- [x] Rekap mingguan/bulanan/ganjil/genap/tahunan memiliki sumber agregasi sama. Mingguan/bulanan menampilkan status berdasarkan akumulasi semester hingga periode yang dipilih; tahunan menunjukkan status **masing-masing semester**, bukan sanksi atas jumlah tahunan.
- [x] Template/impor/ekspor operasional Excel, **bukan CSV**: Kelas + Wali Kelas satu file, Siswa enam kolom (NOMOR/NIS/NISN/NAMA/JENIS KELAMIN/KELAS), JP mingguan, semua periode laporan.
- [x] Impor: jumlah baru, diperbarui, dilewati, gagal; 100 alasan baris gagal pertama; dapat memilih skip atau update; tidak diam-diam memindahkan kelas siswa.
- [x] Dropdown kelas tersaring tahun pelajaran pada input, siswa dan laporan.
- [x] Dashboard menampilkan nama, kelas, dan JP siswa yang tidak hadir pada minggu berjalan/sebelumnya, bukan data harian yang tidak tersedia.
- [x] Surat tata tertib: draf berdasarkan pencapaian ambang; petugas menerbitkan secara sadar.

## Dari seluruh percakapan awal yang MASIH BELUM LENGKAP

- [x] Kode profil madrasah, NPSN/NSM, alamat, kepala, unggah logo; kop dasar laporan/surat (belum diuji di Laragon).
- [x] Kode pengaturan pengguna: tambah/edit akun, aktif/nonaktif, ganti password, perlindungan admin terakhir.
- [ ] Hak akses wali kelas terikat kelasnya (belum tersedia).
- [x] Kode unduh backup lengkap MySQL (tabel + data) dengan autentikasi ulang admin; tidak mencakup .env dan unggahan logo.
- [ ] Reset data operasional aman dan restore backup dengan konfirmasi berlapis; akun dan profil tetap.
- [ ] Impor mutasi siswa/kenaikan kelas beserta riwayat penempatan yang konsisten.
- [ ] Status tindak lanjut surat (diterbitkan, selesai, tanggal tindak lanjut, berkas); saat ini baru draf/cetak dasar.
- [ ] Cetak dan ekspor laporan administrasi A4 landscape satu tabel, kop sekolah, pengulangan header dan pemenggalan halaman sesuai kebutuhan.
- [ ] Ekspor laporan administrasi pendukung lain (mis. riwayat surat) ke Excel ketika modulnya sudah final.
- [ ] Pengujian integrasi online, audit izin akses per peran, hardening, backup terjadwal, HTTPS, keamanan unggahan dan pemantauan operasional.
- [ ] Pengujian Laragon dengan MySQL nyata, browser, test otomatis Laravel dan contoh riwayat siswa naik kelas.

Jangan tandai aplikasi sebagai siap produksi sebelum butir tertunda diuji dan diselesaikan.
