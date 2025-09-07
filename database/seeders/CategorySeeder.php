<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\SubCategory;
use Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'Cement & Concrete Products' => [
                'Ordinary Portland Cement (OPC)',
                'Portland Pozzolana Cement (PPC)',
                'Ready-Mix Concrete (RMC)',
                'White Cement',
                'Rapid Hardening Cement',
                'Sulphate Resistant Cement',
                'Concrete Pavers',
                'Precast Concrete Slabs',
            ],
            'Bricks & Blocks' => [
                'Red Clay Bricks',
                'Fly Ash Bricks',
                'Concrete Blocks',
                'AAC Blocks',
                'Hollow Blocks',
                'Solid Blocks',
                'Curb Stones',
                'Interlocking Blocks',
            ],
            'Steel & Metal Products' => [
                'TMT Bars',
                'Mild Steel Rods',
                'Structural Steel',
                'Galvanized Sheets',
                'Binding Wire',
                'Steel Angles',
                'Steel Channels',
                'Steel Pipes',
            ],
            'Sand & Aggregates' => [
                'River Sand',
                'M-Sand (Manufactured Sand)',
                'Coarse Aggregate',
                'Fine Aggregate',
                'Gravel',
                'Crushed Stone',
            ],
            'Wood & Plywood' => [
                'Hardwood',
                'Softwood',
                'Commercial Plywood',
                'Waterproof Plywood',
                'MDF Board',
                'Particle Board',
                'Veneer Sheets',
                'Laminates',
            ],
            'Roofing Materials' => [
                'GI Sheets',
                'Asbestos Sheets',
                'Clay Roof Tiles',
                'Concrete Roof Tiles',
                'Bitumen Sheets',
                'Polycarbonate Sheets',
            ],
            'Plumbing Materials' => [
                'PVC Pipes',
                'CPVC Pipes',
                'GI Pipes',
                'HDPE Pipes',
                'Sanitary Fittings',
                'Water Storage Tanks',
                'Bathroom Accessories',
            ],
            'Electrical Materials' => [
                'Wires & Cables',
                'Switches & Sockets',
                'MCBs & Distribution Boards',
                'Lighting Fixtures',
                'LED Bulbs',
                'Ceiling Fans',
                'Electrical Conduits',
            ],
            'Paints & Coatings' => [
                'Interior Paints',
                'Exterior Paints',
                'Primers',
                'Waterproofing Solutions',
                'Enamel Paints',
                'Textured Paints',
                'Wood Coatings',
            ],
            'Poles & Structural Supports' => [
                'RCC Poles',
                'Steel Poles',
                'Lighting Poles',
                'Scaffolding',
                'Formwork Systems',
            ],
            'Glass & Glazing' => [
                'Clear Glass',
                'Tempered Glass',
                'Laminated Glass',
                'Glass Blocks',
                'Aluminium Frames',
            ],
            'Tiles & Flooring' => [
                'Ceramic Tiles',
                'Vitrified Tiles',
                'Marble Slabs',
                'Granite Slabs',
                'Wooden Flooring',
                'PVC Flooring',
                'Mosaic Tiles',
            ],
            'Doors & Windows' => [
                'Wooden Doors',
                'UPVC Windows',
                'Aluminium Windows',
                'Steel Doors',
                'Glass Doors',
                'Door Frames',
                'Window Grills',
            ],
            'Insulation & Waterproofing' => [
                'Thermal Insulation Sheets',
                'Soundproof Panels',
                'Bitumen Membranes',
                'Liquid Waterproofing',
                'EPDM Membranes',
            ],
            'Fasteners & Hardware' => [
                'Nails',
                'Screws',
                'Bolts & Nuts',
                'Hinges',
                'Door Locks',
                'Handles',
            ],
            'Safety Equipment' => [
                'Helmets',
                'Safety Shoes',
                'Safety Harnesses',
                'Gloves',
                'Reflective Jackets',
                'Dust Masks',
            ],
        ];

        foreach ($categories as $parentName => $subCategories) {
            $parent = Category::firstOrCreate(
                ['slug' => Str::slug($parentName)],
                [
                    'name' => $parentName,
                    'code' => strtoupper(Str::slug($parentName, '_')),
                    'slug' => Str::slug($parentName),
                    'is_active' => true,
                ]
            );

            foreach ($subCategories as $childName) {
                Category::firstOrCreate(
                    ['slug' => Str::slug($childName), 'parent_id' => $parent->id],
                    [
                        'name' => $childName,
                        'code' => strtoupper(Str::slug($childName, '_')),
                        'slug' => Str::slug($childName),
                        'parent_id' => $parent->id,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
