<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Str;

class BrandSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $brands = [
            'DLF Ltd',
            'Godrej Properties',
            'Lodha Group',
            'Oberoi Realty',
            'Sobha Ltd',
            'Prestige Estates Projects',
            'Brigade Group',
            'Tata Housing',
            'L&T Realty',
            'Anant Raj Ltd',
            'Adani Group',
            'Ashiana Housing',
            'ATS Infrastructure',
            'Century Real Estate',
            'Emaar India',
            'Embassy Group',
            'Gaursons India',
            'Hiranandani Group',
            'K Raheja Corp',
            'Piramal Realty',
            'Puravankara Ltd',
            'Raheja Developers',
            'Shapoorji Pallonji',
            'TVS Emerald',
            'Unitech Group',
            'Virtuous Retail',
            'M3M India',
            'Signature Global',
            'Omaxe Ltd',
            'Brick & Bolt',
        ];

        foreach ($brands as $name) {
            Brand::firstOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'slug' => Str::slug($name),
                    // Additional fields can be added here if your migration includes them.
                ]
            );
        }
    }
}
