<?php

namespace App\Services\Onboarding;

use App\Models\Application;
use App\Models\Candidate;
use App\Models\CandidateIdentityVerification;
use App\Models\CandidateVerificationItem;
use App\Models\OnboardingSubmission;
use App\Services\Verification\VerificationStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The new hire's onboarding checklist (REQ-HRX-07). The checklist itself (labels,
 * instructions, required flags, review results) is read from the HR module; what
 * the new hire enters is saved ONLY in Talent's own `onboarding_submissions`
 * (typed values encrypted with the key shared with HR, files in the private
 * bucket). Nothing is written into the HR schema; the HR module picks the
 * submissions up within a minute.
 *
 * An item can be saved as a draft and changed until it is submitted. After
 * submission it is read-only unless HR returns it, and once HR approves or
 * waives it, it is final.
 */
class OnboardingService
{
    public const FUNDS = ['NSSF', 'PSSSF', 'other'];
    public const ID_TYPES = ['national_id', 'passport', 'driving_licence'];

    /** The typed fields each item type takes; a save that carries none of them leaves the saved values alone. */
    private const FIELD_KEYS = [
        'pension_declaration' => ['member', 'fund', 'membership_number', 'fund_other'],
        'identity_document' => ['doc_type', 'number'],
        'tin' => ['tin'],
    ];

    public function __construct(private readonly HrOnboardingGateway $origin, private readonly OnboardingVault $vault)
    {
    }

    /**
     * Everything the checklist page needs, or null while onboarding is not open
     * (the offer is not accepted, or the checklist is still locked).
     *
     * @return array{hr: object, items: Collection<int, object>, done: int, total: int, can_submit_all: bool, completed: bool}|null
     */
    public function overview(Application $application): ?array
    {
        $hr = $this->origin->hrApplication($application);
        if (! $hr || $hr->offer_status !== 'accepted') {
            return null;
        }

        $checklist = $this->origin->checklist($application)->reject(fn ($i) => $i->status === 'locked')->values();
        if ($checklist->isEmpty()) {
            return null;
        }

        $local = OnboardingSubmission::query()->where('source_schema', $application->source_schema)->where('source_application_id', $hr->id)->get()->keyBy('requirement_code');
        $items = $checklist->map(fn ($item) => $this->view($item, $local->get($item->requirement_code)));

        return [
            'hr' => $hr,
            'items' => $items,
            'done' => $items->where('complete', true)->count(),
            'total' => $items->count(),
            'can_submit_all' => $items->filter(fn ($i) => $i->required)->every(fn ($i) => $i->complete)
                && $items->contains(fn ($i) => $i->editable && $i->submission && $i->submission->status === 'draft'),
            'completed' => ($hr->onboarding_status ?? null) === 'completed',
        ];
    }

    private function view(object $item, ?OnboardingSubmission $submission): object
    {
        $data = [];
        if ($submission?->data) {
            try {
                $data = SharedEncrypter::fromConfig()->decryptJson($submission->data);
            } catch (\Throwable $e) {
                $data = [];
            }
        }
        $files = array_values((array) ($submission?->files ?? []));

        $item->submission = $submission;
        $item->data = $data;
        $item->files = $files;
        $item->hr_status = $item->status;
        $item->editable = ! in_array($item->status, ['approved', 'waived'], true)
            && ($item->status === 'returned' || ! $submission || $submission->status === 'draft');
        $item->display = match (true) {
            $item->status === 'waived' => 'waived',
            $item->status === 'approved' => 'approved',
            $item->status === 'returned' => 'returned',
            $submission?->status === 'submitted' => 'submitted',
            $submission !== null => 'saved',
            default => 'todo',
        };
        $item->complete = in_array($item->display, ['approved', 'waived', 'submitted'], true)
            || ($item->display === 'saved' && self::isComplete($item->type, $data, $files));

        return $item;
    }

