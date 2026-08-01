<?php

namespace App\Models\Constant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Read-only reference to constant.school_levels — real teaching levels
 * (Nursery, Primary, O-level, A-level, ...) scoped per refer_country_id,
 * each carrying the syllabus/exam-board system it runs under (NECTA,
 * Cambridge, NACTE, ...). Never written to from this app.
 */
class SchoolLevel extends Model
{
    protected $connection = 'constant';
    protected $table = 'school_levels';
    public $timestamps = false;
    protected $guarded = ['id'];

    public function classes(): HasMany
    {
        return $this->hasMany(ReferClass::class, 'school_level_id');
    }
}
