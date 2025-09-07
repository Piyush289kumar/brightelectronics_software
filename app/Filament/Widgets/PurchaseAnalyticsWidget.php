<?php

namespace App\Filament\Widgets;

use App\Models\Invoice;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\ChartWidget;

class PurchaseAnalyticsWidget extends ChartWidget
{
    use HasWidgetShield;

    protected static ?string $heading = 'Purchase Orders vs. Purchase Invoices';

    protected function getData(): array
    {
        // Purchase Orders (from invoices table)
        $pendingOrders = Invoice::where('document_type', 'purchase_order')
            ->where('status', 'pending')
            ->count();

        $completedOrders = Invoice::where('document_type', 'purchase_order')
            ->where('status', 'completed')
            ->count();

        // Purchase Invoices (from invoices table)
        $pendingInvoices = Invoice::where('document_type', 'purchase')
            ->where('status', 'pending')
            ->count();

        $completedInvoices = Invoice::where('document_type', 'purchase')
            ->where('status', 'completed')
            ->count();

        return [
            'datasets' => [
                [
                    'label' => 'Purchase Orders',
                    'data' => [$pendingOrders, $completedOrders],
                    'backgroundColor' => ['#FF9800', '#4CAF50'],
                ],
                [
                    'label' => 'Purchase Invoices',
                    'data' => [$pendingInvoices, $completedInvoices],
                    'backgroundColor' => ['#03A9F4', '#8BC34A'],
                ],
            ],
            'labels' => ['Pending', 'Completed'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'x' => [
                    'stacked' => true,
                ],
                'y' => [
                    'stacked' => true,
                    'beginAtZero' => true,
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar'; // use bar for stacked chart, or pie if you prefer
    }
}
