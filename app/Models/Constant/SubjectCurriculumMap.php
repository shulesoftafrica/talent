<?php

namespace App\Models\Constant;

use Illuminate\Database\Eloquent\Model;

/**
 * Read-only reference to constant.subject_curriculum_map — which subjects
 * are actually relevant to a given school level/class. Used to narrow the
 * Career Profile Builder's subject chip list instead of showing all subjects
 * unconditionally. Never written to from this app.
 */
class SubjectCurriculumMap extends Model
{
    protected $connection = 'constant';
    protected $table = 'subject_curriculum_map';
    public $timestamps = false;
    protected $guarded = ['id'];
}
