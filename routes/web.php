<?php

use App\Http\Controllers\Auth\OnboardingController;
use App\Http\Controllers\Auth\OtpController;
use App\Http\Controllers\BillingWebhookController;
use App\Http\Controllers\Candidate\AiCoachController;
use App\Http\Controllers\Candidate\ApplicationsController;
use App\Http\Controllers\Candidate\ApplicationWithdrawalController;
use App\Http\Controllers\Candidate\CareerBuilderController;
use App\Http\Controllers\Candidate\IdentityVerificationController;
use App\Http\Controllers\Candidate\InterviewResponseController;
use App\Http\Controllers\Candidate\BackgroundVerificationController;
use App\Http\Controllers\Candidate\EducationVerificationController;
use App\Http\Controllers\Candidate\EmploymentVerificationController;
use App\Http\Controllers\Candidate\JobMatchesController;
use App\Http\Controllers\Candidate\LicenseVerificationController;
use App\Http\Controllers\Candidate\ReferenceVerificationController;
use App\Http\Controllers\Candidate\NotificationsController;
use App\Http\Controllers\Candidate\PaymentController;
use App\Http\Controllers\Candidate\ProfileController;
use App\Http\Controllers\Candidate\PremiumController;
use App\Http\Controllers\Candidate\ProfileItemController;
use App\Http\Controllers\Candidate\TrainingController;
use App\Http\Controllers\Candidate\VerificationCheckoutController;
use App\Http\Controllers\ActivityPingController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\ReferenceController;
use App\Http\Controllers\Officer\AiUsageController as OfficerAiUsageController;
use App\Http\Controllers\Officer\AnalyticsController as OfficerAnalyticsController;
use App\Http\Controllers\Officer\AuthController as OfficerAuthController;
use App\Http\Controllers\Officer\DashboardController as OfficerDashboardController;
use App\Http\Controllers\Officer\QueueController as OfficerQueueController;
use App\Http\Middleware\EnsureOfficerHasVerificationAccess;
use App\Http\Middleware\SyncApplicationNotifications;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Route params bound to models that use HasUuidRouteKey (see that trait)
// must actually look like a uuid — otherwise a guessed sequential number
// like "1" reaches the database as a malformed uuid literal and throws a
// 500 instead of cleanly 404ing.
Route::pattern('application', '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}');
Route::pattern('notification', '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}');
Route::pattern('order', '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}');
Route::pattern('experience', '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}');
Route::pattern('education', '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}');
Route::pattern('certification', '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}');
Route::pattern('skill', '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}');
Route::pattern('hobby', '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}');
Route::pattern('portfolioItem', '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}');
Route::pattern('subject', '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}');
Route::pattern('reference', '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}');

Route::get('/', [LandingController::class, 'index'])->name('landing');

Route::get('/lang/{locale}', [LanguageController::class, 'switch'])->name('language.switch');

Route::get('/reference/cities', [ReferenceController::class, 'cities'])->name('reference.cities');

Route::post('/activity/ping', [ActivityPingController::class, 'ping'])->name('activity.ping');

Route::post('/webhooks/billing', [BillingWebhookController::class, 'handle'])->name('webhooks.billing');

Route::prefix('onboarding')->name('onboarding.')->group(function () {
    Route::post('/cv', [OnboardingController::class, 'uploadCv'])->name('cv');
});

Route::prefix('otp')->name('otp.')->group(function () {
    Route::post('/send', [OtpController::class, 'send'])->name('send');
    Route::post('/resend', [OtpController::class, 'resend'])->name('resend');
    Route::post('/verify', [OtpController::class, 'verify'])->name('verify');
});

Route::post('/logout', function () {
    Auth::guard('candidate')->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect()->route('landing');
})->middleware('auth:candidate')->name('logout');

