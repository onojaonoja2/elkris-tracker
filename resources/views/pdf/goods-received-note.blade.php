<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Goods Received Note #{{ $transaction->id }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; padding: 40px; }
        h1 { color: #166534; border-bottom: 2px solid #166534; padding-bottom: 8px; }
        .header { margin-bottom: 30px; }
        .details { margin-bottom: 30px; }
        .details td { padding: 4px 8px; }
        .details td:first-child { font-weight: bold; width: 160px; }
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        table.items th { background: #166534; color: white; padding: 10px 8px; text-align: left; }
        table.items td { padding: 8px; border-bottom: 1px solid #ddd; }
        table.items tr:nth-child(even) { background: #f0fdf4; }
        .footer { margin-top: 40px; border-top: 1px solid #ccc; padding-top: 16px; font-size: 10px; color: #666; text-align: center; }
        .signature { margin-top: 40px; }
        .signature td { width: 50%; padding: 20px; text-align: center; }
        .signature .line { border-top: 1px solid #333; padding-top: 6px; margin-top: 40px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Goods Received Note</h1>
        <p><strong>GRN #:</strong> {{ $transaction->id }}</p>
        <p><strong>Date:</strong> {{ ($transaction->transaction_date ?? $transaction->created_at)->format('d/m/Y') }}</p>
    </div>

    <div class="details">
        <table>
            <tr><td>Product:</td><td>{{ $transaction->product_name }}</td></tr>
            <tr><td>Grammage:</td><td>{{ $transaction->grammage }}g</td></tr>
            <tr><td>Quantity Received:</td><td>{{ $transaction->quantity }}</td></tr>
            <tr><td>Received By:</td><td>{{ $transaction->user?->name ?? 'N/A' }}</td></tr>
            @if($warehouse ?? null)
            <tr><td>Warehouse:</td><td>{{ $warehouse->name }}</td></tr>
            @endif
            @if($transaction->disbursed_to)
            <tr><td>Supplier/Source:</td><td>{{ $transaction->disbursed_to }}</td></tr>
            @endif
        </table>
    </div>

    <div class="signature">
        <table>
            <tr>
                <td>
                    <div class="line">Received By</div>
                    <p>{{ $transaction->user?->name ?? '' }}</p>
                </td>
                <td>
                    <div class="line">Verified By</div>
                    <p>____________________</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <p>Elkris Tracker - Goods Received Note #{{ $transaction->id }} - Generated on {{ now()->format('d/m/Y H:i') }}</p>
        <p>This document certifies that the above goods have been received in good condition.</p>
    </div>
</body>
</html>
