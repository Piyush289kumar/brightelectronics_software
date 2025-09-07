<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Invoice #{{ $invoice->invoice_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 13px;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 8px;
        }

        th {
            background-color: #2662fa;
            color: white;
            text-align: left;
        }
    </style>
</head>

<body>

    {{-- Header --}}
    <table style="width: 100%; margin-bottom: 20px;">
        <tr>
            <td>
                <h1 style="color:#2662fa; font-size:40px; font-weight:900; margin:0;">Vipprow</h1>
                <p><small>MUMBAI &amp; JABALPUR</small></p>
                <p><small>Phone No.: 96699-32121</small></p>
                <p><small>Email: vipprowdigital@gmail.com</small></p>
                <p><small>State: 23-Madhya Pradesh</small></p>
            </td>
            <td style="text-align:right;">
                <h2 style="font-size:25px; font-weight:900; border-top:1px solid #2662fa; padding-top:10px;">TAX INVOICE
                </h2>
            </td>
        </tr>
    </table>

    {{-- Bill To + Invoice Details --}}
    <table style="margin-bottom:20px; font-size:14px;">
        <tr>
            <td style="width:50%; vertical-align:top;">
                <h3>Bill To</h3>
                <p><strong>{{ $invoice->billable->name ?? '' }}</strong></p>
                <p><small>{{ $invoice->billable->address ?? '' }}</small></p>
                <p><small>Contact No.: {{ $invoice->billable->phone ?? '' }}</small></p>
                <p><small>GSTIN: {{ $invoice->billable->gstin ?? '' }}</small></p>
                <p><small>State: {{ $invoice->billable->state ?? '' }}</small></p>
            </td>
            <td style="width:50%; text-align:right; vertical-align:top;">
                <h3>Invoice Details</h3>
                <p><strong>Invoice No.:</strong> {{ $invoice->invoice_number }}</p>
                <p><strong>Date:</strong> {{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d-m-Y') }}</p>
                <p><strong>Place of Supply:</strong> {{ $invoice->place_of_supply ?? '-' }}</p>
            </td>
        </tr>
    </table>

    {{-- Items --}}
    <table style="margin-bottom:20px; font-size:14px;">
        <thead>
            <tr>
                <th>SR. NO.</th>
                <th>ITEM NAME</th>
                <th>QTY</th>
                <th>PRICE/UNIT</th>
                <th>DISCOUNT</th>
                <th>AMOUNT</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $item->product->name ?? '' }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>₹{{ number_format($item->unit_price, 2) }}</td>
                    <td>₹{{ number_format($item->discount ?? 0, 2) }}</td>
                    <td>₹{{ number_format($item->total_amount, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Totals --}}
    <table style="width:100%; margin-top:20px; font-size:14px;">
        <tr>
            <td style="width:60%;">
                <p><strong>Invoice Amount In Words</strong></p>
                <p><small>{{ \NumberFormatter::create('en_IN', \NumberFormatter::SPELLOUT)->format($invoice->total_amount) }}
                        Rupees Only</small></p>
                <p><strong>Terms and Conditions</strong></p>
                <p><small>This invoice does not include ad spend.</small></p>
                <p><small>Additional 15% service charge will be added on actual ad spend.</small></p>
            </td>
            <td style="width:40%;">
                <table style="width:100%; font-size:14px;">
                    <tr>
                        <td>Sub Total</td>
                        <td>₹{{ number_format($invoice->subtotal ?? 0, 2) }}</td>
                    </tr>
                    <tr>
                        <td>Discount</td>
                        <td>₹{{ number_format($invoice->discount ?? 0, 2) }}</td>
                    </tr>
                    <tr style="background:#2662fa; color:#fff; font-weight:bold;">
                        <td>Total</td>
                        <td>₹{{ number_format($invoice->total_amount, 2) }}</td>
                    </tr>
                    <tr>
                        <td>Received</td>
                        <td>₹{{ number_format($invoice->amount_received ?? 0, 2) }}</td>
                    </tr>
                    <tr>
                        <td>Balance</td>
                        <td>₹{{ number_format($invoice->total_amount - ($invoice->amount_received ?? 0), 2) }}</td>
                    </tr>
                    <tr>
                        <td>You Saved</td>
                        <td>₹{{ number_format($invoice->discount ?? 0, 2) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Footer --}}
    <table style="margin-top:20px; font-size:12px;">
        <tr>
            <td style="width:60%; vertical-align:top;">
                <img src="{{ public_path('storage/images/c2dc973f-9745-4787-8428-e7130d1cb760.png') }}" width="120"
                    height="120" alt="QR">
                <p><strong>Pay To:</strong></p>
                <p><small>Bank Name: KOTAK MAHINDRA BANK LIMITED</small></p>
                <p><small>Account No.: 8050762756</small></p>
                <p><small>IFSC Code: KKBK0005980</small></p>
                <p><small>Holder: VIPPROW</small></p>
            </td>
            <td style="width:40%; text-align:right; vertical-align:bottom;">
                <p>For: <strong>Vipprow</strong></p>
                <br><br>
                <h4>Authorized Signatory</h4>
            </td>
        </tr>
    </table>

</body>

</html>
