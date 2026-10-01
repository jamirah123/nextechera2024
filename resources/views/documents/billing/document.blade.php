@php
    $company = config('psg.company', 'Platinum Security Group');
    $tagline = config('psg.tagline', 'New Age Security and Protection');
    $email = config('psg.support_email');
    $phone = config('psg.support_phone');
    $currency = $profile->currency ?: config('psg.currency', 'UGX');
    $companyLogo = $companyLogo ?? null;
    $mode = $profile->billing_mode;
    $usesMonthly = $mode?->usesMonthlyRates() ?? true;
    $usesShift = (bool) $mode?->usesShiftRates();
    $siteName = $profile->site?->name ?? 'All client sites';
    $reference = 'BP-'.str_pad((string) $profile->id, 4, '0', STR_PAD_LEFT);
    $armedRate = $profile->monthlyArmedRate();
    $unarmedRate = $profile->monthlyUnarmedRate();

    $postLines = [];
    if ($usesMonthly) {
        foreach ([
            ['Day armed security posts', (int) $profile->contracted_day_armed_guards, $armedRate],
            ['Day unarmed security posts', (int) $profile->contracted_day_unarmed_guards, $unarmedRate],
            ['Night armed security posts', (int) $profile->contracted_night_armed_guards, $armedRate],
            ['Night unarmed security posts', (int) $profile->contracted_night_unarmed_guards, $unarmedRate],
        ] as [$label, $quantity, $rate]) {
            if ($quantity <= 0) {
                continue;
            }
            $postLines[] = [
                'description' => $label.' — '.$siteName,
                'quantity' => $quantity,
                'unit_price' => $rate,
                'amount' => $quantity * $rate,
            ];
        }

        $split = (int) $profile->contracted_day_armed_guards
            + (int) $profile->contracted_day_unarmed_guards
            + (int) $profile->contracted_night_armed_guards
            + (int) $profile->contracted_night_unarmed_guards;

        if ($postLines === [] && $split === 0) {
            if ((int) $profile->contracted_armed_guards > 0) {
                $postLines[] = [
                    'description' => 'Armed security posts — '.$siteName,
                    'quantity' => (int) $profile->contracted_armed_guards,
                    'unit_price' => $armedRate,
                    'amount' => (int) $profile->contracted_armed_guards * $armedRate,
                ];
            }
            if ((int) $profile->contracted_unarmed_guards > 0) {
                $postLines[] = [
                    'description' => 'Unarmed security posts — '.$siteName,
                    'quantity' => (int) $profile->contracted_unarmed_guards,
                    'unit_price' => $unarmedRate,
                    'amount' => (int) $profile->contracted_unarmed_guards * $unarmedRate,
                ];
            }
        }

        if ((float) $profile->monthly_site_fee > 0) {
            $postLines[] = [
                'description' => 'Monthly site fee — '.$siteName,
                'quantity' => 1,
                'unit_price' => (float) $profile->monthly_site_fee,
                'amount' => (float) $profile->monthly_site_fee,
            ];
        }
    }

    $postsTotal = array_sum(array_column($postLines, 'amount'));

    $shiftLines = [];
    if ($usesShift) {
        foreach ([
            ['Day armed shift', (float) $profile->rate_per_armed_day_shift],
            ['Day unarmed shift', (float) $profile->rate_per_unarmed_day_shift],
            ['Night armed shift', (float) $profile->rate_per_armed_night_shift],
            ['Night unarmed shift', (float) $profile->rate_per_unarmed_night_shift],
        ] as [$label, $rate]) {
            if ($rate <= 0) {
                continue;
            }
            $shiftLines[] = [
                'description' => $label.' — '.$siteName,
                'rate' => $rate,
            ];
        }
    }
@endphp

@include('documents.partials.formal-styles')