    /** Whether the saved values and files satisfy the item's type. */
    public static function isComplete(string $type, array $data, array $files): bool
    {
        return match ($type) {
            'file', 'form_return', 'photo', 'multi_file' => count($files) >= 1,
            'pension_declaration' => array_key_exists('member', $data) && ($data['member'] === false
                || (in_array($data['fund'] ?? null, self::FUNDS, true) && ($data['membership_number'] ?? '') !== ''
                    && (($data['fund'] ?? null) !== 'other' || ($data['fund_other'] ?? '') !== ''))),
            'identity_document' => in_array($data['doc_type'] ?? null, self::ID_TYPES, true) && ($data['number'] ?? '') !== '' && count($files) >= 1,
            'tin' => preg_match('/^\d{9}$/', (string) ($data['tin'] ?? '')) === 1,
            default => false,
        };
    }

    private function item(Application $application, int $itemId): object
    {
        $overview = $this->overview($application);
        if (! $overview) {
            throw new OfferAnswerRejectedException('Onboarding opens after you accept the offer.');
        }

        $item = $overview['items']->firstWhere('id', $itemId);
        if (! $item) {
            throw new OfferAnswerRejectedException('That item is not on your checklist.');
        }
        if (! $item->editable) {
            throw new OfferAnswerRejectedException($item->display === 'submitted'
                ? 'This item has been submitted and can only be changed if the school returns it.'
                : 'The school has already approved this item, so it can no longer be changed.');
        }

        return $item;
    }

    // ---- saving --------------------------------------------------------------------

    /**
     * Saves a draft of one item. `$input` holds the typed fields, `$newFiles` any uploads,
     * `$labels` a label per new upload (same order), `$remove` ids of current files to drop.
     *
     * @param  array<string, mixed>  $input
     * @param  array<int, UploadedFile>  $newFiles
     * @param  array<int, string>  $labels
     * @param  array<int, string>  $remove
     *
     * @throws ValidationException|UploadRejectedException|OfferAnswerRejectedException
     */
    public function save(Application $application, Candidate $candidate, int $itemId, array $input, array $newFiles = [], array $labels = [], array $remove = []): OnboardingSubmission
    {
        $item = $this->item($application, $itemId);
        $key = "item_{$item->id}";
        $data = $this->parseData($item, $input, $key);
        $keys = self::FIELD_KEYS[$item->type] ?? [];
        if ($keys && ! array_intersect_key($input, array_flip($keys))) {
            $data = $item->data;
        }

        $files = $item->files;
        $dropped = [];
        if ($remove) {
            [$dropped, $files] = collect($files)->partition(fn ($f) => in_array($f['id'] ?? null, $remove, true))->map(fn ($c) => $c->values()->all())->all();
        }

        $single = in_array($item->type, ['file', 'form_return', 'photo'], true);
        $maxFiles = $single ? 1 : (int) $item->max_files;
        if ($newFiles && $single) {
            $dropped = array_merge($dropped, $files);
            $files = [];
        }
        if (count($files) + count($newFiles) > $maxFiles) {
            throw ValidationException::withMessages([$key => "You can have at most {$maxFiles} file(s) here."]);
        }

        $hr = $this->origin->hrApplication($application);
        $stored = [];
        foreach (array_values($newFiles) as $i => $file) {
            $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');
            $path = $this->vault->pathFor($application->source_schema, (int) $hr->id, $item->requirement_code, $extension);
            $meta = $this->vault->store($file, $path, (int) $item->max_size_kb * 1024, $item->accepts);
            $stored[] = [
                'id' => (string) Str::uuid(), 'path' => $meta['path'], 'original_name' => $meta['original_name'], 'mime' => $meta['mime'],
                'size' => $meta['size'], 'sha256' => $meta['sha256'], 'label' => isset($labels[$i]) ? mb_substr(trim((string) $labels[$i]), 0, 100) : null, 'source' => 'upload',
            ];
        }

        // Existing files may be relabelled (multi-file lists).
        $files = array_map(function ($f) use ($input) {
            $relabel = $input['file_labels'][$f['id']] ?? null;

            return $relabel !== null ? array_merge($f, ['label' => mb_substr(trim((string) $relabel), 0, 100)]) : $f;
        }, $files);

        $row = DB::transaction(function () use ($application, $candidate, $hr, $item, $data, $files, $stored) {
            $row = OnboardingSubmission::query()->firstOrNew(['source_schema' => $application->source_schema, 'source_application_id' => $hr->id, 'requirement_code' => $item->requirement_code]);
            $row->fill([
                'candidate_id' => $candidate->id, 'application_id' => $application->id,
                'data' => $data ? SharedEncrypter::fromConfig()->encryptJson($data) : null,
                'files' => array_merge($files, $stored),
                'status' => 'draft',
                'version' => $row->version ?: 1,
            ])->save();

            return $row;
        });

        foreach ($dropped as $gone) {
            $this->vault->disk()->delete($gone['path'] ?? '');
        }

        return $row;
    }

