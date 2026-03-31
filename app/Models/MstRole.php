<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MstRole extends Model
{
    protected $table = 'mst_role';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = ['id', 'name'];

    public function permissions()
    {
        return $this->hasMany(MstPermission::class, 'role_id');
    }
}