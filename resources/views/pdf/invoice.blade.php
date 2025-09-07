<!DOCTYPE html>
<html>

<head>
    <title>Invoice #{{ $record->id }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
        }
    </style>
</head>

<body>
    <h1>Invoice #{{ $record->id }}</h1>
    <p>Vendor: {{ $record->vendor->name }}</p>
    <p>Status: {{ $record->status }}</p>
    <h3>Items:</h3>
    <ul>
        @foreach ($record->items as $item)
            <li>{{ $item->product->name }} — {{ $item->quantity }}</li>
        @endforeach
    </ul>
</body>

</html>
