<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidatePortfolioItem extends Model
{
    protected $fillable = [
        'candidate_id', 'type', 'title', 'file_path', 'file_size_bytes', 'description',
    ];

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }
}
