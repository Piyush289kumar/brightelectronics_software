// resources/views/filament/components/purchase-order.blade.php

<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 10px 20px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 16px;
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
            /* ~half of A4 (297mm) minus margins/cut-line space */
            overflow: hidden;
            box-sizing: border-box;
            padding: 6px 4px;
        }

        /* ===== Cut line between the two halves ===== */
        .cut-line-wrapper {
            width: 100%;
            text-align: center;
            font-size: 10px;
            color: #555;
            border-top: 1px dashed #000;
            position: relative;
            margin: 4px 0 4px 0;
            height: 1px;
        }

        .cut-line-label {
            position: relative;
            top: -8px;
            background: #fff;
            padding: 0 8px;
            display: inline-block;
        }

        /* Copy type badge */
        .copy-badge {
            text-align: right;
            font-size: 11px;
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
            width: 36px;
            height: 36px;
        }

        .brand-name {
            font-size: 18px;
            font-weight: 800;
            padding-left: 8px;
        }

        /* Info box */
        .info-table {
            border: 1px solid #000;
            margin-top: 8px;
        }

        .info-table td {
            border: 1px solid #000;
            padding: 4px 8px;
            vertical-align: top;
        }

        .info-table b {
            font-weight: bold;
        }

        /* Items table */
        .items-table {
            border: 1px solid #000;
            margin-top: 8px;
        }

        .items-table th,
        .items-table td {
            border: 1px solid #000;
            padding: 4px 8px;
        }

        .items-table th {
            font-weight: bold;
            text-align: left;
            background-color: #fff;
        }

        .items-table .text-center {
            text-align: center;
        }

        .items-table .text-right {
            text-align: right;
        }

        /* Signature */
        .signature-block {
            margin-top: 18px;
            width: 100%;
        }

        .signature-line {
            width: 150px;
            border-top: 1px solid #000;
            text-align: center;
            padding-top: 4px;
            margin-left: auto;
            font-weight: bold;
            font-size: 12px;
        }
    </style>
</head>

<body>

    @php
        $copies = ['OFFICE COPY', 'VENDOR COPY'];
    @endphp

    @foreach ($copies as $i => $copyLabel)
        <div class="half-copy">

            <span class="copy-badge">{{ $copyLabel }}</span>

            {{-- Header / Brand --}}
            <table class="brand-table">
                <tr>
                    <td style="width: 45px;">
                        <img src="{{ public_path('images/logo.png') }}" class="brand-logo">
                    </td>
                    <td class="brand-name">{{ config('app.company_name', 'Bright Electronic\'s') }}</td>
                </tr>
            </table>

            {{-- Order Info --}}
            <table class="info-table">
                <tr>
                    <td style="width: 60%;">
                        Purchase/Service Order No. : <b>{{ $record->number }}</b>
                    </td>
                    <td style="width: 40%;">
                        Date :
                        <b>{{ \Carbon\Carbon::parse($record->document_date)->format('d/m/Y H:i:s') }}</b>
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        Order To: <b>{{ strtoupper($record->billable->name ?? '') }}
                            @if (!empty($record->billable->code))
                                - {{ $record->billable->code }}
                            @endif
                        </b>
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        Order From : <b>{{ $record->branch->code ?? 'BRT01' }} -
                            {{ $record->branch->name ?? 'Main Branch' }}</b>
                    </td>
                </tr>
            </table>

            {{-- Items --}}
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 8%;">Sr<br>No</th>
                        <th style="width: 42%;">Purchase Item</th>
                        <th style="width: 16%;">Unit Per Rate</th>
                        <th style="width: 14%;">Quantity</th>
                        <th style="width: 20%;">Invoice Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($record->items as $index => $item)
                        <tr>
                            <td class="text-center">{{ $index + 1 }}.</td>
                            <td>{{ $item->product->name ?? '' }}</td>
                            <td class="text-center">
                                {{ $item->unit_price ? number_format($item->unit_price, 2) : '' }}</td>
                            <td class="text-center">{{ $item->quantity }}</td>
                            <td class="text-right"><b>Rs.{{ number_format($item->total_amount ?? 0, 2) }}</b>
                            </td>
                        </tr>
                    @endforeach
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

        {{-- Cut line only between the two halves, not after the last one --}}
        @if ($i === 0)
            <div class="cut-line-wrapper">
                <span class="cut-line-label">&#9986; cut here &#9986;</span>
            </div>
        @endif
    @endforeach

</body>

</html>
