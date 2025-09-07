<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $units = [
            // Weight
            ['name' => 'Kilogram', 'symbol' => 'kg'],
            ['name' => 'Gram', 'symbol' => 'g'],
            ['name' => 'Milligram', 'symbol' => 'mg'],
            ['name' => 'Quintal', 'symbol' => 'q'],
            ['name' => 'Tonne', 'symbol' => 'ton'],
            ['name' => 'Carat', 'symbol' => 'ct'],
            ['name' => 'Ounce', 'symbol' => 'oz'],
            ['name' => 'Pound', 'symbol' => 'lb'],

            // Length
            ['name' => 'Meter', 'symbol' => 'm'],
            ['name' => 'Centimeter', 'symbol' => 'cm'],
            ['name' => 'Millimeter', 'symbol' => 'mm'],
            ['name' => 'Kilometer', 'symbol' => 'km'],
            ['name' => 'Inch', 'symbol' => 'in'],
            ['name' => 'Foot', 'symbol' => 'ft'],
            ['name' => 'Yard', 'symbol' => 'yd'],
            ['name' => 'Mile', 'symbol' => 'mi'],

            // Area
            ['name' => 'Square Millimeter', 'symbol' => 'mm²'],
            ['name' => 'Square Centimeter', 'symbol' => 'cm²'],
            ['name' => 'Square Meter', 'symbol' => 'm²'],
            ['name' => 'Square Foot', 'symbol' => 'ft²'],
            ['name' => 'Square Yard', 'symbol' => 'yd²'],
            ['name' => 'Acre', 'symbol' => 'acre'],
            ['name' => 'Hectare', 'symbol' => 'ha'],
            ['name' => 'Guntha', 'symbol' => 'guntha'],
            ['name' => 'Bigha', 'symbol' => 'bigha'],
            ['name' => 'Katha', 'symbol' => 'katha'],
            ['name' => 'Square Mile', 'symbol' => 'mi²'],

            // Volume (Liquid & Solid)
            ['name' => 'Litre', 'symbol' => 'L'],
            ['name' => 'Millilitre', 'symbol' => 'mL'],
            ['name' => 'Cubic Meter', 'symbol' => 'm³'],
            ['name' => 'Cubic Centimeter', 'symbol' => 'cm³'],
            ['name' => 'Cubic Foot', 'symbol' => 'ft³'],
            ['name' => 'Cubic Inch', 'symbol' => 'in³'],
            ['name' => 'Gallon (UK)', 'symbol' => 'gal-uk'],
            ['name' => 'Gallon (US)', 'symbol' => 'gal-us'],
            ['name' => 'Pint (UK)', 'symbol' => 'pt-uk'],
            ['name' => 'Pint (US)', 'symbol' => 'pt-us'],

            // Time
            ['name' => 'Second', 'symbol' => 's'],
            ['name' => 'Minute', 'symbol' => 'min'],
            ['name' => 'Hour', 'symbol' => 'hr'],
            ['name' => 'Day', 'symbol' => 'day'],

            // Pieces & Pack
            ['name' => 'Piece', 'symbol' => 'pc'],
            ['name' => 'Dozen', 'symbol' => 'doz'],
            ['name' => 'Packet', 'symbol' => 'pkt'],
            ['name' => 'Roll', 'symbol' => 'roll'],
            ['name' => 'Set', 'symbol' => 'set'],
            ['name' => 'Pair', 'symbol' => 'pair'],
            ['name' => 'Box', 'symbol' => 'box'],
            ['name' => 'Bag', 'symbol' => 'bag'],
            ['name' => 'Carton', 'symbol' => 'ctn'],

            // Textile
            ['name' => 'Metre (Fabric)', 'symbol' => 'm-fab'],
            ['name' => 'Yard (Fabric)', 'symbol' => 'yd-fab'],
            ['name' => 'Bundle', 'symbol' => 'bdl'],

            // Special Real Estate
            ['name' => 'Marla', 'symbol' => 'marla'],
            ['name' => 'Cent', 'symbol' => 'cent'],
            ['name' => 'Ground', 'symbol' => 'ground'],
            ['name' => 'Are', 'symbol' => 'are'],

            // Miscellaneous
            ['name' => 'Sheet', 'symbol' => 'sheet'],
            ['name' => 'Unit', 'symbol' => 'unit'],
        ];

        foreach ($units as $unit) {
            Unit::firstOrCreate(['symbol' => $unit['symbol']], $unit);
        }
    }
}
