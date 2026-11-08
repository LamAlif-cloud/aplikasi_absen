<?php
namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdmin extends Command
{
    protected $signature = 'madrasah:admin';
    protected $description = 'Buat atau pulihkan akun admin; password dimasukkan secara tersembunyi';
    public function handle(): int
    {
        $username = trim($this->ask('Username admin', 'admin'));
        $name = trim($this->ask('Nama lengkap', 'Administrator'));
        if (!preg_match('/^[A-Za-z0-9_.-]{3,60}$/', $username) || $name === '') {
            $this->error('Username harus 3–60 karakter huruf/angka/._- dan nama tidak boleh kosong.');
            return self::FAILURE;
        }
        $password = (string) $this->secret('Password baru (minimal 12 karakter)');
        if (strlen($password) < 12) { $this->error('Password terlalu pendek.'); return self::FAILURE; }
        if (User::where('username', $username)->exists() && !$this->confirm('Akun sudah ada. Ubah menjadi admin dan ganti password?', false)) return self::FAILURE;
        User::updateOrCreate(['username' => $username], [
            'full_name' => $name, 'password' => Hash::make($password), 'role' => 'admin', 'active' => true,
        ]);
        $this->info('Akun administrator siap. Password tidak ditampilkan atau dicatat.');
        return self::SUCCESS;
    }
}
