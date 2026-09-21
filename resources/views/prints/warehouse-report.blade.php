<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>گزارش انبار — {{ $fromDay }} تا {{ $toDay }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: Vazirmatn, Tahoma, Arial, sans-serif;
            color: #1c1917;
            margin: 24px;
            background: #fff;
        }
        header { border-bottom: 2px solid #1c1917; padding-bottom: 12px; margin-bottom: 16px; }
        header h1 { font-size: 20px; margin: 0 0 4px; }
        header p { margin: 0; font-size: 12px; color: #57534e; }
        .totals { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; font-size: 12px; }
        .totals div { border: 1px solid #d6d3d1; border-radius: 8px; padding: 8px 12px; }
        .totals strong { display: block; font-size: 14px; margin-top: 2px; }
        section { margin-bottom: 20px; }
        h2 { font-size: 15px; margin: 0 0 6px; }
        h2 small { font-weight: normal; color: #78716c; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border: 1px solid #d6d3d1; padding: 6px 8px; text-align: right; }
        th { background: #fafaf9; }
        td.num, th.num { text-align: left; direction: ltr; }
        tfoot td { font-weight: bold; background: #fafaf9; }
        footer { margin-top: 24px; font-size: 10px; color: #a8a29e; }
        @page { size: A4; margin: 14mm; }
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body onload="window.print()">
    <header>
        <h1>گزارش انبار — {{ $packet['branch'] }}</h1>
        <p>
            بازه: {{ $fromDay }} تا {{ $toDay }}
            · {{ number_format($packet['movement_count']) }} ردیف دفتر کل
            · چاپ در {{ date('Y-m-d H:i') }}
        </p>
    </header>

    <div class="totals">
        <div>ارزش خروجی (مصرف + ضایعات)<strong>{{ number_format($packet['outflow_value']) }} تومان</strong></div>
        <div>ارزش ورودی (خرید + بازگشت + اصلاح)<strong>{{ number_format($packet['inflow_value']) }} تومان</strong></div>
    </div>

    {!! $typeSections !!}

    <footer>
        ارزش‌ها بر اساس قیمت واحد مؤثر هر متریال — میانگین موزون اسنپ‌شات‌های قیمت لحظهٔ ثبت حرکت‌ها.
        produced by VeeKitchen
    </footer>
</body>
</html>
