<?php

/**
 * ONE-TIME, TEMPORARY script — invite a batch of prospective candidates by
 * email to join ShuleSoft Talent Network. Not wired into the app anywhere;
 * run manually via `php artisan tinker --execute='require "..."'` and
 * delete when the job is done.
 *
 * Input:  a CSV with a header row, columns "email" (required) and
 *         "name" (optional — used to personalize the greeting when present).
 * Output: a results CSV logging exactly what happened to every row, so a
 *         re-run after an interruption skips rows already sent instead of
 *         double-emailing anyone.
 *
 * Config (edit these two constants, or override via env vars of the same
 * name, before running):
 *   INVITE_CSV_PATH    — input CSV path.
 *   INVITE_RESULTS_PATH — output CSV path (created/appended).
 *   INVITE_DRY_RUN     — '1' (default) previews only, sends nothing.
 *   INVITE_LIMIT       — max rows to actually process this run (0 = no limit).
 *   INVITE_IGNORE_EXISTING — '1' to send even to an email that's already a
 *                            registered candidate. Only for deliverability
 *                            testing against your own address — leave unset
 *                            for the real batch, where skipping existing
 *                            candidates is the whole point.
 */

use App\Services\Notifications\UnifiedNotificationClient;
use App\Models\Candidate;

// getenv() returns the literal string '0' when INVITE_DRY_RUN=0 is set,
// and PHP's ?: treats "0" as falsy — using it here would silently fall
// back to the dry-run default and ignore an explicit request to send for
// real, so this checks presence with !== false instead.
$csvPath = getenv('INVITE_CSV_PATH') !== false ? getenv('INVITE_CSV_PATH') : '/tmp/candidate_invite_list.csv';
$resultsPath = getenv('INVITE_RESULTS_PATH') !== false ? getenv('INVITE_RESULTS_PATH') : '/tmp/candidate_invite_results.csv';
$dryRun = (getenv('INVITE_DRY_RUN') !== false ? getenv('INVITE_DRY_RUN') : '1') === '1';
$limit = (int) (getenv('INVITE_LIMIT') !== false ? getenv('INVITE_LIMIT') : 0);
$ignoreExisting = getenv('INVITE_IGNORE_EXISTING') === '1';
$delayMicroseconds = 300_000; // 0.3s between real sends — gentle on the notification API

if (!file_exists($csvPath)) {
    echo "Input CSV not found: {$csvPath}\n";
    return;
}

// Load already-processed emails from a prior run so this is safely re-runnable.
$alreadyDone = [];
if (file_exists($resultsPath)) {
    $rh = fopen($resultsPath, 'r');
    $header = fgetcsv($rh);
    while (($row = fgetcsv($rh)) !== false) {
        $record = array_combine($header, $row);
        if (($record['status'] ?? '') === 'sent') {
            $alreadyDone[strtolower(trim($record['email']))] = true;
        }
    }
    fclose($rh);
}

$resultsExists = file_exists($resultsPath);
$resultsHandle = fopen($resultsPath, 'a');
if (!$resultsExists) {
    fputcsv($resultsHandle, ['email', 'name', 'status', 'reason', 'timestamp']);
}

$notifications = app(UnifiedNotificationClient::class);

$landingUrl = 'https://talent.shulesoft.africa';
$logoUrl = 'https://talent.shulesoft.africa/logo.png';

// The notification API rejects any message over 4096 characters
// (confirmed live: a first, more spacious version of this template hit
// that limit and every send failed). The current template renders well
// under it, but this collapses incidental whitespace as a safety margin
// against future edits creeping back over the limit — HTML doesn't care
// about whitespace between tags, so this is visually a no-op.
$buildMessage = function (?string $name) use ($landingUrl, $logoUrl): string {
    $html = view('emails.candidate-invite', [
        'name' => $name,
        'ctaUrl' => $landingUrl,
        'logoUrl' => $logoUrl,
    ])->render();

    return preg_replace('/>\s+</', '><', trim(preg_replace('/\s+/', ' ', $html)));
};

$subject = '600+ schools are hiring on ShuleSoft — create your free profile';

$inHandle = fopen($csvPath, 'r');
$inHeader = array_map(fn ($h) => strtolower(trim($h)), fgetcsv($inHandle));
$emailCol = array_search('email', $inHeader, true);
$nameCol = array_search('name', $inHeader, true);

if ($emailCol === false) {
    echo "Input CSV has no 'email' column. Header found: " . implode(', ', $inHeader) . "\n";
    return;
}

$counts = ['sent' => 0, 'skipped_existing' => 0, 'skipped_already_sent' => 0, 'skipped_invalid' => 0, 'failed' => 0, 'previewed' => 0];
$processed = 0;

while (($row = fgetcsv($inHandle)) !== false) {
    $email = strtolower(trim($row[$emailCol] ?? ''));
    $name = $nameCol !== false ? trim($row[$nameCol] ?? '') : null;

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        fputcsv($resultsHandle, [$email, $name, 'skipped_invalid', 'not a valid email', now()->toDateTimeString()]);
        $counts['skipped_invalid']++;
        continue;
    }

    if (isset($alreadyDone[$email])) {
        $counts['skipped_already_sent']++;
        continue;
    }

    if (!$ignoreExisting && Candidate::where('email', $email)->exists()) {
        fputcsv($resultsHandle, [$email, $name, 'skipped_existing', 'already a registered candidate', now()->toDateTimeString()]);
        $counts['skipped_existing']++;
        continue;
    }

    if ($limit > 0 && $processed >= $limit) {
        break;
    }

    if ($dryRun) {
        $counts['previewed']++;
        $processed++;
        continue;
    }

    $message = $buildMessage($name ?: null);

    if (strlen($message) > 4096) {
        fputcsv($resultsHandle, [$email, $name, 'failed', 'rendered message exceeds the API\'s 4096-char limit (' . strlen($message) . ' chars) — template needs trimming, not a per-recipient issue', now()->toDateTimeString()]);
        $counts['failed']++;
        $processed++;
        continue;
    }

    $result = $notifications->send([
        'channel' => 'email',
        'to' => $email,
        'subject' => $subject,
        'message' => $message,
    ]);

    if ($result) {
        fputcsv($resultsHandle, [$email, $name, 'sent', '', now()->toDateTimeString()]);
        $counts['sent']++;
    } else {
        fputcsv($resultsHandle, [$email, $name, 'failed', 'notification API call failed — see laravel.log', now()->toDateTimeString()]);
        $counts['failed']++;
    }

    fflush($resultsHandle);
    $processed++;
    usleep($delayMicroseconds);
}

fclose($inHandle);
fclose($resultsHandle);

echo ($dryRun ? "DRY RUN — nothing was actually sent.\n" : "Live run complete.\n");
echo 'Would send / Sent: ' . ($dryRun ? $counts['previewed'] : $counts['sent']) . PHP_EOL;
echo 'Skipped (already a candidate): ' . $counts['skipped_existing'] . PHP_EOL;
echo 'Skipped (already sent in a prior run): ' . $counts['skipped_already_sent'] . PHP_EOL;
echo 'Skipped (invalid email): ' . $counts['skipped_invalid'] . PHP_EOL;
echo 'Failed: ' . $counts['failed'] . PHP_EOL;
echo "Results log: {$resultsPath}\n";
