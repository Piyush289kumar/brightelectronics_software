<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseOrderResource\Pages;
use App\Filament\Resources\EstimateResource\RelationManagers;
use App\Models\Estimate;
use Filament\Forms;
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
class PurchaseOrderResource extends Resource
{
    protected static ?string $model = Invoice::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Purchase';
    protected static ?string $pluralLabel = 'Purchase Order';
    protected static ?string $label = 'Purchase Orders';

    protected static ?int $navigationSort = 3;

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
                Grid::make(3)
                    ->schema([
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
                        Repeater::make('items')
                            ->relationship('items')
                            ->label('Items')
                            ->required()
                            ->reactive()
                            // Recalculate invoice totals only once when entire items array updates
                            ->afterStateUpdated(function (callable $set, callable $get, $state) {
                                InvoiceResource::recalculateInvoiceTotals($set, $get);
                            })
                            ->addActionLabel('Add Item')
                            ->addAction(fn($action) => $action->color('primary'))

                            ->schema([
                                Grid::make(12)
                                    ->schema([
                                        Grid::make(12)
                                            ->schema([




                                                Select::make('product_id')
                                                    ->label('Product')
                                                    ->options(function () {
                                                        return Product::query()
                                                            ->orderBy('name')
                                                            ->limit(200)
                                                            ->get()
                                                            ->mapWithKeys(fn($p) => [
                                                                $p->id => "{$p->name} ({$p->barcode})",
                                                            ]);
                                                    })
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
                                                        InvoiceResource::recalculateItem($set, $get);
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
                                                        InvoiceResource::recalculateItem($set, $get);
                                                    })
                                                    ->columnSpan(2),
                                                TextInput::make('unit_price')
                                                    ->label('Unit Price')
                                                    ->numeric()
                                                    ->required()
                                                    ->lazy() // <-- update only on blur
                                                    // ->reactive()
                                                    ->afterStateUpdated(function (callable $set, callable $get, $state) {
                                                        InvoiceResource::recalculateItem($set, $get);
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
                                                        InvoiceResource::recalculateItem($set, $get);
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
                                                    ->afterStateUpdated(fn($set, $get) => InvoiceResource::recalculateItem($set, $get))
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
            $gstAmount = ($amountAfterDiscount * $gstRate) / 100;
            // Final total per item
            $itemTotal = $amountAfterDiscount + $gstAmount;
            // Save calculated values into item array
            $items[$index]['discount_amount_per_item'] = round($discountAmount, 2);
            $items[$index]['gst_amount'] = round($gstAmount, 2);
            $items[$index]['total_amount'] = round($itemTotal, 2);
            // Sum totals
            $taxableValue += $amountAfterDiscount;
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
        // Subtotal before discount
        $subtotal = $quantity * $unitPrice;
        // Discount amount per item
        $discountAmount = ($subtotal * $discountPercent) / 100;
        $set('discount_amount_per_item', round($discountAmount, 2));
        // Amount after discount
        $amountAfterDiscount = $subtotal - $discountAmount;
        // GST amount
        $gstAmount = ($amountAfterDiscount * $gstRate) / 100;
        $set('gst_amount', round($gstAmount, 2));
        // Final total
        $total = $amountAfterDiscount + $gstAmount;
        $set('total_amount', round($total, 2));
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

        // ✅ Restrict visibility for non-admin users
        if (!$user->hasRole(['Administrator', 'Developer', 'admin']) && $user->email !== 'vipprow@gmail.com') {
            $query->where('created_by', $user->id);
        }

        return $query;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')->sortable()->searchable()->label('Invoice No.'),
                Tables\Columns\TextColumn::make('billable.name')->label('Billed To')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('document_date')->date()->sortable()->label('Invoice Date'),
                Tables\Columns\TextColumn::make('due_date')->date()->sortable()->label('Due Date')->toggleable(),

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
                            // 1) Fetch template
                            $template = DocumentTemplate::find(6);
                            $templateBody = (string) ($template->body ?? '');
                            // 2) Build replacements
                            $itemsHtml = $record->items->map(function ($item, $index) {
                                $discountPercent = $item->discount ?? 0; // discount %
                                $discountAmount = $item->discount_amount_per_item ?? 0; // discount ₹
                                $gstRate = $item->gst_rate ?? 0; // GST %
                                $gstAmount = $item->gst_amount ?? 0; // GST ₹
                
                                return "<tr style='border-bottom: 1px solid #000;'>
                <td style='padding:6px; text-align:center; font-weight: 900;'>" . ($index + 1) . "</td>
                <td style='padding:6px;'>{$item->product->name}</td>
                <td style='padding:6px; text-align:center;'>{$item->quantity}</td>
                <td style='padding:2px; text-align:center;'>₹ " . number_format($item->unit_price, 2) . "</td>
                <td style='padding:2px; text-align:center;'>₹ " . number_format($discountAmount, 2) . "<br><small>(" . number_format($discountPercent, 0) . "%)</small></td>
                <td style='padding:2px; text-align:center;'>₹ " . number_format($gstAmount, 2) . "<br><small>(" . number_format($gstRate, 0) . "%)</small></td>
                <td style='padding:2px; text-align:center;'>₹ " . number_format($item->total_amount, 2) . "</td>
            </tr>";
                            })->implode('');
                            $map = [
                                '$NUMBER' => (string) $record->number,
                                '$DOCUMENT_DATE' => Carbon::parse($record->document_date)->format('d-m-Y'),
                                '$PLACE_OF_SUPPLY' => (string) ($record->place_of_supply ?? ''),
                                '$ACCOUNT_NAME' => (string) ($record->billable->name ?? ''),
                                '$ACCOUNT_ADDRESS' => (string) ($record->billable->address ?? ''),
                                '$ACCOUNT_PHONE' => (string) ($record->billable->phone ?? ''),
                                '$ACCOUNT_GSTIN' => (string) ($record->billable->gst_number ?? ''),
                                '$ACCOUNT_STATE' => (string) ($record->billable->state ?? ''),
                                '$SUB_TOTAL' => number_format($record->items->sum(fn($i) => (float) $i->unit_price * (float) $i->quantity), 2),
                                '$DISCOUNT' => number_format($record->discount_amount, 2),
                                '$GST_AMOUNT' => number_format($record->gst_amount, 2),
                                '$TOTAL_AMOUNT' => number_format($record->total_amount, 2),
                                '$AMOUNT_RECEIVED' => number_format($record->amount_received, 2),
                                '$AMOUNT_BALANCE' => number_format($record->total_amount - $record->amount_received, 2),
                                '$YOU_SAVED' => number_format($record->discount_amount, 2),
                                '$AMOUNT_IN_WORDS' => \NumberFormatter::create('en_IN', \NumberFormatter::SPELLOUT)->format($record->total_amount),
                                '$ITEMS' => $itemsHtml,
                            ];
                            // 3) Replace vars inside template HTML
                            $body = $templateBody;
                            foreach ($map as $key => $value) {
                                $body = str_replace($key, (string) $value, $body);
                            }
                            // 4) Delete old document if exists
                            if (!empty($record->document_id)) {
                                Document::where('id', $record->document_id)->delete();
                            }
                            // 5) Create new document
                            $document = Document::create([
                                'document_template_id' => 6,
                                'model_type' => Invoice::class,
                                'model_id' => $record->id,
                                'body' => $body,
                            ]);
                            // 6) Update invoice with new document_id
                            $record->document_id = $document->id;
                            $record->save();
                            // 7) Show document preview inside modal
                            return view('filament-docs::print', ['record' => $document]);
                        })
                        ->tooltip('Preview'),

                    // ✅ Generate and then Print (with print preview)
                    Tables\Actions\Action::make('generateAndPrintDocument')
                        ->label('Print')
                        ->color('warning')
                        ->icon('heroicon-s-printer')
                        ->tooltip('Print Document')
                        ->action(function ($record, $livewire) {
                            // 1) Fetch template
                            $template = DocumentTemplate::find(6);
                            $templateBody = (string) ($template->body ?? '');
                            // 2) Build replacements
                            $itemsHtml = $record->items->map(function ($item, $index) {
                                $discountPercent = $item->discount ?? 0; // discount %
                                $discountAmount = $item->discount_amount_per_item ?? 0; // discount ₹
                                $gstRate = $item->gst_rate ?? 0; // GST %
                                $gstAmount = $item->gst_amount ?? 0; // GST ₹
                
                                return "<tr style='border-bottom: 1px solid #000;'>
                <td style='padding:6px; text-align:center; font-weight: 900;'>" . ($index + 1) . "</td>
                <td style='padding:6px;'>{$item->product->name}</td>
                <td style='padding:6px; text-align:center;'>{$item->quantity}</td>
                <td style='padding:2px; text-align:center;'>₹ " . number_format($item->unit_price, 2) . "</td>
                <td style='padding:2px; text-align:center;'>₹ " . number_format($discountAmount, 2) . "<br><small>(" . number_format($discountPercent, 0) . "%)</small></td>
                <td style='padding:2px; text-align:center;'>₹ " . number_format($gstAmount, 2) . "<br><small>(" . number_format($gstRate, 0) . "%)</small></td>
                <td style='padding:2px; text-align:center;'>₹ " . number_format($item->total_amount, 2) . "</td>
            </tr>";
                            })->implode('');
                            $map = [
                                '$NUMBER' => (string) $record->number,
                                '$DOCUMENT_DATE' => Carbon::parse($record->document_date)->format('d-m-Y'),
                                '$PLACE_OF_SUPPLY' => (string) ($record->place_of_supply ?? ''),
                                '$ACCOUNT_NAME' => (string) ($record->billable->name ?? ''),
                                '$ACCOUNT_ADDRESS' => (string) ($record->billable->address ?? ''),
                                '$ACCOUNT_PHONE' => (string) ($record->billable->phone ?? ''),
                                '$ACCOUNT_GSTIN' => (string) ($record->billable->gst_number ?? ''),
                                '$ACCOUNT_STATE' => (string) ($record->billable->state ?? ''),
                                '$SUB_TOTAL' => number_format($record->items->sum(fn($i) => (float) $i->unit_price * (float) $i->quantity), 2),
                                '$DISCOUNT' => number_format($record->discount_amount, 2),
                                '$GST_AMOUNT' => number_format($record->gst_amount, 2),
                                '$TOTAL_AMOUNT' => number_format($record->total_amount, 2),
                                '$AMOUNT_RECEIVED' => number_format($record->amount_received, 2),
                                '$AMOUNT_BALANCE' => number_format($record->total_amount - $record->amount_received, 2),
                                '$YOU_SAVED' => number_format($record->discount, 2),
                                '$AMOUNT_IN_WORDS' => \NumberFormatter::create('en_IN', \NumberFormatter::SPELLOUT)->format($record->total_amount),
                                '$ITEMS' => $itemsHtml,
                            ];
                            // 3) Replace vars inside template HTML
                            $body = $templateBody;
                            foreach ($map as $key => $value) {
                                $body = str_replace($key, (string) $value, $body);
                            }
                            // 4) Delete old document if exists
                            if (!empty($record->document_id)) {
                                Document::where('id', $record->document_id)->delete();
                            }
                            // 5) Create new document
                            $document = Document::create([
                                'document_template_id' => 6,
                                'model_type' => Invoice::class,
                                'model_id' => $record->id,
                                'body' => $body,
                            ]);
                            // 6) Update invoice with new document_id
                            $record->document_id = $document->id;
                            $record->save();
                            // 7) Trigger print preview with hidden iframe
                            $url = PrintDocument::getUrl(['record' => $document->id]);
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
