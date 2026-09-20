<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>گزارش هفتگی انبار</title>
    <style>
        body {
            font-family: Vazirmatn, Tahoma, Arial, sans-serif;
            background: #f5f5f4;
            color: #1c1917;
            margin: 0;
            padding: 24px;
        }
        .card {
            max-width: 640px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #e7e5e4;
            border-radius: 12px;
            overflow: hidden;
        }
        .head {
            background: linear-gradient(135deg, #b45309, #92400e);
            color: #fff;
            padding: 20px 24px;
        }
        .head h1 { margin: 0 0 4px; font-size: 18px; }
        .head .range { font-size: 13px; opacity: .85; }
        .body { padding: 20px 24px; }
        .pipe {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
        }
        .pipe .box {
            flex: 1;
            border-radius: 8px;
            padding: 12px;
            text-align: center;
        }
        .box .label { font-size: 12px; color: #78716c; }
        .box .num { font-size: 16px; font-weight: 700; margin-top: 4px; }
        .box.out { background: #fef2f2; }
        .box.out .num { color: #b91c1c; }
        .box.in { background: #f0fdf4; }
        .box.in .num { color: #15803d; }
        h2 { font-size: 15px; margin: 20px 0 8px; color: #92400e; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td {
            border: 1px solid #e7e5e4;
            padding: 6px 8px;
            font-size: 13px;
            text-align: right;
        }
        th { background: #fafaf9; font-weight: 700; }
        td.num { direction: ltr; text-align: left; font-variant-numeric: tabular-nums; }
        .chip {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 999px;
            font-size: 12px;
            background: #fef3c7;
            color: #92400e;
        }
        .foot {
            padding: 14px 24px;
            background: #fafaf9;
            border-top: 1px solid #e7e5e4;
            font-size: 12px;
            color: #78716c;
        }
        .foot a { color: #b45309; }
    </style>
</head>
<body>
    <div class="card">
        <div class="head">
            <h1>گزارش هفتگی انبار — {{ $branchName }}</h1>
            <div class="range">بازه: {{ $fromDay }} تا {{ $toDay }}</div>
        </div>
        <div class="body">
            @php
                $outflow = number_format((int) $report['outflow_value']);
                $inflow = number_format((int) $report['inflow_value']);
            @endphp

            <div class="pipe">
                <div class="box out">
                    <div class="label">ارزش خروجی از انبار</div>
                    <div class="num">{{ $outflow }} تومان</div>
                </div>
                <div class="box in">
                    <div class="label">ارزش ورودی به انبار</div>
                    <div class="num">{{ $inflow }} تومان</div>
                </div>
            </div>

            @foreach ($report['types'] as $type)
                @continue($type['movements'] === 0)
                <h2>
                    <span class="chip">{{ $type['label'] }}</span>
                    <span style="font-size: 12px; color: #78716c;">
                        {{ $type['movements'] }} ردیف · جمع
                        {{ str_starts_with((string) $type['total'], '-') ? '' : '+' }}{{ rtrim(rtrim(number_format($type['total'], 3, '.', ''), '0'), '.') }}
                    </span>
                </h2>
                <table>
                    <thead>
                        <tr>
                            <th>متریال</th>
                            <th>جمع مقدار</th>
                            <th>واحد</th>
                            <th>ارزش ریالی</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($type['items'] as $line)
                            <tr>
                                <td>{{ $line['name'] }}</td>
                                <td class="num">{{ rtrim(rtrim(number_format($line['total'], 3, '.', ''), '0'), '.') }}</td>
                                <td>{{ $line['unit_label'] }}</td>
                                <td class="num">{{ $line['value'] > 0 ? number_format($line['value']) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endforeach

            @if ($report['movement_count'] === 0)
                <p>در این بازه حرکتی در انبار ثبت نشده است.</p>
            @endif
        </div>
        <div class="foot">
            ارزش‌ها بر اساس آخرین قیمت خرید هر متریال محاسبه شده‌اند ·
            <a href="{{ $reportUrl }}">گزارش کامل در پنل ادمین</a>
        </div>
    </div>
</body>
</html>
