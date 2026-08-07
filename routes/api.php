<?php

use App\Http\Controllers\Api\JobMatchController;
use Illuminate\Support\Facades\Route;

// Internal, server-to-server only — see VerifyInternalApiKey. Not part of
// any candidate/officer-facing surface.
Route::middleware('verify.internal.key')->group(function () {
    Route::get('/internal/job-match', [JobMatchController::class, 'show']);
});
