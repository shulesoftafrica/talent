<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;

class Candidate extends Authenticatable
{
    /**
     * constant.refer_countries.id for Tanzania — same id CareerBuilderDataService
     * already treats as the default/home market. Used to decide local (TZS)
     * vs international (USD) pricing.
     */
    public const TANZANIA_COUNTRY_ID = 1;

    protected $fillable = [
        'full_name', 'email', 'phone', 'avatar_path', 'cv_path', 'cv_raw_text',
        'cv_parsed_at', 'dob', 'sex', 'country_id', 'current_location', 'profession',
        'current_employer', 'current_employer_verified', 'availability',
        'open_to_opportunities', 'is_premium', 'premium_until', 'billing_ucn', 'trust_score',
        'profile_strength', 'career_score', 'status',
    ];

    protected $hidden = ['remember_token'];

    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'cv_parsed_at' => 'datetime',
            'dob' => 'date',
            'current_employer_verified' => 'boolean',
            'open_to_opportunities' => 'boolean',
            'is_premium' => 'boolean',
            'premium_until' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Candidate $candidate) {
            $candidate->uuid ??= (string) Str::uuid();
        });
    }

    /**
     * OTP-based auth has no password to check against.
     */
    public function getAuthPassword(): string
    {
        return '';
    }

    public function experiences(): HasMany
    {
        return $this->hasMany(CandidateExperience::class);
    }

    public function educations(): HasMany
    {
        return $this->hasMany(CandidateEducation::class);
    }

    public function certifications(): HasMany
    {
        return $this->hasMany(CandidateCertification::class);
    }

    public function skills(): HasMany
    {
        return $this->hasMany(CandidateSkill::class);
    }

    public function hobbies(): HasMany
    {
        return $this->hasMany(CandidateHobby::class);
    }

    public function portfolioItems(): HasMany
    {
        return $this->hasMany(CandidatePortfolioItem::class);
    }

    public function preferences(): HasOne
    {
        return $this->hasOne(CandidatePreference::class);
    }

    public function verificationItems(): HasMany
    {
        return $this->hasMany(CandidateVerificationItem::class);
    }

    public function careerAnswers(): HasMany
    {
        return $this->hasMany(CandidateCareerAnswer::class);
    }

    public function teachingSubjects(): HasMany
    {
        return $this->hasMany(CandidateTeachingSubject::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function verificationOrders(): HasMany
    {
        return $this->hasMany(VerificationOrder::class);
    }

    public function trainings(): HasMany
    {
        return $this->hasMany(CandidateTraining::class);
    }

    public function aiUsageLogs(): HasMany
    {
        return $this->hasMany(AiUsageLog::class);
    }

    public function isInTanzania(): bool
    {
        return $this->country_id === self::TANZANIA_COUNTRY_ID;
    }

    /**
     * Career answers keyed by field_key, values unwrapped from jsonb.
     *
     * @return Attribute<array<string, mixed>, never>
     */
    protected function careerAnswersMap(): Attribute
    {
        return Attribute::get(fn () => $this->careerAnswers()
            ->pluck('field_value', 'field_key')
            ->all());
    }
}
