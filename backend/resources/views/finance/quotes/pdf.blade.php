<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 28mm 20mm; }
        body { color: #172033; font-family: DejaVu Sans, sans-serif; font-size: 10pt; line-height: 1.45; }
        h1 { font-size: 22pt; margin: 0 0 4mm; }
        h2 { font-size: 12pt; margin: 8mm 0 3mm; }
        .muted { color: #5f6b7a; }
        .meta { margin-bottom: 8mm; }
        .meta td { padding: 0 5mm 1mm 0; }
        table { border-collapse: collapse; width: 100%; }
        .lines th, .lines td, .totals td { border-bottom: 1px solid #d8dee8; padding: 2.5mm 2mm; text-align: left; }
        .lines th { background: #eef2f7; }
        .number { text-align: right !important; white-space: nowrap; }
        .totals { margin: 6mm 0 8mm auto; width: 58%; }
        .totals .gross td { border-top: 2px solid #172033; font-weight: bold; }
        .text { white-space: pre-line; }
    </style>
</head>
<body>
    <h1>Angebot {{ $documentNumber }}</h1>
    <div class="muted">Revision {{ $revisionLabel }}</div>
    <h2>{{ $title }}</h2>

    <table class="meta">
        <tr><td>Ausgestellt</td><td>{{ $issueDate }}</td><td>Gültig bis</td><td>{{ $validUntil }}</td></tr>
    </table>

    <div>
        <strong>{{ $customer['name'] }}</strong><br>
        @if($customer['street']){{ $customer['street'] }}<br>@endif
        @if($customer['postalCode'] || $customer['city']){{ trim(($customer['postalCode'] ?? '').' '.($customer['city'] ?? '')) }}<br>@endif
        @if($customer['country']){{ $customer['country'] }}<br>@endif
        @if($customer['email']){{ $customer['email'] }}@endif
    </div>

    @if($introText)<p class="text">{{ $introText }}</p>@endif

    <table class="lines">
        <thead><tr><th>Beschreibung</th><th class="number">Menge</th><th>Einheit</th><th class="number">Einzelpreis</th><th class="number">Steuer %</th><th class="number">Netto</th></tr></thead>
        <tbody>
        @foreach($lines as $line)
            <tr>
                <td>{{ $line['description'] }}</td>
                <td class="number">{{ $line['quantity'] }}</td>
                <td>{{ $line['unit'] }}</td>
                <td class="number">{{ $line['unitPrice'] }}</td>
                <td class="number">{{ $line['taxRate'] }}</td>
                <td class="number">{{ $line['net'] }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Zwischensumme netto</td><td class="number">{{ $subtotal }}</td></tr>
        @if($discount !== '0,00 '.$currency)<tr><td>Rabatt</td><td class="number">-{{ $discount }}</td></tr>@endif
        <tr><td>Netto nach Rabatt</td><td class="number">{{ $net }}</td></tr>
        @foreach($taxBreakdowns as $tax)
            <tr><td>USt. {{ $tax['rate'] }} %</td><td class="number">{{ $tax['vat'] }}</td></tr>
        @endforeach
        <tr class="gross"><td>Gesamt</td><td class="number">{{ $gross }}</td></tr>
    </table>

    @if($customerNote)<p class="text">{{ $customerNote }}</p>@endif
    @if($outroText)<p class="text">{{ $outroText }}</p>@endif
</body>
</html>
