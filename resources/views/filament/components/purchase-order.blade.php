<!-- resources/views/filament/components/purchase-order.blade.php -->

<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 8px 18px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9.5px;
            color: #000;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .no-border td,
        .no-border th {
            border: none;
        }

        /* ===== Half-page wrapper ===== */
        .half-copy {
            height: 138mm;
            overflow: hidden;
            box-sizing: border-box;
            padding: 5px 4px;
        }

        /* ===== Cut line ===== */
        .cut-line-wrapper {
            width: 100%;
            text-align: center;
            border-top: 1px dashed #000;
            position: relative;
            margin: 3px 0;
            height: 1px;
        }

        .cut-line-label {
            position: relative;
            top: -8px;
            background: #fff;
            padding: 0 8px;
            font-size: 9px;
            color: #555;
        }

        .copy-badge {
            text-align: right;
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
            border: 1px solid #000;
            padding: 2px 8px;
            display: inline-block;
            float: right;
        }

        /* Header */
        .brand-table td {
            vertical-align: middle;
            border: none;
            padding: 0;
        }

        .brand-logo {
            width: 32px;
            height: 32px;
        }

        .brand-name {
            font-size: 16px;
            font-weight: 800;
            padding-left: 8px;
        }

        /* Info box */
        .info-table {
            border: 1px solid #000;
            margin-top: 6px;
        }

        .info-table td {
            border: 1px solid #000;
            padding: 3px 6px;
            vertical-align: top;
        }

        .info-table b {
            font-weight: bold;
        }

        .info-table .muted {
            font-size: 8.5px;
            color: #333;
        }

        /* Items table */
        .items-table {
            border: 1px solid #000;
            margin-top: 6px;
        }

        .items-table th,
        .items-table td {
            border: 1px solid #000;
            padding: 3px 5px;
        }

        .items-table th {
            font-weight: bold;
            text-align: left;
            background-color: #fff;
            font-size: 9px;
        }

        .items-table .text-center {
            text-align: center;
        }

        .items-table .text-right {
            text-align: right;
        }

        .items-table small {
            font-size: 7.5px;
            color: #333;
        }

        /* Totals box */
        .totals-table {
            margin-top: 4px;
        }

        .totals-table td {
            border: none;
            padding: 1px 6px;
        }

        .totals-table .totals-label {
            text-align: right;
            width: 80%;
        }

        .totals-table .totals-value {
            text-align: right;
            width: 20%;
            white-space: nowrap;
        }

        .totals-table .grand-total td {
            border-top: 1px solid #000;
            font-weight: bold;
            font-size: 11px;
            padding-top: 3px;
        }

        .amount-words {
            font-size: 8.5px;
            font-style: italic;
            margin-top: 3px;
        }

        /* Signature */
        .signature-block {
            margin-top: 12px;
            width: 100%;
        }

        .signature-line {
            width: 140px;
            border-top: 1px solid #000;
            text-align: center;
            padding-top: 3px;
            margin-left: auto;
            font-weight: bold;
            font-size: 9px;
        }
    </style>
</head>

