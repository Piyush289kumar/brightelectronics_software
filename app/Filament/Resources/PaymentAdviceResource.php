<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentAdviceItemResource\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\PaymentAdviceResource\Pages;
use App\Filament\Resources\PaymentAdviceResource\RelationManagers;
use App\Models\Invoice;
use App\Models\PaymentAdvice;
use App\Models\Vendor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

use Illuminate\Support\Carbon;
use TomatoPHP\FilamentDocs\Filament\Resources\DocumentResource\Pages\PrintDocument;
use TomatoPHP\FilamentDocs\Models\Document;
use TomatoPHP\FilamentDocs\Models\DocumentTemplate;

class PaymentAdviceResource extends Resource
{
    protected static ?string $model = PaymentAdvice::class;
    protected static ?string $navigationGroup = 'Advices';
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Payment Advice';
    protected static ?string $pluralModelLabel = 'Payment Advices';
    protected static ?string $label = 'Payment Advices';
    protected static ?string $pluralLabel = 'Payment Advices';


    public static function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Section::make('Payment Advice')
                ->schema([
                    Forms\Components\DatePicker::make('date')
                        ->label('Payment Date')
                        ->required()
                        ->default(now()),

                    Forms\Components\Select::make('vendor_id')
                        ->label('Vendor')
                        ->options(Vendor::pluck('name', 'id'))
                        ->searchable()
                        ->required(),
                    Forms\Components\DatePicker::make('payment_advice_start_date')
                        ->label('Start Date')
                        ->default(now()->subDays(7))   // ⬅️ 7 days previous
                        ->required(),

                    Forms\Components\DatePicker::make('payment_advice_end_date')
                        ->label('End Date')
                        ->default(now())               // ⬅️ today
                        ->required(),
                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('load_po')
                            ->label('Load Purchase Orders')
                            ->color('success')
                            ->extraAttributes(['class' => 'w-full mt-6'])
                            ->action(function ($get, $set) {

                                $vendorId = $get('vendor_id');
                                $start = $get('payment_advice_start_date');
                                $end = $get('payment_advice_end_date');

                                if (!$vendorId || !$start || !$end) {
                                    return;
                                }

                                // 🔒 Already used PO IDs
                                $usedPoIds = \App\Models\PaymentAdviceItem::pluck('purchase_order_id')->toArray();

                                // ✅ Fetch ONLY unused Purchase Orders
                                $pos = Invoice::query()
                                    ->where('document_type', 'purchase_order')
                                    ->where('billable_id', $vendorId)
                                    ->whereBetween('document_date', [$start, $end])
                                    ->whereNotIn('id', $usedPoIds) // ⭐ KEY LINE
                                    ->get();

                                $rows = [];

                                foreach ($pos as $po) {
                                    $rows[] = [
                                        'purchase_order_id' => $po->id,
                                        'po_date' => $po->document_date,
                                        'po_number' => $po->number,
                                        'amount' => $po->total_amount,
                                        'payment_doc_no' => 0,
                                    ];
                                }

                                $set('items_data', $rows);
                            }),
                    ]),

                ])
                ->columns(5),

            Forms\Components\Section::make('Payment Advice Items')
                ->visible(fn(string $operation) => $operation === 'create')
                ->schema([
                    Forms\Components\Repeater::make('items_data')
                        ->visible(fn(string $operation) => $operation === 'create')
                        ->schema([
                            Forms\Components\DatePicker::make('po_date')->label('PON Date')->disabled(),
                            Forms\Components\TextInput::make('po_number')->label('PON')->disabled(),
                            Forms\Components\TextInput::make('invoice')->label('Invoice No.'),
                            Forms\Components\TextInput::make('amount')->label('Invoice Amount'),
                            Forms\Components\TextInput::make('payment_doc_no')->default(0)->label('Payment doc no.'),
                        ])
                        ->columns(5),
                ])
        ]);
    }



    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make("vendor.name")->searchable()->sortable(),
                Tables\Columns\TextColumn::make("date")->label('Payment Date')->date()->searchable()->sortable(),
                Tables\Columns\TextColumn::make("payment_advice_start_date")->label('Start Date')->date()->searchable()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make("payment_advice_end_date")->label('End Date')->date()->searchable()->sortable()->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([

                    /* =========================
      PREVIEW PAYMENT ADVICE
      ========================= */
                    Tables\Actions\Action::make('preview_payment_advice')
                        ->label('Preview')
                        ->icon('heroicon-s-eye')
                        ->color('info')
                        ->modalHeading('Payment Advice Preview')
                        ->modalButton('Print')
                        ->modalContent(function (PaymentAdvice $record) {

                            // 1️⃣ Fetch template (ID = 16)
                            $template = DocumentTemplate::find(16);
                            $templateBody = (string) ($template->body ?? '');

                            // 2️⃣ Build items HTML
                            $itemsHtml = $record->items->map(function ($item, $index) {
                                return "
                <tr style='border-bottom:1px solid #000;'>
                    <td style='padding:6px; text-align:center;'>" . ($index + 1) . "</td>
                    <td style='padding:6px; text-align:center;'>" . \Carbon\Carbon::parse($item->po_date)->format('d-m-Y') . "</td>
                    <td style='padding:6px; text-align:center;'>" . ($item->purchaseOrder?->number ?? '') . "</td>
                    <td style='padding:6px; text-align:center;'>" . ($item->invoice_no ?? '') . "</td>
                    <td style='padding:6px; text-align:right;'>₹ " . number_format($item->amount, 2) . "</td>
                    <td style='padding:6px; text-align:center;'>" . $item->payment_doc_no . "</td>
                </tr>";
                            })->implode('');

                            $totalAmount = (float) $record->items->sum('amount');

                            // 3️⃣ Replace placeholders
                            $map = [
                                '$ACCOUNT_NAME' => $record->vendor->name ?? '',
                                '$ACCOUNT_ADDRESS' => $record->vendor->address ?? '',
                                '$ACCOUNT_PHONE' => $record->vendor->phone ?? '',
                                '$ACCOUNT_GSTIN' => $record->vendor->gst_number ?? '',
                                '$ACCOUNT_STATE' => $record->vendor->state ?? '',
                                '$PAYMENT_DOC_NO' => $record->payment_doc_no,
                                '$DATE' => \Carbon\Carbon::parse($record->date)->format('d-m-Y'),
                                '$AMOUNT' => number_format($totalAmount, 2),
                                '$AMOUNT_IN_WORDS' => ucfirst(
                                    \NumberFormatter::create('en_IN', \NumberFormatter::SPELLOUT)->format($totalAmount)
                                ),
                                '$ITEMS' => $itemsHtml,
                            ];

                            $body = $templateBody;
                            foreach ($map as $key => $value) {
                                $body = str_replace($key, (string) $value, $body);
                            }

                            // 4️⃣ Delete old document
                            if ($record->document_id) {
                                Document::where('id', $record->document_id)->delete();
                            }

                            // 5️⃣ Create document
                            $document = Document::create([
                                'document_template_id' => 16,
                                'model_type' => PaymentAdvice::class,
                                'model_id' => $record->id,
                                'body' => $body,
                            ]);

                            // 6️⃣ Save document_id
                            // $record->document_id = $document->id;
                            $record->save();

                            // 7️⃣ Return preview
                            return view('filament-docs::print', ['record' => $document]);
                        }),

                    /* =========================
                       PRINT PAYMENT ADVICE
                       ========================= */
                    Tables\Actions\Action::make('print_payment_advice')
                        ->label('Print')
                        ->icon('heroicon-s-printer')
                        ->color('warning')
                        ->action(function (PaymentAdvice $record, $livewire) {

                            // 🔁 SAME LOGIC AS PREVIEW (generate first)
                
                            $template = DocumentTemplate::find(16);
                            $templateBody = (string) ($template->body ?? '');

                            $itemsHtml = $record->items->map(function ($item, $index) {
                                return "
                <tr style='border-bottom:1px solid #000;'>
                    <td style='padding:6px; text-align:center;'>" . ($index + 1) . "</td>
                    <td style='padding:6px; text-align:center;'>" . \Carbon\Carbon::parse($item->po_date)->format('d-m-Y') . "</td>
                    <td style='padding:6px; text-align:center;'>" . ($item->purchaseOrder?->number ?? '') . "</td>
                    <td style='padding:6px; text-align:center;'>" . ($item->invoice_no ?? '') . "</td>
                    <td style='padding:6px; text-align:right;'>₹ " . number_format($item->amount, 2) . "</td>
                    <td style='padding:6px; text-align:center;'>" . $item->payment_doc_no . "</td>
                </tr>";
                            })->implode('');

                            $totalAmount = (float) $record->items->sum('amount');

                            $map = [
                                '$ACCOUNT_NAME' => $record->vendor->name ?? '',
                                '$ACCOUNT_ADDRESS' => $record->vendor->address ?? '',
                                '$ACCOUNT_PHONE' => $record->vendor->phone ?? '',
                                '$ACCOUNT_GSTIN' => $record->vendor->gst_number ?? '',
                                '$ACCOUNT_STATE' => $record->vendor->state ?? '',
                                '$PAYMENT_DOC_NO' => $record->payment_doc_no,
                                '$DATE' => \Carbon\Carbon::parse($record->date)->format('d-m-Y'),
                                '$AMOUNT' => number_format($totalAmount, 2),
                                '$AMOUNT_IN_WORDS' => ucfirst(
                                    \NumberFormatter::create('en_IN', \NumberFormatter::SPELLOUT)->format($totalAmount)
                                ),
                                '$ITEMS' => $itemsHtml,
                            ];

                            $body = $templateBody;
                            foreach ($map as $key => $value) {
                                $body = str_replace($key, (string) $value, $body);
                            }

                            if ($record->document_id) {
                                Document::where('id', $record->document_id)->delete();
                            }

                            $document = Document::create([
                                'document_template_id' => 16,
                                'model_type' => PaymentAdvice::class,
                                'model_id' => $record->id,
                                'body' => $body,
                            ]);

                            // $record->document_id = $document->id;
                            $record->save();

                            // 🖨 PRINT
                            $url = PrintDocument::getUrl(['record' => $document->id]);

                            $livewire->js(<<<JS
                const iframe = document.createElement('iframe');
                iframe.style.position = 'absolute';
                iframe.style.width = '0';
                iframe.style.height = '0';
                iframe.style.border = '0';
                iframe.src = "{$url}";
                document.body.appendChild(iframe);
                iframe.onload = function () {
                    iframe.contentWindow.focus();
                    iframe.contentWindow.print();
                };
            JS);
                        }),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),

                ])->dropdown()->tooltip('More actions'),
            ])

            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaymentAdvice::route('/'),
            'create' => Pages\CreatePaymentAdvice::route('/create'),
            'edit' => Pages\EditPaymentAdvice::route('/{record}/edit'),
        ];
    }

    protected static function generatePaymentAdviceDocument(PaymentAdvice $record)
    {
        // 1️⃣ Fetch template
        $template = DocumentTemplate::find(16);
        $body = (string) ($template->body ?? '');

        // 2️⃣ Build ITEMS rows
        $itemsHtml = $record->items->map(function ($item, $index) {

            $poNumber = $item->purchaseOrder?->number ?? '';
            $invoiceNo = $item->invoice_no ?? '';
            $date = Carbon::parse($item->po_date)->format('d-m-Y');

            return "
        <tr style='border-bottom:1px solid #000;'>
            <td style='padding:6px; text-align:center;'>" . ($index + 1) . "</td>
            <td style='padding:6px; text-align:center;'>{$date}</td>
            <td style='padding:6px; text-align:center;'>{$poNumber}</td>
            <td style='padding:6px; text-align:center;'>{$invoiceNo}</td>
            <td style='padding:6px; text-align:right;'>₹ " . number_format($item->amount, 2) . "</td>
            <td style='padding:6px; text-align:center;'>{$item->payment_doc_no}</td>
        </tr>";
        })->implode('');

        // 3️⃣ Calculate total amount
        $totalAmount = $record->items->sum('amount');

        // 4️⃣ Replace template variables
        $map = [
            '$ACCOUNT_NAME' => $record->vendor->name ?? '',
            '$ACCOUNT_ADDRESS' => $record->vendor->address ?? '',
            '$ACCOUNT_PHONE' => $record->vendor->phone ?? '',
            '$ACCOUNT_GSTIN' => $record->vendor->gst_number ?? '',
            '$ACCOUNT_STATE' => $record->vendor->state ?? '',
            '$PAYMENT_DOC_NO' => $record->payment_doc_no,
            '$DATE' => Carbon::parse($record->date)->format('d-m-Y'),
            '$AMOUNT' => number_format($totalAmount, 2),
            '$AMOUNT_IN_WORDS' => \NumberFormatter::create('en_IN', \NumberFormatter::SPELLOUT)->format($record->total_amount),
            '$ITEMS' => $itemsHtml,
        ];

        foreach ($map as $key => $value) {
            $body = str_replace($key, (string) $value, $body);
        }

        // 5️⃣ Delete old document if exists
        if ($record->document_id) {
            Document::where('id', $record->document_id)->delete();
        }

        // 6️⃣ Create document
        $document = Document::create([
            'document_template_id' => 16,
            'model_type' => PaymentAdvice::class,
            'model_id' => $record->id,
            'body' => $body,
        ]);

        $record->save();

        return view('filament-docs::print', ['record' => $document]);
    }

    protected static function printPaymentAdvice(PaymentAdvice $record, $livewire): void
    {
        // 1️⃣ Generate (or regenerate) the document
        self::generatePaymentAdviceDocument($record);

        // 2️⃣ Open print preview via iframe
        $url = PrintDocument::getUrl(['record' => $record->document_id]);

        $livewire->js(<<<JS
        const iframe = document.createElement('iframe');
        iframe.style.position = 'absolute';
        iframe.style.width = '0';
        iframe.style.height = '0';
        iframe.style.border = '0';
        iframe.src = "{$url}";
        document.body.appendChild(iframe);
        iframe.onload = function () {
            iframe.contentWindow.focus();
            iframe.contentWindow.print();
        };
    JS);
    }


}
