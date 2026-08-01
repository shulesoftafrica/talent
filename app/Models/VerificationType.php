<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VerificationType extends Model
{
    protected $fillable = [
        'key', 'name', 'default_payer', 'default_price', 'currency', 'sort_order',
    ];
}
