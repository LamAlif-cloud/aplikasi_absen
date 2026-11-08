<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['username' => 'admin@gmail.com'],
            [
                'full_name' => 'Admin',
                'password' => 'passsword', // User hashes passwords through its model cast.
                'role' => 'admin',
                'active' => true,
            ],
        );
    }
}