    /**
     * Saves (with the given input) and submits one item.
     *
     * @throws ValidationException|UploadRejectedException|OfferAnswerRejectedException
     */
    public function submitItem(Application $application, Candidate $candidate, int $itemId, array $input = [], array $newFiles = [], array $labels = [], array $remove = []): OnboardingSubmission
    {
        $row = $this->save($application, $candidate, $itemId, $input, $newFiles, $labels, $remove);
        $item = $this->overview($application)['items']->firstWhere('id', $itemId);

        if (! self::isComplete($item->type, $item->data, $item->files)) {
            throw ValidationException::withMessages(["item_{$itemId}" => 'This item is not complete yet: fill in everything it asks for, then submit.']);
        }

        return $this->markSubmitted($row);
    }

    /**
     * Submit for review: enabled only when every required item is complete; sends every saved item.
     *
     * @return int how many items were submitted
     *
     * @throws OfferAnswerRejectedException
     */
    public function submitAll(Application $application): int
    {
        $overview = $this->overview($application);
        if (! $overview) {
            throw new OfferAnswerRejectedException('Onboarding opens after you accept the offer.');
        }
        if (! $overview['items']->filter(fn ($i) => $i->required)->every(fn ($i) => $i->complete)) {
            throw new OfferAnswerRejectedException('Complete every required item before you submit for review.');
        }

        $count = 0;
        foreach ($overview['items'] as $item) {
            if ($item->editable && $item->submission && $item->submission->status === 'draft' && self::isComplete($item->type, $item->data, $item->files)) {
                $this->markSubmitted($item->submission);
                $count++;
            }
        }

        return $count;
    }

    private function markSubmitted(OnboardingSubmission $row): OnboardingSubmission
    {
        // A first submission keeps version 1; every later one (after HR returned it) is a new version.
        $version = (int) ($row->version ?: 1);
        $row->fill(['status' => 'submitted', 'version' => $row->submitted_at ? $version + 1 : $version, 'submitted_at' => now()])->save();

        return $row;
    }

    // ---- reusing what the candidate already has on Talent -----------------------------

    /** Whether a verified identity document can be reused for the identity item. */
    public function verifiedIdentity(Candidate $candidate): ?CandidateIdentityVerification
    {
        return CandidateIdentityVerification::query()
            ->where('candidate_id', $candidate->id)->whereNotNull('primary_doc_path')
            // An explicit subquery: the model's own verificationItem() relation names the wrong foreign key.
            ->whereIn('candidate_verification_item_id', CandidateVerificationItem::query()->where('candidate_id', $candidate->id)->where('status', VerificationStatus::VERIFIED)->select('id'))
            ->latest('id')->first();
    }

    public function useVerifiedId(Application $application, Candidate $candidate, int $itemId): OnboardingSubmission
    {
        $verified = $this->verifiedIdentity($candidate);
        $item = $this->item($application, $itemId);
        if (! $verified || $item->type !== 'identity_document' || ! Storage::disk('local')->exists($verified->primary_doc_path)) {
            throw new OfferAnswerRejectedException('You do not have a verified identity document to reuse.');
        }

        $type = ['national_id' => 'national_id', 'nida' => 'national_id', 'passport' => 'passport', 'driving_licence' => 'driving_licence', 'drivers_license' => 'driving_licence'][$verified->primary_doc_type] ?? ($item->data['doc_type'] ?? null);

        return $this->copyIn($application, $candidate, $item, $verified->primary_doc_path, 'local', 'verified_id', array_filter(['doc_type' => $type] + $item->data));
    }

