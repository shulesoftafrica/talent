<?php

namespace App\Services\Onboarding;

use App\Models\Application;
use App\Models\Candidate;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The candidate's side of the HR offer and onboarding flow (REQ-HRX-03/04).
 *
 * The HR application, its offer terms, its onboarding checklist and the
 * candidate's submissions all live in the school's own schema
 * (shulesoft / safaribook), which Talent already reads for every application.
 * This class is the only place Talent touches those HR tables, and it applies
 * the same rules HR applies on its side: an offer can be answered only while
 * it is open, accepting opens the checklist but never the staff account, and
 * items HR has approved can no longer be changed.
 */
class HrOnboardingGateway
{
    public const MODULES = ['shulesoft', 'safaribook'];

    public function __construct(private readonly OnboardingVault $vault)
    {
    }

    private function db(string $module): ConnectionInterface
    {
        if (! in_array($module, self::MODULES, true)) {
            throw new \InvalidArgumentException('Unknown module.');
        }

        return DB::connection($module);
    }

    public function hrApplication(Application $application): ?object
    {
        if (! $application->source_application_id) {
            return null;
        }

        return $this->db($application->source_schema)->table('applications')->find($application->source_application_id);
    }

    /** True when the offer/onboarding flow applies to this application. */
    public function hasOffer(Application $application): bool
    {
        $hr = $this->hrApplication($application);

        return $hr !== null && ! empty($hr->offer_status);
    }

    // ---- linking a careers-page applicant to a Talent account ------------------

    /**
     * Finds the Talent application for an offer link, creating the link when
     * this is a careers-page applicant seeing Talent for the first time. The
     * candidate must be the person the offer was made to: their email or the
     * last nine digits of their phone must match the HR application.
     */
    public function claim(Candidate $candidate, string $module, string $token): ?Application
    {
        $hr = $this->db($module)->table('applications')->where('offer_token', $token)->first();
        if (! $hr || ! $this->isSamePerson($candidate, $hr)) {
            return null;
        }

        $application = Application::query()->firstOrNew(['source_schema' => $module, 'source_application_id' => $hr->id]);
        if (! $application->exists) {
            $application->fill([
                'candidate_id' => $candidate->id,
                'source_job_posting_id' => $hr->job_posting_id,
                'source_channel' => 'offer',
                'last_seen_status' => $hr->status,
                'applied_at' => $hr->created_at ?? now(),
            ])->save();

            $this->db($module)->table('applications')->where('id', $hr->id)->whereNull('talent_application_id')->update(['talent_application_id' => $application->id]);
        }

        return $application->candidate_id === $candidate->id ? $application : null;
    }

    public function isSamePerson(Candidate $candidate, object $hr): bool
    {
        $email = strtolower(trim((string) $hr->email));
        if ($email !== '' && $candidate->email && strtolower(trim($candidate->email)) === $email) {
            return true;
        }

        $digits = fn (?string $v) => substr(preg_replace('/\D+/', '', (string) $v), -9);
        $phone = $digits($hr->phone);

        return $phone !== '' && strlen($phone) === 9 && $digits($candidate->phone) === $phone;
    }

    // ---- the offer -----------------------------------------------------------

    /** open | answered | expired for an application that has an offer. */
    public function offerState(object $hr): string
    {
        if ($hr->offer_status !== 'sent') {
            return 'answered';
        }

        return ($hr->offer_token_expires_at && strtotime($hr->offer_token_expires_at) < time()) ? 'expired' : 'open';
    }

    /**
     * @throws OfferAnswerRejectedException
     */
    public function accept(Application $application, string $signedName, ?string $ip, ?string $userAgent): void
    {
        $signedName = trim($signedName);
        if (mb_strlen($signedName) < 3) {
            throw new OfferAnswerRejectedException('Please type your full name to accept the offer.');
        }

        $module = $application->source_schema;
        $this->expireIfDue($application);
        $this->db($module)->transaction(function () use ($application, $module, $signedName, $ip, $userAgent) {
            $hr = $this->lockOpenOffer($application);

            $this->db($module)->table('applications')->where('id', $hr->id)->update([
                'offer_status' => 'accepted', 'offer_response' => 'accepted', 'offer_responded_at' => now(),
                'offer_signed_name' => mb_substr($signedName, 0, 150), 'offer_response_channel' => 'talent',
                'offer_response_ip' => $ip, 'offer_response_user_agent' => $userAgent ? mb_substr($userAgent, 0, 300) : null,
                'status' => 'hired', 'notes' => 'Offer accepted by the candidate', 'updated_at' => now(),
            ]);

            $this->db($module)->table('hr_onboarding_items')->where('application_id', $hr->id)->where('status', 'locked')->update(['status' => 'pending', 'updated_at' => now()]);
            $this->db($module)->table('applications')->where('id', $hr->id)
                ->where(fn ($q) => $q->whereNull('onboarding_status')->orWhere('onboarding_status', 'locked'))->update(['onboarding_status' => 'not_started']);

            $this->audit($module, $hr, 'offer_accepted', "Offer accepted by {$signedName} via Talent", $ip, $userAgent, ['signed_name' => $signedName, 'channel' => 'talent']);
        });
    }

