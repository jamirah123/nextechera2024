@php
    $isTaxInvoice = (float) $invoice->tax_amount > 0;
    $documentTitle = $isTaxInvoice ? 'Tax Invoice' : 'Invoice';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $documentTitle }} {{ $invoice->reference }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 24px 28px 20px;
        }
    </style>
</head>
<body>
    @include('documents.invoice.document', [
        'invoice' => $invoice,
        'paymentTerms' => $paymentTerms,
        'bankDetails' => $bankDetails,
        'companyLogo' => $companyLogo,
    ])
</body>
</html>
