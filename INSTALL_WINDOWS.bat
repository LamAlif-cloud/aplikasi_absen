@echo off
setlocal
chcp 65001 >nul
echo ========================================
echo  LAMALIF MADRASAH - Persiapan Laravel
echo ========================================
echo.
where php >nul 2>nul
if errorlevel 1 (echo ERROR: PHP tidak ditemukan di PATH. & goto :end)
where composer >nul 2>nul
if errorlevel 1 (echo ERROR: Composer belum terpasang atau belum ada di PATH. & goto :end)
php -r "exit(version_compare(PHP_VERSION, '8.3', '>=') ? 0 : 1);"
if errorlevel 1 (echo ERROR: Laravel 13 memerlukan PHP 8.3 atau lebih baru. & php -v & goto :end)
echo [1/4] Instalasi dependensi resmi Laravel melalui Composer...
call composer install --no-interaction
if errorlevel 1 (echo ERROR: composer install gagal. & goto :end)
if not exist .env copy .env.example .env >nul
echo [2/4] Membuat APP_KEY...
php artisan key:generate --force
if errorlevel 1 goto :end
echo.
echo PENTING: Edit .env sesuai MySQL XAMPP dahulu.
echo Buat database BARU bernama lamalif_madrasah lewat phpMyAdmin.
echo Jangan gunakan database aplikasi lama.
echo.
pause
echo [3/4] Membuat tabel baru...
php artisan migrate
if errorlevel 1 (echo Migrasi gagal. Periksa setting database .env. & goto :end)
echo [4/4] Membuat akun admin...
php artisan madrasah:admin
if errorlevel 1 goto :end
echo.
echo Selesai. Jalankan: php artisan serve
:end
echo.
pause
endlocal