<body>


    @php
    $copies = ['OFFICE COPY', 'VENDOR COPY'];

    $subTotal = $record->items->sum(
    fn($i) => (float) $i->unit_price * (float) $i->quantity
    );

    $totalDiscount = $record->discount_amount ?? 0;
    $totalGst = $record->gst_amount ?? 0;
    $grandTotal = $record->total_amount ?? 0;
    $amountReceived = $record->amount_received ?? 0;
    $amountBalance = $grandTotal - $amountReceived;

    $amountInWords = $grandTotal
    ? \NumberFormatter::create('en_IN', \NumberFormatter::SPELLOUT)->format($grandTotal)
    : '';

    // Load PO invoice items.
    $record->loadMissing('items.product');

    // Find requisition IDs from the existing invoice fields.
    $requisitionIds = [];

    if (!empty($record->purchase_req_to_purchase_order_no)) {
    preg_match_all(
    '/\d+/',
    (string) $record->purchase_req_to_purchase_order_no,
    $matches
    );

    $requisitionIds = array_merge($requisitionIds, $matches[0]);
    }

    // Bulk PO stores IDs in notes, e.g. Requisitions: #12, #13
    if (!empty($record->notes)) {
    preg_match_all('/#(\d+)/', $record->notes, $matches);
    $requisitionIds = array_merge($requisitionIds, $matches[1]);
    }

    $requisitionIds = array_values(array_unique($requisitionIds));

    // Load requisitions and their original/linked products.
    $requisitions = \App\Models\PurchaseRequisition::with([
    'items.product',
    'items.fulfillments.product',
    ])
    ->whereIn('id', $requisitionIds)
    ->get()
    ->keyBy('id');

    // Keep invoice lines available for matching against fulfillment lines.
    $availableInvoiceItems = $record->items
    ->sortBy('id')
    ->values();

    $poDisplayRows = [];

    foreach ($requisitionIds as $requisitionId) {
    $requisition = $requisitions->get($requisitionId);

    if (!$requisition) {
    continue;
    }


    foreach ($requisition->items as $requisitionItem) {
    $originalProduct = $requisitionItem->product;
    $fulfillments = $requisitionItem->fulfillments;

    $partNumbers = [];
    $unitPrices = [];

    $totalQty = 0;
    $totalAmount = 0;
    $originalQty = 0;
    $originalAmount = 0;

    // Original product quantity.
    $originalQty = (float) $fulfillments
    ->where('product_id', $requisitionItem->product_id)
    ->sum('quantity');

    // Original product comes first.
    if ($originalProduct) {
    $partNumbers[] = ($originalProduct->barcode ?? 'N/A')
    . ' [' . number_format($originalQty, 0) . ']';
    }

    foreach ($fulfillments as $fulfillment) {
    $quantity = (float) $fulfillment->quantity;
    $totalQty += $quantity;

    $lineIndex = $availableInvoiceItems->search(
    function ($line) use ($fulfillment) {
    return $line->product_id == $fulfillment->product_id
    && abs(
    (float) $line->quantity - (float) $fulfillment->quantity
    ) < 0.0001;
        }
        );

        // Fallback: match by product ID.
        if ($lineIndex===false) {
        $lineIndex=$availableInvoiceItems->search(
        fn($line) => $line->product_id == $fulfillment->product_id
        );
        }

        $invoiceLine = null;

        if ($lineIndex !== false) {
        $invoiceLine = $availableInvoiceItems->get($lineIndex);

        $lineAmount = (float) (
        $invoiceLine->total_amount
        ?? ((float) $invoiceLine->unit_price * $quantity)
        );

        $totalAmount += $lineAmount;

        // Consume matched invoice line.
        $availableInvoiceItems->forget($lineIndex);
        } else {
        $lineAmount = 0;
        }

        $productUnitPrice = $quantity > 0
        ? $lineAmount / $quantity
        : 0;

        // Original product price.
        if ($fulfillment->product_id == $requisitionItem->product_id) {
        $originalAmount += $lineAmount;
        continue;
        }

        // Linked product and its individual unit price.
        $linkedProduct = $fulfillment->product;

        if ($linkedProduct) {
        $partNumbers[] = ($linkedProduct->barcode ?? 'N/A')
        . ' [' . number_format($quantity, 0) . ']';

        $unitPrices[] = number_format($productUnitPrice, 2);
        }
        }

        // Original product unit price comes first.
        $originalUnitPrice = $originalQty > 0
        ? $originalAmount / $originalQty
        : 0;

        array_unshift(
        $unitPrices,
        number_format($originalUnitPrice, 2)
        );
        
        // Correct requisition row
        $poDisplayRows[] = [
            'part_numbers' => implode(' / ', $partNumbers),
            'unit_prices' => implode(' / ', $unitPrices),
            'quantity' => $totalQty,
            'total_amount' => $totalAmount,
        ];

        }

        }

        // Fallback for old POs without a matching requisition reference.
        // Fallback for old POs without a matching requisition reference.
        if (empty($poDisplayRows)) {
        foreach ($record->items as $invoiceItem) {
        $poDisplayRows[] = [
        'part_numbers' => ($invoiceItem->product?->barcode ?? 'N/A')
        . ' [' . number_format((float) $invoiceItem->quantity, 0) . ']',

        'unit_prices' => number_format(
        (float) $invoiceItem->unit_price,
        2
        ),

        'quantity' => $invoiceItem->quantity,

        'total_amount' => $invoiceItem->total_amount ?? 0,
        ];
        }
        }
        @endphp


        @foreach ($copies as $i => $copyLabel)
        <div class="half-copy">

            <span class="copy-badge">{{ $copyLabel }}</span>

            {{-- Header / Brand --}}
            <table class="brand-table">
                <tr>
                    {{-- <td style="width: 40px;">
                        <img src="{{ public_path('images/logo.png') }}" class="brand-logo">
                    </td> --}}
                    <td class="brand-name">{{ config('app.company_name', 'Bright Electronic\'s') }}</td>
                </tr>
            </table>

            {{-- Order Info --}}
            <table class="info-table">
                <tr>
                    <td style="width: 33%;">
                        PO No. : <b>{{ $record->number }}</b>
                    </td>
                    <td style="width: 34%;">
                        Purchase Req. No. : <b>{{ $record->purchase_req_to_purchase_order_no ?? '--' }}</b>
                    </td>
                    <td style="width: 33%;">
                        Date : <b>{{ \Carbon\Carbon::parse($record->document_date)->format('d-m-Y') }}</b>
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        Vendor: <b>
                            {{ strtoupper($record->billable->name ?? '') }}
                            @if (!empty($record->billable->code))
                            ({{ $record->billable->code }})
                            @endif
                        </b><br>
                        <span class="muted">{{ $record->billable->address ?? '' }}</span><br>
                        <span class="muted">
                            @if (!empty($record->billable->phone))
                            Ph: {{ $record->billable->phone }} &nbsp;
                            @endif
                            @if (!empty($record->billable->gst_number))
                            GSTIN: {{ $record->billable->gst_number }}
                            @endif
                        </span>
                    </td>
                    <td>
                        Place of Supply: <b>{{ $record->place_of_supply ?? '--' }}</b><br>
                        State: <b>{{ $record->billable->state ?? '--' }}</b>
                    </td>
                </tr>
                <tr>
                    <td colspan="3">
                        Order From : <b>{{ $record->store->code ?? 'BRT01' }} -
                            {{ $record->store->name ?? 'Main Branch' }}</b>
                    </td>
                </tr>
            </table>

            {{-- Items --}}


            {{-- Items --}}
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 6%;">Sr</th>
                        <th style="width: 44%;">Part Number / Linked Products</th>
                        <th style="width: 8%;">Qty</th>
                        <th style="width: 14%;">Unit Price</th>
                        <th style="width: 14%;">Total Amount</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($poDisplayRows as $index => $row)
                    <tr>
                        <td class="text-center">
                            {{ $index + 1 }}
                        </td>

                        <td>
                            {{ $row['part_numbers'] }}
                        </td>

                        <td class="text-center">
                            {{ number_format((float) $row['quantity'], 0) }}
                        </td>

                        <td class="text-right">
                            Rs.{{ $row['unit_prices'] }}
                        </td>

                        <td class="text-right">
                            <b>Rs.{{ number_format($row['total_amount'], 2) }}</b>
                        </td>
                    </tr>
                    @endforeach

                    <tr>
                        <td colspan="2" class="text-right">
                            <b>GRAND TOTAL</b>
                        </td>
                        <td class="text-center">
                            <b>{{ number_format((float) collect($poDisplayRows)->sum('quantity'), 0) }}</b>
                        </td>
                        <td></td>
                        <td class="text-right">
                            <b>Rs.{{ number_format((float) collect($poDisplayRows)->sum('total_amount'), 2) }}</b>
                        </td>
                    </tr>
                </tbody>
            </table>


            {{-- Signature --}}
            <table class="no-border signature-block">
                <tr>
                    <td>
                        <div class="signature-line">(Authorised)</div>
                    </td>
                </tr>
            </table>

        </div>

        @if ($i === 0)
        <div class="cut-line-wrapper">
            <span class="cut-line-label">&#9986; cut here &#9986;</span>
        </div>
        @endif
        @endforeach

</body>

</html>