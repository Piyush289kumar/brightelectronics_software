<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $services = [
            'BACK LIGHT/CHANGED',
            'PANNEL BREAK / CHANGED',
            'PENNAL PROBLEM',
            'SOUND PROBLEM',
            'MOTHER BOARD PROBLEM',
            'MOTHER BOARD CHANGE',
            'SOFTWARE',
            'LOGO ME HANGH',
            'REPEAT WORK',
            'SUPPLY PROBLEM',
            'SUPPLY REPAIR',
            'DEAD SET',
            'STAND BY PROBLEM',
            'SWITCH PROBLEM',
            'LCD,LED TV REPAIRED',
            'SPEAKER CHANGED',
            'MATTRIX PROBLEM',
            'NOT WORK',
            'UNIVERSAL BOARD INSTALLATION',
        ];

        foreach ($services as $serviceType) {
            Service::create([
                'service_type' => $serviceType,
                'category' => 'General',
                'condition' => 'Condition',
                'price' => null,
                'duration' => null,
                'priority' => 0,
                'is_active' => true,
                'tags' => [],
                'meta' => [],
                'notes' => 'Notes about ' . $serviceType,
            ]);
        }
    }
}
