<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    @php
    function faNum($num) {
    $en = ['0','1','2','3','4','5','6','7','8','9'];
    $fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    return str_replace($en, $fa, (string) $num);
    }

    function ltr($text) {
    $escaped = e($text);
    return preg_replace_callback(
    '/[A-Za-z0-9][A-Za-z0-9\s\-\.\_\/]*[A-Za-z0-9]|[A-Za-z0-9]/u',
    function ($m) {
    return '<bdo dir="ltr">' . $m[0] . '</bdo>';
    },
    $escaped
    );
    }
    @endphp
    <style>
        body {
            font-family: vazirmatn;
            font-size: 11px;
            color: #222;
        }

        .header {
            width: 100%;
            border-bottom: 3px solid #c9a227;
            padding-bottom: 10px;
            margin-bottom: 16px;
        }

        .header table {
            width: 100%;
        }

        .shop-title {
            font-size: 20px;
            font-weight: bold;
            color: #1a1a1a;
        }

        .invoice-badge {
            font-size: 13px;
            color: #c9a227;
            font-weight: bold;
        }

        .meta-table {
            width: 100%;
            margin-bottom: 14px;
            border-collapse: collapse;
        }

        .meta-table td {
            padding: 5px 8px;
            font-size: 11px;
        }

        .meta-label {
            color: #777;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .items-table th {
            background: #1a1a1a;
            color: #fff;
            padding: 8px 6px;
            font-size: 10.5px;
            text-align: right;
        }

        .items-table td {
            padding: 7px 6px;
            font-size: 10.5px;
            border-bottom: 1px solid #e5e5e5;
        }

        .items-table tr:nth-child(even) td {
            background: #faf8f2;
        }

        .totals-table {
            width: 45%;
            margin-top: 14px;
            margin-right: 0;
            margin-left: auto;
            border-collapse: collapse;
        }

        .totals-table td {
            padding: 6px 8px;
            font-size: 11px;
        }

        .totals-table .final-row td {
            border-top: 2px solid #c9a227;
            font-weight: bold;
            font-size: 13px;
            color: #c9a227;
        }

        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: bold;
        }

        .status-paid {
            background: #e6f4ea;
            color: #2e7d4f;
        }

        .status-unpaid {
            background: #fdecea;
            color: #c0392b;
        }

        .status-returned {
            background: #eceef1;
            color: #555;
        }

        .footer {
            margin-top: 30px;
            font-size: 9.5px;
            color: #999;
            text-align: center;
            border-top: 1px solid #eee;
            padding-top: 8px;
        }
    </style>
</head>

<body>

    <div class="header">
        <table>
            <tr>
                <td style="width:50%">
                    <div class="shop-title">گیم‌شاپ </div>
                    <div style="font-size:10px;color:#888;margin-top:2px">فاکتور فروش</div>
                </td>
                <td style="width:50%;text-align:left">
                    <div class="invoice-badge">شماره فاکتور: {!! ltr($invoice->invoice_number) !!}</div>
                    <div style="font-size:10px;color:#888;margin-top:2px">
                        تاریخ پرداخت: {{ faNum(\Morilog\Jalali\Jalalian::fromCarbon($invoice->paid_at ?? $invoice->created_at)->format('Y/m/d')) }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <table class="meta-table">
        <tr>
            <td style="width:50%">
                <span class="meta-label">مشتری:</span>
                <strong>{{ $invoice->customer?->name ?? 'بدون مشتری' }}</strong>
            </td>
            <td style="width:50%;text-align:left">
                <span class="meta-label">وضعیت پرداخت:</span>
                <span class="status-badge status-{{ $invoice->payment_status }}">
                    @if($invoice->payment_status === 'paid') پرداخت شده
                    @elseif($invoice->payment_status === 'returned') مرجوع شده
                    @else پرداخت نشده @endif
                </span>
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th>ردیف</th>
                <th>شرح</th>
                <th>تعداد</th>
                <th>قیمت واحد</th>
                <th>جمع</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->orderItems as $i => $item)
            <tr>
                <td>{{ faNum($i + 1) }}</td>
                <td>{!! ltr($item->product_name) !!}{{ $item->is_returned ? ' (مرجوع شده)' : '' }}</td>
                <td>{{ faNum($item->quantity) }}</td>
                <td>{{ faNum(number_format($item->price)) }}</td>
                <td>{{ faNum(number_format($item->total_price)) }}</td>
            </tr>
            @endforeach
            @foreach($invoice->serviceJobs as $sj)
            <tr>
                <td>-</td>
                <td>سرویس {!! ltr('#' . $sj->id) !!} - {{ $sj->device_type ?? 'دستگاه' }}</td>
                <td>{{ faNum(1) }}</td>
                <td>{{ faNum(number_format($sj->final_price)) }}</td>
                <td>{{ faNum(number_format($sj->final_price)) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals-table">
        <tr>
            <td class="meta-label">جمع اقلام</td>
            <td style="text-align:left">{{ faNum(number_format($invoice->total_amount)) }} تومان</td>
        </tr>
        @foreach($invoice->adjustments as $adj)
        <tr>
            <td class="meta-label">{{ $adj->title }}</td>
            <td style="text-align:left">
                {{ $adj->direction === 'increase' ? '+' : '-' }}
                {{ $adj->type === 'percentage' ? faNum($adj->value) . '%' : faNum(number_format($adj->value)) . ' تومان' }}
            </td>
        </tr>
        @endforeach
        <tr class="final-row">
            <td>مبلغ نهایی</td>
            <td style="text-align:left">{{ faNum(number_format($invoice->final_amount)) }} تومان</td>
        </tr>
    </table>

    <div class="footer">
        این فاکتور توسط سامانه گیم‌شاپ صادر شده است.
    </div>

</body>

</html>