<?php

namespace App\Models\Constant;

use Illuminate\Database\Eloquent\Model;

/**
 * Read-only reference to constant.refer_countries. Never written to from
 * this app.
 */
class ReferCountry extends Model
{
    protected $connection = 'constant';
    protected $table = 'refer_countries';
    public $timestamps = false;
    protected $guarded = ['id'];
}