    /** @throws OfferAnswerRejectedException */
    public function decline(Application $application, ?string $reason, ?string $ip, ?string $userAgent): void
    {
        $module = $application->source_schema;
        $reason = $reason !== null ? mb_substr(trim($reason), 0, 500) : null;
        $this->expireIfDue($application);

        $this->db($module)->transaction(function () use ($application, $module, $reason, $ip, $userAgent) {
            $hr = $this->lockOpenOffer($application);

            $this->db($module)->table('applications')->where('id', $hr->id)->update([
                'offer_status' => 'declined', 'offer_response' => 'rejected', 'offer_responded_at' => now(),
                'offer_decline_reason' => $reason ?: null, 'offer_response_channel' => 'talent',
                'offer_response_ip' => $ip, 'offer_response_user_agent' => $userAgent ? mb_substr($userAgent, 0, 300) : null,
                'status' => 'rejected', 'notes' => 'Offer declined by the candidate', 'updated_at' => now(),
            ]);

            $this->audit($module, $hr, 'offer_declined', 'Offer declined by the candidate'.($reason ? ": {$reason}" : ''), $ip, $userAgent, ['reason' => $reason, 'channel' => 'talent']);
        });
    }

    /** Records an overdue offer as expired -- outside the answer's transaction, so the record survives the refusal. */
    private function expireIfDue(Application $application): void
    {
        $hr = $this->hrApplication($application);
        if ($hr && $this->offerState($hr) === 'expired') {
            $this->db($application->source_schema)->table('applications')->where('id', $hr->id)->where('offer_status', 'sent')->update(['offer_status' => 'expired', 'updated_at' => now()]);
            throw new OfferAnswerRejectedException('This offer has expired. Please contact the school.');
        }
    }

    private function lockOpenOffer(Application $application): object
    {
        $hr = $this->db($application->source_schema)->table('applications')->where('id', $application->source_application_id)->lockForUpdate()->first();

        if (! $hr || $hr->offer_status !== 'sent') {
            throw new OfferAnswerRejectedException('This offer has already been answered or is no longer open.');
        }
        if ($this->offerState($hr) === 'expired') {
            throw new OfferAnswerRejectedException('This offer has expired. Please contact the school.');
        }

        return $hr;
    }

    // ---- the checklist -------------------------------------------------------

    /** @return Collection<int, object> items with `files` (current uploads) and `values` (decrypted answers) */
    public function items(Application $application): Collection
    {
        $hr = $this->hrApplication($application);
        if (! $hr) {
            return collect();
        }
        $db = $this->db($application->source_schema);

        $submissions = $db->table('hr_onboarding_submissions')->where('application_id', $hr->id)->whereNull('superseded_at')->orderBy('id')->get()->groupBy('item_id');

        return $db->table('hr_onboarding_items')->where('application_id', $hr->id)->orderBy('sort_order')->orderBy('id')->get()->map(function ($item) use ($submissions) {
            $rows = $submissions->get($item->id, collect());
            $item->accepts = $item->accepts ? (array) json_decode($item->accepts, true) : [];
            $item->required = in_array($item->required, [true, 1, '1', 't', 'true'], true);
            $item->files = $rows->filter(fn ($r) => $r->file_path)->values();
            $item->values = $rows->reduce(function (array $carry, $r) {
                if (! $r->value_encrypted) {
                    return $carry;
                }
                try {
                    return array_merge($carry, SharedEncrypter::fromConfig()->decryptJson($r->value_encrypted));
                } catch (\Throwable $e) {
                    return $carry;
                }
            }, []);

            return $item;
        });
    }

    public function item(Application $application, int $itemId): ?object
    {
        return $this->items($application)->firstWhere('id', $itemId);
    }

    /** Whether the candidate may change this item now. */
    public function editable(object $item): bool
    {
        return in_array($item->status, ['pending', 'returned', 'submitted'], true);
    }

