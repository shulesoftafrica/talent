<?php

namespace App\Models\Constant;

use Illuminate\Database\Eloquent\Model;

/**
 * Read-only reference to constant.refer_subjects — real school subjects
 * (Mathematics, Physics, Civics, ...), scoped per country_id. Never written
 * to from this app.
 */
class ReferSubject extends Model
{
    protected $connection = 'constant';
    protected $table = 'refer_subjects';
    protected $primaryKey = 'subject_id';
    public $timestamps = false;
    protected $guarded = ['subject_id'];
}
