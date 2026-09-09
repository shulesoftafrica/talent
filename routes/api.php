<?php

use App\Http\Controllers\Api\JobMatchController;
use App\Http\Controllers\Api\SkillsCatalogController;
use App\Http\Controllers\Api\VacancySkillRequirementController;
use Illuminate\Support\Facades\Route;

// Internal, server-to-server only — see VerifyInternalApiKey. Not part of
// any candidate/officer-facing surface.
Route::middleware('verify.internal.key')->group(function () {
    Route::get('/internal/job-match', [JobMatchController::class, 'show']);
    Route::get('/internal/vacancy-skills', [VacancySkillRequirementController::class, 'index']);
    Route::post('/internal/vacancy-skills', [VacancySkillRequirementController::class, 'upsert']);
    Route::get('/internal/skills-catalog', [SkillsCatalogController::class, 'index']);
});
