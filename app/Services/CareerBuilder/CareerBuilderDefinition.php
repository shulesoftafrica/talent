<?php

namespace App\Services\CareerBuilder;

/**
 * Static definition of the Career Profile Builder's steps/fields, mirroring
 * the mockup's BASE_STEPS/PROF_SPECS exactly. Teacher's 'teachLevel',
 * 'curriculum', and 'subjects' fields are marked dynamic — their options
 * come from the constant schema (see CareerBuilderDataService) rather than
 * being hardcoded here, per product direction. Every other profession's
 * fields stay as simple static option lists, matching the mockup.
 */
class CareerBuilderDefinition
{
    public const PROFESSIONS = [
        'Teacher', 'Accountant', 'Driver', 'Nurse', 'Administrator', 'Software Developer', 'Librarian',
    ];

    /**
     * Steps shared by every profession.
     */
    public static function baseSteps(): array
    {
        return [
            'common' => [
                'title' => 'Career Preferences',
                'subtitle' => 'Shared by every profession — pay, availability and location.',
                'fields' => [
                    ['key' => 'employmentType', 'label' => 'Employment Type', 'kind' => 'multi', 'options' => ['Full Time', 'Part Time', 'Contract', 'Substitute', 'Volunteer']],
                    ['key' => 'availability', 'label' => 'Availability', 'kind' => 'single', 'options' => ['Available Immediately', 'In 1 month', 'End of term', 'End of year']],
                    ['key' => 'salary', 'label' => 'Minimum Expected Salary (TZS/month)', 'kind' => 'money'],
                    ['key' => 'relocate', 'label' => 'Willing to Relocate', 'kind' => 'single', 'options' => ['Yes, anywhere', 'Within country only', 'No']],
                    ['key' => 'countries', 'label' => 'Preferred Countries', 'kind' => 'multi', 'dynamic' => true, 'hint' => 'Only countries where ShuleSoft schools are currently active.'],
                    ['key' => 'cities', 'label' => 'Preferred Cities', 'kind' => 'city-multi', 'dynamic' => true, 'hint' => 'Pick a suggestion or type any city.'],
                    ['key' => 'languages', 'label' => 'Languages Spoken', 'kind' => 'multi', 'options' => ['Swahili', 'English', 'French', 'Arabic', 'Portuguese']],
                ],
            ],
        ];
    }

