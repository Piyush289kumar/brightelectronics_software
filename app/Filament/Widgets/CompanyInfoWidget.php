<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class CompanyInfoWidget extends Widget
{
    protected static ?int $sort = -2;

    protected static bool $isLazy = false;

    // Use your own Blade view
    protected static string $view = 'filament.widgets.company-info-widget';

    public string $companyName;
    public string $companyWeb;

    public function mount(): void
    {
        $this->companyName = 'Vipprow'; // Set your company name here
        $this->companyWeb = 'https://vipprow.com/'; // Set your company name here
    }
}
