<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>Confirmed Registrations — {{ $reunion->title }}</title>
    <style>
        /* A4 landscape, narrow margins so the wide table fits comfortably. */
        @page {
            size: A4 landscape;
            margin: 8mm;
        }

        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            color: #1f2937;
            font-size: 9px;
            line-height: 1.25;
        }

        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 12px;
            margin-bottom: 10px;
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
        }

        .toolbar h1 {
            margin: 0;
            font-size: 12px;
            font-weight: 600;
        }

        .toolbar button {
            padding: 4px 10px;
            background: #1f3864;
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 11px;
            cursor: pointer;
        }

        .toolbar button:hover { background: #16284a; }

        /* Meta block under the title. */
        .meta {
            display: flex;
            flex-wrap: wrap;
            gap: 4px 24px;
            margin: 0 0 8px;
            font-size: 10px;
        }

        .meta dt {
            font-weight: 600;
            color: #374151;
            display: inline;
        }

        .meta dd {
            margin: 0 16px 0 4px;
            color: #4b5563;
            display: inline;
        }

        /* The table. Percentage widths so it always fits the printable width. */
        .sheet {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .sheet th,
        .sheet td {
            border: 1px solid #9ca3af;
            padding: 3px 4px;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .sheet thead th {
            background: #1f3864;
            color: #ffffff;
            font-weight: 600;
            text-align: left;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }

        .sheet tbody tr {
            /* Avoid splitting a single registration row across pages. */
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .sheet tbody td.col-photo { text-align: center; }

        .sheet img.photo {
            width: 36px;
            height: 36px;
            object-fit: cover;
            border-radius: 3px;
            border: 1px solid #d1d5db;
            display: block;
            margin: 0 auto;
        }

        /* Stacked address lines inside the single Addresses cell. */
        .addr-block {
            display: block;
        }
        .addr-block .label {
            display: block;
            font-size: 7px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .addr-block .line {
            display: block;
            margin-bottom: 2px;
        }

        /* Column widths — percentages of the printable area. Total ≈ 100%.
           Reference widths (rough): index 2.5, code 6, photo 5, name 8,
           phone 6, year 4, dept 6, session 4, occ 7, addr 14, tshirt 4,
           guests 5, fee 5, pay 5, reg 6, paidamt 5, txn 6, method 5,
           paidat 6, notes 7, registered 6 → ≈ 111. Scaled to 100 below. */
        .col-index      { width: 2.4%; }
        .col-code       { width: 5.5%; }
        .col-photo      { width: 4.5%; }
        .col-name       { width: 7.5%; }
        .col-phone      { width: 5.5%; }
        .col-year       { width: 3.6%; }
        .col-dept       { width: 5.5%; }
        .col-session    { width: 3.6%; }
        .col-occ        { width: 6.4%; }
        .col-addr       { width: 12.5%; }
        .col-tshirt     { width: 3.6%; }
        .col-guests     { width: 4.5%; }
        .col-fee        { width: 4.5%; }
        .col-pay        { width: 4.5%; }
        .col-reg        { width: 5.5%; }
        .col-paidamt    { width: 4.5%; }
        .col-txn        { width: 5.5%; }
        .col-method     { width: 4.5%; }
        .col-paidat     { width: 5.5%; }
        .col-notes      { width: 6.5%; }
        .col-registered { width: 5.4%; }

        /* Hide the toolbar when printing. */
        @media print {
            .toolbar { display: none; }
            body { font-size: 8px; }
            .sheet thead th { font-size: 7px; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <h1>Confirmed Registrations — {{ $reunion->title }}</h1>
        <div>
            <button type="button" onclick="window.print()">Print / Save as PDF</button>
            <button type="button" onclick="window.close()" style="background:#6b7280;margin-left:6px;">Close</button>
        </div>
    </div>

    <dl class="meta">
        <div><dt>Event Date</dt><dd>{{ optional($reunion->event_date)->format('d M Y') ?: '—' }}</dd></div>
        <div><dt>Generated</dt><dd>{{ $generatedAt->format('d M Y H:i') }}</dd></div>
        <div><dt>Total Confirmed</dt><dd>{{ $registrations->count() }}</dd></div>
    </dl>

    <table class="sheet">
        <colgroup>
            <col class="col-index">
            <col class="col-code">
            <col class="col-photo">
            <col class="col-name">
            <col class="col-phone">
            <col class="col-year">
            <col class="col-dept">
            <col class="col-session">
            <col class="col-occ">
            <col class="col-addr">
            <col class="col-tshirt">
            <col class="col-guests">
            <col class="col-fee">
            <col class="col-pay">
            <col class="col-reg">
            <col class="col-paidamt">
            <col class="col-txn">
            <col class="col-method">
            <col class="col-paidat">
            <col class="col-notes">
            <col class="col-registered">
        </colgroup>
        <thead>
            <tr>
                <th>#</th>
                <th>Code</th>
                <th>Photo</th>
                <th>Name</th>
                <th>Phone</th>
                <th>Grad. Year</th>
                <th>Department</th>
                <th>Session</th>
                <th>Occupation</th>
                <th>Addresses</th>
                <th>T-Shirt</th>
                <th>Guests (Adult/Child)</th>
                <th>Total Fee</th>
                <th>Payment</th>
                <th>Registration</th>
                <th>Paid Amount</th>
                <th>Txn ID</th>
                <th>Method</th>
                <th>Paid At</th>
                <th>Notes</th>
                <th>Registered</th>
            </tr>
        </thead>
        <tbody>
            @php $rowNumber = 0; @endphp
            @forelse ($registrations as $registration)
                @php
                    $rowNumber++;
                    $adultCount = $registration->guests->where('guest_type', 'Adult')->count();
                    $childCount = $registration->guests->where('guest_type', 'Child')->count();
                    $photoUrl = $registration->student?->photo
                        ? asset('uploads/'.$registration->student->photo)
                        : null;
                @endphp
                <tr>
                    <td>{{ $rowNumber }}</td>
                    <td>{{ $registration->registration_code }}</td>
                    <td class="col-photo">
                        @if ($photoUrl)
                            <img class="photo" src="{{ $photoUrl }}" alt="Photo of {{ $registration->student?->full_name }}">
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $registration->student?->full_name ?: '—' }}</td>
                    <td>{{ $registration->student?->phone ?: '—' }}</td>
                    <td>{{ $registration->student?->graduation_year ?: '—' }}</td>
                    <td>{{ $registration->student?->department ?: '—' }}</td>
                    <td>{{ $registration->student?->session ?: '—' }}</td>
                    <td>{{ $registration->student?->current_occupation ?: '—' }}</td>
                    <td>
                        <span class="addr-block">
                            <span class="label">Present</span>
                            <span class="line">{{ $registration->student?->present_address ?: '—' }}</span>
                            <span class="label">Permanent</span>
                            <span class="line">{{ $registration->student?->permanent_address ?: '—' }}</span>
                            <span class="label">Office</span>
                            <span class="line">{{ $registration->student?->office_address ?: '—' }}</span>
                        </span>
                    </td>
                    <td>{{ $registration->tshirt_size ?: '—' }}</td>
                    <td>Adult: {{ $adultCount }}<br>Child: {{ $childCount }}</td>
                    <td>{{ $registration->total_fee !== null ? '৳'.number_format((float) $registration->total_fee, 2) : '—' }}</td>
                    <td>{{ ucfirst($registration->payment_status ?? '—') }}</td>
                    <td>{{ ucfirst($registration->registration_status ?? '—') }}</td>
                    <td>{{ $registration->total_paid_amount !== null ? '৳'.number_format((float) $registration->total_paid_amount, 2) : '—' }}</td>
                    <td>{{ $registration->latest_transaction_id ?: '—' }}</td>
                    <td>{{ $registration->latest_payment_method ?: '—' }}</td>
                    <td>{{ $registration->latest_paid_at ?: '—' }}</td>
                    <td>{{ $registration->notes ?: '—' }}</td>
                    <td>{{ optional($registration->created_at)->format('d M Y H:i') ?: '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="21" style="text-align:center;padding:24px;color:#6b7280;">
                        No confirmed registrations for this reunion yet.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <script>
        // Auto-open the browser's print dialog so the admin can save as PDF with
        // one click. Suppressed in headless / non-print contexts (e.g. when the
        // page is embedded as an iframe inside Filament).
        if (window === window.top) {
            window.addEventListener('load', function () {
                // Give the photo <img> tags a tick to render before printing.
                setTimeout(function () { window.print(); }, 250);
            });
        }
    </script>
</body>
</html>