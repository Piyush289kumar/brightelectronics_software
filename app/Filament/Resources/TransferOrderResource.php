<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransferOrderResource\Pages;
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
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use TomatoPHP\FilamentDocs\Filament\Resources\DocumentResource\Pages\PrintDocument;
use TomatoPHP\FilamentDocs\Models\Document;
use TomatoPHP\FilamentDocs\Models\DocumentTemplate;
use Filament\Tables\Filters\Filter;

class TransferOrderResource extends Resource
{
    protected static ?string $model = Invoice::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Purchase';
    protected static ?string $pluralLabel = 'Transfer Order Invoice';
    protected static ?int $navigationSort = 5;



    // 🔹 Show badge count (only invoices where document_type = 'invoice')
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('document_type', 'transfer_order')->count();
    }

    // 🔹 Badge color (always primary in your case)
    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }
    // (Optional) Add tooltip to the badge
    protected static ?string $navigationBadgeTooltip = 'Total Transfer orders';


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(3)
                    ->schema([
                        TextInput::make('number')
                            ->label(fn(callable $get) => match ($get('document_type')) {
                                'purchase_order' => 'PO Number',
                                'transfer_order' => 'Transfer Order Number',
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
                                return \App\Models\Vendor::create($data)->id;
                            }),

                    ]),

                Grid::make(4)
                    ->schema([
                        Select::make('document_type')
                            ->label('Type')
                            ->options([
                                'purchase_order' => 'Purchase Order',
                                'transfer_order' => 'Transfer Order Number',
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
                            ->default('transfer_order') // default selected option
                            ->reactive(), // if you want to use it in dependent logic

                        DatePicker::make('document_date')
                            ->label('Transfer Date')
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
                            ->schema([
                                Grid::make(12)
                                    ->schema([
                                        Grid::make(12)
                                            ->schema([
                                                Select::make('product_id')
                                                    ->label('Product')
                                                    ->options(Product::pluck('name', 'id'))
                                                    ->searchable()
                                                    ->required()
                                                    ->reactive()
                                                    ->createOptionForm([
                                                        Grid::make(2)
                                                            ->schema([
                                                                Forms\Components\TextInput::make('name')
                                                                    ->label('Product Name')
                                                                    ->placeholder('Enter product name') // <-- placeholder added
                                                                    ->required(),
                                                                Forms\Components\TextInput::make('selling_price')
                                                                    ->label('Selling Price')
                                                                    ->placeholder('Enter selling price') // <-- placeholder added
                                                                    ->numeric()
                                                                    ->default(0)
                                                                    ->required(),
                                                            ]),
                                                    ])
                                                    ->createOptionUsing(function (array $data) {
                                                        $sku = 'PRD-' . str_pad(Product::max('id') + 1, 5, '0', STR_PAD_LEFT);
                                                        $product = Product::create([
                                                            'name' => $data['name'],
                                                            'selling_price' => $data['selling_price'] ?? 0,
                                                            'is_active' => false,
                                                            'sku' => $sku,
                                                            'purchase_price' => 0,
                                                            'track_inventory' => false,
                                                        ]);
                                                        return $product->id;
                                                    })
                                                    ->afterStateUpdated(function (callable $set, $get, $state) {
                                                        if ($state) {
                                                            $product = Product::find($state);
                                                            if ($product) {
                                                                $set('unit_price', $product->selling_price);
                                                                // Set GST rates to 0 as tax slab is removed
                                                                $set('cgst_rate', 0);
                                                                $set('sgst_rate', 0);
                                                                $set('igst_rate', 0);
                                                                InvoiceResource::recalculateItem($set, $get);
                                                            }
                                                        }
                                                    })
                                                    ->columnSpan(5),
                                                TextInput::make('quantity')
                                                    ->label('Quantity')
                                                    ->numeric()
                                                    ->required()
                                                    ->default(0) // ensures it starts at 0
                                                    ->placeholder('0') // optional, shows 0 when empty
                                                    ->reactive()
                                                    ->afterStateUpdated(function (callable $set, callable $get, $state) {
                                                        InvoiceResource::recalculateItem($set, $get);
                                                    })
                                                    ->columnSpan(2),
                                                TextInput::make('unit_price')
                                                    ->label('Unit Price')
                                                    ->numeric()
                                                    ->required()
                                                    ->reactive()
                                                    ->afterStateUpdated(function (callable $set, callable $get, $state) {
                                                        InvoiceResource::recalculateItem($set, $get);
                                                    })
                                                    ->columnSpan(2),
                                                TextInput::make('cgst_rate')
                                                    ->label('CGST (%)')
                                                    ->numeric()
                                                    ->default(0)
                                                    ->reactive()
                                                    ->afterStateUpdated(function (callable $set, callable $get, $state) {
                                                        InvoiceResource::recalculateItem($set, $get);
                                                    })
                                                    ->columnSpan(1),
                                                TextInput::make('sgst_rate')
                                                    ->label('SGST (%)')
                                                    ->numeric()
                                                    ->default(0)
                                                    ->reactive()
                                                    ->afterStateUpdated(function (callable $set, callable $get, $state) {
                                                        InvoiceResource::recalculateItem($set, $get);
                                                    })
                                                    ->columnSpan(1),
                                                TextInput::make('igst_rate')
                                                    ->label('IGST (%)')
                                                    ->numeric()
                                                    ->default(0)
                                                    ->reactive()
                                                    ->afterStateUpdated(function (callable $set, callable $get, $state) {
                                                        InvoiceResource::recalculateItem($set, $get);
                                                    })
                                                    ->columnSpan(1),
                                            ]),
                                        Grid::make(12)
                                            ->schema([
                                                TextInput::make('cgst_amount')->label('CGST Amount')->numeric()->disabled()->dehydrated(true)->columnSpan(3),
                                                TextInput::make('sgst_amount')->label('SGST Amount')->numeric()->disabled()->dehydrated(true)->columnSpan(3),
                                                TextInput::make('igst_amount')->label('IGST Amount')->numeric()->disabled()->dehydrated(true)->columnSpan(3),
                                                TextInput::make('total_amount')->label('Total Amount')->numeric()->disabled()->dehydrated(true)->columnSpan(3),
                                            ]),
                                    ]),
                            ]),
                    ]),
                Grid::make(5)
                    ->schema([
                        TextInput::make('taxable_value')
                            ->label('Taxable Value')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(true)
                            ->reactive()
                            ->default(0),
                        TextInput::make('cgst_amount')
                            ->label('CGST Amount')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(true)
                            ->reactive()
                            ->default(0),
                        TextInput::make('sgst_amount')
                            ->label('SGST Amount')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(true)
                            ->reactive()
                            ->default(0),
                        TextInput::make('igst_amount')
                            ->label('IGST Amount')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(true)
                            ->reactive()
                            ->default(0),
                        TextInput::make('total_tax')
                            ->label('Total Tax')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(true)
                            ->reactive()
                            ->default(0),
                        TextInput::make('discount')
                            ->label('Discount')
                            ->placeholder('0') // optional, shows 0 when empty
                            ->numeric()
                            ->default(0)
                            ->reactive()
                            ->afterStateUpdated(function (callable $set, callable $get, $state) {
                                // only recalc totals here, no item recalcs
                                InvoiceResource::recalculateInvoiceTotals($set, $get);
                            }),
                        TextInput::make('total_amount')
                            ->label('Total Amount')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(true)
                            ->reactive()
                            ->default(0),
                        Select::make('status')->label('Payment Status')->options([
                            'pending' => 'Pending',
                            'paid' => 'Paid',
                            'partial' => 'Partial',
                            'cancelled' => 'Cancelled',
                        ])->default('pending')->required(),
                    ]),
                Textarea::make('notes')->label('Additional Notes')->rows(3)->columnSpanFull(),
            ]);
    }
    public static function recalculateInvoiceTotals(callable $set, callable $get): void
    {
        $items = $get('items') ?? [];
        $taxableValue = 0;
        $cgstAmount = 0;
        $sgstAmount = 0;
        $igstAmount = 0;
        $totalAmount = 0;
        foreach ($items as $index => $item) {
            $quantity = (float) ($item['quantity'] ?? 0);
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $discount = (float) ($item['discount'] ?? 0);
            $cgstRate = (float) ($item['cgst_rate'] ?? 0);
            $sgstRate = (float) ($item['sgst_rate'] ?? 0);
            $igstRate = (float) ($item['igst_rate'] ?? 0);
            $taxable = ($unitPrice * $quantity) - $discount;
            $itemCgstAmount = ($taxable * $cgstRate) / 100;
            $itemSgstAmount = ($taxable * $sgstRate) / 100;
            $itemIgstAmount = ($taxable * $igstRate) / 100;
            $itemTotalAmount = $taxable + $itemCgstAmount + $itemSgstAmount + $itemIgstAmount;
            // Update item amounts in the repeater
            $items[$index]['cgst_amount'] = round($itemCgstAmount, 2);
            $items[$index]['sgst_amount'] = round($itemSgstAmount, 2);
            $items[$index]['igst_amount'] = round($itemIgstAmount, 2);
            $items[$index]['total_amount'] = round($itemTotalAmount, 2);
            // Sum totals
            $taxableValue += $taxable;
            $cgstAmount += $itemCgstAmount;
            $sgstAmount += $itemSgstAmount;
            $igstAmount += $itemIgstAmount;
            $totalAmount += $itemTotalAmount;
        }
        $totalTax = $cgstAmount + $sgstAmount + $igstAmount;
        // Cast discount safely to float
        $invoiceDiscount = (float) ($get('discount') ?? 0);
        $totalAmountAfterDiscount = $totalAmount - $invoiceDiscount;
        // Update form values
        $set('items', $items); // <-- make sure each item's total updates
        $set('taxable_value', round($taxableValue, 2));
        $set('cgst_amount', round($cgstAmount, 2));
        $set('sgst_amount', round($sgstAmount, 2));
        $set('igst_amount', round($igstAmount, 2));
        $set('total_tax', round($totalTax, 2));
        $set('total_amount', round($totalAmountAfterDiscount, 2));
    }
    public static function recalculateItem(callable $set, callable $get): void
    {
        $quantity = (float) ($get('quantity') ?? 0);
        $unitPrice = (float) ($get('unit_price') ?? 0);
        $discount = (float) ($get('discount') ?? 0);
        $cgstRate = (float) ($get('cgst_rate') ?? 0);
        $sgstRate = (float) ($get('sgst_rate') ?? 0);
        $igstRate = (float) ($get('igst_rate') ?? 0);
        $taxable = ($unitPrice * $quantity) - $discount;
        $cgstAmount = ($taxable * $cgstRate) / 100;
        $sgstAmount = ($taxable * $sgstRate) / 100;
        $igstAmount = ($taxable * $igstRate) / 100;
        $totalAmount = $taxable + $cgstAmount + $sgstAmount + $igstAmount;
        $set('cgst_amount', round($cgstAmount, 2));
        $set('sgst_amount', round($sgstAmount, 2));
        $set('igst_amount', round($igstAmount, 2));
        $set('total_amount', round($totalAmount, 2));
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

    /**
     * Show Only Document transfer_order
     */

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('document_type', 'transfer_order'); // Only invoices
    }
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('billable.name')->label('Source Store')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('destinationStore.name')->label('Destination Store')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('document_date')->date()->sortable(),                                
                Tables\Columns\TextColumn::make('status')->sortable(),
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
                // 👇 Generate (or regenerate) and then View
                Action::make('generateAndViewDocument')
                    ->label('View Document')
                    ->color('info')
                    ->icon('heroicon-s-eye')
                    ->modalHeading('View Document')
                    ->modalContent(function ($record) {
                        // 1) Fetch template
                        $template = DocumentTemplate::find(4);
                        $templateBody = (string) ($template->body ?? '');
                        // 2) Build replacements
                        $itemsHtml = $record->items->map(function ($item, $index) {
                            return "<tr style='border-bottom: 1px solid #000;'>
                <td style='padding:6px; text-align:center; font-weight: 900;'>" . ($index + 1) . "</td>
                <td style='padding:6px;'>{$item->product->name}</td>
                <td style='padding:6px; text-align:center;'>{$item->quantity}</td>
                <td style='padding:6px; text-align:center;'>₹ " . number_format($item->unit_price, 2) . "</td>
                <td style='padding:6px; text-align:center;'>₹ " . number_format($item->discount, 2) . "</td>
                <td style='padding:6px; text-align:center;'>₹ " . number_format($item->total_amount, 2) . "</td>
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
                            '$DISCOUNT' => number_format($record->discount, 2),
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
                            'document_template_id' => 4,
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
                    ->iconButton()
                    ->tooltip('View Document'),
                // ✅ Generate and then Print (with print preview)
                Action::make('generateAndPrintDocument')
                    ->label('Print Document')
                    ->color('warning')
                    ->icon('heroicon-s-printer')
                    ->action(function ($record, $livewire) {
                        // 1) Fetch template
                        $template = DocumentTemplate::find(4);
                        $templateBody = (string) ($template->body ?? '');
                        // 2) Build replacements
                        $itemsHtml = $record->items->map(function ($item, $index) {
                            return "<tr style='border-bottom: 1px solid #000;'>
                <td style='padding:6px; text-align:center; font-weight: 900;'>" . ($index + 1) . "</td>
                <td style='padding:6px;'>{$item->product->name}</td>
                <td style='padding:6px; text-align:center;'>{$item->quantity}</td>
                <td style='padding:6px; text-align:center;'>₹ " . number_format($item->unit_price, 2) . "</td>
                <td style='padding:6px; text-align:center;'>₹ " . number_format($item->discount, 2) . "</td>
                <td style='padding:6px; text-align:center;'>₹ " . number_format($item->total_amount, 2) . "</td>
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
                            '$DISCOUNT' => number_format($record->discount, 2),
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
                            'document_template_id' => 4,
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
                    ->iconButton()
                    ->tooltip('Print Document'),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListTransferOrders::route('/'),
            'create' => Pages\CreateTransferOrder::route('/create'),
            'edit' => Pages\EditTransferOrder::route('/{record}/edit'),
        ];
    }
}
