<?php
// app/Helpers/TaxHelper.php
namespace App\Helpers;

use App\Models\Product;

class TaxHelper
{
    public static function getRate(Product $product): float
    {
        return $product->custom_tax_rate ?? ($product->tax->rate ?? 0);
    }

    public static function calculateAmount(Product $product, float $price): float
    {
        $rate = self::getRate($product);
        return ($price * $rate) / 100;
    }
}
