<?php

namespace App\Services\Academy;

use App\Models\Candidate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reads a candidate's verified skill credentials from the Academy system.
 *
 * Academy is the source of truth for verified skills (spec §31): it owns the
 * assessment, scoring and credential lifecycle. Talent never reaches into
 * Academy's internal tables — it reads the stable `academy.v_candidate_verified_skills`
 * view, joined on the durable ShuleSoft `sid` (email/phone are mutable; the sid
 * is the fixed cross-application key). If a candidate has no sid yet, there is
 * no reliable link and no verified skills are shown.
 */
class VerifiedSkillsRepository
{
    /**
     * All active, non-expired verified skills for the candidate, richest level
     * first, shaped for display with a public credential-verification link.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function forCandidate(Candidate $candidate): Collection
    {
        $sid = $candidate->sid ?? null;
        if (!$sid) {
            return collect();
        }

        $base = rtrim((string) config('services.academy.url', 'http://localhost/academy'), '/');

        // Verified skills are an enhancement on top of the profile page, not
        // core candidate data -- the academy.v_candidate_verified_skills view
        // this reads does not exist yet on live (confirmed: every candidate
        // profile page load was throwing a 500 here, "relation
        // v_candidate_verified_skills does not exist", because the Academy
        // side of this integration hasn't been built out yet). Fail soft to
        // "no verified skills" rather than take down the whole profile page
        // over a downstream feature that isn't ready.
        try {
            return DB::connection('academy')->table('v_candidate_verified_skills')
                ->where('sid', $sid)
                ->where('is_valid', true)
                ->orderByDesc('level_rank')
                ->orderByDesc('overall_score')
                ->get()
                ->map(fn (object $r) => [
                    'skill_id' => $r->skill_id,
                    'skill_name' => $r->skill_name,
                    'skill_slug' => $r->skill_slug,
                    'level_name' => $r->level_name,
                    'level_rank' => (int) $r->level_rank,
                    'score' => is_numeric($r->overall_score) ? (float) $r->overall_score : null,
                    'credential_number' => $r->credential_number,
                    'issued_at' => $r->issued_at,
                    'expires_at' => $r->expires_at,
                    'verification_url' => $base . '/skills/verify/' . $r->verification_token,
                ]);
        } catch (Throwable $e) {
            Log::error('VerifiedSkillsRepository: failed to read academy.v_candidate_verified_skills', [
                'sid' => $sid,
                'error' => $e->getMessage(),
            ]);

            return collect();
        }
    }

    /**
     * Skill slugs the candidate already holds a valid credential for — used to
     * avoid recommending a skill they've already proven.
     *
     * @return array<int, string>
     */
    public function verifiedSkillSlugs(Candidate $candidate): array
    {
        return $this->forCandidate($candidate)->pluck('skill_slug')->filter()->values()->all();
    }
}
