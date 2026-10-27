<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    @php
        $faNum = static function ($value) {
            return str_replace(
                ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
                ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'],
                (string) $value
            );
        };

        $ltr = static function ($value) {
            $parts = preg_split(
                '/([\x00-\x7F]+)/u',
                (string) $value,
                -1,
                PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
            ) ?: [];

            return implode('', array_map(static function ($part) {
                $escaped = e($part);

                return preg_match('/^[\x00-\x7F]+$/', $part)
                    ? '<bdo dir="ltr">' . $escaped . '</bdo>'
                    : $escaped;
            }, $parts));
        };

        $jalali = static fn ($d) => $d ? \Morilog\Jalali\Jalalian::fromCarbon($d)->format('Y/m/d') : null;

        $storeName = $storeProfile?->display_name ?: 'گیم‌شاپ';

        $statuses = [
            'received'          => ['label' => 'دریافت شده',   'class' => 'returned'],
            'diagnosing'        => ['label' => 'در حال بررسی', 'class' => 'returned'],
            'waiting_for_parts' => ['label' => 'انتظار قطعه',  'class' => 'unpaid'],
            'in_progress'       => ['label' => 'در حال تعمیر', 'class' => 'returned'],
            'completed'         => ['label' => 'تکمیل شده',    'class' => 'paid'],
            'delivered'         => ['label' => 'تحویل داده شده', 'class' => 'paid'],
            'canceled'          => ['label' => 'لغو شده',      'class' => 'unpaid'],
        ];
        $status = $statuses[$job->status] ?? ['label' => (string) $job->status, 'class' => 'returned'];

        $dateRows = array_values(array_filter([
            ['label' => 'پذیرش',  'value' => $jalali($job->received_at)],
            ['label' => 'شروع',   'value' => $jalali($job->started_at)],
            ['label' => 'اتمام',  'value' => $jalali($job->completed_at)],
            ['label' => 'تحویل',  'value' => $jalali($job->delivered_at)],
        ], static fn ($r) => $r['value']));

        $servicesTotal = (float) $job->serviceTypes->sum('price');
        $itemsTotal    = (float) $job->items->sum('total_price');
        $finalTotal    = (float) ($job->final_price ?: ($servicesTotal + $itemsTotal));

        $storePhone = implode(' / ', array_filter([
            $storeProfile?->phone,
            $storeProfile?->secondary_phone,
        ]));
        $storeContacts = array_values(array_filter([
            ['label' => 'تلفن', 'value' => $storePhone],
            ['label' => 'ایمیل', 'value' => $storeProfile?->email],
            ['label' => 'وب‌سایت', 'value' => $storeProfile?->website],
            ['label' => 'تلگرام', 'value' => $storeProfile?->telegram],
            ['label' => 'اینستاگرام', 'value' => $storeProfile?->instagram],
        ], static fn ($contact) => trim((string) $contact['value']) !== ''));
        $storeAddress = $storeProfile?->invoice_address;
    @endphp

    <style>
        body {
            margin: 0;
            padding: 0;
            color: #282720;
            background-color: #ffffff;
            font-family: vazirmatn, Tahoma, Arial, sans-serif;
            font-size: 9.5pt;
            line-height: 1.75;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .invoice-sheet {
            width: 100%;
        }

        .masthead {
            margin-bottom: 0;
            border-bottom: 3px solid #c7a24e;
        }

        .masthead td {
            padding: 14px 16px;
            color: #f5f0e4;
            background-color: #191916;
            vertical-align: middle;
        }

        .brand-cell {
            width: 64%;
        }

        .invoice-meta {
            width: 36%;
            text-align: left;
            border-right: 1px solid #38372f;
        }

        .brand-lockup {
            width: auto;
        }

        .brand-lockup td {
            padding: 0;
            background-color: transparent;
            vertical-align: middle;
        }

        .brand-mark {
            width: 42px;
            height: 42px;
            color: #191916;
            background-color: #d6b65e !important;
            border-radius: 7px;
            text-align: center;
            direction: ltr;
            font-family: Arial, sans-serif;
            font-size: 10pt;
            font-weight: bold;
        }

        .brand-copy {
            padding-right: 11px !important;
        }

        .brand-kicker {
            margin-bottom: 1px;
            color: #d8bb6d;
            direction: ltr;
            text-align: right;
            font-family: Arial, sans-serif;
            font-size: 7pt;
            letter-spacing: 1px;
        }

        .shop-title {
            color: #fbf8ef;
            font-size: 17pt;
            font-weight: bold;
            line-height: 1.35;
        }

        .shop-caption {
            margin-top: 2px;
            color: #a9a494;
            font-size: 8.5pt;
        }

        .invoice-meta-label {
            color: #aaa594;
            font-size: 8pt;
        }

        .invoice-number {
            margin-top: 2px;
            color: #e9cc77;
            font-family: Arial, Tahoma, sans-serif;
            font-size: 14pt;
            font-weight: bold;
            line-height: 1.5;
        }

        .contact-strip {
            margin-top: 7px;
            border: 1px solid #e9e4d9;
            background-color: #f8f7f3;
        }

        .contact-cell,
        .address-cell {
            padding: 4px 9px;
            color: #6e6b62;
            border-bottom: 1px solid #e9e4d9;
            font-size: 8pt;
        }

        .contact-cell {
            width: 33.333%;
        }

        .contact-label {
            color: #9a772c;
            font-weight: bold;
        }

        .contact-value {
            color: #45433d;
        }

        .address-cell {
            border-bottom: 0;
        }

        .customer-summary {
            margin-top: 13px;
            margin-bottom: 17px;
            border: 1px solid #e4ddcd;
            background-color: #f6f3eb;
        }

        .customer-summary td {
            padding: 9px 12px;
            border-left: 1px solid #e4ddcd;
            vertical-align: middle;
        }

        .customer-summary td:last-child {
            border-left: 0;
        }

        .customer-cell {
            width: 46%;
        }

        .date-cell {
            width: 27%;
        }

        .status-cell {
            width: 27%;
        }

        .summary-label {
            display: block;
            color: #898476;
            font-size: 8pt;
        }

        .customer-name {
            display: block;
            margin-top: 2px;
            color: #292821;
            font-size: 10.5pt;
            font-weight: bold;
        }

        .summary-value {
            display: block;
            margin-top: 3px;
            color: #36352e;
            font-size: 9.5pt;
            font-weight: bold;
        }

        .status-tag {
            display: inline-block;
            margin-top: 4px;
            padding: 2px 8px;
            border: 1px solid transparent;
            border-radius: 10px;
            font-size: 8pt;
            font-weight: bold;
        }

        .status-paid {
            color: #286945;
            border-color: #c9e1cf;
            background-color: #e9f3eb;
        }

        .status-unpaid {
            color: #9b4e3b;
            border-color: #efd3c9;
            background-color: #fbefeb;
        }

        .status-returned {
            color: #62615c;
            border-color: #d9d8d2;
            background-color: #eeede9;
        }

        .summary-subline {
            margin-top: 3px;
            color: #777368;
            font-size: 7.5pt;
        }

        .section-heading {
            margin-bottom: 7px;
            padding-right: 8px;
            color: #26251f;
            border-right: 3px solid #c7a24e;
            font-size: 11pt;
            font-weight: bold;
            line-height: 1.4;
        }

        .items-table {
            table-layout: fixed;
            border: 1px solid #e4e0d8;
        }

        .items-table thead {
            display: table-header-group;
        }

        .items-table th {
            padding: 7px 6px;
            color: #f8f5ec;
            border-bottom: 2px solid #c7a24e;
            background-color: #292821;
            text-align: right;
            font-size: 8pt;
            font-weight: bold;
            line-height: 1.5;
        }

        .items-table td {
            padding: 7px 6px;
            color: #35342e;
            border-bottom: 1px solid #e9e6df;
            font-size: 8.7pt;
            vertical-align: middle;
        }

        .items-table tbody tr {
            page-break-inside: avoid;
        }

        .items-table .row-even td {
            background-color: #faf9f6;
        }

        .cell-center {
            text-align: center !important;
        }

        .money-cell {
            text-align: left;
            direction: ltr;
            font-family: vazirmatn, Tahoma, Arial, sans-serif;
        }

        .money-heading {
            text-align: right !important;
            direction: rtl;
            font-family: vazirmatn, Tahoma, Arial, sans-serif;
        }

        .money-strong {
            color: #806323 !important;
            font-weight: bold;
        }

        .item-name {
            word-wrap: break-word;
        }

        .return-tag {
            display: inline-block;
            margin-right: 5px;
            padding: 1px 5px;
            color: #8e503e;
            border: 1px solid #ead2c7;
            border-radius: 6px;
            background-color: #faf0ec;
            font-size: 7pt;
        }

        .empty-row td {
            padding: 18px 8px;
            color: #8b867a;
            text-align: center;
        }

        .totals-table {
            width: 57%;
            margin-top: 13px;
            margin-right: 0;
            margin-left: auto;
        }

        .totals-table td {
            padding: 5px 9px;
            border-bottom: 1px solid #ece8df;
            font-size: 8.8pt;
        }

        .total-label {
            color: #777267;
        }

        .total-value {
            color: #34332d;
            text-align: left;
            white-space: nowrap;
        }

        .adjustment-label {
            color: #777267;
        }

        .adjustment-value {
            color: #34332d;
            text-align: left;
            white-space: nowrap;
        }

        .total-final td {
            padding: 9px 11px;
            color: #fbf8ef;
            border-top: 2px solid #d0ae55;
            border-bottom: 0;
            background-color: #201f1a;
            font-size: 10.5pt;
            font-weight: bold;
        }

        .total-final .total-value {
            color: #efd889;
            font-size: 12pt;
        }

        .currency {
            color: inherit;
            font-size: 7.5pt;
            font-weight: normal;
        }

        .invoice-footer {
            margin-top: 22px;
            border-top: 1px solid #e5e0d5;
        }

        .invoice-footer td {
            padding-top: 9px;
            color: #898477;
            font-size: 8pt;
        }

        .footer-thanks {
            color: #806323 !important;
            font-size: 9.5pt !important;
            font-weight: bold;
        }

        .footer-note {
            text-align: left;
        }
    

        .info-table {
            margin-bottom: 14px;
            border: 1px solid #e4ddcd;
        }

        .info-table td {
            padding: 6px 10px;
            border-bottom: 1px solid #ece8df;
            border-left: 1px solid #ece8df;
            font-size: 8.8pt;
            vertical-align: middle;
        }

        .info-table tr:last-child td {
            border-bottom: 0;
        }

        .info-label {
            width: 17%;
            color: #898476;
            background-color: #f6f3eb;
            font-size: 8pt;
        }

        .info-value {
            width: 33%;
            color: #292821;
            font-weight: bold;
        }

        .note-box {
            margin-bottom: 12px;
            padding: 7px 11px;
            border: 1px solid #e9e4d9;
            border-right: 3px solid #c7a24e;
            background-color: #faf9f6;
            color: #45433d;
            font-size: 8.8pt;
            line-height: 1.9;
        }

        .note-title {
            display: block;
            margin-bottom: 2px;
            color: #9a772c;
            font-size: 8pt;
            font-weight: bold;
        }

        .block-gap {
            margin-bottom: 14px;
        }
    </style>
</head>

<body>
    <div class="invoice-sheet">
        <table class="masthead" dir="rtl">
            <tr>
                <td class="brand-cell">
                    <table class="brand-lockup" dir="rtl">
                        <tr>
                            <td class="brand-mark">GS</td>
                            <td class="brand-copy">
                                <div class="brand-kicker">GAME STORE / SERVICE</div>
                                <div class="shop-title">{{ $storeName }}</div>
                                <div class="shop-caption">گزارش سرویس و تعمیر</div>
                            </td>
                        </tr>
                    </table>
                </td>
                <td class="invoice-meta">
                    <div class="invoice-meta-label">شمارهٔ سرویس</div>
                    <div class="invoice-number">#{{ $job->id }}</div>
                </td>
            </tr>
        </table>

        @if(count($storeContacts) || $storeAddress)
            <table class="contact-strip" dir="rtl">
                @foreach($storeContacts as $contact)
                    @if($loop->first || $loop->index % 3 === 0)
                        <tr>
                    @endif
                    <td class="contact-cell">
                        <span class="contact-label">{{ $contact['label'] }}:</span>
                        <span class="contact-value">{!! $ltr($contact['value']) !!}</span>
                    </td>
                    @if($loop->iteration % 3 === 0 || $loop->last)
                        </tr>
                    @endif
                @endforeach
                @if($storeAddress)
                    <tr>
                        <td colspan="3" class="address-cell">
                            <span class="contact-label">نشانی فروشگاه:</span>
                            {{ $storeAddress }}
                        </td>
                    </tr>
                @endif
            </table>
        @endif

        <table class="customer-summary" dir="rtl">
            <tr>
                <td class="customer-cell">
                    <span class="summary-label">سرویس برای</span>
                    <strong class="customer-name">{{ $job->customer?->name ?? 'بدون مشتری' }}</strong>
                    @if($job->customer?->phone)
                        <div class="summary-subline">تلفن: {!! $ltr($job->customer->phone) !!}</div>
                    @endif
                </td>
                <td class="date-cell">
                    <span class="summary-label">تاریخ پذیرش</span>
                    <strong class="summary-value"><bdo dir="ltr">{{ $faNum($jalali($job->received_at) ?? '—') }}</bdo></strong>
                </td>
                <td class="status-cell">
                    <span class="summary-label">وضعیت سرویس</span>
                    <span class="status-tag status-{{ $status['class'] }}">{{ $status['label'] }}</span>
                </td>
            </tr>
        </table>

        <div class="section-heading">مشخصات دستگاه و پرونده</div>

        <table class="info-table" dir="rtl">
            <tr>
                <td class="info-label">نوع دستگاه</td>
                <td class="info-value">{!! $ltr($job->device_type ?: '—') !!}</td>
                <td class="info-label">سریال دستگاه</td>
                <td class="info-value">{!! $ltr($job->device_serial ?: '—') !!}</td>
            </tr>
            <tr>
                <td class="info-label">درخواست مرتبط</td>
                <td class="info-value">{{ $job->request_id ? '#' . $faNum($job->request_id) : '—' }}</td>
                <td class="info-label">فاکتور مرتبط</td>
                <td class="info-value">{!! $job->invoice ? $ltr($job->invoice->invoice_number) : '—' !!}</td>
            </tr>
            @if(count($dateRows))
                <tr>
                    <td class="info-label">تاریخ‌ها</td>
                    <td colspan="3" class="info-value" style="font-weight: normal;">
                        @foreach($dateRows as $row)
                            {{ $row['label'] }}: <bdo dir="ltr">{{ $faNum($row['value']) }}</bdo>@if(!$loop->last) &nbsp;&nbsp;|&nbsp;&nbsp; @endif
                        @endforeach
                    </td>
                </tr>
            @endif
        </table>

        @if($job->customer_problem_description)
            <div class="note-box">
                <span class="note-title">شرح مشکل از زبان مشتری</span>
                {{ $job->customer_problem_description }}
            </div>
        @endif

        @if($job->diagnosis_description)
            <div class="note-box">
                <span class="note-title">تشخیص و گزارش فنی</span>
                {{ $job->diagnosis_description }}
            </div>
        @endif

        <div class="section-heading">خدمات انجام‌شده</div>

        <table class="items-table block-gap" dir="rtl">
            <colgroup>
                <col style="width: 8%;">
                <col style="width: 72%;">
                <col style="width: 20%;">
            </colgroup>
            <thead>
                <tr>
                    <th class="cell-center">ردیف</th>
                    <th>شرح خدمت</th>
                    <th class="money-heading">مبلغ (تومان)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($job->serviceTypes as $row)
                    <tr class="{{ $loop->even ? 'row-even' : '' }}">
                        <td class="cell-center">{{ $faNum($loop->iteration) }}</td>
                        <td class="item-name">{!! $ltr($row->serviceType?->name ?? '—') !!}</td>
                        <td class="money-cell money-strong"><bdo dir="ltr">{{ $faNum(number_format($row->price)) }}</bdo></td>
                    </tr>
                @empty
                    <tr class="empty-row"><td colspan="3">خدمتی ثبت نشده است.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="section-heading">قطعات مصرف‌شده</div>

        <table class="items-table" dir="rtl">
            <colgroup>
                <col style="width: 8%;">
                <col style="width: 44%;">
                <col style="width: 12%;">
                <col style="width: 18%;">
                <col style="width: 18%;">
            </colgroup>
            <thead>
                <tr>
                    <th class="cell-center">ردیف</th>
                    <th>نام قطعه</th>
                    <th class="cell-center">تعداد</th>
                    <th class="money-heading">قیمت واحد (تومان)</th>
                    <th class="money-heading">مبلغ (تومان)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($job->items as $row)
                    <tr class="{{ $loop->even ? 'row-even' : '' }}">
                        <td class="cell-center">{{ $faNum($loop->iteration) }}</td>
                        <td class="item-name">{!! $ltr($row->item?->name ?? '—') !!}</td>
                        <td class="cell-center"><bdo dir="ltr">{{ $faNum($row->quantity) }}</bdo></td>
                        <td class="money-cell"><bdo dir="ltr">{{ $faNum(number_format($row->unit_price)) }}</bdo></td>
                        <td class="money-cell money-strong"><bdo dir="ltr">{{ $faNum(number_format($row->total_price)) }}</bdo></td>
                    </tr>
                @empty
                    <tr class="empty-row"><td colspan="5">قطعه‌ای ثبت نشده است.</td></tr>
                @endforelse
            </tbody>
        </table>

        <table class="totals-table" dir="rtl">
            <tr>
                <td class="total-label">جمع خدمات</td>
                <td class="total-value">
                    <bdo dir="ltr">{{ $faNum(number_format($servicesTotal)) }}</bdo>
                    <span class="currency">تومان</span>
                </td>
            </tr>
            <tr>
                <td class="total-label">جمع قطعات</td>
                <td class="total-value">
                    <bdo dir="ltr">{{ $faNum(number_format($itemsTotal)) }}</bdo>
                    <span class="currency">تومان</span>
                </td>
            </tr>
            <tr class="total-final">
                <td>مبلغ نهایی سرویس</td>
                <td class="total-value">
                    <bdo dir="ltr">{{ $faNum(number_format($finalTotal)) }}</bdo>
                    <span class="currency">تومان</span>
                </td>
            </tr>
        </table>

        <table class="invoice-footer" dir="rtl">
            <tr>
                <td class="footer-thanks">از اعتماد شما سپاسگزاریم.</td>
                <td class="footer-note">این گزارش توسط سامانه {{ $storeName }} صادر شده است.</td>
            </tr>
        </table>
    </div>
</body>

</html>
