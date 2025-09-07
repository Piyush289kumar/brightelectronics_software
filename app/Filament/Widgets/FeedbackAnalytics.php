<?php

namespace App\Filament\Widgets;

use App\Models\Feedback;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\ChartWidget;

class FeedbackAnalytics extends ChartWidget
{
    use HasWidgetShield;
    protected static ?string $heading = 'Feedback Overview';
    protected function getData(): array
    {
        $customerCount = Feedback::where('type', 'customer')->count();
        $managerCount = Feedback::where('type', 'manager')->count();

        return [
            'datasets' => [
                [
                    'label' => 'Feedback Distribution',
                    'data' => [$customerCount, $managerCount],
                    'backgroundColor' => ['#4CAF50', '#FFC107'],
                ],
            ],
            'labels' => ['Customer Feedback', 'Manager Feedback'],
        ];
    }

    protected function getType(): string
    {
        return 'polarArea';
    }
}
