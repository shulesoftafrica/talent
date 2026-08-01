<?php

namespace App\Models;

use App\Models\Constant\ReferClass;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidateTeachingSubjectClass extends Model
{
    protected $fillable = ['candidate_teaching_subject_id', 'refer_class_id'];

    public function teachingSubject(): BelongsTo
    {
        return $this->belongsTo(CandidateTeachingSubject::class, 'candidate_teaching_subject_id');
    }

    /**
     * Cross-schema relation into constant.refer_classes — read-only.
     */
    public function referClass(): BelongsTo
    {
        return $this->belongsTo(ReferClass::class, 'refer_class_id');
    }
}
