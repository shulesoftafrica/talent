<?php

namespace App\Services\Onboarding;

/** An offer answer or onboarding submission the rules do not allow; the message is safe to show the candidate. */
class OfferAnswerRejectedException extends \RuntimeException
{
}
