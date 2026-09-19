<?php

namespace App\Services;

use App\Models\Branch;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Renders the warehouse ledger report as files. Two outputs share one
 * "report packet" built by WarehouseReportService:
 *
 *  - an XLSX workbook (summary sheet + one row per type/item line), and
 *  - a self-contained print HTML document (A4-friendly, RTL, opens the
 *    browser print dialog automatically).
 */
class WarehouseReportExportService
{
    public function __construct(
        protected WarehouseReportService $reports,
    ) {}

    /**
     * Build a flat representation both exporters consume.
     *
     * @return array{branch: string, label: string, from: Carbon, to: Carbon, movement_count: int, outflow_value: int, inflow_value: int, types: array<int, array{label: string, total: float, value: int, items: array<int, array{name: string, unit_label: string, total: float, value: int}>}>}
     */
    public function packet(Branch $branch, Carbon $from, Carbon $to): array
    {
        $report = $this->reports->rangeByType($branch, $from, $to);

        $types = collect($report['types'])
            ->filter(fn (array $type) => $type['movements'] > 0)
            ->map(fn (array $type): array => [
                'label' => $type['label'],
                'total' => (float) $type['total'],
                'value' => (int) $type['value'],
                'items' => $type['items'],
            ])->values()->all();

        return [
            'branch' => $branch->name,
            'label' => 'گزارش انبار',
            'from' => $from,
            'to' => $to,
            'movement_count' => $report['movement_count'],
            'outflow_value' => $report['outflow_value'],
            'inflow_value' => $report['inflow_value'],
            'types' => $types,
        ];
    }

    /**
     * The XLSX workbook: sheet 1 = type summary, sheet 2 = item lines.
     */
    public function xlsx(Branch $branch, Carbon $from, Carbon $to): string
    {
        $packet = $this->packet($branch, $from, $to);

        $spreadsheet = new Spreadsheet;

        $summary = $spreadsheet->getActiveSheet();
        $summary->setTitle('خلاصه');
        $summary->setRightToLeft(true);
        $summary->fromArray([
            ['گزارش انبار — '.$packet['branch']],
            ['از', $this->day($packet['from']), 'تا', $this->day($packet['to'])],
            ['تعداد ردیف دفتر کل', $packet['movement_count']],
            ['ارزش خروجی (تومان)', $packet['outflow_value']],
            ['ارزش ورودی (تومان)', $packet['inflow_value']],
            [],
            ['نوع حرکت', 'جمع مقدار', 'ارزش ریالی (تومان)'],
        ], null, 'A1');

        $row = 8;
        foreach ($packet['types'] as $type) {
            $summary->fromArray([
                $type['label'],
                $type['total'],
                $type['value'],
            ], null, "A{$row}");
            $row++;
        }

        $summary->getColumnDimension('A')->setAutoSize(true);
        $summary->getColumnDimension('B')->setAutoSize(true);
        $summary->getColumnDimension('C')->setAutoSize(true);
        $summary->getStyle('A7:C7')->getFont()->setBold(true);

        $detail = $spreadsheet->createSheet();
        $detail->setTitle('اقلام');
        $detail->setRightToLeft(true);
        $detail->fromArray([
            ['نوع حرکت', 'متریال', 'واحد', 'جمع مقدار', 'ارزش ریالی (تومان)'],
        ], null, 'A1');
        $detail->getStyle('A1:E1')->getFont()->setBold(true);

        $row = 2;
        foreach ($packet['types'] as $type) {
            foreach ($type['items'] as $item) {
                $detail->fromArray([
                    $type['label'],
                    $item['name'],
                    $item['unit_label'],
                    $item['total'],
                    $item['value'],
                ], null, "A{$row}");
                $row++;
            }
        }

        foreach (['A', 'B', 'C', 'D', 'E'] as $column) {
            $detail->getColumnDimension($column)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $spreadsheet->disconnectWorksheets();

        return (string) ob_get_clean();
    }

    /**
     * Self-contained print HTML (RTL, print CSS, auto print dialog).
     */
    public function printView(Branch $branch, Carbon $from, Carbon $to): string
    {
        $packet = $this->packet($branch, $from, $to);

        $typeSections = '';
        foreach ($packet['types'] as $type) {
            $rows = '';
            foreach ($type['items'] as $item) {
                $rows .= '<tr>'
                    .'<td>'.htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8').'</td>'
                    .'<td>'.htmlspecialchars($item['unit_label'], ENT_QUOTES, 'UTF-8').'</td>'
                    .'<td>'.number_format(abs((float) $item['total']), 3).'</td>'
                    .'<td class="num">'.number_format((float) $item['value']).'</td>'
                    .'</tr>';
            }

            $typeSections .= '<section>'
                .'<h2>'.htmlspecialchars($type['label'], ENT_QUOTES, 'UTF-8')
                .' <small>ارزش: '.number_format((float) $type['value']).' تومان</small></h2>'
                .'<table><thead><tr><th>متریال</th><th>واحد</th><th>جمع مقدار</th><th>ارزش ریالی (تومان)</th></tr></thead>'
                .'<tbody>'.$rows.'</tbody></table>'
                .'</section>';
        }

        return view('prints.warehouse-report', [
            'packet' => $packet,
            'typeSections' => $typeSections,
            'fromDay' => $this->day($packet['from']),
            'toDay' => $this->day($packet['to']),
        ])->render();
    }

    /**
     * A suggested client-side filename for the exported workbook.
     */
    public function filename(Branch $branch, Carbon $from, Carbon $to): string
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->startOfDay();

        if ($from->equalTo($to)) {
            return 'warehouse-report-'.$from->format('Y-m-d').'.xlsx';
        }

        return 'warehouse-report-'.$from->format('Y-m-d').'_'.$to->format('Y-m-d').'.xlsx';
    }

    protected function day(Carbon $date): string
    {
        return $date->format('Y-m-d');
    }
}
