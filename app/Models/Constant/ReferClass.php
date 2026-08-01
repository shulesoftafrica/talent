<?php

namespace App\Models\Constant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Read-only reference to constant.refer_classes — real classes/grades
 * (Grade One..Seven, Form One..Six, Baby Class, ...) linked to a school
 * level. Never written to from this app.
 */
class ReferClass extends Model
{
    protected $connection = 'constant';
    protected $table = 'refer_classes';
    public $timestamps = false;
    protected $guarded = ['id'];

    public function schoolLevel(): BelongsTo
    {
        return $this->belongsTo(SchoolLevel::class, 'school_level_id');
    }
}
