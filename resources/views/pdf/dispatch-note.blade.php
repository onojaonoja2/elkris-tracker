<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Dispatch Note #{{ $transfer->id }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; padding: 40px; }
        h1 { color: #1e40af; border-bottom: 2px solid #1e40af; padding-bottom: 8px; }
        .header { margin-bottom: 30px; }
        .details { margin-bottom: 30px; }
        .details td { padding: 4px 8px; }
        .details td:first-child { font-weight: bold; width: 140px; }
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        table.items th { background: #1e40af; color: white; padding: 10px 8px; text-align: left; }
        table.items td { padding: 8px; border-bottom: 1px solid #ddd; }
        table.items tr:nth-child(even) { background: #f8fafc; }
        .footer { margin-top: 40px; border-top: 1px solid #ccc; padding-top: 16px; font-size: 10px; color: #666; text-align: center; }
        .signature { margin-top: 40px; }
        .signature td { width: 50%; padding: 20px; text-align: center; }
        .signature .line { border-top: 1px solid #333; padding-top: 6px; margin-top: 40px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Dispatch Note</h1>
        <p><strong>Dispatch #:</strong> {{ $transfer->id }}</p>
        <p><strong>Date:</strong> {{ $transfer->created_at->format('d/m/Y') }}</p>
    </div>

    <div class="details">
        <table>
            <tr><td>From:</td><td>{{ $transfer->fromWarehouse?->name ?? 'N/A' }}</td></tr>
            @if($transfer->toWarehouse)
            <tr><td>To (Warehouse):</td><td>{{ $transfer->toWarehouse->name }}</td></tr>
            @endif
            @if($transfer->toStockist)
            <tr><td>To (Stockist):</td><td>{{ $transfer->toStockist->name }}</td></tr>
            <tr><td>Stockist Location:</td><td>{{ $transfer->toStockist->city }}, {{ $transfer->toStockist->state }}</td></tr>
            @endif
            <tr><td>Dispatched By:</td><td>{{ $transfer->dispatcher?->name ?? 'N/A' }}</td></tr>
            <tr><td>Notes:</td><td>{{ $transfer->notes ?? 'N/A' }}</td></tr>
        </table>
    </div>

    <h3>Stock Items</h3>
    <table class="items">
        <thead>
            <tr>
                <th>S/N</th>
                <th>Product</th>
                <th>Weight</th>
                <th>Quantity</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transfer->items as $index => $item)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $item->productType?->name ?? 'N/A' }}</td>
                <td>{{ $item->grammage }}g</td>
                <td>{{ $item->quantity }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="signature">
        <table>
            <tr>
                <td>
                    <div class="line">Dispatcher Signature</div>
                    <p>{{ $transfer->dispatcher?->name ?? '' }}</p>
                </td>
                <td>
                    <div class="line">Receiver Signature</div>
                    <p>{{ $transfer->receiver?->name ?? '____________________' }}</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <p>Elkris Tracker - Dispatch Note #{{ $transfer->id }} - Generated on {{ now()->format('d/m/Y H:i') }}</p>
        <p>This document accompanies the physical goods being transferred.</p>
    </div>
</body>
</html>
