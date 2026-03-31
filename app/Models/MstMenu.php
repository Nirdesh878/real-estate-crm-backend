<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MstMenu extends Model
{
    protected $table = 'mst_menu';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = ['id', 'key', 'label', 'path', 'sort'];
}