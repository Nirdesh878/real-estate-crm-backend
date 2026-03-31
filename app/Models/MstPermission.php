<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MstPermission extends Model
{
    protected $table = 'mst_permissions';

    protected $fillable = ['role_id', 'menu_id', 'enabled'];

    protected $casts = [
        'enabled' => 'bool',
    ];

    public function menu()
    {
        return $this->belongsTo(MstMenu::class, 'menu_id');
    }

    public function role()
    {
        return $this->belongsTo(MstRole::class, 'role_id');
    }
}