    public function useProfilePhoto(Application $application, Candidate $candidate, int $itemId): OnboardingSubmission
    {
        $item = $this->item($application, $itemId);
        $disk = collect(['local', 'public'])->first(fn ($d) => $candidate->avatar_path && Storage::disk($d)->exists($candidate->avatar_path));
        if ($item->type !== 'photo' || ! $disk) {
            throw new OfferAnswerRejectedException('You do not have a profile photo to reuse.');
        }

        return $this->copyIn($application, $candidate, $item, $candidate->avatar_path, $disk, 'profile_photo', []);
    }

    private function copyIn(Application $application, Candidate $candidate, object $item, string $path, string $disk, string $source, array $data): OnboardingSubmission
    {
        $temp = tempnam(sys_get_temp_dir(), 'onb');
        file_put_contents($temp, Storage::disk($disk)->get($path));
        try {
            $file = new UploadedFile($temp, basename($path), null, null, true);
            $row = $this->save($application, $candidate, (int) $item->id, $data, [$file]);
        } finally {
            @unlink($temp);
        }

        $files = collect($row->files)->map(fn ($f, $i) => $i === count($row->files) - 1 ? array_merge($f, ['source' => $source]) : $f)->all();
        $row->forceFill(['files' => $files])->save();

        return $row;
    }

    // ---- parsing typed fields ----------------------------------------------------------

    /** Validates the fields given for an item's type; a draft may be partial, but what is given must be well-formed. */
    private function parseData(object $item, array $input, string $key): array
    {
        $fail = fn (string $message) => throw ValidationException::withMessages([$key => $message]);
        $data = [];

        switch ($item->type) {
            case 'pension_declaration':
                $member = $input['member'] ?? null;
                if ($member === null || $member === '') {
                    break;
                }
                if (! in_array($member, ['yes', 'no'], true)) {
                    $fail('Say whether you are a member of a pension fund.');
                }
                $data['member'] = $member === 'yes';
                if ($member === 'yes') {
                    $fund = $input['fund'] ?? null;
                    if ($fund !== null && $fund !== '') {
                        in_array($fund, self::FUNDS, true) || $fail('Choose your pension fund.');
                        $data['fund'] = $fund;
                    }
                    $number = trim((string) ($input['membership_number'] ?? ''));
                    if ($number !== '') {
                        mb_strlen($number) <= 40 || $fail('That membership number is too long.');
                        $data['membership_number'] = $number;
                    }
                    if (($data['fund'] ?? null) === 'other') {
                        $other = trim((string) ($input['fund_other'] ?? ''));
                        if ($other !== '') {
                            mb_strlen($other) <= 100 || $fail('That fund name is too long.');
                            $data['fund_other'] = $other;
                        }
                    }
                }
                break;

            case 'identity_document':
                $type = $input['doc_type'] ?? null;
                if ($type !== null && $type !== '') {
                    in_array($type, self::ID_TYPES, true) || $fail('Choose the type of identity document.');
                    $data['doc_type'] = $type;
                }
                $number = strtoupper(preg_replace('/[\s\-]+/', '', (string) ($input['number'] ?? '')));
                if ($number !== '') {
                    if (($data['doc_type'] ?? null) === 'national_id') {
                        preg_match('/^\d{20}$/', $number) || $fail('A national ID (NIDA) number has 20 digits.');
                    } else {
                        preg_match('/^[A-Z0-9\/]{5,30}$/', $number) || $fail('Enter the document number using letters and digits only (5 to 30 characters).');
                    }
                    $data['number'] = $number;
                }
                break;

            case 'tin':
                $tin = preg_replace('/[\s\-]+/', '', (string) ($input['tin'] ?? ''));
                if ($tin !== '') {
                    preg_match('/^\d{9}$/', $tin) || $fail('A TIN has exactly 9 digits.');
                    $data['tin'] = $tin;
                }
                break;
        }

        return $data;
    }
}
