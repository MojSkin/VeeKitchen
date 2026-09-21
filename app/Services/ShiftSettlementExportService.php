<?php

namespace App\Services;

use App\Models\StaffShift;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Renders one shift's settlement as files. Two outputs share the
 * settlement packet built by ShiftService::settlementSummary():
 *
 *  - an XLSX workbook (the money trail + the documented cash movements), and
 *  - a self-contained print HTML document (A4-friendly, RTL, opens the
 *    browser print dialog automatically).
 */
class ShiftSettlementExportService
{
    /**
     * Build a flat representation both exporters consume.
     *
     * @return array{id: int, cashier: string, opened_at: Carbon, closed_at: Carbon|null, closed_by: string|null, opening_cash: int, cash_payments: int, card_payments: int, movements_net: int, expected_cash: int, counted_cash: int|null, discrepancy: int|null, movements: array<int, array{type: string, amount: int, reason: string, by: string, at: Carbon}>}
     */
    public function packet(StaffShift $shift): array
    {
        $shift->loadMissing(['user', 'closer', 'cashMovements.user']);

        return [
            'id' => $shift->id,
            'cashier' => $shift->user?->name ?? '—',
            'opened_at' => $shift->opened_at,
            'closed_at' => $shift->closed_at,
            'closed_by' => $shift->closer?->name,
            'opening_cash' => $shift->opening_cash,
            'cash_payments' => $shift->cashPaymentsTotal(),
            'card_payments' => $shift->cardPaymentsTotal(),
            'movements_net' => $shift->cashMovementsNet(),
            'expected_cash' => $shift->expected_cash ?? $shift->computeExpectedCash(),
            'counted_cash' => $shift->closing_cash,
            'discrepancy' => $shift->discrepancy,
            'movements' => $shift->cashMovements
                ->map(fn ($movement): array => [
                    'type' => $movement->type->label(),
                    'amount' => $movement->amount,
                    'reason' => $movement->reason,
                    'by' => $movement->user?->name ?? '—',
                    'at' => $movement->created_at,
                ])->all(),
        ];
    }

    /**
     * The XLSX workbook: sheet 1 = the settlement, sheet 2 = movements.
     */
    public function xlsx(StaffShift $shift): string
    {
        $packet = $this->packet($shift);

        $spreadsheet = new Spreadsheet;

        $summary = $spreadsheet->getActiveSheet();
        $summary->setTitle('تسویه');
        $summary->setRightToLeft(true);
        // strictNullComparison: the loose default silently drops zero-valued
        // cells, and a zero discrepancy is the whole point of a clean shift.
        $summary->fromArray([
            ['تسویهٔ شیفت — '.$packet['cashier']],
            ['باز شد', $this->moment($packet['opened_at'])],
            ['بسته شد', $packet['closed_at'] === null ? 'در جریان' : $this->moment($packet['closed_at'])],
            [],
            ['موجودی اولیه (تومان)', $packet['opening_cash']],
            ['دریافت نقدی (تومان)', $packet['cash_payments']],
            ['دریافت کارت‌خوان (تومان)', $packet['card_payments']],
            ['حرکات نقدی خالص (تومان)', $packet['movements_net']],
            ['صندوق مورد انتظار (تومان)', $packet['expected_cash']],
            ['شمارش واقعی (تومان)', $packet['counted_cash'] ?? 'در جریان'],
            ['مغایرت (تومان)', $packet['discrepancy'] ?? '—'],
        ], null, 'A1', true);

        foreach (['A', 'B'] as $column) {
            $summary->getColumnDimension($column)->setAutoSize(true);
        }

        $detail = $spreadsheet->createSheet();
        $detail->setTitle('حرکات نقدی');
        $detail->setRightToLeft(true);
        $detail->fromArray([
            ['نوع', 'مبلغ (تومان)', 'دلیل', 'ثبت توسط', 'زمان'],
        ], null, 'A1');
        $detail->getStyle('A1:E1')->getFont()->setBold(true);

        $row = 2;

        foreach ($packet['movements'] as $movement) {
            $detail->fromArray([
                $movement['type'],
                $movement['amount'],
                $movement['reason'],
                $movement['by'],
                $movement['at']->format('Y-m-d H:i'),
            ], null, "A{$row}");
            $row++;
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
    public function printView(StaffShift $shift): string
    {
        $packet = $this->packet($shift);

        $movementRows = '';

        foreach ($packet['movements'] as $movement) {
            $movementRows .= '<tr>'
                .'<td>'.htmlspecialchars($movement['type'], ENT_QUOTES, 'UTF-8').'</td>'
                .'<td class="num">'.number_format($movement['amount']).'</td>'
                .'<td>'.htmlspecialchars($movement['reason'], ENT_QUOTES, 'UTF-8').'</td>'
                .'<td>'.htmlspecialchars($movement['by'], ENT_QUOTES, 'UTF-8').'</td>'
                .'<td>'.$movement['at']->format('H:i').'</td>'
                .'</tr>';
        }

        return view('prints.shift-settlement', [
            'packet' => $packet,
            'movementRows' => $movementRows,
        ])->render();
    }

    /**
     * A suggested client-side filename for the exported workbook.
     */
    public function filename(StaffShift $shift, string $extension = 'xlsx'): string
    {
        return 'shift-settlement-'.$shift->id.'-'.$shift->opened_at->format('Y-m-d').'.'.$extension;
    }

    protected function moment(Carbon $date): string
    {
        return $date->format('Y-m-d H:i');
    }
}
