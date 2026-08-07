<?php

namespace App\Services\AI;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use Smalot\PdfParser\Parser as PdfParser;

/**
 * Extracts raw text from an uploaded CV (PDF or DOCX) and asks OpenAI to
 * turn it into a structured profile the candidate can confirm before it's
 * saved — matching the mockup's "CV parsed — please confirm your details"
 * step.
 */
class CvParserService
{
    public function __construct(private readonly OpenAiClient $openAi)
    {
    }

    public function extractText(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension === 'pdf') {
            $parser = new PdfParser();
            return $parser->parseFile($file->getRealPath())->getText();
        }

        if (in_array($extension, ['docx', 'doc'], true)) {
            $phpWord = WordIOFactory::load($file->getRealPath());
            $text = '';
            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    $text .= $this->elementText($element);
                }
            }
            return $text;
        }

        throw new \InvalidArgumentException("Unsupported CV file type: {$extension}");
    }

    /**
     * Several PhpWord element classes define a getText() method with a
     * different return type than plain text runs (e.g. some return an
     * array or another element object, not a string) — calling it
     * unconditionally crashed real CV uploads. Only trust the result when
     * it's actually a string; recurse into containers (getElements())
     * otherwise, and silently skip anything that yields neither.
     */
    private function elementText(mixed $element): string
    {
        if (method_exists($element, 'getText')) {
            $value = $element->getText();

            if (is_string($value)) {
                return $value . "\n";
            }

            // A Field's text can itself be a TextRun (e.g. a MERGEFIELD) —
            // worth recursing into rather than discarding.
            return is_object($value) ? $this->elementText($value) : '';
        }

        if (method_exists($element, 'getElements')) {
            $text = '';
            foreach ($element->getElements() as $inner) {
                $text .= $this->elementText($inner);
            }

            return $text;
        }

        return '';
    }

    /**
     * @return array{full_name:?string,email:?string,phone:?string,location:?string,experiences:array,educations:array,skills:array,certifications:array}|null
     */
    public function parse(string $rawText): ?array
    {
        $system = <<<'PROMPT'
            You extract structured candidate profile data from a CV/resume's raw text
            for a job platform serving schools in Africa (teachers, accountants, drivers,
            nurses, administrators, and other school staff). Respond ONLY with a JSON
            object matching exactly this shape, using null/empty arrays for anything not
            found — never invent data that isn't in the text:
            {
              "full_name": string|null,
              "email": string|null,
              "phone": string|null,
              "location": string|null,
              "experiences": [{"title": string, "organization": string, "location": string|null, "start_date": string|null, "end_date": string|null, "is_current": boolean, "tasks": [string]}],
              "educations": [{"degree": string, "school": string, "start_year": string|null, "end_year": string|null}],
              "skills": [string],
              "certifications": [{"name": string, "issuer": string|null}]
            }
            PROMPT;

        // A rich CV (e.g. many experience entries with several tasks each)
        // easily exceeds the app-wide default max_tokens (800, sized for the
        // short career-coach modals) — that silently truncates the JSON
        // mid-object, which then fails to decode and looks like "parsing
        // didn't work" even though the API call itself succeeded.
        //
        // No candidate_id here — this runs during signup, before the
        // candidate record exists, so cost tracking logs it under
        // AiFeature::CV_PARSE with candidate_id null.
        return $this->openAi->chatJson($system, $rawText, maxTokens: 3000, feature: AiFeature::CV_PARSE);
    }
}
