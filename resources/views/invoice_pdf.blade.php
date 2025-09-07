<!DOCTYPE html>
<html>

<head>
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 5px;
            text-align: left;
        }
    </style>
</head>

<body>
    <h1>Invoice: {{ $invoice->invoice_number }}</h1>
    <p>Date: {{ $invoice->invoice_date }}</p>
    <p>Billed To: {{ $invoice->billable->name ?? '' }}</p>

    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th>Qty</th>
                <th>Unit Price</th>
                <th>CGST</th>
                <th>SGST</th>
                <th>IGST</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td>{{ $item->product->name ?? '' }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ $item->unit_price }}</td>
                    <td>{{ $item->cgst_amount }}</td>
                    <td>{{ $item->sgst_amount }}</td>
                    <td>{{ $item->igst_amount }}</td>
                    <td>{{ $item->total_amount }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p>Taxable Value: {{ $invoice->taxable_value }}</p>
    <p>Total Tax: {{ $invoice->total_tax }}</p>
    <p>Discount: {{ $invoice->discount }}</p>
    <p>Total Amount: {{ $invoice->total_amount }}</p>
</body>

</html>