    /**
     * Records a submission for one item. `$files` are already-validated uploads;
     * `$values` are typed answers (encrypted before they are stored).
     *
     * @param  array<int, UploadedFile>  $files
     * @param  array<string, mixed>  $values
     *
     * @throws UploadRejectedException|OfferAnswerRejectedException
     */
    public function submit(Application $application, object $item, array $files, array $values, ?string $ip = null, ?string $userAgent = null): void
    {
        $hr = $this->hrApplication($application);
        if (! $hr || $hr->offer_status !== 'accepted') {
            throw new OfferAnswerRejectedException('Onboarding opens after you accept the offer.');
        }
        if (! $this->editable($item)) {
            throw new OfferAnswerRejectedException('The school has already approved this item, so it can no longer be changed.');
        }

        $module = $application->source_schema;
        $stored = [];
        foreach ($files as $file) {
            $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');
            $path = $this->vault->pathFor($module, (int) $hr->id, $item->requirement_code, $extension);
            $stored[] = $this->vault->store($file, $path, (int) $item->max_size_kb * 1024, $item->accepts);
        }

        $db = $this->db($module);
        $db->transaction(function () use ($db, $hr, $item, $stored, $values, $module, $ip, $userAgent) {
            $now = now();
            $current = fn () => $db->table('hr_onboarding_submissions')->where('item_id', $item->id)->whereNull('superseded_at');

            // Typed answers and uploads are separate rows, so replacing one never discards the other.
            // Replaced rows are kept and marked superseded.
            if ($stored !== []) {
                $current()->whereNotNull('file_path')->update(['superseded_at' => $now, 'updated_at' => $now]);
            }
            if ($values) {
                $current()->whereNull('file_path')->update(['superseded_at' => $now, 'updated_at' => $now]);
            }

            $base = ['schema_name' => $hr->schema_name, 'application_id' => $hr->id, 'item_id' => $item->id, 'created_at' => $now, 'updated_at' => $now, 'superseded_at' => null];
            $rows = [];
            foreach ($stored as $file) {
                $rows[] = $base + ['file_path' => $file['path'], 'original_name' => $file['original_name'], 'mime' => $file['mime'], 'size' => $file['size'], 'sha256' => $file['sha256'], 'value_encrypted' => null];
            }
            if ($values) {
                $rows[] = $base + ['file_path' => null, 'original_name' => null, 'mime' => null, 'size' => null, 'sha256' => null, 'value_encrypted' => SharedEncrypter::fromConfig()->encryptJson($values)];
            }
            if ($rows !== []) {
                $db->table('hr_onboarding_submissions')->insert($rows);
            }

            $db->table('hr_onboarding_items')->where('id', $item->id)->update([
                'status' => 'submitted', 'submitted_at' => $now, 'review_note' => null, 'reviewed_by' => null, 'reviewed_at' => null, 'updated_at' => $now,
            ]);

            $this->refreshOnboardingStatus($module, $hr->id);
            $this->audit($module, $hr, 'onboarding_item_submitted', "Submitted: {$item->label}", $ip, $userAgent, ['item' => $item->requirement_code, 'files' => count($stored)]);
        });
    }

    /** Same derivation HR uses: not_started, in_progress, submitted (all required handed in) or approved. Never changes an active account. */
    public function refreshOnboardingStatus(string $module, int $hrApplicationId): string
    {
        $db = $this->db($module);
        $current = (string) $db->table('applications')->where('id', $hrApplicationId)->value('onboarding_status');
        if (in_array($current, ['active', 'locked', ''], true)) {
            return $current;
        }

        $required = $db->table('hr_onboarding_items')->where('application_id', $hrApplicationId)->get()
            ->filter(fn ($i) => in_array($i->required, [true, 1, '1', 't', 'true'], true));
        $done = fn ($i) => in_array($i->status, ['approved', 'waived'], true);
        $handedIn = fn ($i) => in_array($i->status, ['submitted', 'approved', 'waived'], true);

        $state = match (true) {
            $required->isNotEmpty() && $required->every($done) => 'approved',
            $required->isNotEmpty() && $required->every($handedIn) => 'submitted',
            $required->contains(fn ($i) => in_array($i->status, ['submitted', 'approved', 'returned', 'waived'], true)) => 'in_progress',
            default => 'not_started',
        };
        $db->table('applications')->where('id', $hrApplicationId)->update(['onboarding_status' => $state]);

        return $state;
    }

    /** A 10-minute link to the template HR attached to an item. */
    public function templateUrl(object $item): ?string
    {
        return $item->template_path ? $this->vault->temporaryUrl($item->template_path) : null;
    }

    private function audit(string $module, object $hr, string $action, string $description, ?string $ip, ?string $userAgent, array $new): void
    {
        $this->db($module)->table('hr_audit_logs')->insert([
            'user_id' => 0, 'action_type' => $action, 'table_affected' => 'applications', 'record_id' => $hr->id,
            'description' => mb_substr($description, 0, 500), 'new_value' => json_encode($new), 'ip_address' => $ip,
            'user_agent' => $userAgent ? mb_substr($userAgent, 0, 300) : null, 'severity' => 'low',
            'requires_review' => DB::raw('false'), 'additional_context' => json_encode(['source' => 'talent']),
            'schema_name' => $hr->schema_name, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
