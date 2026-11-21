<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseOrderResource\Pages;
use App\Filament\Resources\EstimateResource\RelationManagers;
use App\Models\Estimate;
use Filament\Forms;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Models\Invoice;
use App\Models\Product;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Textarea;
use Illuminate\Support\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Auth;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use TomatoPHP\FilamentDocs\Filament\Resources\DocumentResource\Pages\PrintDocument;
use TomatoPHP\FilamentDocs\Models\Document;
use TomatoPHP\FilamentDocs\Models\DocumentTemplate;
use Filament\Tables\Filters\Filter;

class PurchaseOrderResource extends BaseResource
{
    protected static ?string $model = Invoice::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Purchase';
    protected static ?string $pluralLabel = 'Purchase Order';
    protected static ?string $label = 'Purchase Orders';
    protected static ?int $navigationSort = 3;

    protected static function permission(string $action): string
    {
        return "{$action}_purchase::order";
    }

    // 🔹 Show badge count (only invoices where document_type = 'invoice')
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('document_type', 'purchase_order')->count();
    }

    // 🔹 Badge color (always primary in your case)
    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }
    // (Optional) Add tooltip to the badge
    protected static ?string $navigationBadgeTooltip = 'Total Purchase Orders';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(4)
                    ->schema([
                        Select::make('store_id')
                            ->label('Branch')
                            ->relationship(
                                name: 'store',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn(Builder $query) => $query->orderBy('name')
                            )
                            ->getOptionLabelFromRecordUsing(
                                fn($record) => "{$record->name} [{$record->code}]"
                            )
                            ->searchable(['name', 'code'])
                            ->preload()
                            ->required(),

                        TextInput::make('number')
                            ->label(fn(callable $get) => match ($get('document_type')) {
                                'purchase_order' => 'PO Number',
                                'purchase' => 'Purchase Number',
                                'invoice' => 'Invoice Number',
                                'estimate' => 'Estimate Number',
                                'quotation' => 'Quotation Number',
                                'credit_note' => 'Credit Note Number',
                                'debit_note' => 'Debit Note Number',
                                'delivery_note' => 'Delivery Note Number',
                                'proforma' => 'Proforma Number',
                                'receipt' => 'Receipt Number',
                                'payment_voucher' => 'Payment Voucher Number',
                                default => 'Document Number',
                            })
                            ->readonly()
                            ->placeholder('Will be auto-generated')
                            ->unique(ignoreRecord: true),
                        Select::make('billable_type')
                            ->label('Bill To')
                            ->options([
                                'App\Models\Customer' => 'Customer',
                                'App\Models\Vendor' => 'Vendor',
                            ])->disabled(true)
                            ->default('App\Models\Vendor') // Always default to Vendor                            
                            ->required()
                            ->dehydrated(true) // 👈 Force saving to DB
                            ->reactive(),

                        Select::make('billable_id')
                            ->label('Select Vendor')
                            ->options(function (callable $get) {
                                $type = $get('billable_type');
                                if (!$type) {
                                    return [];
                                }
                                return $type::query()->pluck('name', 'id')->toArray();
                            })
                            ->searchable()
                            ->required()
                            ->reactive()
                            ->createOptionForm([   // 👈 Allow creating a new Vendor directly
                                Grid::make('3')
                                    ->schema([
                                        Forms\Components\TextInput::make('name')
                                            ->label('Vendor Name')
                                            ->required(),
                                        Forms\Components\TextInput::make('email')
                                            ->label('Email')
                                            ->email(),
                                        Forms\Components\TextInput::make('phone')
                                            ->label('Phone'),
                                    ]),
                            ])
                            ->createOptionUsing(function ($data) {
                                return \App\Models\Vendor::create($data)->name;
                            }),
                    ]),
                Grid::make(4)
                    ->schema([
                        Select::make('document_type')
                            ->label('Invoice Type')
                            ->options([
                                'purchase_order' => 'Purchase Order',
                                'purchase' => 'Purchase',
                                'invoice' => 'Invoice',
                                'estimate' => 'Estimate',
                                'quotation' => 'Quotation',
                                'credit_note' => 'Credit Note',
                                'debit_note' => 'Debit Note',
                                'delivery_note' => 'Delivery Note',
                                'proforma' => 'Proforma',
                                'receipt' => 'Receipt',
                                'payment_voucher' => 'Payment Voucher',
                            ])->disabled()
                            ->dehydrated(true) // 👈 Force saving to DB
                            ->required()
                            ->default('purchase_order') // default selected option
                            ->reactive(), // if you want to use it in dependent logic
                        DatePicker::make('document_date')
                            ->label('Date')
                            ->required()
                            ->default(now()),
                        DatePicker::make('due_date')
                            ->label('Due Date'),
                        TextInput::make('place_of_supply')
                            ->label('Place of Supply (State Code)')
                            ->maxLength(5),
                    ]),
                Grid::make('1')
                    ->schema([

                        ToggleButtons::make('gst_type')
                            ->label('GST Type')
                            ->options([
                                'exclusive' => 'Exclude GST',
                                'inclusive' => 'Include GST',
                            ])
                            ->inline()
                            ->default('inclusive')
                            ->live()
                            ->afterStateUpdated(function ($set, $get) {
                                PurchaseOrderResource::recalculateInvoiceTotals($set, $get);
                            }),
                        Repeater::make('items')
                            ->relationship('items')
                            ->label('Items')
                            ->required()
                            ->reactive()
                            // Recalculate invoice totals only once when entire items array updates
                            ->afterStateUpdated(function (callable $set, callable $get, $state) {
                                PurchaseOrderResource::recalculateInvoiceTotals($set, $get);
                            })
                            ->addActionLabel('Add Item')
                            ->addAction(fn($action) => $action->color('primary'))

                            ->schema([
                                Grid::make(12)
                                    ->schema([
                                        Grid::make(12)
                                            ->schema([
                                                Select::make('product_id')
                                                    ->label('Spare Parts')
                                                    ->options(function () {
                                                        return Product::query()
                                                            ->orderBy('name')
                                                            ->limit(200)
                                                            ->get()
                                                            ->mapWithKeys(fn($p) => [
                                                                $p->id => "{$p->name} ({$p->barcode})",
                                                            ]);
                                                    })
                                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                                    ->getSearchResultsUsing(function (string $query) {
                                                        return Product::query()
                                                            ->where('name', 'like', "%{$query}%")
                                                            ->orWhere('barcode', 'like', "%{$query}%")
                                                            ->orWhere('sku', 'like', "%{$query}%")
                                                            ->orWhere('id', 'like', "%{$query}%")
                                                            ->limit(50)
                                                            ->get()
                                                            ->mapWithKeys(fn($p) => [
                                                                $p->id => "{$p->name} ({$p->barcode})",
                                                            ]);
                                                    })
                                                    ->getOptionLabelUsing(function ($value): ?string {
                                                        $product = Product::find($value);
                                                        return $product
                                                            ? "{$product->name} ({$product->barcode})"
                                                            : null;
                                                    })
                                                    ->searchable()
                                                    ->required()
                                                    ->reactive()
                                                    ->createOptionForm([
                                                        Grid::make(2)->schema([
                                                            Forms\Components\TextInput::make('name')
                                                                ->label('Product Name')
                                                                ->required(),
                                                            Forms\Components\TextInput::make('barcode')
                                                                ->label('Barcode')
                                                                ->required(),
                                                            Forms\Components\TextInput::make('selling_price')
                                                                ->label('Selling Price')
                                                                ->numeric()
                                                                ->default(0)
                                                                ->required(),
                                                        ]),
                                                    ])
                                                    ->createOptionUsing(function (array $data) {
                                                        $product = Product::create([
                                                            'name' => $data['name'],
                                                            'barcode' => $data['barcode'],
                                                            'selling_price' => $data['selling_price'] ?? 0,
                                                            'purchase_price' => 0,
                                                            'track_inventory' => false,
                                                            'is_active' => false,
                                                            'sku' => 'PRD-' . str_pad((Product::max('id') ?? 0) + 1, 5, '0', STR_PAD_LEFT),
                                                        ]);
                                                        return $product->id;
                                                    })
                                                    ->afterStateUpdated(function (callable $set, $get, $state) {
                                                        if (!$state) {
                                                            return;
                                                        }
                                                        $product = Product::find($state);
                                                        if (!$product) {
                                                            return;
                                                        }
                                                        $set('unit_price', $product->selling_price);
                                                        $set('cgst_rate', 0);
                                                        $set('sgst_rate', 0);
                                                        $set('igst_rate', 0);

                                                        // Auto fill GST from Product table
                                                        $set('gst_rate', (float) $product->gst_rate);

                                                        PurchaseOrderResource::recalculateItem($set, $get);
                                                    })
                                                    ->columnSpan(5),

                                                TextInput::make('quantity')
                                                    ->label('Quantity')
                                                    ->numeric()
                                                    ->required()
                                                    ->default(0) // ensures it starts at 0
                                                    ->placeholder('0') // optional, shows 0 when empty
                                                    // ->reactive()
                                                    ->lazy() // <-- update only on blur
                                                    ->afterStateUpdated(function (callable $set, callable $get, $state) {
                                                        // Prevent negative quantity
                                                        if ($state < 0) {
                                                            $set('quantity', 0);
                                                        }
                                                        PurchaseOrderResource::recalculateItem($set, $get);
                                                    })
                                                    ->columnSpan(2),
                                                TextInput::make('unit_price')
                                                    ->label('Unit Price')
                                                    ->numeric()
                                                    ->required()
                                                    ->lazy() // <-- update only on blur
                                                    // ->reactive()
                                                    ->afterStateUpdated(function (callable $set, callable $get, $state) {
                                                        PurchaseOrderResource::recalculateItem($set, $get);
                                                    })
                                                    ->columnSpan(3),
                                                // ✅ Discount percentage input (user editable)
                                                TextInput::make('discount')
                                                    ->label('Discount (%)')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->maxValue(100)
                                                    ->default(0)
                                                    ->placeholder('0')
                                                    ->lazy()
                                                    ->afterStateUpdated(function (callable $set, callable $get, $state) {
                                                        if ($state < 0)
                                                            $set('discount', 0);
                                                        if ($state > 100)
                                                            $set('discount', 100);
                                                        PurchaseOrderResource::recalculateItem($set, $get);
                                                    })
                                                    ->columnSpan(2),
                                                // ✅ Discount amount (auto-calculated)
                                                TextInput::make('discount_amount_per_item')
                                                    ->label('Discount Amount')
                                                    ->numeric()
                                                    ->disabled()
                                                    ->dehydrated(true)
                                                    ->default(0)
                                                    ->columnSpan(3),
                                                // ✅ Single GST rate input
                                                TextInput::make('gst_rate')
                                                    ->label('GST (%)')
                                                    ->numeric()
                                                    ->default(0)
                                                    ->lazy()
                                                    ->afterStateUpdated(fn($set, $get) => PurchaseOrderResource::recalculateItem($set, $get))
                                                    ->columnSpan(2),
                                                // ✅ Auto calculated fields
                                                TextInput::make('gst_amount')
                                                    ->label('GST Amount')
                                                    ->numeric()
                                                    ->disabled()
                                                    ->dehydrated(true)
                                                    ->default(0)
                                                    ->columnSpan(3),
                                                TextInput::make('total_amount')->label('Total Amount')->numeric()->disabled()->dehydrated(true)->columnSpan(4),
                                            ]),
                                    ]),
                            ]),
                    ]),
                Grid::make(4)
                    ->schema([
                        TextInput::make('taxable_value')
                            ->label('Taxable Value')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(true)
                            ->default(0),
                        TextInput::make('discount_amount')->label('Total Discount Amount')->disabled()->dehydrated(true),
                        TextInput::make('gst_amount')->label('Total GST Amount')->disabled()->dehydrated(true),
                        TextInput::make('total_amount')->label('Grand Total')->disabled()->dehydrated(true),
                    ]),
                Textarea::make('notes')->label('Additional Notes')->rows(3)->columnSpanFull(),
            ]);
    }
    public static function recalculateInvoiceTotals(callable $set, callable $get): void
    {
        $items = $get('items') ?? [];
        $taxableValue = 0;
        $totalGstAmount = 0;
        $totalDiscount = 0;
        $totalAmount = 0;
        foreach ($items as $index => $item) {
            $quantity = (float) ($item['quantity'] ?? 0);
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $discountPercent = (float) ($item['discount'] ?? 0); // <- renamed
            $gstRate = (float) ($item['gst_rate'] ?? 0);
            // Subtotal before discount
            $subtotal = $quantity * $unitPrice;
            // Discount per item
            $discountAmount = ($subtotal * $discountPercent) / 100;
            // After discount
            $amountAfterDiscount = $subtotal - $discountAmount;
            // GST amount per item
            $isInclusive = $get('gst_type') === 'inclusive';

            if ($isInclusive) {

                $taxable = $amountAfterDiscount / (1 + ($gstRate / 100));

                $gstAmount = $amountAfterDiscount - $taxable;

                $itemTotal = $amountAfterDiscount;
            } else {

                $taxable = $amountAfterDiscount;

                $gstAmount = ($taxable * $gstRate) / 100;

                $itemTotal = $taxable + $gstAmount;
            }
            // Save calculated values into item array
            $items[$index]['discount_amount_per_item'] = round($discountAmount, 2);
            $items[$index]['gst_amount'] = round($gstAmount, 2);
            $items[$index]['total_amount'] = round($itemTotal, 2);
            // Sum totals
            $taxableValue += $taxable;
            $totalGstAmount += $gstAmount;
            $totalDiscount += $discountAmount;
            $totalAmount += $itemTotal;
        }
        // Update repeater and summary fields
        $set('items', $items);
        $set('taxable_value', round($taxableValue, 2));
        $set('gst_amount', round($totalGstAmount, 2));      // total GST
        $set('discount_amount', round($totalDiscount, 2));  // total discount
        $set('total_amount', round($totalAmount, 2));       // grand total
    }
    public static function recalculateItem(callable $set, callable $get)
    {
        $quantity = (float) ($get('quantity') ?? 0);
        $unitPrice = (float) ($get('unit_price') ?? 0);
        $discountPercent = (float) ($get('discount') ?? 0); // <- renamed
        $gstRate = (float) ($get('gst_rate') ?? 0);
        $subtotal = $quantity * $unitPrice;

        $discountAmount = ($subtotal * $discountPercent) / 100;

        $amountAfterDiscount = $subtotal - $discountAmount;

        $isInclusive = $get('../../gst_type') === 'inclusive';

        if ($isInclusive) {

            $taxable = $amountAfterDiscount / (1 + ($gstRate / 100));

            $gstAmount = $amountAfterDiscount - $taxable;

            $total = $amountAfterDiscount;
        } else {

            $taxable = $amountAfterDiscount;

            $gstAmount = ($taxable * $gstRate) / 100;

            $total = $taxable + $gstAmount;
        }

        $set('gst_amount', round($gstAmount, 2));
        $set('total_amount', round($total, 2));
        $set('discount_amount_per_item', round($discountAmount, 2));
    }


    protected static function buildPurchaseOrderTemplateBody($record): string
    {
        $record->loadMissing(['items.product', 'billable']);

        $template = DocumentTemplate::findOrFail(6);
        $templateBody = (string) $template->body;

        // Get requisition IDs from the PO reference and bulk PO notes.
        $requisitionIds = [];

        if (!empty($record->purchase_req_to_purchase_order_no)) {
            preg_match_all(
                '/\d+/',
                (string) $record->purchase_req_to_purchase_order_no,
                $matches
            );

            $requisitionIds = array_merge($requisitionIds, $matches[0]);
        }

        if (!empty($record->notes)) {
            preg_match_all('/#(\d+)/', $record->notes, $matches);
            $requisitionIds = array_merge($requisitionIds, $matches[1]);
        }

        $requisitionIds = array_values(array_unique($requisitionIds));

        $requisitions = \App\Models\PurchaseRequisition::with([
            'items.product',
            'items.fulfillments.product',
        ])
            ->whereIn('id', $requisitionIds)
            ->get()
            ->keyBy('id');

        // Invoice lines available for matching with fulfillment products.
        $availableInvoiceItems = $record->items
            ->sortBy('id')
            ->values();

        /*
     * Match an invoice line against a fulfillment.
     * A matched line is consumed to prevent duplicate amounts.
     */
        $matchInvoiceLine = function ($fulfillment) use (&$availableInvoiceItems) {
            $lineIndex = $availableInvoiceItems->search(
                function ($line) use ($fulfillment) {
                    return $line->product_id == $fulfillment->product_id
                        && abs(
                            (float) $line->quantity - (float) $fulfillment->quantity
                        ) < 0.0001;
                }
            );

            if ($lineIndex === false) {
                $lineIndex = $availableInvoiceItems->search(
                    fn($line) => $line->product_id == $fulfillment->product_id
                );
            }

            if ($lineIndex === false) {
                return null;
            }

            $invoiceLine = $availableInvoiceItems->get($lineIndex);
            $availableInvoiceItems->forget($lineIndex);

            return $invoiceLine;
        };

        $poDisplayRows = [];

        foreach ($requisitionIds as $requisitionId) {
            $requisition = $requisitions->get($requisitionId);

            if (!$requisition) {
                continue;
            }

            foreach ($requisition->items as $requisitionItem) {
                $originalProduct = $requisitionItem->product;
                $fulfillments = $requisitionItem->fulfillments;

                $originalQty = (float) $fulfillments
                    ->where('product_id', $requisitionItem->product_id)
                    ->sum('quantity');

                $totalQty = 0;
                $totalAmount = 0;
                $originalAmount = 0;

                $partNumbers = [];
                $unitPrices = [];

                // Original product always appears first.
                if ($originalProduct) {
                    $partNumbers[] = ($originalProduct->barcode ?? 'N/A')
                        . ' [' . number_format($originalQty, 0) . ']';
                }

                // Process every fulfilled product.
                foreach ($fulfillments as $fulfillment) {
                    $quantity = (float) $fulfillment->quantity;
                    $totalQty += $quantity;

                    $invoiceLine = null;

                    if ($quantity > 0) {
                        $invoiceLine = $matchInvoiceLine($fulfillment);
                    }

                    $lineAmount = $invoiceLine
                        ? (float) ($invoiceLine->total_amount
                            ?? ((float) $invoiceLine->unit_price * $quantity))
                        : 0;

                    $totalAmount += $lineAmount;

                    // Original product price is calculated separately.
                    if ($fulfillment->product_id == $requisitionItem->product_id) {
                        $originalAmount += $lineAmount;
                        continue;
                    }

                    // Linked product comes after the original product.
                    $linkedProduct = $fulfillment->product;

                    if ($linkedProduct) {
                        $partNumbers[] = ($linkedProduct->barcode ?? 'N/A')
                            . ' [' . number_format($quantity, 0) . ']';

                        $linkedUnitPrice = $invoiceLine
                            ? ($quantity > 0
                                ? $lineAmount / $quantity
                                : (float) $invoiceLine->unit_price)
                            : 0;

                        $unitPrices[] = number_format($linkedUnitPrice, 2);
                    }
                }

                // Original product's unit price is always the first price.
                $originalUnitPrice = $originalQty > 0
                    ? $originalAmount / $originalQty
                    : 0;

                array_unshift(
                    $unitPrices,
                    number_format($originalUnitPrice, 2)
                );

                $poDisplayRows[] = [
                    'part_numbers' => implode(' / ', $partNumbers),
                    'unit_prices' => implode(' / ', $unitPrices),
                    'quantity' => $totalQty,
                    'total_amount' => $totalAmount,
                ];
            }
        }

        // Fallback for older POs without requisition references.
        if (empty($poDisplayRows)) {
            foreach ($record->items as $invoiceItem) {
                $quantity = (float) $invoiceItem->quantity;

                $poDisplayRows[] = [
                    'part_numbers' => ($invoiceItem->product?->barcode ?? 'N/A')
                        . ' [' . number_format($quantity, 0) . ']',
                    'unit_prices' => number_format(
                        (float) $invoiceItem->unit_price,
                        2
                    ),
                    'quantity' => $quantity,
                    'total_amount' => (float) (
                        $invoiceItem->total_amount
                        ?? ($invoiceItem->unit_price * $quantity)
                    ),
                ];
            }
        }

        // Generate item rows for the saved HTML template.
        $itemsHtml = collect($poDisplayRows)
            ->values()
            ->map(function ($row, $index) {
                return '
                <tr>
                    <td style="border:1px solid #000; padding:5px; text-align:center;">
                        ' . ($index + 1) . '
                    </td>

                    <td style="border:1px solid #000; padding:5px;">
                        ' . e($row['part_numbers']) . '
                    </td>

                    <td style="border:1px solid #000; padding:5px; text-align:center;">
                        ' . number_format((float) $row['quantity'], 0) . '
                    </td>

                    <td style="border:1px solid #000; padding:5px; text-align:right;">
                        Rs. ' . e($row['unit_prices']) . '
                    </td>

                    <td style="border:1px solid #000; padding:5px; text-align:right;">
                        Rs. ' . number_format((float) $row['total_amount'], 2) . '
                    </td>
                </tr>
            ';
            })
            ->implode('');

        $map = [
            '$PURCHAS_REQ_NUMBER' => (string) ($record->purchase_req_to_purchase_order_no ?? '--'),
            '$NUMBER' => (string) $record->number,
            '$DOCUMENT_DATE' => $record->document_date
                ? Carbon::parse($record->document_date)->format('d-m-Y')
                : '',
            '$PLACE_OF_SUPPLY' => e((string) ($record->place_of_supply ?? '')),
            '$ACCOUNT_NAME' => e((string) ($record->billable->name ?? '')),
            '$ACCOUNT_ADDRESS' => e((string) ($record->billable->address ?? '')),
            '$ACCOUNT_PHONE' => e((string) ($record->billable->phone ?? '')),
            '$ACCOUNT_GSTIN' => e((string) ($record->billable->gst_number ?? '')),
            '$ACCOUNT_STATE' => e((string) ($record->billable->state ?? '')),

            '$ITEMS' => $itemsHtml,

            '$TOTAL_QTY' => number_format(
                (float) collect($poDisplayRows)->sum('quantity'),
                0
            ),

            '$TOTAL_AMOUNT' => number_format(
                (float) collect($poDisplayRows)->sum('total_amount'),
                2
            ),
        ];

        foreach ($map as $key => $value) {
            $templateBody = str_replace($key, (string) $value, $templateBody);
        }

        return $templateBody;
    }

    public static function mutateFormDataBeforeSave(array $data): array
    {
        $taxableValue = 0;
        $cgstAmount = 0;
        $sgstAmount = 0;
        $igstAmount = 0;
        $totalAmount = 0;
        if (!empty($data['items'])) {
            foreach ($data['items'] as &$item) {
                $quantity = $item['quantity'] ?? 0;
                $unitPrice = $item['unit_price'] ?? 0;
                $discount = $item['discount'] ?? 0;
                $cgstRate = $item['cgst_rate'] ?? 0;
                $sgstRate = $item['sgst_rate'] ?? 0;
                $igstRate = $item['igst_rate'] ?? 0;
                $taxable = ($unitPrice * $quantity) - $discount;
                $itemCgstAmount = ($taxable * $cgstRate) / 100;
                $itemSgstAmount = ($taxable * $sgstRate) / 100;
                $itemIgstAmount = ($taxable * $igstRate) / 100;
                $itemTotalAmount = $taxable + $itemCgstAmount + $itemSgstAmount + $itemIgstAmount;
                $item['cgst_amount'] = round($itemCgstAmount, 2);
                $item['sgst_amount'] = round($itemSgstAmount, 2);
                $item['igst_amount'] = round($itemIgstAmount, 2);
                $item['total_amount'] = round($itemTotalAmount, 2);
                $taxableValue += $taxable;
                $cgstAmount += $itemCgstAmount;
                $sgstAmount += $itemSgstAmount;
                $igstAmount += $itemIgstAmount;
                $totalAmount += $itemTotalAmount;
            }
            unset($item);
        }
        $totalTax = $cgstAmount + $sgstAmount + $igstAmount;
        $discount = $data['discount'] ?? 0;
        $totalAmountAfterDiscount = $totalAmount - $discount;
        return array_merge($data, [
            'items' => $data['items'],
            'taxable_value' => round($taxableValue, 2),
            'cgst_amount' => round($cgstAmount, 2),
            'sgst_amount' => round($sgstAmount, 2),
            'igst_amount' => round($igstAmount, 2),
            'total_tax' => round($totalTax, 2),
            'total_amount' => round($totalAmountAfterDiscount, 2),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();

        $query = parent::getEloquentQuery()
            ->where('document_type', 'purchase_order'); // Show only 'invoice' documents

        return $query;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')->sortable()->searchable()->label('PON'),
                Tables\Columns\TextColumn::make('store.name')
                    ->label('Branch')
                    ->formatStateUsing(
                        fn($state, $record) =>
                        $record->store
                            ? "{$record->store->name} [{$record->store->code}]"
                            : '-'
                    )
                    ->searchable(['store.name', 'store.code'])
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('purchase_req_to_purchase_order_no')->sortable()->searchable()->label('Purchase req. No.')->default('--'),
                Tables\Columns\TextColumn::make('billable.name')->label('Billed To')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('document_date')->date('d-m-Y')->sortable()->label('PON Date'),
                Tables\Columns\TextColumn::make('due_date')->date('d-m-Y')->sortable()->label('Due Date')->toggleable(),

                Tables\Columns\TextColumn::make('total_amount')->money('INR')->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('amount_received')->label('Amount Received')->money('INR')->color('success')->sortable(),
                Tables\Columns\TextColumn::make('balance')->label('Balance Due')->money('INR')->color('danger')->sortable()->toggleable(),

                Tables\Columns\TextColumn::make('status')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('creator.name')->label("Created By")->sortable()->toggleable(),
            ])->defaultSort('created_at', 'desc')
            ->filters([
                // Filter by status
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'partial' => 'Partial',
                        'cancelled' => 'Cancelled',
                        'completed' => 'Completed',
                    ]),
                // Filter by document date range
                Filter::make('document_date')
                    ->form([
                        DatePicker::make('document_date_from')->label('Date From'),
                        DatePicker::make('document_date_to')->label('Date To'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['document_date_from'], fn($q, $val) => $q->where('document_date', '>=', $val))
                            ->when($data['document_date_to'], fn($q, $val) => $q->where('document_date', '<=', $val));
                    }),
                // Filter by due date range
                Filter::make('due_date')
                    ->form([
                        DatePicker::make('due_date_from')->label('Due From'),
                        DatePicker::make('due_date_to')->label('Due To'),
                    ])->columns(2)
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['due_date_from'], fn($q, $val) => $q->where('due_date', '>=', $val))
                            ->when($data['due_date_to'], fn($q, $val) => $q->where('due_date', '<=', $val));
                    }),
                // Filter by total_amount range
                Filter::make('total_amount')
                    ->form([
                        TextInput::make('min')->label('Min')->numeric(),
                        TextInput::make('max')->label('Max')->numeric(),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['min'], fn($q, $val) => $q->where('total_amount', '>=', $val))
                            ->when($data['max'], fn($q, $val) => $q->where('total_amount', '<=', $val));
                    }),
                TrashedFilter::make()->label('Deleted Document'),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([

                    Tables\Actions\Action::make('printPurchaseOrder')
                        ->label('One Pager Order PDF')
                        ->color('success')
                        ->icon('heroicon-s-arrow-down-tray')
                        ->tooltip('Preview then download 2-copy PDF')
                        ->modalHeading('Purchase Order Preview (Office + Vendor Copy)')
                        ->modalWidth('4xl')
                        ->modalContent(fn($record) => view(
                            'filament.components.purchase-order',
                            ['record' => $record]
                        ))
                        ->modalSubmitActionLabel('Download PDF')
                        ->modalCancelActionLabel('Close')
                        ->action(function ($record) {
                            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView(
                                'filament.components.purchase-order',
                                ['record' => $record]
                            )->setPaper('a4', 'portrait');

                            return response()->streamDownload(
                                fn() => print($pdf->output()),
                                "purchase-order-{$record->number}.pdf"
                            );
                        }),

                    // 👇 Generate (or regenerate) and then View
                    Action::make('generateAndViewDocument')
                        ->label('Preview')
                        ->color('info')
                        ->icon('heroicon-s-eye')
                        ->modalHeading('View Document')
                        ->modalButton('Print') // <--------- ADD THIS
                        ->action(function ($record, $livewire) {
                            // after generate doc store same as you already doing
                            // then print automatically:
                            $url = PrintDocument::getUrl(['record' => $record->document_id]);
                            $livewire->js(<<<JS
                                    const iframe = document.createElement('iframe');
                                    iframe.style.position = 'absolute';
                                    iframe.style.width = '0';
                                    iframe.style.height = '0';
                                    iframe.style.border = '0';
                                    iframe.src = "{$url}";
                                    document.body.appendChild(iframe);
                                    iframe.onload = function() {
                                        iframe.contentWindow.focus();
                                        iframe.contentWindow.print();
                                    };
                                JS);
                        })

                        ->modalContent(function ($record) {
                            $body = static::buildPurchaseOrderTemplateBody($record);

                            if (!empty($record->document_id)) {
                                Document::where('id', $record->document_id)->delete();
                            }

                            $document = Document::create([
                                'document_template_id' => 6,
                                'model_type' => Invoice::class,
                                'model_id' => $record->id,
                                'body' => $body,
                            ]);

                            $record->document_id = $document->id;
                            $record->save();

                            return view('filament-docs::print', [
                                'record' => $document,
                            ]);
                        })

                        ->tooltip('Preview'),

                    // ✅ Generate and then Print (with print preview)
                    Tables\Actions\Action::make('generateAndPrintDocument')
                        ->label('Print')
                        ->color('warning')
                        ->icon('heroicon-s-printer')
                        ->tooltip('Print Document')

                        ->action(function ($record, $livewire) {
                            $body = static::buildPurchaseOrderTemplateBody($record);

                            if (!empty($record->document_id)) {
                                Document::where('id', $record->document_id)->delete();
                            }

                            $document = Document::create([
                                'document_template_id' => 6,
                                'model_type' => Invoice::class,
                                'model_id' => $record->id,
                                'body' => $body,
                            ]);

                            $record->document_id = $document->id;
                            $record->save();

                            $url = PrintDocument::getUrl([
                                'record' => $document->id,
                            ]);

                            $livewire->js(<<<JS
                        const iframe = document.createElement('iframe');
                        iframe.style.position = 'absolute';
                        iframe.style.width = '0';
                        iframe.style.height = '0';
                        iframe.style.border = '0';
                        iframe.src = "{$url}";
                        document.body.appendChild(iframe);

                        iframe.onload = function() {
                            iframe.contentWindow.focus();
                            iframe.contentWindow.print();
                        };
                    JS);
                        })

                        ->tooltip('Print'),

                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ])->dropdown()->tooltip('More actions')

            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    ExportBulkAction::make()
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchaseOrders::route('/'),
            'create' => Pages\CreatePurchaseOrder::route('/create'),
            'edit' => Pages\EditPurchaseOrder::route('/{record}/edit'),
        ];
    }
}