    /**
     * Profession-specific steps. Fields marked 'dynamic' => true have their
     * 'options' filled in at render time from the constant schema.
     */
    public static function specSteps(string $profession): array
    {
        return match ($profession) {
            'Teacher' => [
                'teaching' => [
                    'title' => 'Teaching Profile',
                    'subtitle' => 'The strongest signal schools search on.',
                    'fields' => [
                        ['key' => 'teachLevel', 'label' => 'Teaching Level', 'kind' => 'multi', 'dynamic' => true, 'hint' => 'Also sets the classes you can tag per subject below.'],
                        ['key' => 'curriculum', 'label' => 'Curriculum Experience', 'kind' => 'multi', 'dynamic' => true],
                        ['key' => 'subjects', 'label' => 'Subjects, Classes & Years', 'kind' => 'subjects', 'hint' => 'Pick a subject, then tag the classes you taught and how long.'],
                        ['key' => 'examExp', 'label' => 'National Examination Experience', 'kind' => 'multi', 'options' => ['NECTA Form 4 Marking', 'NECTA Form 6 Marking', 'Cambridge IGCSE Examiner', 'Mock Setting', 'None yet']],
                    ],
                ],
                'school' => [
                    'title' => 'School Preferences',
                    'subtitle' => 'Where and how you want to teach.',
                    'fields' => [
                        ['key' => 'boarding', 'label' => 'Boarding or Day School', 'kind' => 'single', 'options' => ['Boarding', 'Day', 'Either']],
                        ['key' => 'schoolSize', 'label' => 'Preferred School Size', 'kind' => 'single', 'options' => ['Under 300 students', '300–800', '800+', 'No preference']],
                    ],
                ],
                'beyond' => [
                    'title' => 'Beyond the Classroom',
                    'subtitle' => 'Leadership and co-curricular strength — often the tie-breaker.',
                    'fields' => [
                        ['key' => 'leadership', 'label' => 'Leadership Roles Held', 'kind' => 'multi', 'options' => ['Head of Department', 'Academic Master', 'Class Teacher', 'Discipline Master', 'Exam Officer', 'Deputy Head', 'Head Teacher']],
                        ['key' => 'cocurricular', 'label' => 'Co-curricular Activities', 'kind' => 'multi', 'options' => ['Football', 'Netball', 'Debate', 'Music', 'Drama', 'Robotics', 'Scouts', 'Science Club', 'Chess']],
                        ['key' => 'digital', 'label' => 'Digital Teaching Skills', 'kind' => 'multi', 'options' => ['ShuleSoft', 'Google Classroom', 'Microsoft Teams', 'Moodle', 'AI Tools', 'Interactive Whiteboard']],
                    ],
                ],
            ],
            'Accountant' => [
                'finance' => [
                    'title' => 'Finance Profile',
                    'subtitle' => 'Systems and statutory work schools screen for.',
                    'fields' => [
                        ['key' => 'acctSystems', 'label' => 'Accounting Systems', 'kind' => 'multi', 'options' => ['ShuleSoft Finance', 'QuickBooks', 'Tally', 'Sage', 'Advanced Excel']],
                        ['key' => 'acctSpecial', 'label' => 'Specialisation', 'kind' => 'multi', 'options' => ['Bookkeeping', 'Payroll', 'Budgeting', 'Audit', 'Tax']],
                        ['key' => 'acctCerts', 'label' => 'Professional Certification', 'kind' => 'multi', 'options' => ['CPA (T)', 'ACCA', 'NBAA Graduate', 'Diploma in Accountancy']],
                        ['key' => 'statutory', 'label' => 'Statutory Filing Experience', 'kind' => 'multi', 'options' => ['TRA / PAYE', 'NSSF', 'WCF', 'VAT Returns']],
                    ],
                ],
            ],
            'Driver' => [
                'driving' => [
                    'title' => 'Driving Profile',
                    'subtitle' => 'Licences, vehicles and safety record.',
                    'fields' => [
                        ['key' => 'licenseClass', 'label' => 'Licence Class', 'kind' => 'multi', 'options' => ['Class B', 'Class C', 'Class C1', 'Class D', 'Class E']],
                        ['key' => 'vehicles', 'label' => 'Vehicle Types Driven', 'kind' => 'multi', 'options' => ['School Bus 30+', 'Coaster', 'Minibus', 'Sedan', 'Truck']],
                        ['key' => 'safety', 'label' => 'Safety & Records', 'kind' => 'multi', 'options' => ['Clean record 5+ yrs', 'Defensive driving cert', 'First Aid trained', 'Vehicle maintenance']],
                    ],
                ],
            ],
            'Nurse' => [
                'clinical' => [
                    'title' => 'Clinical Profile',
                    'subtitle' => 'Registration and school-clinic experience.',
                    'fields' => [
                        ['key' => 'registration', 'label' => 'Registration', 'kind' => 'single', 'options' => ['Registered Nurse', 'Enrolled Nurse', 'Nurse Midwife']],
                        ['key' => 'clinicalSpecial', 'label' => 'Specialisation', 'kind' => 'multi', 'options' => ['Paediatrics', 'Emergency & First Aid', 'Public Health', 'Counselling']],
                        ['key' => 'clinicExp', 'label' => 'School Health Experience', 'kind' => 'multi', 'options' => ['Boarding school clinic', 'Health screening drives', 'Outbreak management', 'Health education']],
                    ],
                ],
            ],
            'Administrator' => [
                'admin' => [
                    'title' => 'Administration Profile',
                    'subtitle' => 'Functions owned and systems used.',
                    'fields' => [
                        ['key' => 'adminFunction', 'label' => 'Function', 'kind' => 'multi', 'options' => ['Admissions', 'Front Office', 'HR', 'Operations', 'Procurement']],
                        ['key' => 'adminSystems', 'label' => 'Systems', 'kind' => 'multi', 'options' => ['ShuleSoft Admin', 'Advanced Excel', 'ERP', 'CRM']],
                        ['key' => 'teamSize', 'label' => 'Largest Team Managed', 'kind' => 'single', 'options' => ['None', '1–5', '6–15', '15+']],
                    ],
                ],
            ],
            'Software Developer' => [
                'engineering' => [
                    'title' => 'Engineering Profile',
                    'subtitle' => 'Stack and domain depth.',
                    'fields' => [
                        ['key' => 'stack', 'label' => 'Stack', 'kind' => 'multi', 'options' => ['JavaScript / TS', 'Python', 'PHP / Laravel', 'React', 'Flutter', 'Node.js']],
                        ['key' => 'domain', 'label' => 'Domain', 'kind' => 'multi', 'options' => ['EdTech', 'Fintech', 'Mobile', 'Data / BI']],
                        ['key' => 'seniority', 'label' => 'Seniority', 'kind' => 'single', 'options' => ['Junior', 'Mid', 'Senior', 'Lead']],
                    ],
                ],
            ],
            'Librarian' => [
                'library' => [
                    'title' => 'Library Profile',
                    'subtitle' => 'Cataloguing systems and reading programmes.',
                    'fields' => [
                        ['key' => 'libSystems', 'label' => 'Library Systems', 'kind' => 'multi', 'options' => ['ShuleSoft Library', 'Koha', 'Manual cataloguing', 'Dewey Decimal']],
                        ['key' => 'libPrograms', 'label' => 'Programmes Run', 'kind' => 'multi', 'options' => ['Reading clubs', 'Literacy drives', 'Research skills classes', 'Digital archive']],
                    ],
                ],
            ],
            default => [],
        };
    }

    /**
     * All steps for a profession (base + spec), in display order.
     */
    public static function allSteps(?string $profession): array
    {
        $steps = self::baseSteps();

        if ($profession) {
            $steps += self::specSteps($profession);
        }

        return $steps;
    }
}
