<?php

namespace App\Mail;

use App\Models\Branch;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Carbon;

/**
 * The weekly warehouse turnover of one branch, emailed to its admins.
 *
 * The numbers are the exact same shape the on-screen report serves — the
 * append-only StockMovement ledger aggregated per movement type — so the
 * email can never disagree with the admin panel.
 */
class WarehouseWeeklyReport extends Mailable
{
    use Queueable;

    /**
     * @param  array{types: array<int, array{type: string, label: string, total: float, movements: int, value: int, items: array<int, array{name: string, unit_label: string, total: float, value: int}>}>, movement_count: int, generated_at: string, from: string, to: string, inflow_value: int, outflow_value: int}  $report
     */
    public function __construct(
        public Branch $branch,
        public array $report,
        public Carbon $fromDay,
        public Carbon $toDay,
    ) {}

    public function envelope(): Envelope
    {
        $subject = sprintf(
            'گزارش هفتگی انبار «%s» — %s تا %s',
            $this->branch->name,
            $this->fromDay->format('Y-m-d'),
            $this->toDay->format('Y-m-d'),
        );

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.warehouse-weekly-report',
            with: [
                'branchName' => $this->branch->name,
                'report' => $this->report,
                'fromDay' => $this->fromDay->format('Y-m-d'),
                'toDay' => $this->toDay->format('Y-m-d'),
                'reportUrl' => route('admin.inventory.report'),
            ],
        );
    }

    /**
     * @return array<int, object>
     */
    public function attachments(): array
    {
        return [];
    }
}
