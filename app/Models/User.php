<?php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;
    protected $fillable = ['full_name', 'username', 'password', 'role', 'active'];
    protected $hidden = ['password', 'remember_token'];
    protected $casts = ['password' => 'hashed', 'active' => 'boolean'];
    public function isAnyRole(string ...$roles): bool { return in_array($this->role, $roles, true); }
}
