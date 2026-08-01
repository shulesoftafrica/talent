<?php

namespace Database\Seeders;

use App\Models\Training;
use Illuminate\Database\Seeder;

class TrainingSeeder extends Seeder
{
    public function run(): void
    {
        $trainings = [
            [
                'title' => 'Practical Guide to Using ShuleSoft',
                'profession' => 'Teacher',
                'priority_label' => 'HIGH PRIORITY',
                'why' => 'Many schools using ShuleSoft expect teachers to already understand how to use the platform. Completing this certification can improve your chances of being shortlisted.',
                'duration' => '3 Hours',
                'price_label' => 'FREE',
                'issues_certificate' => true,
                'sort_order' => 1,
            ],
            [
                'title' => 'AI Foundations for Teachers',
                'profession' => 'Teacher',
                'priority_label' => 'RECOMMENDED',
                'why' => 'Schools are increasingly asking teachers to use AI tools for lesson planning and grading. Early adopters stand out in interviews.',
                'next_training_date' => '23 August',
                'seats_available' => 32,
                'price_label' => 'FREE',
                'issues_certificate' => false,
                'sort_order' => 2,
            ],
            [
                'title' => 'Competency Based Curriculum Workshop',
                'profession' => 'Teacher',
                'priority_label' => 'GROWING DEMAND',
                'why' => 'Tanzania schools are shifting to Competency Based Curriculum — familiarity is now commonly requested in job postings for your subject.',
                'organizer' => 'Ministry of Education',
                'price_label' => 'FREE',
                'issues_certificate' => false,
                'sort_order' => 3,
            ],
        ];

        foreach ($trainings as $training) {
            Training::updateOrCreate(['title' => $training['title']], $training);
        }
    }
}