Route::middleware(['auth:candidate', SyncApplicationNotifications::class])->prefix('app')->name('candidate.')->group(function () {
    Route::get('/jobs', [JobMatchesController::class, 'index'])->name('jobs');
    Route::get('/jobs/{sourceSchema}/{jobPostingId}', [JobMatchesController::class, 'show'])
        ->whereIn('sourceSchema', ['shulesoft', 'safaribook'])
        ->whereNumber('jobPostingId')
        ->name('jobs.show');
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile');

    Route::prefix('applications')->name('applications.')->group(function () {
        Route::get('/', [ApplicationsController::class, 'index'])->name('index');
        Route::post('/apply', [ApplicationsController::class, 'apply'])->name('apply');
        Route::post('/{application}/withdraw', [ApplicationWithdrawalController::class, 'store'])->name('withdraw');
        Route::post('/{application}/withdrawal-details', [ApplicationWithdrawalController::class, 'storeOfferDetails'])->name('withdrawal-details');
        Route::post('/{application}/interview-response', [InterviewResponseController::class, 'store'])->name('interview-response');
    });

    Route::get('/notifications/{notification}/open', [NotificationsController::class, 'open'])->name('notifications.open');

    Route::prefix('verification')->name('verification.')->group(function () {
        Route::get('/', [VerificationCheckoutController::class, 'show'])->name('show');
        Route::post('/', [VerificationCheckoutController::class, 'store'])->name('store');

        Route::prefix('identity')->name('identity.')->group(function () {
            Route::get('/', [IdentityVerificationController::class, 'show'])->name('show');
            Route::post('/', [IdentityVerificationController::class, 'store'])->name('store');
        });

        Route::prefix('license')->name('license.')->group(function () {
            Route::get('/', [LicenseVerificationController::class, 'show'])->name('show');
            Route::post('/', [LicenseVerificationController::class, 'store'])->name('store');
        });

        Route::prefix('education')->name('education.')->group(function () {
            Route::get('/', [EducationVerificationController::class, 'show'])->name('show');
            Route::post('/', [EducationVerificationController::class, 'store'])->name('store');
        });

        Route::prefix('employment')->name('employment.')->group(function () {
            Route::get('/', [EmploymentVerificationController::class, 'show'])->name('show');
            Route::post('/', [EmploymentVerificationController::class, 'store'])->name('store');
        });

        Route::prefix('background')->name('background.')->group(function () {
            Route::get('/', [BackgroundVerificationController::class, 'show'])->name('show');
            Route::post('/', [BackgroundVerificationController::class, 'store'])->name('store');
        });

        Route::prefix('references')->name('references.')->group(function () {
            Route::get('/', [ReferenceVerificationController::class, 'show'])->name('show');
            Route::post('/', [ReferenceVerificationController::class, 'store'])->name('store');
            Route::post('/submit', [ReferenceVerificationController::class, 'submit'])->name('submit');
            Route::delete('/{reference}', [ReferenceVerificationController::class, 'destroy'])->name('destroy');
        });
    });

    Route::prefix('premium')->name('premium.')->group(function () {
        Route::get('/', [PremiumController::class, 'show'])->name('show');
        Route::post('/', [PremiumController::class, 'store'])->name('store');
    });

    Route::prefix('payment')->name('payment.')->group(function () {
        Route::get('/{order}', [PaymentController::class, 'show'])->name('show');
        Route::post('/{order}/finish', [PaymentController::class, 'finish'])->name('finish');
    });

    Route::prefix('profile')->name('profile.')->group(function () {
        Route::put('/', [ProfileItemController::class, 'updatePersonalInfo'])->name('update');

        Route::post('/experiences', [ProfileItemController::class, 'storeExperience'])->name('experiences.store');
        Route::put('/experiences/{experience}', [ProfileItemController::class, 'updateExperience'])->name('experiences.update');
        Route::delete('/experiences/{experience}', [ProfileItemController::class, 'destroyExperience'])->name('experiences.destroy');

        Route::post('/educations', [ProfileItemController::class, 'storeEducation'])->name('educations.store');
        Route::put('/educations/{education}', [ProfileItemController::class, 'updateEducation'])->name('educations.update');
        Route::delete('/educations/{education}', [ProfileItemController::class, 'destroyEducation'])->name('educations.destroy');

        Route::post('/certifications', [ProfileItemController::class, 'storeCertification'])->name('certifications.store');
        Route::put('/certifications/{certification}', [ProfileItemController::class, 'updateCertification'])->name('certifications.update');
        Route::delete('/certifications/{certification}', [ProfileItemController::class, 'destroyCertification'])->name('certifications.destroy');

        Route::post('/skills', [ProfileItemController::class, 'storeSkill'])->name('skills.store');
        Route::put('/skills/{skill}', [ProfileItemController::class, 'updateSkill'])->name('skills.update');
        Route::delete('/skills/{skill}', [ProfileItemController::class, 'destroySkill'])->name('skills.destroy');

        Route::post('/hobbies', [ProfileItemController::class, 'storeHobby'])->name('hobbies.store');
        Route::put('/hobbies/{hobby}', [ProfileItemController::class, 'updateHobby'])->name('hobbies.update');
        Route::delete('/hobbies/{hobby}', [ProfileItemController::class, 'destroyHobby'])->name('hobbies.destroy');

        Route::post('/portfolio', [ProfileItemController::class, 'storePortfolioItem'])->name('portfolio.store');
        Route::put('/portfolio/{portfolioItem}', [ProfileItemController::class, 'updatePortfolioItem'])->name('portfolio.update');
        Route::delete('/portfolio/{portfolioItem}', [ProfileItemController::class, 'destroyPortfolioItem'])->name('portfolio.destroy');
        Route::get('/portfolio/{portfolioItem}/file', [ProfileItemController::class, 'viewPortfolioFile'])->name('portfolio.file');
    });

    Route::prefix('career-builder')->name('career.')->group(function () {
        Route::post('/profession', [CareerBuilderController::class, 'saveProfession'])->name('profession');
        Route::post('/answers', [CareerBuilderController::class, 'saveStepAnswers'])->name('answers');
        Route::post('/subjects', [CareerBuilderController::class, 'saveSubject'])->name('subjects.save');
        Route::delete('/subjects/{subject}', [CareerBuilderController::class, 'removeSubject'])->name('subjects.remove');
    });

    Route::prefix('ai')->name('ai.')->group(function () {
        Route::get('/profile-review', [AiCoachController::class, 'profileReview'])->name('profile-review');
        Route::get('/job-coach/{sourceSchema}/{jobPostingId}', [AiCoachController::class, 'jobCoach'])->name('job-coach');
        Route::get('/career-readiness', [AiCoachController::class, 'careerReadiness'])->name('career-readiness');
    });

    Route::post('/trainings/{training}/enroll', [TrainingController::class, 'enroll'])->name('trainings.enroll');
});

Route::prefix('officer')->name('officer.')->group(function () {
    Route::get('/login', [OfficerAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [OfficerAuthController::class, 'login'])->name('login.submit');
    Route::post('/logout', [OfficerAuthController::class, 'logout'])->middleware('auth:officer')->name('logout');

    Route::middleware(['auth:officer', EnsureOfficerHasVerificationAccess::class])->group(function () {
        Route::get('/', [OfficerDashboardController::class, 'index'])->name('dashboard');
        Route::get('/queue', [OfficerQueueController::class, 'index'])->name('queue');
        Route::post('/queue/{item}/approve', [OfficerQueueController::class, 'approve'])->name('queue.approve');
        Route::post('/queue/{item}/reject', [OfficerQueueController::class, 'reject'])->name('queue.reject');
        Route::post('/queue/{item}/note', [OfficerQueueController::class, 'addNote'])->name('queue.note');
        Route::get('/ai-usage', [OfficerAiUsageController::class, 'index'])->name('ai-usage');
        Route::get('/analytics', [OfficerAnalyticsController::class, 'index'])->name('analytics');
    });
});
