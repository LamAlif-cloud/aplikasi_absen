<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_can_be_repeated_and_admin_can_login(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('username', 'admin@gmail.com')->sole();
        self::assertSame('Admin', $admin->full_name);
        self::assertSame('admin', $admin->role);
        self::assertTrue($admin->active);
        self::assertTrue(Hash::check('passsword', $admin->password));

        $this->post('/login', [
            'username' => 'admin@gmail.com',
            'password' => 'passsword',
        ])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($admin);
        $this->get('/dashboard')->assertOk();
    }
}
