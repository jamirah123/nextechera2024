<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Billing profile {{ $profile->client?->name }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 24px 28px 20px;
        }
    </style>
</head>
<body>
    @include('documents.billing.document', [
        'profile' => $profile,
        'companyLogo' => $companyLogo,
    ])
</body>
</html>