<div class="invoice-doc">
    <div class="brand-bar"></div>

    <table class="header">
        <tr>
            <td class="brand-block">
                <table class="brand-table">
                    <tr>
                        @if (! empty($companyLogo))
                            <td class="brand-logo-cell">
                                <img src="{{ $companyLogo }}" alt="{{ $company }}" class="brand-logo">
                            </td>
                        @endif
                        <td>
                            <p class="company-name">{{ $company }}</p>
                            <p class="company-tagline">{{ $tagline }}</p>
                            @if (filled($email) || filled($phone))
                                <p class="company-contact">
                                    @if (filled($email)){{ $email }}@endif
                                    @if (filled($email) && filled($phone)) &nbsp;|&nbsp; @endif
                                    @if (filled($phone)){{ $phone }}@endif
                                </p>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
            <td class="doc-panel">
                <p class="doc-title">Billing profile</p>
                <table class="doc-meta">
                    <tr>
                        <td class="k">Profile No.</td>
                        <td class="v">{{ $reference }}</td>
                    </tr>
                    <tr>
                        <td class="k">Effective from</td>
                        <td class="v">{{ $profile->effective_from?->format('d M Y') ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="k">Effective to</td>
                        <td class="v">{{ $profile->effective_to?->format('d M Y') ?? 'Open' }}</td>
                    </tr>
                    <tr>
                        <td class="k">Currency</td>
                        <td class="v">{{ $currency }}</td>
                    </tr>
                    <tr>
                        <td class="k">Status</td>
                        <td class="v">{{ $profile->is_active ? 'Active' : 'Inactive' }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="parties">
        <tr>
            <td>
                <div class="party-box">
                    <p class="party-label">Client</p>
                    <p class="party-name">{{ $profile->client?->name ?? '—' }}</p>
                    @if ($profile->client?->contact_person)
                        <p class="party-line">{{ $profile->client->contact_person }}</p>
                    @endif
                    @if ($profile->client?->address)
                        <p class="party-line">{{ $profile->client->address }}</p>
                    @endif
                    @if ($profile->client?->phone)
                        <p class="party-line">Tel: {{ $profile->client->phone }}</p>
                    @endif
                    @if ($profile->client?->email)
                        <p class="party-line">{{ $profile->client->email }}</p>
                    @endif
                </div>
            </td>
            <td>
                <div class="party-box">
                    <p class="party-label">Coverage</p>
                    <p class="party-name">{{ $siteName }}</p>
                    @if ($profile->site?->code)
                        <p class="party-line">Site code: {{ $profile->site->code }}</p>
                    @endif
                    <p class="party-line">{{ $mode?->label() ?? 'Monthly day / night posts' }}</p>
                    <p class="party-line">{{ $profile->cash_no_tax ? 'Cash / no VAT' : 'VAT applies when an invoice is issued' }}</p>
                </div>
            </td>
        </tr>
    </table>

    @if ($usesMonthly)
        <table class="items">
            <thead>
                <tr>
                    <th style="width: 6%;">#</th>
                    <th>Description</th>
                    <th class="num" style="width: 11%;">Qty</th>
                    <th class="num" style="width: 18%;">Unit price</th>
                    <th class="num" style="width: 18%;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($postLines as $line)
                    <tr>
                        <td class="muted">{{ $loop->iteration }}</td>
                        <td class="desc">{{ $line['description'] }}</td>
                        <td class="num">{{ number_format((float) $line['quantity'], 2) }}</td>
                        <td class="num">{{ \App\Support\Money::format($line['unit_price'], $currency) }}</td>
                        <td class="num amount">{{ \App\Support\Money::format($line['amount'], $currency) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="muted" style="text-align: center; padding: 16px;">No contracted posts on this profile.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @endif

    @if ($usesShift)
        <p class="section-label" style="margin-top: 12px;">Shift rates</p>
        <table class="items">
            <thead>
                <tr>
                    <th style="width: 6%;">#</th>
                    <th>Description</th>
                    <th class="num" style="width: 22%;">Unit price</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($shiftLines as $line)
                    <tr>
                        <td class="muted">{{ $loop->iteration }}</td>
                        <td class="desc">{{ $line['description'] }}</td>
                        <td class="num amount">{{ \App\Support\Money::format($line['rate'], $currency) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="muted" style="text-align: center; padding: 16px;">No shift rates on this profile.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @endif

    <table class="lower">
        <tr>
            <td class="notes-col">
                <p class="section-label">Notes</p>
                @if ($profile->notes)
                    <p class="notes-text">{{ $profile->notes }}</p>
                @else
                    <p class="notes-text" style="color:#94a3b8;">{{ $mode?->description() ?? 'Standing commercial terms used when invoices are raised for this client.' }}</p>
                @endif
            </td>
            <td class="totals-col">
                @if ($usesMonthly)
                    <table class="totals">
                        @if ((float) $profile->monthly_site_fee > 0)
                            <tr>
                                <td class="label">Contracted posts</td>
                                <td class="value">{{ \App\Support\Money::format($postsTotal - (float) $profile->monthly_site_fee, $currency) }}</td>
                            </tr>
                            <tr class="divider">
                                <td class="label">Site fee</td>
                                <td class="value">{{ \App\Support\Money::format($profile->monthly_site_fee, $currency) }}</td>
                            </tr>
                        @endif
                        <tr class="total">
                            <td class="label">Monthly total</td>
                            <td class="value">{{ \App\Support\Money::format($postsTotal, $currency) }}</td>
                        </tr>
                    </table>
                @else
                    <table class="totals">
                        <tr class="total">
                            <td class="label">Billing</td>
                            <td class="value">Per completed shift</td>
                        </tr>
                    </table>
                @endif
            </td>
        </tr>
    </table>

    <div class="payment-box">
        <p class="section-label">How this profile is used</p>
        <p class="payment-text">{{ $mode?->description() ?? 'Invoices use these contracted posts and rates.' }}</p>
        @if ($profile->cash_no_tax)
            <p class="payment-text">Cash terms. No VAT is added when an invoice is raised from this profile.</p>
        @else
            <p class="payment-text">VAT is calculated on the invoice, not on this profile.</p>
        @endif
        @if ($usesShift)
            <p class="payment-text">Shift amounts are counted from completed shifts in the invoice period.</p>
        @endif
        <p class="payment-text" style="margin-bottom: 0;">
            Profile reference <strong>{{ $reference }}</strong>
            · {{ $profile->effective_from?->format('d M Y') ?? '—' }}
            – {{ $profile->effective_to?->format('d M Y') ?? 'open' }}.
        </p>
    </div>

    <table class="signatures">
        <tr>
            <td>
                <p class="section-label">Prepared by</p>
                <div class="sig-line"></div>
                <p class="sig-caption">Authorized signature · {{ $company }}</p>
            </td>
            <td>
                <p class="section-label">Acknowledged by</p>
                <div class="sig-line"></div>
                <p class="sig-caption">Client name / signature / date</p>
            </td>
        </tr>
    </table>

    <p class="doc-footer">
        Computer-generated billing profile from {{ $company }}.
    </p>
</div>
