<?php

namespace App\Services;

use App\Models\Branch;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Renders the admin dashboard as an XLSX workbook fed by the same
 * DashboardSnapshotService the page uses — the workbook mirrors the screen.
 *
 * Sheet 1 «KPIها»: today's headline numbers, the order pipeline, the
 * dining-room snapshot, and the low-stock board.
 * Sheet 2 «نمودار فروش»: the 14-day daily series with the period totals.
 */
class DashboardExportService
{
    public function __construct(
        protected DashboardSnapshotService $snapshots,
    ) {}

    public function xlsx(Branch $branch): string
    {
        $snapshot = $this->snapshots->snapshot($branch);

        $spreadsheet = new Spreadsheet;

        $kpis = $spreadsheet->getActiveSheet();
        $kpis->setTitle('KPIها');
        $kpis->setRightToLeft(true);
        $kpis->fromArray([
            ['داشبورد مدیریتی — '.$branch->name],
            ['تاریخ خروجی', now()->format('Y-m-d H:i')],
            [],
            ['KPI امروز', 'مقدار'],
            ['فروش امروز (تومان)', $snapshot['today']['revenue']],
            ['تعداد سفارش‌های امروز', $snapshot['today']['orders']],
            ['میانگین سبد (تومان)', $snapshot['today']['average_ticket']],
            [],
            ['خط لولهٔ سفارش‌ها', 'تعداد'],
        ], null, 'A1', true);
        $kpis->getStyle('A4:B4')->getFont()->setBold(true);
        $kpis->getStyle('A9:B9')->getFont()->setBold(true);

        $row = 10;
        foreach ($snapshot['orderStatuses'] as $status) {
            $kpis->fromArray([$status['label'], $status['count']], null, "A{$row}", true);
            $row++;
        }

        $row++;
        $kpis->fromArray(['سالن غذاخوری', 'تعداد'], null, "A{$row}", true);
        $kpis->getStyle("A{$row}:B{$row}")->getFont()->setBold(true);
        $row++;
        foreach ($snapshot['tables'] as $table) {
            $kpis->fromArray([$table['label'], $table['count']], null, "A{$row}", true);
            $row++;
        }

        $row++;
        $kpis->fromArray(['موجودی کم', 'موجودی فعلی', 'خط هشدار', 'واحد'], null, "A{$row}", true);
        $kpis->getStyle("A{$row}:D{$row}")->getFont()->setBold(true);
        $row++;

        if ($snapshot['lowStock'] === []) {
            $kpis->fromArray(['(همهٔ متریال‌ها بالای خط هشدار هستند)'], null, "A{$row}", true);
            $row++;
        }

        foreach ($snapshot['lowStock'] as $item) {
            $kpis->fromArray([
                $item['name'],
                (float) $item['current'],
                (float) $item['threshold'],
                $item['unit_label'],
            ], null, "A{$row}", true);
            $row++;
        }

        foreach (['A', 'B', 'C', 'D'] as $column) {
            $kpis->getColumnDimension($column)->setAutoSize(true);
        }

        $chart = $spreadsheet->createSheet();
        $chart->setTitle('نمودار فروش');
        $chart->setRightToLeft(true);
        $chart->fromArray([
            ['نمودار فروش ۱۴ روز'],
            [],
            ['تاریخ', 'روز', 'درآمد (تومان)', 'تعداد سفارش'],
        ], null, 'A1', true);
        $chart->getStyle('A3:D3')->getFont()->setBold(true);

        $days = $snapshot['salesChart']['days'];
        $row = 4;
        foreach ($days as $day) {
            $chart->fromArray([
                $day['date'],
                $day['label'],
                $day['revenue'],
                $day['orders'],
            ], null, "A{$row}", true);
            $row++;
        }

        $chart->fromArray([
            'جمع دوره',
            '',
            $snapshot['salesChart']['revenue_total'],
            $snapshot['salesChart']['orders_total'],
        ], null, "A{$row}", true);
        $chart->getStyle("A{$row}:D{$row}")->getFont()->setBold(true);

        $row++;
        $chart->fromArray(['بهترین روز', $snapshot['salesChart']['best_day_label']], null, "A{$row}", true);

        foreach (['A', 'B', 'C', 'D'] as $column) {
            $chart->getColumnDimension($column)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $spreadsheet->disconnectWorksheets();

        return (string) ob_get_clean();
    }
}
