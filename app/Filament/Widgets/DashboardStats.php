<?php
namespace App\Filament\Widgets;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Inventory;
use App\Models\Invoice;
use App\Models\KnowledgeBase;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\Store;
use App\Models\Ticket;
use App\Models\Vendor;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
class DashboardStats extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            // ---------------- Dashboard ----------------
            Stat::make('Total Products', Product::count())
                ->icon('heroicon-o-cube')
                ->color('success')
                ->description('Total number of products in the inventory')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->chart([5, 10, 15, 20, 25, 30, 35]),
            Stat::make('Total Invoices', Invoice::where('document_type', 'invoice')->count())
                ->icon('heroicon-o-document-text')
                ->color('success')
                ->description('Total number of invoices')
                ->chart([10, 20, 30, 25, 40, 35, 50]),
            Stat::make('Total Purchase Invoices', Invoice::where('document_type', 'purchase')->count())
                ->icon('heroicon-m-banknotes')
                ->color('warning')
                ->description('Total purchase invoices')
                ->chart([5, 10, 15, 20, 18, 22, 30]),         
            // ---------------- Inventory ----------------            
            // Low Stock Products
            Stat::make('Low Stock Products', Inventory::whereColumn('total_quantity', '<=', 'min_stock')->count())
                ->icon('heroicon-o-exclamation-circle')
                ->color('danger')
                ->description('Products with low stock')
                ->chart([1, 2, 3, 4, 2, 5, 3]),
            // ---------------- Sales ----------------           
            Stat::make('Pending Payments', Invoice::where('document_type', 'invoice')->where('status', 'pending')->count())
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->description('Invoices pending payment')
                ->chart([1, 3, 2, 5, 4, 6, 3]),
            // ---------------- Purchases ----------------
            Stat::make('Pending Purchase Orders', Invoice::where('document_type', 'purchase_order')->where('status', 'pending')->count())
                ->icon('heroicon-o-receipt-refund')
                ->color('warning')
                ->description('Purchase orders awaiting approval')
                ->chart([2, 4, 3, 6, 8, 5, 7]),
        ];
    }
}
