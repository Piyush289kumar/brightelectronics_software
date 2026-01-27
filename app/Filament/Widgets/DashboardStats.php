<?php
namespace App\Filament\Widgets;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Inventory;
use App\Models\Invoice;
use App\Models\KnowledgeBase;
use App\Models\Product;
use App\Models\PurchaseRequisition;
use App\Models\StockTransfer;
use App\Models\Store;
use App\Models\StoreTarget;
use App\Models\Ticket;
use App\Models\Vendor;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
class DashboardStats extends BaseWidget
{

    public static function canView(): bool
    {
        $user = Auth::user();

        return $user
            && (
                $user->hasRole(['Administrator', 'Developer', 'admin'])
            );
    }


    protected function getStats(): array
    {
        // ---------------- Purchases ----------------
        $purchaseRequisitionCount = PurchaseRequisition::where('status', 'pending')->count();

        $pendingPurchaseAmount = Invoice::where('document_type', 'purchase')
            ->where('status', 'pending')
            ->sum('total_amount');

        // ---------------- Store Target (This Month) ----------------
        $year = now()->year;
        $month = now()->month;

        // If user belongs to a store
        $storeId = Auth::user()?->store_id;

        $storeTarget = StoreTarget::query()
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        $targetAmount = $storeTarget?->amount ?? 0;
        $collectedAmount = $storeTarget?->collected_amount ?? 0;

        $percentage = $targetAmount > 0
            ? round(($collectedAmount / $targetAmount) * 100, 2)
            : 0;

        return [

            // ---------------- Purchases ----------------
            Stat::make('Purchase Requisitions', $purchaseRequisitionCount)
                ->icon('heroicon-o-clipboard-document')
                ->color('warning')
                ->description('Total pending purchase requests'),

            // ---------------- Branch Target ----------------
            Stat::make(
                'Branch Target (This Month)',
                $storeTarget
                ? '₹' . number_format($collectedAmount, 2) . ' / ₹' . number_format($targetAmount, 2)
                : '-'
            )
                ->icon('heroicon-o-flag')
                ->color(
                    $storeTarget && $collectedAmount >= $targetAmount
                    ? 'success'
                    : 'warning'
                )
                ->description(
                    $storeTarget
                    ? "Achieved {$percentage}%"
                    : 'No target set for this month'
                ),

            // ---------------- Pending Purchase Amount ----------------
            Stat::make(
                'Pending Purchase Amount',
                '₹' . number_format($pendingPurchaseAmount, 2)
            )
                ->icon('heroicon-o-banknotes')
                ->color('danger')
                ->description('Outstanding payable amount'),
        ];

    }
}
