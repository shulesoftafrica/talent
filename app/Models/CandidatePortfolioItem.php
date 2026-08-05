<?php

namespace App\Models;

use App\Models\Concerns\HasUuidRouteKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidatePortfolioItem extends Model
{
    use HasUuidRouteKey;

    protected $fillable = [
        'candidate_id', 'type', 'title', 'file_path', 'file_size_bytes', 'external_url', 'description',
    ];

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }
}
