<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RolePermission extends Model
{
    use HasFactory;
    protected $table = 'role_permissions';

    protected $fillable = ['role_id', 'menu_id', 'can_access'];

    public function role()
    {
        return $this->belongsTo(Roles::class);
    }

    public function menu()
    {
        return $this->belongsTo(Menu::class);
    }
}
