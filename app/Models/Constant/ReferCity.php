<?php

namespace App\Models\Constant;

use Illuminate\Database\Eloquent\Model;

/**
 * Read-only reference to constant.refer_cities, scoped per countryid. Never
 * written to from this app.
 */
class ReferCity extends Model
{
    protected $connection = 'constant';
    protected $table = 'refer_cities';
    public $timestamps = false;
    protected $guarded = ['id'];
}
