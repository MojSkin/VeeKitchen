<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>تسویهٔ شیفت {{ $packet['id'] }} — {{ $packet['cashier'] }}</title>
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
        .totals { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 16px; font-size: 12px; }
        .totals div { border: 1px solid #d6d3d1; border-radius: 8px; padding: 8px 12px; }
        .totals strong { display: block; font-size: 14px; margin-top: 2px; }
        .discrepancy { border-color: #fca5a5 !important; background: #fef2f2; }
        .discrepancy.clean { border-color: #86efac !important; background: #f0fdf4; }
        section { margin-bottom: 20px; }
        h2 { font-size: 15px; margin: 0 0 6px; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border: 1px solid #d6d3d1; padding: 6px 8px; text-align: right; }
        th { background: #fafaf9; }
        td.num, th.num { text-align: left; direction: ltr; }
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
        <h1>تسویهٔ شیفت شمارهٔ {{ $packet['id'] }} — {{ $packet['cashier'] }}</h1>
        <p>
            از {{ $packet['opened_at']->format('Y-m-d H:i') }}
            @if ($packet['closed_at'] !== null)
                تا {{ $packet['closed_at']->format('Y-m-d H:i') }}
                · بستن توسط {{ $packet['closed_by'] ?? '—' }}
            @else
                — شیفت در جریان
            @endif
            · چاپ در {{ date('Y-m-d H:i') }}
        </p>
    </header>

    <div class="totals">
        <div>موجودی اولیه<strong>{{ number_format($packet['opening_cash']) }} تومان</strong></div>
        <div>دریافت نقدی<strong>{{ number_format($packet['cash_payments']) }} تومان</strong></div>
        <div>دریافت کارت‌خوان<strong>{{ number_format($packet['card_payments']) }} تومان</strong></div>
        <div>حرکات نقدی خالص<strong>{{ number_format($packet['movements_net']) }} تومان</strong></div>
        <div>صندوق مورد انتظار<strong>{{ number_format($packet['expected_cash']) }} تومان</strong></div>
        @if ($packet['counted_cash'] !== null)
            <div class="{{ ($packet['discrepancy'] ?? 0) === 0 ? 'discrepancy clean' : 'discrepancy' }}">
                شمارش واقعی<strong>{{ number_format($packet['counted_cash']) }} تومان</strong>
            </div>
            <div class="{{ ($packet['discrepancy'] ?? 0) === 0 ? 'discrepancy clean' : 'discrepancy' }}">
                مغایرت<strong>
                    @if (($packet['discrepancy'] ?? 0) === 0)
                        صفر ✓
                    @else
                        {{ ($packet['discrepancy'] > 0 ? '+' : '−') . number_format(abs($packet['discrepancy'])) }} تومان
                    @endif
                </strong>
            </div>
        @endif
    </div>

    <section>
        <h2>حرکات نقدی مستند</h2>
        @if ($movementRows !== '')
            <table>
                <thead>
                    <tr><th>نوع</th><th class="num">مبلغ (تومان)</th><th>دلیل</th><th>ثبت توسط</th><th>زمان</th></tr>
                </thead>
                <tbody>{!! $movementRows !!}</tbody>
            </table>
        @else
            <p style="font-size: 12px; color: #78716c;">حرکت نقدی ثبت نشده است.</p>
        @endif
    </section>

    <footer>
        دریافت کارت‌خوان داخل کشوی صندوق حساب نمی‌شود؛ صندوقِ انتظار = موجودی اولیه + نقدی − برداشت + واریز/اصلاح.
        produced by VeeKitchen
    </footer>
</body>
</html>
