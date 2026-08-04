<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AI\CvParserService;
use App\Services\Location\CountryDetectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Handles the "I'm New" landing-page flow: CV upload -> AI parse -> the
 * candidate confirms the extracted details before an account is created
 * (account creation itself happens once the phone OTP is verified, in
 * Auth\OtpController::verify).
 */
class OnboardingController extends Controller
{
    public function uploadCv(Request $request, CvParserService $parser, CountryDetectionService $countryDetection): JsonResponse
    {
        $request->validate([
            'cv' => ['required', 'file', 'mimes:pdf,docx,doc', 'max:5120'],
        ]);

        $file = $request->file('cv');
        $storedPath = $file->store('cv-uploads/pending', 'local');

        $rawText = $parser->extractText($file);

        if (trim($rawText) === '') {
            Storage::disk('local')->delete($storedPath);

            return response()->json([
                'success' => false,
                'message' => 'We could not read any text from that file. Please try a different PDF or DOCX.',
            ], 422);
        }

        // AI parsing is a convenience, not a hard requirement — if OpenAI is
        // unavailable or the account is out of quota, the candidate can still
        // continue and fill in name/phone manually rather than being blocked.
        $parsed = $parser->parse($rawText) ?? [];

        $request->session()->put('onboarding', [
            'cv_stored_path' => $storedPath,
            'cv_original_name' => $file->getClientOriginalName(),
            'cv_raw_text' => Str::limit($rawText, 20000, ''),
            'parsed' => $parsed,
        ]);

        $firstExperience = $parsed['experiences'][0] ?? null;

        // Best-effort guess from whatever the CV happened to contain — the
        // candidate confirms/corrects it on the signup screen, and the
        // final phone number they actually verify with is re-checked
        // server-side at signup regardless (see Auth\OtpController).
        $countryId = $countryDetection->detect($parsed['phone'] ?? null, $parsed['location'] ?? null);

        return response()->json([
            'success' => true,
            'full_name' => $parsed['full_name'] ?? null,
            'role' => $firstExperience['title'] ?? null,
            'phone' => $parsed['phone'] ?? null,
            'country_id' => $countryId,
        ]);
    }
}
