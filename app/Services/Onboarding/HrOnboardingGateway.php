<?php

namespace App\Services\Onboarding;

use App\Models\Application;
use App\Models\Candidate;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Talent's READ-ONLY window onto the HR module's data for one application
 * (REQ-HRX-04/07): the origin application row, and the onboarding checklist the
 * employer defined. The HR module is the system of record for both; Talent never
 * writes them. The only HR-side write here is the one-off link that lets a
 * careers-page applicant's origin row point back at its Talent application
 * (`talent_application_id`), which the HR module already expects Talent to set.
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

    /** True when the school has made an offer on this application. */
    public function hasOffer(Application $application): bool
    {
        $hr = $this->hrApplication($application);

        return $hr !== null && ! empty($hr->offer_status);
    }

    // ---- linking a careers-page applicant to a Talent account ------------------

    /**
     * Finds the Talent application for an offer link, creating it when this is a
     * careers-page applicant seeing Talent for the first time. The candidate must
     * be the person the offer was made to: their email or the last nine digits of
     * their phone must match the HR application.
     */
    public function claim(Candidate $candidate, string $module, string $token, string $channel = 'offer_claim'): ?Application
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
                'source_channel' => $channel,
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

    // ---- the checklist -------------------------------------------------------

    /**
     * The employer's checklist for this application, in order.
     *
     * @return Collection<int, object> rows with `accepts` decoded and `required` as a bool
     */
    public function checklist(Application $application): Collection
    {
        $hr = $this->hrApplication($application);
        if (! $hr) {
            return collect();
        }

        return $this->db($application->source_schema)->table('hr_onboarding_items')
            ->where('application_id', $hr->id)->orderBy('sort_order')->orderBy('id')->get()
            ->map(function ($item) {
                $item->accepts = $item->accepts ? (array) json_decode($item->accepts, true) : [];
                $item->required = in_array($item->required, [true, 1, '1', 't', 'true'], true);

                return $item;
            });
    }

    /** A 10-minute link to the template HR attached to an item. */
    public function templateUrl(object $item): ?string
    {
        return $item->template_path ? $this->vault->temporaryUrl($item->template_path) : null;
    }
}
