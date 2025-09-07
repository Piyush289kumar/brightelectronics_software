<?php

namespace App\Filament\Widgets;

use App\Models\Invoice;
use App\Models\Vendor;
use Filament\Widgets\ChartWidget;

class MonthlyPurchaseTrendWidget extends ChartWidget
{
    protected static ?string $heading = 'Monthly Purchase Trend';

    protected function getData(): array
    {
        $data = Invoice::query()
            ->where('document_type', 'purchase') // ✅ Only purchases
            ->whereHasMorph('billable', [Vendor::class])
            ->selectRaw("DATE_FORMAT(document_date, '%Y-%m') as month, SUM(total_amount) as total")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Purchases',
                    'data' => $data->pluck('total')->toArray(),
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.2)',
                    'fill' => true,
                ],
            ],
            'labels' => $data->pluck('month')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
