<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Unit;
use App\Models\TaxSlab;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = [
            // Cement
            ['Cement - OPC 43 Grade', 'CEM43', '50kg Ordinary Portland Cement, 43 Grade'],
            ['Cement - OPC 53 Grade', 'CEM53', '50kg Ordinary Portland Cement, 53 Grade'],
            ['Cement - PPC', 'CEMPPC', 'Portland Pozzolana Cement, 50kg bag'],
            ['White Cement', 'CEMWH', '25kg White Cement for finishing'],
            ['Rapid Hardening Cement', 'CEMRH', '25kg rapid setting cement'],
            ['Sulphate Resisting Cement', 'CEMSR', 'Special cement for sulphate resistance'],

            // Bricks & Blocks
            ['Clay Bricks', 'BRICKCL', 'Standard size clay bricks'],
            ['Fly Ash Bricks', 'BRICKFA', 'Eco-friendly fly ash bricks'],
            ['AAC Blocks', 'AACBLK', 'Lightweight Autoclaved Aerated Concrete Blocks'],
            ['Hollow Concrete Blocks', 'HCBLOCK', 'Hollow concrete masonry units'],
            ['Solid Concrete Blocks', 'SCBLOCK', 'Solid concrete masonry units'],

            // Steel
            ['TMT Bars - 8mm', 'TMT8', 'Thermo-Mechanically Treated Bars 8mm'],
            ['TMT Bars - 10mm', 'TMT10', 'Thermo-Mechanically Treated Bars 10mm'],
            ['TMT Bars - 12mm', 'TMT12', 'Thermo-Mechanically Treated Bars 12mm'],
            ['TMT Bars - 16mm', 'TMT16', 'Thermo-Mechanically Treated Bars 16mm'],
            ['TMT Bars - 20mm', 'TMT20', 'Thermo-Mechanically Treated Bars 20mm'],
            ['Binding Wire', 'BINDW', 'Binding wire for reinforcement'],
            ['GI Wire', 'GIWIRE', 'Galvanized iron wire'],

            // Sand & Aggregates
            ['River Sand', 'SANDRV', 'River sand for plaster and masonry'],
            ['M Sand', 'SANDM', 'Manufactured sand for construction'],
            ['Coarse Aggregate - 10mm', 'AGG10', '10mm granite aggregate'],
            ['Coarse Aggregate - 20mm', 'AGG20', '20mm granite aggregate'],
            ['Coarse Aggregate - 40mm', 'AGG40', '40mm granite aggregate'],
            ['Crushed Stone Dust', 'STONEF', 'Stone dust for filler'],

            // Plumbing
            ['PVC Pipe 1 inch', 'PVC1', 'PVC water supply pipe, 1 inch diameter'],
            ['PVC Pipe 2 inch', 'PVC2', 'PVC water supply pipe, 2 inch diameter'],
            ['CPVC Pipe 1 inch', 'CPVC1', 'Chlorinated PVC hot water pipe, 1 inch'],
            ['UPVC Pipe 2 inch', 'UPVC2', 'Unplasticized PVC pipe, 2 inch'],
            ['PVC Elbow 1 inch', 'ELBPVC1', 'PVC elbow joint, 1 inch'],
            ['PVC Tee 1 inch', 'TEEPVC1', 'PVC tee joint, 1 inch'],
            ['Brass Tap ½ inch', 'BRASSTAP', '½ inch brass water tap'],

            // Electrical
            ['1.5mm Copper Wire', 'WIRE15', '1.5mm electrical copper wire'],
            ['2.5mm Copper Wire', 'WIRE25', '2.5mm electrical copper wire'],
            ['4.0mm Copper Wire', 'WIRE40', '4.0mm electrical copper wire'],
            ['LED Bulb 9W', 'LED9W', '9 watt LED bulb'],
            ['LED Tube Light 20W', 'LEDT20', '20 watt LED tube light'],
            ['Switch - 1 Way', 'SWITCH1', '1-way electrical switch'],
            ['Switch - 2 Way', 'SWITCH2', '2-way electrical switch'],
            ['MCB 6A', 'MCB6A', 'Miniature Circuit Breaker, 6A'],

            // Paints & Finishes
            ['Interior Emulsion Paint - 20L', 'PAINTINT20', '20 litre interior emulsion paint'],
            ['Exterior Emulsion Paint - 20L', 'PAINTEXT20', '20 litre exterior emulsion paint'],
            ['Primer - 10L', 'PRIMER10', '10 litre wall primer'],
            ['Wall Putty - 20kg', 'PUTTY20', '20kg white wall putty'],
            ['Wood Polish - 5L', 'POLISHWD', '5 litre wood polish'],

            // Tiles
            ['Ceramic Floor Tiles 2x2', 'TILEC22', '2x2 ft ceramic floor tiles'],
            ['Vitrified Floor Tiles 2x2', 'TILEV22', '2x2 ft vitrified floor tiles'],
            ['Wall Tiles 1x1', 'TILEW11', '1x1 ft wall tiles'],
            ['Granite Slab', 'GRANSLAB', 'Polished granite slab'],
            ['Marble Slab', 'MARBSLAB', 'Polished marble slab'],

            // Roofing
            ['GI Sheet 0.5mm', 'GISHEET05', 'Galvanized iron sheet, 0.5mm thick'],
            ['GI Sheet 0.8mm', 'GISHEET08', 'Galvanized iron sheet, 0.8mm thick'],
            ['Color Coated Roofing Sheet', 'COLORRF', 'Color coated roofing sheet'],
            ['Clay Roof Tiles', 'CLAYRT', 'Clay roof tiles'],

            // Doors & Windows
            ['Wooden Door', 'DOORWD', 'Solid wooden door'],
            ['Flush Door', 'DOORFL', 'Flush door, standard size'],
            ['Steel Door Frame', 'DOORFR', 'Steel door frame'],
            ['Aluminium Window Frame', 'WINAL', 'Aluminium window frame'],
            ['Glass Window Pane', 'GLASSWP', 'Glass window pane'],

            // Hardware
            ['Construction Nails 2 inch', 'NAIL2', '2 inch steel nails'],
            ['Construction Nails 3 inch', 'NAIL3', '3 inch steel nails'],
            ['Galvanized Screws 1 inch', 'SCREW1', '1 inch galvanized screws'],
            ['Galvanized Screws 2 inch', 'SCREW2', '2 inch galvanized screws'],
            ['Door Hinges', 'HINGE', 'Stainless steel door hinges'],
            ['Padlock', 'PADLOCK', 'Steel padlock'],

            // Miscellaneous
            ['Bitumen 20kg', 'BITUMEN', '20kg paving grade bitumen'],
            ['PVC Water Tank 1000L', 'TANK1000', '1000 litre PVC water tank'],
            ['PVC Water Tank 500L', 'TANK500', '500 litre PVC water tank'],
            ['Safety Helmet', 'HELMET', 'Construction safety helmet'],
            ['Safety Gloves', 'GLOVES', 'Construction safety gloves'],
            ['Safety Shoes', 'SHOES', 'Construction safety shoes'],
            ['Reflective Safety Jacket', 'JACKET', 'High visibility safety jacket'],
        ];

        $unit = Unit::first(); // You can randomize if needed
        $brand = Brand::first();
        $taxSlab = TaxSlab::first();
        $category = Category::first();

        foreach ($products as $item) {
            Product::create([
                'name' => $item[0],
                'sku' => $item[1],
                'barcode' => strtoupper(Str::random(12)),
                'unit_id' => $unit?->id,
                'brand_id' => $brand?->id,
                'category_id' => $category?->id,
                'hsn_code' => '999999',
                'tax_slab_id' => $taxSlab?->id,
                'gst_rate' => $taxSlab?->rate ?? 18,
                'purchase_price' => rand(50, 5000),
                'selling_price' => rand(60, 5500),
                'mrp' => rand(65, 6000),
                'track_inventory' => true,
                'min_stock' => rand(5, 50),
                'max_stock' => rand(100, 500),
                'image_path' => null,
                'is_active' => true,
                'meta' => [],
            ]);
        }
    }
}
