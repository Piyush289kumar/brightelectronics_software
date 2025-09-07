<?php

namespace App\Filament\Resources\DiscountCouponResource\Pages;

use App\Filament\Resources\DiscountCouponResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDiscountCoupons extends ListRecords
{
    protected static string $resource = DiscountCouponResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
