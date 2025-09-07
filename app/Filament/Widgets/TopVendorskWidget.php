<?php

namespace App\Filament\Widgets;

use App\Models\Invoice;
use App\Models\Vendor;
use Filament\Widgets\ChartWidget;

class TopVendorskWidget extends ChartWidget
{
    protected static ?string $heading = 'Top Vendors by Purchase Value';

    protected function getData(): array
    {
        $data = Invoice::query()
            ->where('document_type', 'purchase') // ✅ Only purchase invoices
            ->whereHasMorph('billable', [Vendor::class]) // ✅ Only vendors
            ->selectRaw('billable_id, SUM(total_amount) as total')
            ->groupBy('billable_id')
            ->orderByDesc('total')
            ->with('billable')
            ->limit(5)
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Total Purchase Value',
                    'data' => $data->pluck('total')->toArray(),
                    'backgroundColor' => '#3b82f6',
                ],
            ],
            'labels' => $data
                ->filter(fn($row) => $row->billable !== null)
                ->map(fn($row) => $row->billable->name)
                ->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar'; // ✅ Horizontal bar chart
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y', // ✅ Horizontal orientation
        ];
    }
}
