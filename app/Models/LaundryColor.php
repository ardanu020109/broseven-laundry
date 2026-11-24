<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LaundryColor extends Model
{
    protected $table = 'laundry_colors';

    protected $fillable = [
        'key',
        'label',
        'value',
        'default_value',
    ];
}